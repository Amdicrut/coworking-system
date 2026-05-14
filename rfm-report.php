<?php
// =====================================================
// rfm-report.php - RFM сегментация клиентов
// Коворкинг-центр: анализ клиентской базы по модели RFM
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();

// Получение параметров фильтрации
$segment_filter = $_GET['segment'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'rfm_score';
$order = $_GET['order'] ?? 'DESC';

// Обработка пересчёта RFM
if (isset($_GET['recalc'])) {
    try {
        // Обновляем статистику клиентов
        $db->exec("
            UPDATE clients c
            SET 
                total_visits = (
                    SELECT COUNT(*) FROM visits WHERE client_id = c.id AND status = 'completed'
                ),
                total_spent = (
                    SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE client_id = c.id AND status = 'completed'
                ),
                total_hours = (
                    SELECT COALESCE(SUM(duration_hours), 0) FROM visits WHERE client_id = c.id AND status = 'completed'
                ),
                last_visit_date = (
                    SELECT MAX(end_time) FROM visits WHERE client_id = c.id AND status = 'completed'
                )
        ");
        
        // Обновляем RFM баллы
        $db->exec("
            UPDATE clients 
            SET 
                r_score = CASE 
                    WHEN DATEDIFF(NOW(), last_visit_date) <= 7 THEN 3
                    WHEN DATEDIFF(NOW(), last_visit_date) <= 30 THEN 2
                    WHEN DATEDIFF(NOW(), last_visit_date) <= 90 THEN 1
                    ELSE 0
                END,
                f_score = CASE 
                    WHEN total_visits >= 10 THEN 3
                    WHEN total_visits >= 5 THEN 2
                    WHEN total_visits >= 1 THEN 1
                    ELSE 0
                END,
                m_score = CASE 
                    WHEN total_spent >= 10000 THEN 3
                    WHEN total_spent >= 5000 THEN 2
                    WHEN total_spent >= 1000 THEN 1
                    ELSE 0
                END,
                rfm_segment = CASE
                    WHEN r_score = 3 AND f_score = 3 AND m_score = 3 THEN 'Champions'
                    WHEN r_score = 3 AND f_score = 3 AND m_score = 2 THEN 'Loyal'
                    WHEN r_score = 2 AND f_score = 3 AND m_score >= 2 THEN 'Potential'
                    WHEN r_score = 3 AND f_score = 2 AND m_score >= 2 THEN 'Promising'
                    WHEN r_score = 3 AND f_score = 1 AND m_score = 1 THEN 'New'
                    WHEN r_score = 1 AND f_score = 1 AND m_score = 1 THEN 'Lost'
                    WHEN r_score = 1 AND f_score >= 2 THEN 'At Risk'
                    WHEN r_score = 2 AND f_score = 2 AND m_score = 2 THEN 'Regular'
                    ELSE 'Other'
                END
        ");
        
        // Сохраняем историю
        $db->exec("
            INSERT INTO rfm_history (client_id, calculation_date, days_since_last_visit, total_visits_90d, total_spent_90d, r_score, f_score, m_score, rfm_segment, rfm_score_total)
            SELECT 
                id, 
                CURDATE(), 
                DATEDIFF(NOW(), last_visit_date),
                total_visits,
                total_spent,
                r_score, 
                f_score, 
                m_score, 
                rfm_segment,
                r_score + f_score + m_score
            FROM clients
        ");
        
        $message = "RFM-сегментация успешно пересчитана";
    } catch (PDOException $e) {
        $error = "Ошибка пересчёта RFM: " . $e->getMessage();
    }
}

// Получение общей статистики по сегментам
$stmt = $db->query("
    SELECT 
        rfm_segment,
        COUNT(*) as count,
        ROUND(AVG(total_spent), 0) as avg_spent,
        ROUND(AVG(total_visits), 1) as avg_visits,
        ROUND(AVG(DATEDIFF(NOW(), last_visit_date)), 0) as avg_days_since_last,
        SUM(total_spent) as total_revenue,
        SUM(total_visits) as total_visits_sum
    FROM clients 
    WHERE rfm_segment IS NOT NULL AND rfm_segment != ''
    GROUP BY rfm_segment
    ORDER BY 
        CASE rfm_segment
            WHEN 'Champions' THEN 1
            WHEN 'Loyal' THEN 2
            WHEN 'Potential' THEN 3
            WHEN 'Promising' THEN 4
            WHEN 'Regular' THEN 5
            WHEN 'New' THEN 6
            WHEN 'At Risk' THEN 7
            WHEN 'Lost' THEN 8
            ELSE 9
        END
");
$segment_stats = $stmt->fetchAll();

// Общая статистика
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_clients,
        SUM(total_spent) as total_revenue,
        AVG(total_spent) as avg_revenue_per_client,
        SUM(total_visits) as total_visits,
        AVG(total_visits) as avg_visits_per_client,
        COUNT(CASE WHEN r_score = 3 THEN 1 END) as high_recency,
        COUNT(CASE WHEN f_score = 3 THEN 1 END) as high_frequency,
        COUNT(CASE WHEN m_score = 3 THEN 1 END) as high_monetary
    FROM clients
");
$overall_stats = $stmt->fetch();

// Получение списка клиентов для таблицы
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_sql = "";
$params = [];

if ($segment_filter != 'all') {
    $where_sql = "WHERE rfm_segment = ?";
    $params[] = $segment_filter;
}

// Сортировка
$sort_field = match($sort_by) {
    'name' => 'full_name',
    'visits' => 'total_visits',
    'spent' => 'total_spent',
    'last_visit' => 'last_visit_date',
    'rfm_score' => '(r_score + f_score + m_score)',
    default => '(r_score + f_score + m_score)'
};

// Получение общего количества
$count_sql = "SELECT COUNT(*) as total FROM clients $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_clients = $stmt->fetch()['total'];
$total_pages = ceil($total_clients / $limit);

// Получение списка клиентов
$sql = "
    SELECT 
        id, full_name, phone, email,
        total_visits, total_spent, total_hours,
        last_visit_date, registration_date,
        r_score, f_score, m_score, rfm_segment,
        (r_score + f_score + m_score) as rfm_total
    FROM clients
    $where_sql
    ORDER BY $sort_field $order
    LIMIT $offset, $limit
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Получение динамики RFM по месяцам
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(calculation_date, '%Y-%m') as month,
        rfm_segment,
        COUNT(*) as count
    FROM rfm_history
    WHERE calculation_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(calculation_date, '%Y-%m'), rfm_segment
    ORDER BY month ASC
");
$rfm_trends = $stmt->fetchAll();

// Рекомендации по сегментам
$recommendations = [
    'Champions' => [
        'color' => 'bg-yellow-100 text-yellow-800',
        'icon' => '🏆',
        'advice' => 'Предложите программу лояльности, эксклюзивные мероприятия, персонализированные скидки'
    ],
    'Loyal' => [
        'color' => 'bg-green-100 text-green-800',
        'icon' => '❤️',
        'advice' => 'Поощряйте рефералов, предлагайте бонусы за постоянство, приглашайте на закрытые мероприятия'
    ],
    'Potential' => [
        'color' => 'bg-blue-100 text-blue-800',
        'icon' => '📈',
        'advice' => 'Увеличьте частоту визитов через специальные предложения и пакетные тарифы'
    ],
    'Promising' => [
        'color' => 'bg-purple-100 text-purple-800',
        'icon' => '✨',
        'advice' => 'Стимулируйте увеличение среднего чека, предлагайте дополнительные услуги'
    ],
    'Regular' => [
        'color' => 'bg-gray-100 text-gray-800',
        'icon' => '📋',
        'advice' => 'Поддерживайте интерес через регулярные акции и персонализированные предложения'
    ],
    'New' => [
        'color' => 'bg-cyan-100 text-cyan-800',
        'icon' => '🆕',
        'advice' => 'Обеспечьте отличный первый опыт, отправьте приветственное предложение'
    ],
    'At Risk' => [
        'color' => 'bg-red-100 text-red-800',
        'icon' => '⚠️',
        'advice' => 'Срочно свяжитесь, предложите специальные условия, выясните причину снижения активности'
    ],
    'Lost' => [
        'color' => 'bg-gray-300 text-gray-700',
        'icon' => '💔',
        'advice' => 'Попробуйте Reactivation-кампанию с большими скидками или специальными условиями'
    ]
];

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">🏆 RFM-сегментация клиентов</h1>
        <p class="text-gray-600 mt-1">Анализ клиентской базы по модели Recency, Frequency, Monetary</p>
    </div>
    
    <!-- Сообщения -->
    <?php if (isset($message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <!-- Общая статистика -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-blue-600"><?= $overall_stats['total_clients'] ?></div>
            <div class="text-xs text-gray-500">Всего клиентов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-xl font-bold text-purple-600"><?= number_format($overall_stats['total_revenue'], 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Общая выручка</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-green-600"><?= number_format($overall_stats['avg_revenue_per_client'], 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Средний чек</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-orange-600"><?= $overall_stats['total_visits'] ?></div>
            <div class="text-xs text-gray-500">Всего визитов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-indigo-600"><?= round($overall_stats['avg_visits_per_client'], 1) ?></div>
            <div class="text-xs text-gray-500">Ср. визитов/клиент</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-yellow-600"><?= $overall_stats['high_recency'] ?>%</div>
            <div class="text-xs text-gray-500">Активных (R=3)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <button onclick="location.href='?recalc=1'" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                <i class="fas fa-sync-alt mr-1"></i>Пересчёт RFM
            </button>
        </div>
    </div>
    
    <!-- Сегменты -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php foreach ($segment_stats as $segment): 
            $rec = $recommendations[$segment['rfm_segment']] ?? $recommendations['Regular'];
        ?>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-<?= str_replace('bg-', '', explode(' ', $rec['color'])[0]) ?>-500">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold <?= $rec['color'] ?>">
                            <?= $rec['icon'] ?> <?= $segment['rfm_segment'] ?>
                        </span>
                        <div class="text-2xl font-bold mt-2"><?= $segment['count'] ?></div>
                        <div class="text-xs text-gray-500">клиентов</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold"><?= number_format($segment['avg_spent'], 0) ?> ₽</div>
                        <div class="text-xs text-gray-500">ср. траты</div>
                        <div class="text-sm font-semibold mt-1"><?= $segment['avg_visits'] ?> виз.</div>
                        <div class="text-xs text-gray-500">ср. визитов</div>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-600">
                    <i class="fas fa-lightbulb mr-1"></i> <?= $rec['advice'] ?>
                </div>
                <div class="mt-2">
                    <button onclick="filterBySegment('<?= $segment['rfm_segment'] ?>')" 
                            class="text-xs text-blue-600 hover:text-blue-800">
                        Показать клиентов →
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- RFM матрица -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📊 RFM матрица распределения</h2>
            <p class="text-sm text-gray-500 mt-1">Распределение клиентов по уровням R, F, M</p>
        </div>
        <div class="p-6">
            <canvas id="rfmMatrixChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Динамика сегментов -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📈 Динамика сегментов за 6 месяцев</h2>
            <p class="text-sm text-gray-500 mt-1">Изменение распределения клиентов по сегментам</p>
        </div>
        <div class="p-6">
            <canvas id="trendChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- Таблица клиентов -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4 flex justify-between items-center flex-wrap gap-3">
            <h2 class="text-xl font-bold text-gray-800">👥 Клиенты по сегментам</h2>
            <div class="flex gap-2">
                <select id="segmentFilter" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                    <option value="all" <?= $segment_filter == 'all' ? 'selected' : '' ?>>Все сегменты</option>
                    <option value="Champions" <?= $segment_filter == 'Champions' ? 'selected' : '' ?>>Champions</option>
                    <option value="Loyal" <?= $segment_filter == 'Loyal' ? 'selected' : '' ?>>Loyal</option>
                    <option value="Potential" <?= $segment_filter == 'Potential' ? 'selected' : '' ?>>Potential</option>
                    <option value="Promising" <?= $segment_filter == 'Promising' ? 'selected' : '' ?>>Promising</option>
                    <option value="Regular" <?= $segment_filter == 'Regular' ? 'selected' : '' ?>>Regular</option>
                    <option value="New" <?= $segment_filter == 'New' ? 'selected' : '' ?>>New</option>
                    <option value="At Risk" <?= $segment_filter == 'At Risk' ? 'selected' : '' ?>>At Risk</option>
                    <option value="Lost" <?= $segment_filter == 'Lost' ? 'selected' : '' ?>>Lost</option>
                </select>
                <select id="sortBy" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                    <option value="rfm_score" <?= $sort_by == 'rfm_score' ? 'selected' : '' ?>>По RFM сумме</option>
                    <option value="spent" <?= $sort_by == 'spent' ? 'selected' : '' ?>>По тратам</option>
                    <option value="visits" <?= $sort_by == 'visits' ? 'selected' : '' ?>>По визитам</option>
                    <option value="last_visit" <?= $sort_by == 'last_visit' ? 'selected' : '' ?>>По давности</option>
                    <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>По имени</option>
                </select>
                <button onclick="applyFilters()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-filter"></i> Применить
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Клиент</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Визитов</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Потрачено</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Последний визит</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">R</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">F</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">M</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">RFM</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Сегмент</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-500">
                                <i class="fas fa-users fa-2x mb-2 block"></i>
                                Нет клиентов в выбранном сегменте
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): 
                            $rec = $recommendations[$client['rfm_segment']] ?? $recommendations['Regular'];
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($client['full_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($client['phone']) ?></div>
                                 </td>
                                <td class="py-3 px-4 text-center"><?= $client['total_visits'] ?></td>
                                <td class="py-3 px-4 text-right font-semibold text-purple-600">
                                    <?= number_format($client['total_spent'], 0, ',', ' ') ?> ₽
                                 </td>
                                <td class="py-3 px-4 text-center text-sm">
                                    <?= $client['last_visit_date'] ? date('d.m.Y', strtotime($client['last_visit_date'])) : '—' ?>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block w-8 h-8 rounded-full bg-<?= $client['r_score'] == 3 ? 'green' : ($client['r_score'] == 2 ? 'yellow' : 'red') ?>-100 text-<?= $client['r_score'] == 3 ? 'green' : ($client['r_score'] == 2 ? 'yellow' : 'red') ?>-800 font-bold leading-8">
                                        <?= $client['r_score'] ?>
                                    </span>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block w-8 h-8 rounded-full bg-<?= $client['f_score'] == 3 ? 'green' : ($client['f_score'] == 2 ? 'yellow' : 'red') ?>-100 text-<?= $client['f_score'] == 3 ? 'green' : ($client['f_score'] == 2 ? 'yellow' : 'red') ?>-800 font-bold leading-8">
                                        <?= $client['f_score'] ?>
                                    </span>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block w-8 h-8 rounded-full bg-<?= $client['m_score'] == 3 ? 'green' : ($client['m_score'] == 2 ? 'yellow' : 'red') ?>-100 text-<?= $client['m_score'] == 3 ? 'green' : ($client['m_score'] == 2 ? 'yellow' : 'red') ?>-800 font-bold leading-8">
                                        <?= $client['m_score'] ?>
                                    </span>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="font-bold"><?= $client['rfm_total'] ?>/9</span>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full <?= $rec['color'] ?>">
                                        <?= $rec['icon'] ?> <?= $client['rfm_segment'] ?>
                                    </span>
                                 </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="client-view.php?id=<?= $client['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i>
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
                    Показано <?= count($clients) ?> из <?= $total_clients ?> клиентов
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&segment=<?= $segment_filter ?>&sort=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="px-3 py-1 border rounded hover:bg-gray-100">← Назад</a>
                    <?php endif; ?>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded"><?= $page ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&segment=<?= $segment_filter ?>&sort=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="px-3 py-1 border rounded hover:bg-gray-100">Вперёд →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Данные для графиков
const segmentCounts = <?= json_encode(array_map(function($s) { return $s['count']; }, $segment_stats)) ?>;
const segmentNames = <?= json_encode(array_map(function($s) { return $s['rfm_segment']; }, $segment_stats)) ?>;

// RFM матрица
const rfmCtx = document.getElementById('rfmMatrixChart').getContext('2d');
new Chart(rfmCtx, {
    type: 'bar',
    data: {
        labels: segmentNames,
        datasets: [
            {
                label: 'Количество клиентов',
                data: segmentCounts,
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
                borderRadius: 5
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
                    text: 'Количество клиентов'
                }
            }
        }
    }
});

// Динамика сегментов
<?php
$trend_months = [];
$trend_data = [];
foreach ($rfm_trends as $trend) {
    if (!in_array($trend['month'], $trend_months)) {
        $trend_months[] = $trend['month'];
    }
    if (!isset($trend_data[$trend['rfm_segment']])) {
        $trend_data[$trend['rfm_segment']] = [];
    }
    $trend_data[$trend['rfm_segment']][$trend['month']] = $trend['count'];
}
?>

const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_months) ?>,
        datasets: [
            <?php foreach ($trend_data as $segment => $data): ?>
            {
                label: '<?= $segment ?>',
                data: <?= json_encode(array_values(array_map(function($m) use ($data) { return $data[$m] ?? 0; }, $trend_months))) ?>,
                borderWidth: 2,
                fill: false,
                tension: 0.3
            },
            <?php endforeach; ?>
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: { size: 10 }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Количество клиентов'
                }
            }
        }
    }
});

// Фильтрация
function filterBySegment(segment) {
    document.getElementById('segmentFilter').value = segment;
    applyFilters();
}

function applyFilters() {
    const segment = document.getElementById('segmentFilter').value;
    const sort = document.getElementById('sortBy').value;
    window.location.href = `rfm-report.php?segment=${segment}&sort=${sort}&order=DESC`;
}
</script>

<?php include 'footer.php'; ?>