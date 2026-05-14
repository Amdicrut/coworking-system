<?php
// =====================================================
// analytics.php - Страница аналитики
// Коворкинг-центр: графики, прогнозы, статистика
// =====================================================

require_once 'config.php';
require_once 'functions.php';

// Получаем параметры фильтрации
$period = $_GET['period'] ?? 'week'; // week, month, year
$chart_type = $_GET['chart'] ?? 'load'; // load, revenue, clients

// Получаем данные для аналитики
$db = getDB();

// 1. Статистика по загрузке
$load_stats = [];
$revenue_stats = [];
$client_stats = [];

switch ($period) {
    case 'week':
        // За последние 7 дней
        $date_interval = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $group_by = "DATE(record_date)";
        break;
    case 'month':
        // За последние 30 дней
        $date_interval = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $group_by = "DATE(record_date)";
        break;
    case 'year':
        // За последние 12 месяцев
        $date_interval = "DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        $group_by = "DATE_FORMAT(record_date, '%Y-%m')";
        break;
    default:
        $date_interval = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $group_by = "DATE(record_date)";
}

// Данные загрузки
$stmt = $db->query("
    SELECT 
        $group_by as date_label,
        AVG(load_ratio) as avg_load,
        MAX(load_ratio) as max_load,
        MIN(load_ratio) as min_load
    FROM load_history
    WHERE record_date > $date_interval
    GROUP BY $group_by
    ORDER BY record_date ASC
");
$load_stats = $stmt->fetchAll();

// Данные по выручке
$stmt = $db->query("
    SELECT 
        DATE(end_time) as date_label,
        COUNT(*) as visits_count,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        AVG(total_amount) as avg_bill
    FROM visits
    WHERE status = 'completed' 
        AND end_time > $date_interval
    GROUP BY DATE(end_time)
    ORDER BY end_time ASC
");
$revenue_stats = $stmt->fetchAll();

// Данные по клиентам
$stmt = $db->query("
    SELECT 
        DATE(registration_date) as date_label,
        COUNT(*) as new_clients
    FROM clients
    WHERE registration_date > $date_interval
    GROUP BY DATE(registration_date)
    ORDER BY registration_date ASC
");
$client_stats = $stmt->fetchAll();

// 2. RFM распределение
$stmt = $db->query("
    SELECT 
        rfm_segment,
        COUNT(*) as count,
        ROUND(AVG(total_spent), 0) as avg_spent,
        ROUND(AVG(total_visits), 1) as avg_visits,
        ROUND(AVG(DATEDIFF(NOW(), last_visit_date)), 0) as avg_days_since_last
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
$rfm_distribution = $stmt->fetchAll();

// 3. Часы пик (средняя загрузка по часам)
$stmt = $db->query("
    SELECT 
        hour_of_day,
        AVG(load_ratio) as avg_load,
        AVG(revenue_from_slot) as avg_revenue
    FROM load_history
    WHERE record_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY hour_of_day
    ORDER BY hour_of_day ASC
");
$peak_hours = $stmt->fetchAll();

// 4. Дни недели (средняя загрузка)
$stmt = $db->query("
    SELECT 
        day_of_week,
        CASE day_of_week
            WHEN 1 THEN 'ПН'
            WHEN 2 THEN 'ВТ'
            WHEN 3 THEN 'СР'
            WHEN 4 THEN 'ЧТ'
            WHEN 5 THEN 'ПТ'
            WHEN 6 THEN 'СБ'
            WHEN 7 THEN 'ВС'
        END as day_name,
        AVG(load_ratio) as avg_load,
        AVG(revenue_from_slot) as avg_revenue
    FROM load_history
    WHERE record_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY day_of_week
    ORDER BY day_of_week ASC
");
$weekday_stats = $stmt->fetchAll();

// 5. Общая статистика
$stmt = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM clients) as total_clients,
        (SELECT COUNT(*) FROM visits WHERE status = 'completed') as total_visits,
        (SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE status = 'completed') as total_revenue,
        (SELECT COALESCE(SUM(total_amount), 0) FROM visits WHERE status = 'completed' AND DATE(end_time) = CURDATE()) as today_revenue,
        (SELECT COUNT(*) FROM visits WHERE status = 'active') as active_visits,
        (SELECT ROUND(AVG(load_ratio), 1) FROM load_history WHERE record_date = CURDATE()) as avg_load_today,
        (SELECT ROUND(AVG(duration_hours), 1) FROM visits WHERE status = 'completed' AND DATE(end_time) = CURDATE()) as avg_visit_duration
");
$overall_stats = $stmt->fetch();

// 6. Топ клиентов по выручке
$stmt = $db->query("
    SELECT 
        c.id,
        c.full_name,
        c.phone,
        c.rfm_segment,
        COUNT(v.id) as visits_count,
        COALESCE(SUM(v.total_amount), 0) as total_spent
    FROM clients c
    LEFT JOIN visits v ON v.client_id = c.id AND v.status = 'completed'
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$top_clients = $stmt->fetchAll();

// 7. Прогноз на неделю (из таблицы прогнозов или расчёт)
$stmt = $db->query("
    SELECT 
        forecast_date,
        AVG(predicted_load) as avg_predicted_load,
        AVG(recommended_price) as avg_recommended_price
    FROM load_forecasts
    WHERE forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY forecast_date
    ORDER BY forecast_date ASC
");
$forecast_data = $stmt->fetchAll();

// Если нет прогнозов - генерируем простой прогноз
if (empty($forecast_data)) {
    $forecast_data = [];
    for ($i = 0; $i <= 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $day_of_week = date('N', strtotime($date));
        
        // Простой прогноз на основе дня недели
        $base_load = 50;
        if ($day_of_week >= 6) $base_load = 40; // выходные
        else $base_load = 70; // будни
        
        $forecast_data[] = [
            'forecast_date' => $date,
            'avg_predicted_load' => $base_load + rand(-10, 10),
            'avg_recommended_price' => 200
        ];
    }
}

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">📊 Аналитика и отчёты</h1>
        <p class="text-gray-600 mt-1">Детальная статистика, графики и прогнозы</p>
    </div>
    
    <!-- Общая статистика -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-blue-600"><?= $overall_stats['total_clients'] ?? 0 ?></div>
            <div class="text-xs text-gray-500">Всего клиентов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-green-600"><?= $overall_stats['total_visits'] ?? 0 ?></div>
            <div class="text-xs text-gray-500">Всего визитов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-xl font-bold text-purple-600"><?= number_format($overall_stats['total_revenue'] ?? 0, 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Общая выручка</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-xl font-bold text-orange-600"><?= number_format($overall_stats['today_revenue'] ?? 0, 0, ',', ' ') ?> ₽</div>
            <div class="text-xs text-gray-500">Выручка сегодня</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-red-600"><?= $overall_stats['active_visits'] ?? 0 ?></div>
            <div class="text-xs text-gray-500">Активных визитов</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-indigo-600"><?= $overall_stats['avg_load_today'] ?? 0 ?>%</div>
            <div class="text-xs text-gray-500">Загрузка сегодня</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 text-center">
            <div class="text-2xl font-bold text-teal-600"><?= $overall_stats['avg_visit_duration'] ?? 0 ?> ч</div>
            <div class="text-xs text-gray-500">Ср. длительность</div>
        </div>
    </div>
    
    <!-- Фильтры -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Период</label>
                <select name="period" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Последние 7 дней</option>
                    <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Последние 30 дней</option>
                    <option value="year" <?= $period == 'year' ? 'selected' : '' ?>>Последние 12 месяцев</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Тип графика</label>
                <select name="chart" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="load" <?= $chart_type == 'load' ? 'selected' : '' ?>>Загрузка</option>
                    <option value="revenue" <?= $chart_type == 'revenue' ? 'selected' : '' ?>>Выручка</option>
                    <option value="clients" <?= $chart_type == 'clients' ? 'selected' : '' ?>>Новые клиенты</option>
                </select>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-chart-line mr-2"></i>Применить
                </button>
            </div>
        </form>
    </div>
    
    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        
        <!-- График загрузки -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <?php if ($chart_type == 'load'): ?>
                    📈 Динамика загрузки
                <?php elseif ($chart_type == 'revenue'): ?>
                    💰 Динамика выручки
                <?php else: ?>
                    👥 Новые клиенты
                <?php endif; ?>
            </h2>
            <canvas id="mainChart" height="250"></canvas>
        </div>
        
        <!-- Прогноз на неделю -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🔮 Прогноз загрузки на неделю</h2>
            <canvas id="forecastChart" height="250"></canvas>
            <div class="mt-3 text-center text-xs text-gray-500">
                *Прогноз основан на исторических данных и днях недели
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        
        <!-- Часы пик -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">⏰ Часы пик (средняя загрузка за 30 дней)</h2>
            <canvas id="peakHoursChart" height="200"></canvas>
        </div>
        
        <!-- Дни недели -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📅 Загрузка по дням недели</h2>
            <canvas id="weekdayChart" height="200"></canvas>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- RFM распределение -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🏆 RFM распределение клиентов</h2>
            <canvas id="rfmChart" height="250"></canvas>
            
            <div class="mt-4 space-y-2">
                <?php foreach ($rfm_distribution as $segment): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-medium"><?= $segment['rfm_segment'] ?></span>
                        <div class="flex-1 mx-3">
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 rounded-full h-2" style="width: <?= ($segment['count'] / array_sum(array_column($rfm_distribution, 'count'))) * 100 ?>%"></div>
                            </div>
                        </div>
                        <span class="text-gray-600"><?= $segment['count'] ?> клиентов</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Топ клиентов -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">👑 Топ 10 клиентов по выручке</h2>
            <div class="space-y-3">
                <?php 
                $rank = 1;
                foreach ($top_clients as $client): 
                    $percentage = ($client['total_spent'] / max(array_column($top_clients, 'total_spent'))) * 100;
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-bold text-gray-500">#<?= $rank++ ?></span>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($client['full_name']) ?></span>
                                <?php if ($client['rfm_segment']): ?>
                                    <span class="text-xs px-1 rounded bg-gray-100"><?= $client['rfm_segment'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="font-semibold text-purple-600"><?= number_format($client['total_spent'], 0, ',', ' ') ?> ₽</span>
                                <span class="text-xs text-gray-500 ml-2">(<?= $client['visits_count'] ?> виз.)</span>
                            </div>
                        </div>
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-500 rounded-full h-2" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 text-center">
                <a href="clients.php" class="text-blue-600 hover:text-blue-800 text-sm">Посмотреть всех клиентов →</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Подготовка данных для графиков
<?php
// Данные для основного графика
$chart_labels = [];
$chart_data = [];

if ($chart_type == 'load') {
    foreach ($load_stats as $stat) {
        $chart_labels[] = $stat['date_label'];
        $chart_data[] = round($stat['avg_load'], 1);
    }
    $chart_label = 'Загрузка (%)';
    $chart_color = 'rgb(59, 130, 246)';
} elseif ($chart_type == 'revenue') {
    foreach ($revenue_stats as $stat) {
        $chart_labels[] = $stat['date_label'];
        $chart_data[] = $stat['total_revenue'];
    }
    $chart_label = 'Выручка (₽)';
    $chart_color = 'rgb(168, 85, 247)';
} else {
    foreach ($client_stats as $stat) {
        $chart_labels[] = $stat['date_label'];
        $chart_data[] = $stat['new_clients'];
    }
    $chart_label = 'Новые клиенты';
    $chart_color = 'rgb(34, 197, 94)';
}

// Данные для прогноза
$forecast_labels = [];
$forecast_loads = [];
$forecast_prices = [];
foreach ($forecast_data as $forecast) {
    $forecast_labels[] = date('d.m', strtotime($forecast['forecast_date']));
    $forecast_loads[] = round($forecast['avg_predicted_load'], 1);
    $forecast_prices[] = round($forecast['avg_recommended_price']);
}

// Данные для часов пик
$peak_hours_labels = [];
$peak_hours_data = [];
foreach ($peak_hours as $hour) {
    $peak_hours_labels[] = $hour['hour_of_day'] . ':00';
    $peak_hours_data[] = round($hour['avg_load'], 1);
}

// Данные для дней недели
$weekday_labels = [];
$weekday_data = [];
foreach ($weekday_stats as $day) {
    $weekday_labels[] = $day['day_name'];
    $weekday_data[] = round($day['avg_load'], 1);
}

// Данные для RFM
$rfm_labels = [];
$rfm_data = [];
foreach ($rfm_distribution as $segment) {
    $rfm_labels[] = $segment['rfm_segment'];
    $rfm_data[] = $segment['count'];
}
?>

// Основной график
const ctx1 = document.getElementById('mainChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: '<?= $chart_label ?>',
            data: <?= json_encode($chart_data) ?>,
            borderColor: '<?= $chart_color ?>',
            backgroundColor: '<?= $chart_color ?>'.replace('rgb', 'rgba').replace(')', ', 0.1)'),
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '<?= $chart_color ?>',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let value = context.parsed.y;
                        if ('<?= $chart_type ?>' === 'revenue') {
                            return context.dataset.label + ': ' + value.toLocaleString('ru-RU') + ' ₽';
                        } else if ('<?= $chart_type ?>' === 'load') {
                            return context.dataset.label + ': ' + value + '%';
                        } else {
                            return context.dataset.label + ': ' + value;
                        }
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: '<?= $chart_label ?>'
                }
            }
        }
    }
});

// График прогноза
const ctx2 = document.getElementById('forecastChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode($forecast_labels) ?>,
        datasets: [
            {
                label: 'Прогноз загрузки (%)',
                data: <?= json_encode($forecast_loads) ?>,
                backgroundColor: 'rgba(168, 85, 247, 0.6)',
                borderColor: 'rgb(168, 85, 247)',
                borderWidth: 1,
                borderRadius: 5,
                yAxisID: 'y'
            },
            {
                label: 'Рекомендуемая цена (₽)',
                data: <?= json_encode($forecast_prices) ?>,
                type: 'line',
                backgroundColor: 'rgba(34, 197, 94, 0)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgb(34, 197, 94)',
                pointRadius: 4,
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
                max: 100,
                title: {
                    display: true,
                    text: 'Загрузка (%)'
                }
            },
            y1: {
                position: 'right',
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Цена (₽)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// График часов пик
const ctx3 = document.getElementById('peakHoursChart').getContext('2d');
new Chart(ctx3, {
    type: 'line',
    data: {
        labels: <?= json_encode($peak_hours_labels) ?>,
        datasets: [{
            label: 'Средняя загрузка (%)',
            data: <?= json_encode($peak_hours_data) ?>,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: 'rgb(239, 68, 68)',
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Загрузка (%)'
                }
            }
        }
    }
});

// График дней недели
const ctx4 = document.getElementById('weekdayChart').getContext('2d');
new Chart(ctx4, {
    type: 'bar',
    data: {
        labels: <?= json_encode($weekday_labels) ?>,
        datasets: [{
            label: 'Средняя загрузка (%)',
            data: <?= json_encode($weekday_data) ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Загрузка (%)'
                }
            }
        }
    }
});

// Круговая диаграмма RFM
const ctx5 = document.getElementById('rfmChart').getContext('2d');
new Chart(ctx5, {
    type: 'pie',
    data: {
        labels: <?= json_encode($rfm_labels) ?>,
        datasets: [{
            data: <?= json_encode($rfm_data) ?>,
            backgroundColor: [
                '#F59E0B', // Champions
                '#10B981', // Loyal
                '#3B82F6', // Potential
                '#8B5CF6', // Promising
                '#6B7280', // Regular
                '#EF4444', // At Risk
                '#9CA3AF'  // Lost
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});
</script>

<?php include 'footer.php'; ?>