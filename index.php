<?php
// =====================================================
// index.php - Главная страница (дашборд)
// Коворкинг-центр: аналитика, прогнозы, RFM
// =====================================================

require_once 'config.php';
require_once 'functions.php';

// Получаем данные для дашборда
$total_clients = getTotalClients();
$active_visits = getActiveVisitsCount();
$today_revenue = getTodayRevenue();
$current_load = getCurrentLoad();
$month_revenue = getMonthRevenue();
$avg_daily_load = getAverageDailyLoad();

// Получаем топ-5 клиентов по RFM
$top_clients = getTopRFMClients(5);

// Получаем данные для графиков
$chart_data = getDashboardChartData();
$forecast_data = getWeekForecast();

// Получаем рекомендации по ценам
$price_recommendations = getPriceRecommendations();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Дашборд коворкинг-центра</h1>
        <p class="text-gray-600 mt-1">Аналитика, прогнозы и управление</p>
    </div>
    
    <!-- Карточки статистики -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Всего клиентов</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_clients ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Активных визитов</p>
                    <p class="text-2xl font-bold text-green-600"><?= $active_visits ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Выручка сегодня</p>
                    <p class="text-2xl font-bold text-purple-600"><?= number_format($today_revenue, 0, ',', ' ') ?> ₽</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Загрузка сейчас</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $current_load ?>%</p>
                </div>
                <div class="bg-orange-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Выручка за месяц</p>
                    <p class="text-2xl font-bold text-indigo-600"><?= number_format($month_revenue, 0, ',', ' ') ?> ₽</p>
                </div>
                <div class="bg-indigo-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Графики: Загрузка сегодня и прогноз -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- График загрузки сегодня -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Загрузка сегодня (по часам)</h2>
                <span class="text-sm text-gray-500">Актуально на <?= date('d.m.Y H:i') ?></span>
            </div>
            <canvas id="todayLoadChart" height="250"></canvas>
            <div class="mt-3 text-center text-sm text-gray-600">
                Средняя загрузка сегодня: <strong><?= $avg_daily_load ?>%</strong>
            </div>
        </div>
        
        <!-- График прогноза на неделю -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Прогноз загрузки на неделю</h2>
                <button onclick="refreshForecast()" class="text-blue-600 hover:text-blue-800 text-sm">
                    🔄 Обновить
                </button>
            </div>
            <canvas id="forecastChart" height="250"></canvas>
            <div class="mt-3 text-center text-sm text-gray-600">
                *Прогноз основан на исторических данных за 4 недели
            </div>
        </div>
    </div>
    
    <!-- Динамическое ценообразование и RFM -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Рекомендации по ценам -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">💰 Динамическое ценообразование</h2>
            <div class="space-y-3">
                <?php foreach ($price_recommendations as $rec): ?>
                <div class="flex items-center justify-between p-3 border rounded-lg 
                    <?= $rec['action'] == 'up' ? 'border-red-200 bg-red-50' : ($rec['action'] == 'down' ? 'border-green-200 bg-green-50' : 'border-gray-200') ?>">
                    <div>
                        <p class="font-semibold text-gray-800"><?= $rec['time_slot'] ?></p>
                        <p class="text-sm text-gray-600">Прогноз: <?= $rec['forecast'] ?>% загрузки</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold 
                            <?= $rec['action'] == 'up' ? 'text-red-600' : ($rec['action'] == 'down' ? 'text-green-600' : 'text-gray-600') ?>">
                            <?= $rec['action'] == 'up' ? '▲ +' . $rec['change'] : ($rec['action'] == 'down' ? '▼ ' . $rec['change'] : '● 0') ?>
                        </p>
                        <p class="text-sm text-gray-500"><?= number_format($rec['old_price'], 0) ?> → <?= number_format($rec['new_price'], 0) ?> ₽/ч</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 text-center">
                <a href="dynamic-prices.php" class="text-blue-600 hover:text-blue-800 text-sm">📊 Подробнее о ценах →</a>
            </div>
        </div>
        
        <!-- RFM: Топ клиентов -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🏆 Топ клиенты (RFM сегментация)</h2>
            <div class="space-y-3">
                <?php if (empty($top_clients)): ?>
                <p class="text-gray-500 text-center py-4">Нет данных о клиентах</p>
                <?php else: ?>
                    <?php foreach ($top_clients as $client): ?>
                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center 
                                <?= $client['rfm_segment'] == 'Champions' ? 'bg-yellow-100 text-yellow-600' : 
                                   ($client['rfm_segment'] == 'Loyal' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600') ?>">
                                <?= mb_substr($client['full_name'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($client['full_name']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= $client['total_visits'] ?> визитов | <?= number_format($client['total_spent'], 0) ?> ₽
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-2 py-1 text-xs rounded-full 
                                <?= $client['rfm_segment'] == 'Champions' ? 'bg-yellow-200 text-yellow-800' : 
                                   ($client['rfm_segment'] == 'Loyal' ? 'bg-green-200 text-green-800' : 'bg-blue-200 text-blue-800') ?>">
                                <?= $client['rfm_segment'] ?>
                            </span>
                            <p class="text-xs text-gray-400 mt-1">
                                RFM: <?= $client['r_score'] ?>/<?= $client['f_score'] ?>/<?= $client['m_score'] ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="mt-3 text-center">
                <a href="rfm-report.php" class="text-blue-600 hover:text-blue-800 text-sm">📈 Полный RFM отчёт →</a>
            </div>
        </div>
    </div>
    
    <!-- Дополнительная аналитика -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Последние визиты -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">🕐 Последние визиты</h2>
            <div class="space-y-2">
                <?php
                $recent_visits = getRecentVisits(5);
                foreach ($recent_visits as $visit):
                ?>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-700"><?= htmlspecialchars($visit['full_name']) ?></span>
                    <span class="text-gray-500"><?= date('H:i', strtotime($visit['start_time'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Распределение RFM сегментов -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">📊 RFM распределение</h2>
            <canvas id="rfmPieChart" height="180"></canvas>
        </div>
        
        <!-- Часы пик -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">⏰ Часы пик (статистика)</h2>
            <div class="space-y-2">
                <?php
                $peak_hours = getPeakHours();
                foreach ($peak_hours as $hour):
                ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700"><?= $hour['hour'] ?>:00</span>
                    <div class="flex-1 mx-3">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-red-500 rounded-full h-2" style="width: <?= $hour['load'] ?>%"></div>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-gray-600"><?= $hour['load'] ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Данные для графиков из PHP
const todayLoadData = <?= json_encode($chart_data['today_loads']) ?>;
const todayHours = <?= json_encode($chart_data['hours']) ?>;
const forecastDays = <?= json_encode($forecast_data['days']) ?>;
const forecastLoads = <?= json_encode($forecast_data['loads']) ?>;
const rfmSegments = <?= json_encode($chart_data['rfm_segments']) ?>;

// График загрузки сегодня
const ctx1 = document.getElementById('todayLoadChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: todayHours,
        datasets: [{
            label: 'Загрузка %',
            data: todayLoadData,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: 'rgb(59, 130, 246)',
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
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + '%';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
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
        labels: forecastDays,
        datasets: [
            {
                label: 'Прогноз загрузки %',
                data: forecastLoads,
                backgroundColor: 'rgba(168, 85, 247, 0.6)',
                borderColor: 'rgb(168, 85, 247)',
                borderWidth: 1,
                borderRadius: 5
            },
            {
                label: 'Рекомендуемая цена (×10 ₽)',
                data: <?= json_encode($forecast_data['prices']) ?>,
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
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (context.dataset.label.includes('Прогноз')) {
                            return context.dataset.label + ': ' + context.parsed.y + '%';
                        } else {
                            return context.dataset.label + ': ' + (context.parsed.y * 10) + ' ₽';
                        }
                    }
                }
            }
        },
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
                    text: 'Цена (×10 ₽)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Круговая диаграмма RFM
const ctx3 = document.getElementById('rfmPieChart').getContext('2d');
new Chart(ctx3, {
    type: 'pie',
    data: {
        labels: Object.keys(rfmSegments),
        datasets: [{
            data: Object.values(rfmSegments),
            backgroundColor: [
                '#F59E0B', // Champions - жёлтый
                '#10B981', // Loyal - зелёный
                '#3B82F6', // Potential - синий
                '#8B5CF6', // Promising - фиолетовый
                '#6B7280', // Regular - серый
                '#EF4444'  // At Risk - красный
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});

// Функция обновления прогноза (AJAX)
function refreshForecast() {
    fetch('api/get-forecast.php')
        .then(response => response.json())
        .then(data => {
            alert('Прогноз обновлён: ' + data.message);
            location.reload();
        })
        .catch(error => console.error('Ошибка:', error));
}

// Автообновление данных каждые 30 секунд (опционально)
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include 'footer.php'; ?>