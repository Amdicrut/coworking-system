<?php
// =====================================================
// tariffs.php - Управление тарифами
// Коворкинг-центр: добавление, редактирование, удаление тарифов
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Обработка добавления тарифа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tariff'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $tariff_type = $_POST['tariff_type'];
    $price = (float)$_POST['price'];
    $price_per_hour = !empty($_POST['price_per_hour']) ? (float)$_POST['price_per_hour'] : null;
    $min_hours = (int)$_POST['min_hours'];
    $max_hours = !empty($_POST['max_hours']) ? (int)$_POST['max_hours'] : null;
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
    $valid_to = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;
    $valid_days = !empty($_POST['valid_days']) ? implode(',', $_POST['valid_days']) : null;
    $sort_order = (int)$_POST['sort_order'];
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Введите название тарифа';
    }
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO tariffs (name, description, tariff_type, price, price_per_hour, min_hours, max_hours, valid_from, valid_to, valid_days, sort_order, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $tariff_type, $price, $price_per_hour, $min_hours, $max_hours, $valid_from, $valid_to, $valid_days, $sort_order]);
            $message = 'Тариф успешно добавлен';
        } catch (PDOException $e) {
            $error = 'Ошибка добавления: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Обработка редактирования тарифа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_tariff'])) {
    $id = (int)$_POST['tariff_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $tariff_type = $_POST['tariff_type'];
    $price = (float)$_POST['price'];
    $price_per_hour = !empty($_POST['price_per_hour']) ? (float)$_POST['price_per_hour'] : null;
    $min_hours = (int)$_POST['min_hours'];
    $max_hours = !empty($_POST['max_hours']) ? (int)$_POST['max_hours'] : null;
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
    $valid_to = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;
    $valid_days = !empty($_POST['valid_days']) ? implode(',', $_POST['valid_days']) : null;
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Введите название тарифа';
    }
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE tariffs 
                SET name = ?, description = ?, tariff_type = ?, price = ?, price_per_hour = ?, 
                    min_hours = ?, max_hours = ?, valid_from = ?, valid_to = ?, valid_days = ?, 
                    sort_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $tariff_type, $price, $price_per_hour, 
                           $min_hours, $max_hours, $valid_from, $valid_to, $valid_days, 
                           $sort_order, $is_active, $id]);
            $message = 'Тариф успешно обновлён';
        } catch (PDOException $e) {
            $error = 'Ошибка обновления: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Обработка удаления тарифа
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Проверка, используется ли тариф в визитах
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE tariff_id = ?");
        $stmt->execute([$id]);
        $used_count = $stmt->fetchColumn();
        
        if ($used_count > 0) {
            $error = "Нельзя удалить тариф, так как он используется в {$used_count} визитах";
        } else {
            $stmt = $db->prepare("DELETE FROM tariffs WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Тариф удалён';
        }
    } catch (PDOException $e) {
        $error = 'Ошибка удаления: ' . $e->getMessage();
    }
}

// Обработка изменения статуса (вкл/выкл)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $db->prepare("UPDATE tariffs SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    $message = 'Статус тарифа изменён';
}

// Получение списка тарифов
$stmt = $db->query("
    SELECT * FROM tariffs 
    ORDER BY sort_order ASC, id ASC
");
$tariffs = $stmt->fetchAll();

// Получение статистики использования тарифов
$usage_stats = [];
foreach ($tariffs as $tariff) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as usage_count, COALESCE(SUM(total_amount), 0) as total_revenue
        FROM visits 
        WHERE status = 'completed' AND tariff_id = ?
    ");
    $stmt->execute([$tariff['id']]);
    $usage_stats[$tariff['id']] = $stmt->fetch();
}

// Получение общей статистики
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_tariffs,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tariffs,
        AVG(price) as avg_price,
        MIN(price) as min_price,
        MAX(price) as max_price
    FROM tariffs
");
$overall_stats = $stmt->fetch();

// Типы тарифов для отображения
$tariff_types = [
    'hourly' => 'Почасовой',
    'daily' => 'Дневной',
    'weekly' => 'Недельный',
    'monthly' => 'Месячный',
    'night' => 'Ночной',
    'weekend' => 'Выходной день'
];

// Дни недели на русском
$weekdays_ru = [
    1 => 'ПН', 2 => 'ВТ', 3 => 'СР', 4 => 'ЧТ', 5 => 'ПТ', 6 => 'СБ', 7 => 'ВС'
];

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🏷️ Управление тарифами</h1>
            <p class="text-gray-600 mt-1">Настройка ценовых планов и тарифов</p>
        </div>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>Добавить тариф</span>
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
                    <p class="text-gray-500 text-sm">Всего тарифов</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $overall_stats['total_tariffs'] ?? 0 ?></p>
                </div>
                <i class="fas fa-tags text-3xl text-blue-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Активных тарифов</p>
                    <p class="text-2xl font-bold text-green-600"><?= $overall_stats['active_tariffs'] ?? 0 ?></p>
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
    
    <!-- Таблица тарифов -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📋 Список тарифов</h2>
            <p class="text-sm text-gray-500 mt-1">Управление тарифными планами коворкинга</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">ID</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Название</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Тип</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Ограничения</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Цена</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Цена/час</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Использовано</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Выручка</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Статус</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tariffs)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-500">
                                <i class="fas fa-tags fa-2x mb-2 block"></i>
                                Нет добавленных тарифов
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tariffs as $tariff): 
                            $usage = $usage_stats[$tariff['id']] ?? ['usage_count' => 0, 'total_revenue' => 0];
                            $type_name = $tariff_types[$tariff['tariff_type']] ?? $tariff['tariff_type'];
                            
                            // Формирование строки ограничений
                            $limits = [];
                            if ($tariff['min_hours'] > 1) $limits[] = "мин. {$tariff['min_hours']} ч";
                            if ($tariff['max_hours']) $limits[] = "макс. {$tariff['max_hours']} ч";
                            if ($tariff['valid_from'] && $tariff['valid_to']) {
                                $limits[] = date('H:i', strtotime($tariff['valid_from'])) . '-' . date('H:i', strtotime($tariff['valid_to']));
                            }
                            if ($tariff['valid_days']) {
                                $days = explode(',', $tariff['valid_days']);
                                $days_ru = array_map(function($d) use ($weekdays_ru) { return $weekdays_ru[$d] ?? $d; }, $days);
                                $limits[] = implode(',', $days_ru);
                            }
                            $limits_str = !empty($limits) ? implode(', ', $limits) : '—';
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 text-gray-500">#<?= $tariff['id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($tariff['name']) ?></div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs"><?= htmlspecialchars($tariff['description'] ?: '—') ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?= $type_name ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-600">
                                    <?= $limits_str ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-semibold text-purple-600"><?= number_format($tariff['price'], 0) ?> ₽</span>
                                </td>
                                <td class="py-3 px-4 text-right text-sm text-gray-600">
                                    <?= $tariff['price_per_hour'] ? number_format($tariff['price_per_hour'], 0) . ' ₽' : '—' ?>
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
                                    <?php if ($tariff['is_active']): ?>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>Активен
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            <i class="fas fa-ban mr-1"></i>Отключён
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button onclick="toggleTariff(<?= $tariff['id'] ?>)" 
                                                class="text-<?= $tariff['is_active'] ? 'yellow' : 'green' ?>-600 hover:text-<?= $tariff['is_active'] ? 'yellow' : 'green' ?>-800 transition"
                                                title="<?= $tariff['is_active'] ? 'Отключить' : 'Включить' ?>">
                                            <i class="fas fa-<?= $tariff['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <button onclick='editTariff(<?= htmlspecialchars(json_encode($tariff), ENT_QUOTES) ?>)' 
                                                class="text-blue-600 hover:text-blue-800 transition" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteTariff(<?= $tariff['id'] ?>, '<?= htmlspecialchars($tariff['name']) ?>')" 
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
</div>

<!-- Модальное окно добавления тарифа -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">➕ Добавить тариф</h2>
            <button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input type="text" name="name" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea name="description" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тип тарифа *</label>
                    <select name="tariff_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="hourly">Почасовой</option>
                        <option value="daily">Дневной</option>
                        <option value="weekly">Недельный</option>
                        <option value="monthly">Месячный</option>
                        <option value="night">Ночной</option>
                        <option value="weekend">Выходной день</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                    <input type="number" name="sort_order" value="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                    <div class="flex">
                        <input type="number" name="price" step="0.01" required
                               class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена за час</label>
                    <div class="flex">
                        <input type="number" name="price_per_hour" step="0.01"
                               class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Мин. часов</label>
                    <input type="number" name="min_hours" value="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Макс. часов</label>
                    <input type="number" name="max_hours"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Время начала</label>
                    <input type="time" name="valid_from"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Время окончания</label>
                    <input type="time" name="valid_to"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дни действия</label>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="1"> <span>ПН</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="2"> <span>ВТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="3"> <span>СР</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="4"> <span>ЧТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="5"> <span>ПТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="6"> <span>СБ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="7"> <span>ВС</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" name="add_tariff" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition">
                    Добавить
                </button>
                <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded-lg transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно редактирования тарифа -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">✏️ Редактировать тариф</h2>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="tariff_id" id="edit_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input type="text" name="name" id="edit_name" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea name="description" id="edit_description" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Тип тарифа *</label>
                    <select name="tariff_type" id="edit_tariff_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="hourly">Почасовой</option>
                        <option value="daily">Дневной</option>
                        <option value="weekly">Недельный</option>
                        <option value="monthly">Месячный</option>
                        <option value="night">Ночной</option>
                        <option value="weekend">Выходной день</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                    <input type="number" name="sort_order" id="edit_sort_order"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                    <div class="flex">
                        <input type="number" name="price" id="edit_price" step="0.01" required
                               class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена за час</label>
                    <div class="flex">
                        <input type="number" name="price_per_hour" id="edit_price_per_hour" step="0.01"
                               class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <span class="bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg px-3 py-2 text-gray-600">₽</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Мин. часов</label>
                    <input type="number" name="min_hours" id="edit_min_hours"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Макс. часов</label>
                    <input type="number" name="max_hours" id="edit_max_hours"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Время начала</label>
                    <input type="time" name="valid_from" id="edit_valid_from"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Время окончания</label>
                    <input type="time" name="valid_to" id="edit_valid_to"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дни действия</label>
                    <div class="flex flex-wrap gap-2" id="edit_valid_days_container">
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="1" class="edit-day-cb"> <span>ПН</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="2" class="edit-day-cb"> <span>ВТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="3" class="edit-day-cb"> <span>СР</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="4" class="edit-day-cb"> <span>ЧТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="5" class="edit-day-cb"> <span>ПТ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="6" class="edit-day-cb"> <span>СБ</span>
                        </label>
                        <label class="flex items-center space-x-1">
                            <input type="checkbox" name="valid_days[]" value="7" class="edit-day-cb"> <span>ВС</span>
                        </label>
                    </div>
                </div>
                
                <div class="col-span-2">
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="is_active" id="edit_is_active">
                        <span class="text-sm text-gray-700">Тариф активен</span>
                    </label>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="submit" name="edit_tariff" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition">
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

// Редактирование тарифа
function editTariff(tariff) {
    document.getElementById('edit_id').value = tariff.id;
    document.getElementById('edit_name').value = tariff.name;
    document.getElementById('edit_description').value = tariff.description || '';
    document.getElementById('edit_tariff_type').value = tariff.tariff_type;
    document.getElementById('edit_sort_order').value = tariff.sort_order;
    document.getElementById('edit_price').value = tariff.price;
    document.getElementById('edit_price_per_hour').value = tariff.price_per_hour || '';
    document.getElementById('edit_min_hours').value = tariff.min_hours;
    document.getElementById('edit_max_hours').value = tariff.max_hours || '';
    document.getElementById('edit_valid_from').value = tariff.valid_from || '';
    document.getElementById('edit_valid_to').value = tariff.valid_to || '';
    document.getElementById('edit_is_active').checked = tariff.is_active == 1;
    
    // Установка дней недели
    const validDays = tariff.valid_days ? tariff.valid_days.split(',') : [];
    document.querySelectorAll('.edit-day-cb').forEach(cb => {
        cb.checked = validDays.includes(cb.value);
    });
    
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

// Переключение статуса тарифа
function toggleTariff(id) {
    window.location.href = `?toggle=${id}`;
}

// Удаление тарифа
function deleteTariff(id, name) {
    if (confirm(`Вы уверены, что хотите удалить тариф "${name}"?`)) {
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