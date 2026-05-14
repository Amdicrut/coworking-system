<?php
// =====================================================
// client-edit.php - Редактирование данных клиента
// Коворкинг-центр: изменение информации о клиенте
// =====================================================

require_once 'config.php';
require_once 'functions.php';

// Получаем ID клиента
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($client_id <= 0) {
    header("Location: clients.php?error=invalid_id");
    exit();
}

// Получаем данные клиента
$db = getDB();
$stmt = $db->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM visits WHERE client_id = c.id AND status = 'completed') as total_visits_count,
           (SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE client_id = c.id AND status = 'completed') as total_spent_amount,
           (SELECT SUM(duration_hours) FROM visits WHERE client_id = c.id AND status = 'completed') as total_hours
    FROM clients c 
    WHERE c.id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: clients.php?error=client_not_found");
    exit();
}

$message = '';
$error = '';

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_client'])) {
    
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Валидация
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Введите ФИО клиента';
    }
    
    if (empty($phone)) {
        $errors[] = 'Введите номер телефона';
    } elseif (!preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
        $errors[] = 'Введите корректный номер телефона';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email';
    }
    
    // Проверка на дубликат телефона (исключая текущего клиента)
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM clients WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $client_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Клиент с таким номером телефона уже существует';
        }
    }
    
    // Проверка на дубликат email (исключая текущего клиента)
    if (empty($errors) && !empty($email)) {
        $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
        $stmt->execute([$email, $client_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Клиент с таким email уже существует';
        }
    }
    
    // Если ошибок нет - обновляем
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE clients 
                SET full_name = ?, phone = ?, email = ?, birthday = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$full_name, $phone, $email, $birthday ?: null, $notes, $client_id])) {
                $message = 'Данные клиента успешно обновлены';
                
                // Обновляем данные в переменной client
                $client['full_name'] = $full_name;
                $client['phone'] = $phone;
                $client['email'] = $email;
                $client['birthday'] = $birthday;
                $client['notes'] = $notes;
            } else {
                $error = 'Ошибка при обновлении данных';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Пересчёт RFM (если запрошено)
if (isset($_GET['recalc_rfm'])) {
    try {
        // Вызов хранимой процедуры или прямой расчёт
        $stmt = $db->prepare("
            UPDATE clients 
            SET total_visits = (
                SELECT COUNT(*) FROM visits WHERE client_id = ? AND status = 'completed'
            ),
            total_spent = (
                SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE client_id = ? AND status = 'completed'
            ),
            total_hours = (
                SELECT COALESCE(SUM(duration_hours), 0) FROM visits WHERE client_id = ? AND status = 'completed'
            ),
            last_visit_date = (
                SELECT MAX(end_time) FROM visits WHERE client_id = ? AND status = 'completed'
            )
            WHERE id = ?
        ");
        $stmt->execute([$client_id, $client_id, $client_id, $client_id, $client_id]);
        
        // Обновляем RFM баллы
        $stmt = $db->prepare("
            UPDATE clients 
            SET 
                r_score = CASE 
                    WHEN DATEDIFF(NOW(), last_visit_date) <= 7 THEN 3
                    WHEN DATEDIFF(NOW(), last_visit_date) <= 30 THEN 2
                    ELSE 1
                END,
                f_score = CASE 
                    WHEN total_visits >= 10 THEN 3
                    WHEN total_visits >= 3 THEN 2
                    ELSE 1
                END,
                m_score = CASE 
                    WHEN total_spent >= 10000 THEN 3
                    WHEN total_spent >= 3000 THEN 2
                    ELSE 1
                END,
                rfm_segment = CASE
                    WHEN r_score = 3 AND f_score = 3 AND m_score = 3 THEN 'Champions'
                    WHEN r_score = 3 AND f_score = 3 AND m_score = 2 THEN 'Loyal'
                    WHEN r_score = 2 AND f_score = 3 AND m_score >= 2 THEN 'Potential'
                    WHEN r_score = 3 AND f_score = 2 AND m_score >= 2 THEN 'Promising'
                    WHEN r_score = 3 AND f_score = 1 AND m_score = 1 THEN 'New'
                    WHEN r_score = 1 AND f_score = 1 AND m_score = 1 THEN 'Lost'
                    WHEN r_score = 1 AND f_score >= 2 THEN 'At Risk'
                    ELSE 'Regular'
                END
            WHERE id = ?
        ");
        $stmt->execute([$client_id]);
        
        $message = 'RFM-сегментация успешно пересчитана';
        
        // Обновляем данные клиента
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error = 'Ошибка при пересчёте RFM: ' . $e->getMessage();
    }
}

// Получение истории визитов клиента
$stmt = $db->prepare("
    SELECT v.*, t.name as tariff_name,
           CASE 
               WHEN v.status = 'active' THEN 'Активен'
               WHEN v.status = 'completed' THEN 'Завершён'
               ELSE 'Отменён'
           END as status_name
    FROM visits v
    LEFT JOIN tariffs t ON t.id = v.tariff_id
    WHERE v.client_id = ?
    ORDER BY v.start_time DESC
    LIMIT 10
");
$stmt->execute([$client_id]);
$visits_history = $stmt->fetchAll();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <div class="max-w-6xl mx-auto">
        
        <!-- Заголовок -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">✏️ Редактирование клиента</h1>
                <p class="text-gray-600 mt-1">Изменение информации о клиенте</p>
            </div>
            <div class="flex space-x-2">
                <a href="client-view.php?id=<?= $client_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
                    <i class="fas fa-eye"></i>
                    <span>Просмотр</span>
                </a>
                <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Назад</span>
                </a>
            </div>
        </div>
        
        <!-- Сообщения -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
                <div class="flex items-start space-x-2">
                    <i class="fas fa-check-circle mt-0.5"></i>
                    <span><?= $message ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
                <div class="flex items-start space-x-2">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span><?= $error ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Форма редактирования -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-6 py-4">
                        <h2 class="text-xl font-bold text-gray-800">📝 Основная информация</h2>
                    </div>
                    
                    <form method="POST" class="p-6">
                        <div class="space-y-4">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ФИО <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="full_name" 
                                       value="<?= htmlspecialchars($client['full_name']) ?>" 
                                       required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Телефон <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       value="<?= htmlspecialchars($client['phone']) ?>" 
                                       required
                                       id="phoneInput"
                                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email
                                </label>
                                <input type="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($client['email']) ?>" 
                                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Дата рождения
                                </label>
                                <input type="date" 
                                       name="birthday" 
                                       value="<?= $client['birthday'] ?>" 
                                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Примечания
                                </label>
                                <textarea name="notes" 
                                          rows="4" 
                                          placeholder="Дополнительная информация..."
                                          class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="window.location.href='clients.php'" 
                                        class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition">
                                    Отмена
                                </button>
                                <button type="submit" name="edit_client" 
                                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center space-x-2">
                                    <i class="fas fa-save"></i>
                                    <span>Сохранить изменения</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Боковая панель с информацией -->
            <div class="space-y-6">
                
                <!-- RFM карточка -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 RFM сегментация</h3>
                    
                    <?php
                    $rfm_colors = [
                        'Champions' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                        'Loyal' => 'bg-green-100 text-green-800 border-green-300',
                        'Potential' => 'bg-blue-100 text-blue-800 border-blue-300',
                        'Promising' => 'bg-purple-100 text-purple-800 border-purple-300',
                        'Regular' => 'bg-gray-100 text-gray-800 border-gray-300',
                        'At Risk' => 'bg-red-100 text-red-800 border-red-300',
                        'Lost' => 'bg-gray-300 text-gray-700 border-gray-400',
                        'New' => 'bg-cyan-100 text-cyan-800 border-cyan-300'
                    ];
                    $segment = $client['rfm_segment'] ?? 'New';
                    $color = $rfm_colors[$segment] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    
                    <div class="text-center mb-4">
                        <span class="inline-block px-4 py-2 text-lg font-bold rounded-lg <?= $color ?>">
                            <?= $segment ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3 text-center mb-4">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?= $client['r_score'] ?? 1 ?></div>
                            <div class="text-xs text-gray-500">Recency</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?= $client['f_score'] ?? 1 ?></div>
                            <div class="text-xs text-gray-500">Frequency</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-purple-600"><?= $client['m_score'] ?? 1 ?></div>
                            <div class="text-xs text-gray-500">Monetary</div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Всего визитов:</span>
                            <span class="font-semibold"><?= $client['total_visits'] ?? 0 ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Всего часов:</span>
                            <span class="font-semibold"><?= round($client['total_hours'] ?? 0, 1) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Потрачено всего:</span>
                            <span class="font-semibold text-purple-600"><?= number_format($client['total_spent'] ?? 0, 0, ',', ' ') ?> ₽</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Последний визит:</span>
                            <span class="font-semibold"><?= $client['last_visit_date'] ? date('d.m.Y H:i', strtotime($client['last_visit_date'])) : 'Нет визитов' ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="?id=<?= $client_id ?>&recalc_rfm=1" 
                           onclick="return confirm('Пересчитать RFM-сегментацию?')"
                           class="block text-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition text-sm">
                            <i class="fas fa-sync-alt mr-2"></i>Пересчитать RFM
                        </a>
                    </div>
                </div>
                
                <!-- Статистика -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">📊 Статистика</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">ID клиента:</span>
                            <span class="font-mono font-semibold">#<?= $client['id'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Дата регистрации:</span>
                            <span><?= date('d.m.Y H:i', strtotime($client['registration_date'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Последнее обновление:</span>
                            <span><?= date('d.m.Y H:i', strtotime($client['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- История визитов -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-6 py-4">
                        <h2 class="text-xl font-bold text-gray-800">📜 История визитов</h2>
                        <p class="text-sm text-gray-500 mt-1">Последние 10 посещений</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Начало</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Окончание</th>
                                    <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Длительность</th>
                                    <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Сумма</th>
                                    <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($visits_history)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-gray-500">
                                            <i class="fas fa-clock fa-2x mb-2 block"></i>
                                            У клиента пока нет визитов
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($visits_history as $visit): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-4 text-sm"><?= date('d.m.Y H:i', strtotime($visit['start_time'])) ?></td>
                                            <td class="py-3 px-4 text-sm">
                                                <?= $visit['end_time'] ? date('d.m.Y H:i', strtotime($visit['end_time'])) : '—' ?>
                                            </td>
                                            <td class="py-3 px-4 text-center text-sm">
                                                <?= $visit['duration_hours'] ? round($visit['duration_hours'], 1) . ' ч' : '—' ?>
                                            </td>
                                            <td class="py-3 px-4 text-right text-sm font-semibold text-purple-600">
                                                <?= number_format($visit['total_amount'], 0, ',', ' ') ?> ₽
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-block px-2 py-1 text-xs rounded-full 
                                                    <?= $visit['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                    <?= $visit['status_name'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($visits_history)): ?>
                        <div class="border-t px-6 py-4 text-center">
                            <a href="visits-history.php?client_id=<?= $client_id ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-history mr-1"></i>Показать всю историю визитов →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Автоматическое форматирование телефона
const phoneInput = document.getElementById('phoneInput');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length === 11) {
            let formatted = '+7 (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
            e.target.value = formatted;
        }
    });
}
</script>

<?php include 'footer.php'; ?>