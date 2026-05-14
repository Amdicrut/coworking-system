<?php
// =====================================================
// visits-history.php - История визитов
// Коворкинг-центр: просмотр завершённых визитов с фильтрацией
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Получение параметров фильтрации
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$client_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$status_filter = $_GET['status'] ?? 'completed';

// Построение запроса
$where_conditions = ["v.status = ?"];
$params = [$status_filter];

if ($date_from && $date_to) {
    $where_conditions[] = "DATE(v.end_time) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

if ($client_filter > 0) {
    $where_conditions[] = "v.client_id = ?";
    $params[] = $client_filter;
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Получение общего количества
$count_sql = "
    SELECT COUNT(*) as total 
    FROM visits v
    $where_sql
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_visits = $stmt->fetch()['total'];
$total_pages = ceil($total_visits / $limit);

// Получение списка визитов
$sql = "
    SELECT 
        v.*,
        c.id as client_id,
        c.full_name as client_name,
        c.phone as client_phone,
        t.name as tariff_name,
        CASE 
            WHEN v.status = 'active' THEN 'Активен'
            WHEN v.status = 'completed' THEN 'Завершён'
            WHEN v.status = 'cancelled' THEN 'Отменён'
        END as status_name,
        CASE 
            WHEN v.status = 'active' THEN 'bg-green-100 text-green-800'
            WHEN v.status = 'completed' THEN 'bg-blue-100 text-blue-800'
            WHEN v.status = 'cancelled' THEN 'bg-red-100 text-red-800'
        END as status_class
    FROM visits v
    JOIN clients c ON c.id = v.client_id
    LEFT JOIN tariffs t ON t.id = v.tariff_id
    $where_sql
    ORDER BY v.end_time DESC, v.start_time DESC
    LIMIT $offset, $limit
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// Получение списка клиентов для фильтра
$stmt = $db->query("
    SELECT id, full_name, phone 
    FROM clients 
    WHERE is_active = 1 
    ORDER BY full_name ASC
");
$clients = $stmt->fetchAll();

// Получение статистики по визитам
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_visits,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_bill,
        COALESCE(SUM(duration_hours), 0) as total_hours,
        COALESCE(AVG(duration_hours), 0) as avg_duration,
        COUNT(CASE WHEN DATE(end_time) = CURDATE() THEN 1 END) as today_visits,
        COALESCE(SUM(CASE WHEN DATE(end_time) = CURDATE() THEN total_amount ELSE 0 END), 0) as today_revenue
    FROM visits v
    WHERE v.status = 'completed'
");
$stmt->execute();
$stats = $stmt->fetch();

// Получение популярных тарифов
$stmt = $db->prepare("
    SELECT 
        t.name,
        COUNT(v.id) as usage_count,
        COALESCE(SUM(v.total_amount), 0) as total_revenue
    FROM visits v
    LEFT JOIN tariffs t ON t.id = v.tariff_id
    WHERE v.status = 'completed'
    GROUP BY t.id
    ORDER BY usage_count DESC
    LIMIT 5
");
$stmt->execute();
$popular_tariffs = $stmt->fetchAll();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">📜 История визитов</h1>
        <p class="text-gray-600 mt-1">Просмотр завершённых и активных визитов</p>
    </div>
    
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-blue-600"><?= $stats['total_visits'] ?></div>
            <div class="text-xs text-gray-500">Всего визитов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-xl font-bold text-purple-600"><?= number_format($stats['total_revenue'], 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Общая выручка</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-green-600"><?= number_format($stats['avg_bill'], 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Средний чек</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-orange-600"><?= round($stats['avg_duration'], 1) ?> ч</div>
            <div class="text-xs text-gray-500">Ср. длительность</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-indigo-600"><?= $stats['today_visits'] ?></div>
            <div class="text-xs text-gray-500">Визитов сегодня</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-xl font-bold text-pink-600"><?= number_format($stats['today_revenue'], 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Выручка сегодня</div>
        </div>
    </div>
    
    <!-- Фильтры -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата с</label>
                <input type="date" name="date_from" value="<?= $date_from ?>"
                       class="border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата по</label>
                <input type="date" name="date_to" value="<?= $date_to ?>"
                       class="border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Клиент</label>
                <select name="client_id" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="0">Все клиенты</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['full_name']) ?> (<?= htmlspecialchars($client['phone']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Завершённые</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Активные</option>
                    <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Отменённые</option>
                </select>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-2"></i>Фильтровать
                </button>
                <a href="visits-history.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition ml-2 inline-block">
                    <i class="fas fa-undo mr-2"></i>Сбросить
                </a>
            </div>
        </form>
    </div>
    
    <!-- Таблица визитов -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📋 Список визитов</h2>
            <p class="text-sm text-gray-500 mt-1">Всего записей: <?= $total_visits ?></p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <table>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">ID</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Клиент</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Тариф</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Начало</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Окончание</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Длительность</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Ставка</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Сумма</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Статус</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visits)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-500">
                                <i class="fas fa-calendar-alt fa-2x mb-2 block"></i>
                                Нет визитов за выбранный период
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visits as $visit): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 text-gray-500">#<?= $visit['id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($visit['client_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($visit['client_phone']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?= htmlspecialchars($visit['tariff_name'] ?? 'Стандартный') ?>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?= date('d.m.Y H:i', strtotime($visit['start_time'])) ?>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?= $visit['end_time'] ? date('d.m.Y H:i', strtotime($visit['end_time'])) : '—' ?>
                                </td>
                                <td class="py-3 px-4 text-center text-sm">
                                    <?= $visit['duration_hours'] ? round($visit['duration_hours'], 1) . ' ч' : '—' ?>
                                </td>
                                <td class="py-3 px-4 text-center text-sm">
                                    <?= number_format($visit['hourly_rate_applied'], 0) ?> ₽
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-semibold text-purple-600"><?= number_format($visit['total_amount'], 0, ',', ' ') ?> ₽</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full <?= $visit['status_class'] ?>">
                                        <?= $visit['status_name'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="visit-receipt.php?id=<?= $visit['id'] ?>" class="text-blue-600 hover:text-blue-800 transition" title="Просмотр чека">
                                        <i class="fas fa-receipt"></i>
                                    </a>
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
                    Показано <?= count($visits) ?> из <?= $total_visits ?> записей
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&client_id=<?= $client_filter ?>&status=<?= $status_filter ?>" 
                           class="px-3 py-1 border rounded hover:bg-gray-100">← Назад</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-blue-600 text-white rounded"><?= $page ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&client_id=<?= $client_filter ?>&status=<?= $status_filter ?>" 
                           class="px-3 py-1 border rounded hover:bg-gray-100">Вперёд →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Популярные тарифы и график -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Популярные тарифы -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">🔥 Популярные тарифы</h3>
            <div class="space-y-4">
                <?php if (empty($popular_tariffs)): ?>
                    <p class="text-gray-500 text-center py-4">Нет данных о тарифах</p>
                <?php else: ?>
                    <?php 
                    $max_usage = max(array_column($popular_tariffs, 'usage_count')) ?: 1;
                    foreach ($popular_tariffs as $tariff): 
                        $percentage = ($tariff['usage_count'] / $max_usage) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium"><?= htmlspecialchars($tariff['name'] ?? 'Стандартный') ?></span>
                                <span class="text-gray-600"><?= $tariff['usage_count'] ?> использований</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-orange-500 rounded-full h-2" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Выручка: <?= number_format($tariff['total_revenue'], 0, ',', ' ') ?> ₽
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- График визитов по дням -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">📊 Динамика визитов</h3>
            <canvas id="visitsChart" height="200"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Получение данных для графика через AJAX
fetch('api/get-visits-stats.php?date_from=<?= $date_from ?>&date_to=<?= $date_to ?>')
    .then(response => response.json())
    .then(data => {
        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [
                    {
                        label: 'Количество визитов',
                        data: data.counts,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Выручка (₽)',
                        data: data.revenues,
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Количество визитов'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Выручка (₽)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    })
    .catch(error => console.error('Error:', error));
</script>

<?php include 'footer.php'; ?>