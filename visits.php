<?php
// =====================================================
// visits.php - Активные визиты и таймеры
// Коворкинг-центр: просмотр активных визитов, управление таймерами
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Получение параметров
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Получение списка активных визитов
$sql = "
    SELECT 
        v.*,
        c.id as client_id,
        c.full_name as client_name,
        c.phone as client_phone,
        c.rfm_segment,
        t.name as tariff_name,
        TIMESTAMPDIFF(MINUTE, v.start_time, NOW()) as minutes_elapsed,
        TIMESTAMPDIFF(HOUR, v.start_time, NOW()) as hours_elapsed
    FROM visits v
    JOIN clients c ON c.id = v.client_id
    LEFT JOIN tariffs t ON t.id = v.tariff_id
    WHERE v.status = 'active'
    ORDER BY v.start_time ASC
    LIMIT $offset, $limit
";
$active_visits = $db->query($sql)->fetchAll();

// Получение общего количества активных визитов
$total_active = $db->query("SELECT COUNT(*) FROM visits WHERE status = 'active'")->fetchColumn();
$total_pages = ceil($total_active / $limit);

// Получение общей статистики по активным визитам
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_active,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, NOW())), 0) as total_minutes,
        COALESCE(AVG(TIMESTAMPDIFF(MINUTE, start_time, NOW())), 0) as avg_minutes,
        COUNT(DISTINCT client_id) as unique_clients
    FROM visits 
    WHERE status = 'active'
");
$stats = $stmt->fetch();

// Получение загрузки по часам (для отображения текущей загрузки)
$current_hour = date('H');
$current_day = date('N');
$stmt = $db->prepare("
    SELECT predicted_load, recommended_price 
    FROM load_forecasts 
    WHERE forecast_date = CURDATE() AND hour_of_day = ?
");
$stmt->execute([$current_hour]);
$forecast = $stmt->fetch();

$current_load = getCurrentLoad();
$total_seats = TOTAL_SEATS;

// Получение рекомендаций по ценам на текущий час
$stmt = $db->prepare("
    SELECT dynamic_price, reason 
    FROM dynamic_prices 
    WHERE day_of_week = ? AND hour_of_day = ? AND is_active = 1
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$current_day, $current_hour]);
$dynamic_price = $stmt->fetch();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">⏱️ Активные визиты</h1>
            <p class="text-gray-600 mt-1">Управление текущими таймерами и посещениями</p>
        </div>
        <a href="visit-start.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
            <i class="fas fa-play"></i>
            <span>Новый визит</span>
        </a>
    </div>
    
    <!-- Сообщения -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
            <span>
                <?php if ($_GET['success'] == 'started'): ?>
                    ✅ Визит успешно начат!
                <?php elseif ($_GET['success'] == 'stopped'): ?>
                    ✅ Визит завершён!
                <?php endif; ?>
            </span>
            <button onclick="this.parentElement.remove()" class="text-green-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Активных визитов</p>
                    <p class="text-2xl font-bold text-green-600"><?= $stats['total_active'] ?></p>
                </div>
                <i class="fas fa-hourglass-half text-3xl text-green-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Уникальных клиентов</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $stats['unique_clients'] ?></p>
                </div>
                <i class="fas fa-users text-3xl text-blue-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Общее время</p>
                    <p class="text-2xl font-bold text-orange-600"><?= round($stats['total_minutes'] / 60, 1) ?> ч</p>
                </div>
                <i class="fas fa-clock text-3xl text-orange-400"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Текущая загрузка</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $current_load ?>%</p>
                </div>
                <i class="fas fa-chart-line text-3xl text-purple-400"></i>
            </div>
            <div class="mt-2 bg-gray-200 rounded-full h-1">
                <div class="bg-purple-500 rounded-full h-1" style="width: <?= $current_load ?>%"></div>
            </div>
        </div>
    </div>
    
    <!-- Информация о динамическом ценообразовании -->
    <?php if ($dynamic_price): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-6 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fas fa-chart-line text-yellow-600"></i>
                <span class="text-sm text-yellow-800">
                    ⚡ Динамическое ценообразование: в текущий час 
                    <strong><?= number_format($dynamic_price['dynamic_price'], 0) ?> ₽/час</strong>
                    <?php if ($dynamic_price['reason']): ?> (<?= $dynamic_price['reason'] ?>)<?php endif; ?>
                </span>
            </div>
            <a href="dynamic-prices.php" class="text-xs text-yellow-800 underline">Подробнее</a>
        </div>
    <?php endif; ?>
    
    <!-- Таблица активных визитов -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📋 Текущие таймеры</h2>
            <p class="text-sm text-gray-500 mt-1">Активные визиты и управление таймерами</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">ID</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Клиент</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Тариф</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Начало</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Длительность</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Текущая сумма</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Статус</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($active_visits)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <i class="fas fa-hourglass fa-2x mb-2 block"></i>
                                Нет активных визитов
                                <div class="mt-2">
                                    <a href="visit-start.php" class="text-blue-600 hover:text-blue-800">Начать новый визит →</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($active_visits as $visit): 
                            $current_amount = $visit['hourly_rate_applied'] * ($visit['minutes_elapsed'] / 60);
                            $rfm_color = '';
                            if ($visit['rfm_segment'] == 'Champions') $rfm_color = 'bg-yellow-100 text-yellow-800';
                            elseif ($visit['rfm_segment'] == 'Loyal') $rfm_color = 'bg-green-100 text-green-800';
                            elseif ($visit['rfm_segment'] == 'Potential') $rfm_color = 'bg-blue-100 text-blue-800';
                            else $rfm_color = 'bg-gray-100 text-gray-800';
                        ?>
                            <tr class="border-b hover:bg-gray-50" data-visit-id="<?= $visit['id'] ?>">
                                <td class="py-3 px-4 text-gray-500">#<?= $visit['id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($visit['client_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($visit['client_phone']) ?></div>
                                    <span class="inline-block px-1 text-xs rounded <?= $rfm_color ?> mt-1">
                                        <?= $visit['rfm_segment'] ?? 'New' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?= htmlspecialchars($visit['tariff_name'] ?? 'Стандартный') ?>
                                    <div class="text-xs text-gray-500"><?= number_format($visit['hourly_rate_applied'], 0) ?> ₽/час</div>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?= date('d.m.Y H:i', strtotime($visit['start_time'])) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="timer-display font-mono text-lg font-bold text-blue-600" 
                                         data-start="<?= strtotime($visit['start_time']) ?>">
                                        --:--:--
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="current-amount font-semibold text-purple-600" 
                                         data-rate="<?= $visit['hourly_rate_applied'] ?>"
                                         data-start="<?= strtotime($visit['start_time']) ?>">
                                        <?= number_format($current_amount, 0) ?> ₽
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-circle text-green-500 mr-1" style="font-size: 8px;"></i>
                                        Активен
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <button onclick="stopVisit(<?= $visit['id'] ?>)" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition text-sm flex items-center space-x-1 mx-auto">
                                        <i class="fas fa-stop"></i>
                                        <span>Остановить</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <div class="border-t px-6 py-4 flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Показано <?= count($active_visits) ?> из <?= $total_active ?> активных
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>" class="px-3 py-1 border rounded hover:bg-gray-100">← Назад</a>
                    <?php endif; ?>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded"><?= $page ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>" class="px-3 py-1 border rounded hover:bg-gray-100">Вперёд →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Рекомендации -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Информация</p>
                    <p>Все активные таймеры автоматически обновляются каждую минуту. При остановке таймера будет рассчитана итоговая стоимость с учётом выбранного тарифа и дополнительных услуг.</p>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-chart-line text-purple-500 mt-0.5"></i>
                <div class="text-sm text-purple-800">
                    <p class="font-semibold mb-1">Текущая загрузка: <?= $current_load ?>%</p>
                    <p>Свободных мест: <?= $total_seats - $stats['total_active'] ?> из <?= $total_seats ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Функция обновления всех таймеров
function updateAllTimers() {
    const now = Math.floor(Date.now() / 1000);
    
    document.querySelectorAll('.timer-display').forEach(el => {
        const startTime = parseInt(el.dataset.start);
        const elapsed = Math.max(0, now - startTime);
        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;
        
        el.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });
    
    document.querySelectorAll('.current-amount').forEach(el => {
        const startTime = parseInt(el.dataset.start);
        const rate = parseFloat(el.dataset.rate);
        const elapsed = Math.max(0, now - startTime);
        const hours = elapsed / 3600;
        const amount = hours * rate;
        
        el.textContent = Math.round(amount).toLocaleString() + ' ₽';
    });
}

// Остановка визита
function stopVisit(visitId) {
    if (confirm('Завершить визит и рассчитать стоимость?')) {
        window.location.href = `visit-stop.php?id=${visitId}`;
    }
}

// Обновление таймеров каждую секунду
setInterval(updateAllTimers, 1000);
updateAllTimers();

// Автообновление страницы каждые 5 минут (обновление данных из БД)
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php include 'footer.php'; ?>