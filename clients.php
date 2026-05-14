<?php
// =====================================================
// clients.php - Управление клиентами и RFM сегментация
// Коворкинг-центр: список, фильтрация, RFM-метки
// =====================================================

require_once 'config.php';
require_once 'functions.php';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Добавление клиента
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_client'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $birthday = $_POST['birthday'] ?: null;
    
    if (empty($full_name) || empty($phone)) {
        $error = 'Заполните имя и телефон';
    } else {
        $db = getDB();
        
        // Проверка на дубликат телефона
        $stmt = $db->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmt->execute([$phone]);
        
        if ($stmt->fetch()) {
            $error = 'Клиент с таким телефоном уже существует';
        } else {
            $stmt = $db->prepare("
                INSERT INTO clients (full_name, phone, email, birthday, registration_date) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$full_name, $phone, $email, $birthday])) {
                $message = 'Клиент успешно добавлен';
            } else {
                $error = 'Ошибка при добавлении клиента';
            }
        }
    }
}

// Редактирование клиента
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_client'])) {
    $id = $_POST['client_id'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $birthday = $_POST['birthday'] ?: null;
    
    if (empty($full_name) || empty($phone)) {
        $error = 'Заполните имя и телефон';
    } else {
        $db = getDB();
        
        // Проверка на дубликат телефона (исключая текущего клиента)
        $stmt = $db->prepare("SELECT id FROM clients WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $id]);
        
        if ($stmt->fetch()) {
            $error = 'Клиент с таким телефоном уже существует';
        } else {
            $stmt = $db->prepare("
                UPDATE clients 
                SET full_name = ?, phone = ?, email = ?, birthday = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$full_name, $phone, $email, $birthday, $id])) {
                $message = 'Данные клиента обновлены';
            } else {
                $error = 'Ошибка при обновлении';
            }
        }
    }
}

// Удаление клиента
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $db = getDB();
    
    // Проверка, есть ли активные визиты
    $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE client_id = ? AND status = 'active'");
    $stmt->execute([$id]);
    $active_visits = $stmt->fetchColumn();
    
    if ($active_visits > 0) {
        $error = 'Нельзя удалить клиента с активными визитами';
    } else {
        $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = 'Клиент удалён';
        } else {
            $error = 'Ошибка при удалении';
        }
    }
}

// Получение списка клиентов с фильтрацией
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$segment_filter = $_GET['segment'] ?? 'all';

$db = getDB();

// Построение запроса
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($segment_filter != 'all') {
    $where_conditions[] = "rfm_segment = ?";
    $params[] = $segment_filter;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Получение общего количества
$count_sql = "SELECT COUNT(*) as total FROM clients $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_clients = $stmt->fetch()['total'];
$total_pages = ceil($total_clients / $limit);

// Получение списка клиентов
$sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM visits WHERE client_id = c.id AND status = 'completed') as total_visits_count,
           (SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE client_id = c.id AND status = 'completed') as total_spent_amount,
           (SELECT MAX(end_time) FROM visits WHERE client_id = c.id AND status = 'completed') as last_visit
    FROM clients c
    $where_sql
    ORDER BY c.created_at DESC
    LIMIT $offset, $limit
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Получение статистики по RFM сегментам
$stmt = $db->query("
    SELECT 
        rfm_segment,
        COUNT(*) as count,
        ROUND(AVG(total_spent), 0) as avg_spent,
        ROUND(AVG(total_visits), 1) as avg_visits
    FROM clients 
    WHERE rfm_segment IS NOT NULL
    GROUP BY rfm_segment
    ORDER BY 
        CASE rfm_segment
            WHEN 'Champions' THEN 1
            WHEN 'Loyal' THEN 2
            WHEN 'Potential' THEN 3
            WHEN 'Promising' THEN 4
            WHEN 'Regular' THEN 5
            WHEN 'At Risk' THEN 6
            WHEN 'Lost' THEN 7
            ELSE 8
        END
");
$segment_stats = $stmt->fetchAll();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Управление клиентами</h1>
            <p class="text-gray-600 mt-1">Просмотр, добавление и RFM-сегментация</p>
        </div>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>Добавить клиента</span>
        </button>
    </div>
    
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
            <span><?= $message ?></span>
            <button onclick="this.parentElement.remove()" class="text-green-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
            <span><?= $error ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Статистика по RFM сегментам -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <?php
        $segment_colors = [
            'Champions' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'Loyal' => 'bg-green-100 text-green-800 border-green-300',
            'Potential' => 'bg-blue-100 text-blue-800 border-blue-300',
            'Promising' => 'bg-purple-100 text-purple-800 border-purple-300',
            'Regular' => 'bg-gray-100 text-gray-800 border-gray-300',
            'At Risk' => 'bg-red-100 text-red-800 border-red-300',
            'Lost' => 'bg-gray-300 text-gray-700 border-gray-400'
        ];
        
        foreach ($segment_stats as $stat):
            $color = $segment_colors[$stat['rfm_segment']] ?? 'bg-gray-100 text-gray-800';
        ?>
        <div class="rounded-lg border p-3 text-center <?= $color ?>">
            <p class="text-xs font-semibold"><?= $stat['rfm_segment'] ?></p>
            <p class="text-2xl font-bold"><?= $stat['count'] ?></p>
            <p class="text-xs"><?= number_format($stat['avg_spent'], 0) ?> ₽</p>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Фильтры и поиск -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Поиск</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Имя, телефон, email..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">RFM сегмент</label>
                <select name="segment" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">Все сегменты</option>
                    <option value="Champions" <?= $segment_filter == 'Champions' ? 'selected' : '' ?>>🏆 Champions</option>
                    <option value="Loyal" <?= $segment_filter == 'Loyal' ? 'selected' : '' ?>>❤️ Loyal</option>
                    <option value="Potential" <?= $segment_filter == 'Potential' ? 'selected' : '' ?>>📈 Potential</option>
                    <option value="Promising" <?= $segment_filter == 'Promising' ? 'selected' : '' ?>>✨ Promising</option>
                    <option value="Regular" <?= $segment_filter == 'Regular' ? 'selected' : '' ?>>📋 Regular</option>
                    <option value="At Risk" <?= $segment_filter == 'At Risk' ? 'selected' : '' ?>>⚠️ At Risk</option>
                    <option value="Lost" <?= $segment_filter == 'Lost' ? 'selected' : '' ?>>💔 Lost</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-2"></i>Фильтровать
                </button>
                <a href="clients.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition ml-2 inline-block">
                    <i class="fas fa-undo mr-2"></i>Сбросить
                </a>
            </div>
        </form>
    </div>
    
    <!-- Таблица клиентов -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-600">ID</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-600">Клиент</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-600">Контакты</th>
                        <th class="text-center py-3 px-4 font-semibold text-gray-600">Визитов</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-600">Потрачено</th>
                        <th class="text-center py-3 px-4 font-semibold text-gray-600">RFM</th>
                        <th class="text-center py-3 px-4 font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <i class="fas fa-users fa-2x mb-2 block"></i>
                                Клиенты не найдены
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): 
                            $rfm_color = $segment_colors[$client['rfm_segment']] ?? 'bg-gray-100 text-gray-800';
                            $rfm_score = ($client['r_score'] ?? 1) + ($client['f_score'] ?? 1) + ($client['m_score'] ?? 1);
                        ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="py-3 px-4 text-gray-500">#<?= $client['id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($client['full_name']) ?></div>
                                    <?php if ($client['birthday']): ?>
                                        <div class="text-xs text-gray-500">🎂 <?= date('d.m.Y', strtotime($client['birthday'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-sm"><?= htmlspecialchars($client['phone']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($client['email']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?= $client['total_visits_count'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-semibold text-purple-600">
                                    <?= number_format($client['total_spent_amount'] ?? 0, 0, ',', ' ') ?> ₽
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full <?= $rfm_color ?>">
                                        <?= $client['rfm_segment'] ?? 'New' ?>
                                    </span>
                                    <div class="text-xs text-gray-400 mt-1">
                                        RFM: <?= $client['r_score'] ?? 1 ?>/<?= $client['f_score'] ?? 1 ?>/<?= $client['m_score'] ?? 1 ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button onclick="viewClient(<?= $client['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 transition" title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editClient(<?= htmlspecialchars(json_encode($client)) ?>)" 
                                                class="text-green-600 hover:text-green-800 transition" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteClient(<?= $client['id'] ?>, '<?= htmlspecialchars($client['full_name']) ?>')" 
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
        
        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-between items-center p-4 bg-gray-50 border-t">
            <div class="text-sm text-gray-600">
                Показано <?= count($clients) ?> из <?= $total_clients ?> клиентов
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&segment=<?= urlencode($segment_filter) ?>" 
                       class="px-3 py-1 border rounded hover:bg-gray-200 transition">← Назад</a>
                <?php endif; ?>
                
                <span class="px-3 py-1 bg-blue-600 text-white rounded"><?= $page ?></span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&segment=<?= urlencode($segment_filter) ?>" 
                       class="px-3 py-1 border rounded hover:bg-gray-200 transition">Вперёд →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Модальное окно добавления клиента -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Добавить клиента</h2>
            <button onclick="closeAddModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ФИО *</label>
                <input type="text" name="full_name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Телефон *</label>
                <input type="tel" name="phone" required placeholder="+7XXXXXXXXXX"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата рождения</label>
                <input type="date" name="birthday"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" name="add_client" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition">
                    Добавить
                </button>
                <button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded-lg transition">
                    Отмена
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно редактирования -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Редактировать клиента</h2>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="client_id" id="edit_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ФИО *</label>
                <input type="text" name="full_name" id="edit_full_name" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Телефон *</label>
                <input type="tel" name="phone" id="edit_phone" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" id="edit_email"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата рождения</label>
                <input type="date" name="birthday" id="edit_birthday"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" name="edit_client" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg transition">
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

// Редактирование клиента
function editClient(client) {
    document.getElementById('edit_id').value = client.id;
    document.getElementById('edit_full_name').value = client.full_name;
    document.getElementById('edit_phone').value = client.phone;
    document.getElementById('edit_email').value = client.email || '';
    document.getElementById('edit_birthday').value = client.birthday || '';
    
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

// Просмотр клиента
function viewClient(id) {
    window.location.href = 'client-view.php?id=' + id;
}

// Удаление клиента
function deleteClient(id, name) {
    if (confirm(`Вы уверены, что хотите удалить клиента "${name}"?`)) {
        window.location.href = '?delete=' + id;
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