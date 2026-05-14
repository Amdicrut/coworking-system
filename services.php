<?php
// =====================================================
// services.php - Управление дополнительными услугами
// Коворкинг-центр: добавление, редактирование, удаление услуг
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Обработка добавления услуги
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_service'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $price_type = $_POST['price_type'];
    $sort_order = (int)$_POST['sort_order'];
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Введите название услуги';
    }
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO extra_services (name, description, price, price_type, sort_order, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $price, $price_type, $sort_order]);
            $message = 'Услуга успешно добавлена';
        } catch (PDOException $e) {
            $error = 'Ошибка добавления: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Обработка редактирования услуги
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_service'])) {
    $id = (int)$_POST['service_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $price_type = $_POST['price_type'];
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Введите название услуги';
    }
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE extra_services 
                SET name = ?, description = ?, price = ?, price_type = ?, sort_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $price_type, $sort_order, $is_active, $id]);
            $message = 'Услуга успешно обновлена';
        } catch (PDOException $e) {
            $error = 'Ошибка обновления: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Обработка удаления услуги
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Проверка, используется ли услуга в визитах
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE extra_services_json LIKE ?");
        $stmt->execute(['%\"' . $id . '\"%']);
        $used_count = $stmt->fetchColumn();
        
        if ($used_count > 0) {
            $error = "Нельзя удалить услугу, так как она используется в {$used_count} визитах";
        } else {
            $stmt = $db->prepare("DELETE FROM extra_services WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Услуга удалена';
        }
    } catch (PDOException $e) {
        $error = 'Ошибка удаления: ' . $e->getMessage();
    }
}

// Обработка изменения статуса (вкл/выкл)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $db->prepare("UPDATE extra_services SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    $message = 'Статус услуги изменён';
}

// Получение списка услуг
$stmt = $db->query("
    SELECT * FROM extra_services 
    ORDER BY sort_order ASC, id ASC
");
$services = $stmt->fetchAll();

// Получение статистики использования услуг
$usage_stats = [];
foreach ($services as $service) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as usage_count, COALESCE(SUM(total_amount), 0) as total_revenue
        FROM visits 
        WHERE status = 'completed' AND extra_services_json LIKE ?
    ");
    $stmt->execute(['%\"' . $service['id'] . '\"%']);
    $usage_stats[$service['id']] = $stmt->fetch();
}

// Получение общей статистики
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_services,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_services,
        AVG(price) as avg_price,
        MIN(price) as min_price,
        MAX(price) as max_price
    FROM extra_services
");
$overall_stats = $stmt->fetch();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">✨ Дополнительные услуги</h1>
            <p class="text-gray-600 mt-1">Управление услугами и их стоимостью</p>
        </div>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>Добавить услугу</span>
        </button>
    </div>
    
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
            <span><?= $message ?></span>
            <button onclick="this.parentElement.remove()" class="text-green-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
            <span><?= $error ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Всего услуг</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $overall_stats['total_services'] ?? 0 ?></p>
                </div>
                <i class="fas fa-cube text-3xl text-blue-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Активных услуг</p>
                    <p class="text-2xl font-bold text-green-600"><?= $overall_stats['active_services'] ?? 0 ?></p>
                </div>
                <i class="fas fa-check-circle text-3xl text-green-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Средняя цена</p>
                    <p class="text-2xl font-bold text-purple-600"><?= number_format($overall_stats['avg_price'] ?? 0, 0) ?> ₽</p>
                </div>
                <i class="fas fa-ruble-sign text-3xl text-purple-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Минимальная цена</p>
                    <p class="text-2xl font-bold text-orange-600"><?= number_format($overall_stats['min_price'] ?? 0, 0) ?> ₽</p>
                </div>
                <i class="fas fa-arrow-down text-3xl text-orange-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Максимальная цена</p>
                    <p class="text-2xl font-bold text-red-600"><?= number_format($overall_stats['max_price'] ?? 0, 0) ?> ₽</p>
                </div>
                <i class="fas fa-arrow-up text-3xl text-red-400"></i>
            </div>
        </div>
    </div>
    
    <!-- Таблица услуг -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📋 Список услуг</h2>
            <p class="text-sm text-gray-500 mt-1">Управление дополнительными услугами коворкинга</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">ID</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Название</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Описание</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Тип оплаты</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Цена</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Использовано</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Выручка</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Статус</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                <i class="fas fa-cube fa-2x mb-2 block"></i>
                                Нет добавленных услуг
                             </td>
                         </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): 
                            $usage = $usage_stats[$service['id']] ?? ['usage_count' => 0, 'total_revenue' => 0];
                            $price_types = [
                                'fixed' => 'Фиксированная',
                                'per_hour' => 'За час',
                                'per_day' => 'За день'
                            ];
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 text-gray-500">#<?= $service['id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($service['name']) ?></div>
                                    <div class="text-xs text-gray-500">Сорт: <?= $service['sort_order'] ?></div>
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-600 max-w-xs truncate">
                                    <?= htmlspecialchars($service['description'] ?: '—') ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?= $price_types[$service['price_type']] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-semibold text-purple-600"><?= number_format($service['price'], 0) ?> ₽</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        <?= $usage['usage_count'] ?> раз
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right text-sm">
                                    <?= number_format($usage['total_revenue'], 0, ',', ' ') ?> ₽
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($service['is_active']): ?>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>Активна
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            <i class="fas fa-ban mr-1"></i>Отключена
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button onclick="toggleService(<?= $service['id'] ?>)" 
                                                class="text-<?= $service['is_active'] ? 'yellow' : 'green' ?>-600 hover:text-<?= $service['is_active'] ? 'yellow' : 'green' ?>-800 transition"
                                                title="<?= $service['is_active'] ? 'Отключить' : 'Включить' ?>">
                                            <i class="fas fa-<?= $service['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <button onclick='editService(<?= json_encode($service) ?>)' 
                                                class="text-blue-600 hover:text-blue-800 transition" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteService(<?= $service['id'] ?>, '<?= htmlspecialchars($service['name']) ?>')" 
                                                class="text-red-600 hover:text-red-800 transition" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Популярные услуги -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">🔥 Самые популярные услуги</h3>
            <div class="space-y-4">
                <?php 
                $popular = array_filter($services, function($s) use ($usage_stats) {
                    return ($usage_stats[$s['id']]['usage_count'] ?? 0) > 0;
                });
                usort($popular, function($a, $b) use ($usage_stats) {
                    return ($usage_stats[$b['id']]['usage_count'] ?? 0) - ($usage_stats[$a['id']]['usage_count'] ?? 0);
                });
                $popular = array_slice($popular, 0, 5);
                
                if (empty($popular)): ?>
                    <p class="text-gray-500 text-center py-4">Нет данных об использовании услуг</p>
                <?php else: ?>
                    <?php foreach ($popular as $service): 
                        $usage = $usage_stats[$service['id']] ?? ['usage_count' => 0];
                        $max_usage = max(array_column($usage_stats, 'usage_count')) ?: 1;
                        $percentage = ($usage['usage_count'] / $max_usage) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium"><?= htmlspecialchars($service['name']) ?></span>
                                <span class="text-gray-600"><?= $usage['usage_count'] ?> использований</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-orange-500 rounded-full h-2" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">💰 Топ по выручке</h3>
            <div class="space-y-4">
                <?php 
                $top_revenue = array_filter($services, function($s) use ($usage_stats) {
                    return ($usage_stats[$s['id']]['total_revenue'] ?? 0) > 0;
                });
                usort($top_revenue, function($a, $b) use ($usage_stats) {
                    return ($usage_stats[$b['id']]['total_revenue'] ?? 0) - ($usage_stats[$a['id']]['total_revenue'] ?? 0);
                });
                $top_revenue = array_slice($top_revenue, 0, 5);
                
                if (empty($top_revenue)): ?>
                    <p class="text-gray-500 text-center py-4">Нет данных о выручке</p>
                <?php else: ?>
                    <?php foreach ($top_revenue as $service): 
                        $revenue = $usage_stats[$service['id']]['total_revenue'] ?? 0;
                        $max_revenue = max(array_column($usage_stats, 'total_revenue')) ?: 1;
                        $percentage = ($revenue / $max_revenue) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium"><?= htmlspecialchars($service['name']) ?></span>
                                <span class="text-green-600 font-semibold"><?= number_format($revenue, 0, ',', ' ') ?> ₽</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 rounded-full h-2" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления услуги -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">➕ Добавить услугу</h2>
            <button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                <input type="text" name="name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                <div class="flex">
                    <input type="number" name="price" step="0.01" required
                           class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Тип оплаты</label>
                <select name="price_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="fixed">Фиксированная (за весь период)</option>
                    <option value="per_hour">За час</option>
                    <option value="per_day">За день</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                <input type="number" name="sort_order" value="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" name="add_service" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition">
                    Добавить
                </button>
                <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded-lg transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно редактирования услуги -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">✏️ Редактировать услугу</h2>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="service_id" id="edit_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                <input type="text" name="name" id="edit_name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                <textarea name="description" id="edit_description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                <div class="flex">
                    <input type="number" name="price" id="edit_price" step="0.01" required
                           class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Тип оплаты</label>
                <select name="price_type" id="edit_price_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value="fixed">Фиксированная (за весь период)</option>
                    <option value="per_hour">За час</option>
                    <option value="per_day">За день</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                <input type="number" name="sort_order" id="edit_sort_order"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" id="edit_is_active">
                    <span class="text-sm text-gray-700">Услуга активна</span>
                </label>
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" name="edit_service" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition">
                    Сохранить
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded-lg transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Открыть модальное окно добавления
function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('addModal').classList.add('flex');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    document.getElementById('addModal').classList.remove('flex');
}

// Редактирование услуги
function editService(service) {
    document.getElementById('edit_id').value = service.id;
    document.getElementById('edit_name').value = service.name;
    document.getElementById('edit_description').value = service.description || '';
    document.getElementById('edit_price').value = service.price;
    document.getElementById('edit_price_type').value = service.price_type;
    document.getElementById('edit_sort_order').value = service.sort_order;
    document.getElementById('edit_is_active').checked = service.is_active == 1;
    
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

// Переключение статуса услуги
function toggleService(id) {
    window.location.href = `?toggle=${id}`;
}

// Удаление услуги
function deleteService(id, name) {
    if (confirm(`Вы уверены, что хотите удалить услугу "${name}"?`)) {
        window.location.href = `?delete=${id}`;
    }
}

// Закрытие модальных окон при клике вне их
document.addEventListener('click', function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    
    if (event.target === addModal) closeAddModal();
    if (event.target === editModal) closeEditModal();
});
</script>

<?php include 'footer.php'; ?>