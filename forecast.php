<?php
// =====================================================
// forecast.php - Прогнозирование загрузки
// Коворкинг-центр: прогноз на основе исторических данных
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Обработка генерации прогноза
if (isset($_GET['generate'])) {
    $forecast_date = $_GET['date'] ?? date('Y-m-d');
    
    try {
        // Удаляем старый прогноз
        $stmt = $db->prepare("DELETE FROM load_forecasts WHERE forecast_date = ?");
        $stmt->execute([$forecast_date]);
        
        // Генерируем новый прогноз
        $day_of_week = date('N', strtotime($forecast_date));
        $base_price = getConfig('base_hourly_price', 200);
        $peak_threshold = getConfig('peak_load_threshold', 80);
        $low_threshold = getConfig('low_load_threshold', 30);
        $peak_multiplier = getConfig('peak_multiplier', 1.3);
        $low_multiplier = getConfig('low_multiplier', 0.7);
        
        for ($hour = 8; $hour <= 22; $hour++) {
            // Получаем исторические данные за последние 4 недели
            $stmt = $db->prepare("
                SELECT AVG(load_ratio) as avg_load, COUNT(*) as data_points
                FROM load_history
                WHERE day_of_week = ? AND hour_of_day = ?
                AND record_date > DATE_SUB(?, INTERVAL 28 DAY)
            ");
            $stmt->execute([$day_of_week, $hour, $forecast_date]);
            $history = $stmt->fetch();
            
            // Если нет исторических данных, используем среднее значение
            if ($history['data_points'] < 3) {
                // Получаем среднюю загрузку для этого часа за все дни
                $stmt = $db->prepare("
                    SELECT AVG(load_ratio) as avg_load
                    FROM load_history
                    WHERE hour_of_day = ?
                ");
                $stmt->execute([$hour]);
                $global_avg = $stmt->fetch();
                $predicted_load = round($global_avg['avg_load'] ?? 50, 1);
            } else {
                $predicted_load = round($history['avg_load'], 1);
            }
            
            // Расчёт рекомендуемой цены
            if ($predicted_load >= $peak_threshold) {
                $recommended_price = $base_price * $peak_multiplier;
                $reason = "Высокая загрузка ({$predicted_load}% > {$peak_threshold}%)";
            } elseif ($predicted_load <= $low_threshold) {
                $recommended_price = $base_price * $low_multiplier;
                $reason = "Низкая загрузка ({$predicted_load}% < {$low_threshold}%)";
            } else {
                $recommended_price = $base_price;
                $reason = "Стандартная загрузка";
            }
            
            // Сохраняем прогноз
            $stmt = $db->prepare("
                INSERT INTO load_forecasts 
                (forecast_date, day_of_week, hour_of_day, predicted_load, recommended_price, price_change_reason, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$forecast_date, $day_of_week, $hour, $predicted_load, $recommended_price, $reason]);
        }
        
        $message = "Прогноз на " . date('d.m.Y', strtotime($forecast_date)) . " успешно сгенерирован";
        
    } catch (PDOException $e) {
        $error = "Ошибка генерации прогноза: " . $e->getMessage();
    }
}

// Получение параметров фильтрации
$selected_date = $_GET['date'] ?? date('Y-m-d');
$view_type = $_GET['view'] ?? 'daily'; // daily, weekly, monthly

// Получение прогноза на выбранную дату
$stmt = $db->prepare("
    SELECT 
        hour_of_day,
        predicted_load,
        recommended_price,
        price_change_reason,
        created_at,
        updated_at
    FROM load_forecasts
    WHERE forecast_date = ?
    ORDER BY hour_of_day ASC
");
$stmt->execute([$selected_date]);
$daily_forecast = $stmt->fetchAll();

// Получение прогноза на неделю
$stmt = $db->prepare("
    SELECT 
        forecast_date,
        DAYOFWEEK(forecast_date) as day_of_week,
        AVG(predicted_load) as avg_load,
        MIN(predicted_load) as min_load,
        MAX(predicted_load) as max_load,
        AVG(recommended_price) as avg_price,
        MIN(recommended_price) as min_price,
        MAX(recommended_price) as max_price,
        SUM(CASE WHEN recommended_price > (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') THEN 1 ELSE 0 END) as peak_hours_count,
        SUM(CASE WHEN recommended_price < (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') THEN 1 ELSE 0 END) as low_hours_count
    FROM load_forecasts
    WHERE forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY forecast_date
    ORDER BY forecast_date ASC
");
$stmt->execute();
$weekly_forecast = $stmt->fetchAll();

// Получение исторической точности прогнозов
$stmt = $db->query("
    SELECT 
        DATE(forecast_date) as date,
        AVG(ABS(predicted_load - COALESCE(
            (SELECT load_ratio FROM load_history lh WHERE lh.record_date = lf.forecast_date AND lh.hour_of_day = lf.hour_of_day), 
            predicted_load
        ))) as avg_error
    FROM load_forecasts lf
    WHERE forecast_date < CURDATE()
    GROUP BY DATE(forecast_date)
    ORDER BY forecast_date DESC
    LIMIT 7
");
$accuracy_history = $stmt->fetchAll();

// Получение общей статистики
$stmt = $db->query("
    SELECT 
        COUNT(DISTINCT forecast_date) as total_forecasts,
        AVG(predicted_load) as overall_avg_load,
        AVG(recommended_price) as overall_avg_price
    FROM load_forecasts
    WHERE forecast_date >= CURDATE()
");
$forecast_stats = $stmt->fetch();

// Получение сравнения с фактической загрузкой (для прошедших дат)
$stmt = $db->prepare("
    SELECT 
        lf.hour_of_day,
        lf.predicted_load,
        lh.load_ratio as actual_load,
        ABS(lf.predicted_load - lh.load_ratio) as error
    FROM load_forecasts lf
    LEFT JOIN load_history lh ON lh.record_date = lf.forecast_date AND lh.hour_of_day = lf.hour_of_day
    WHERE lf.forecast_date = ? AND lh.load_ratio IS NOT NULL
    ORDER BY lf.hour_of_day ASC
");
$stmt->execute([$selected_date]);
$comparison_data = $stmt->fetchAll();

// Получение рекомендаций по оптимизации
$recommendations = [];
$avg_weekday_load = 0;
$avg_weekend_load = 0;
$weekday_count = 0;
$weekend_count = 0;

foreach ($weekly_forecast as $day) {
    $day_num = date('N', strtotime($day['forecast_date']));
    if ($day_num >= 6) {
        $avg_weekend_load += $day['avg_load'];
        $weekend_count++;
    } else {
        $avg_weekday_load += $day['avg_load'];
        $weekday_count++;
    }
}

if ($weekday_count > 0) $avg_weekday_load /= $weekday_count;
if ($weekend_count > 0) $avg_weekend_load /= $weekend_count;

if ($avg_weekday_load > 70) {
    $recommendations[] = "В будние дни высокая загрузка (>70%). Рекомендуется повысить цены в часы пик.";
}
if ($avg_weekend_load < 40) {
    $recommendations[] = "В выходные дни низкая загрузка (<40%). Рекомендуется ввести специальные тарифы или акции.";
}

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">🔮 Прогноз загрузки</h1>
        <p class="text-gray-600 mt-1">Предсказание загрузки и рекомендации по ценообразованию</p>
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
    
    <!-- Панель управления -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата прогноза</label>
                <input type="date" 
                       id="forecastDate" 
                       value="<?= $selected_date ?>" 
                       class="border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Вид просмотра</label>
                <select id="viewType" class="border border-gray-300 rounded-lg px-3 py-2">
                    <option value="daily" <?= $view_type == 'daily' ? 'selected' : '' ?>>Почасовой прогноз</option>
                    <option value="weekly" <?= $view_type == 'weekly' ? 'selected' : '' ?>>Недельный прогноз</option>
                    <option value="comparison" <?= $view_type == 'comparison' ? 'selected' : '' ?>>Сравнение с фактом</option>
                </select>
            </div>
            <div>
                <button onclick="changeDate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-2"></i>Показать
                </button>
            </div>
            <div>
                <button onclick="generateForecast()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-sync-alt mr-2"></i>Сгенерировать прогноз
                </button>
            </div>
        </div>
    </div>
    
    <!-- Статистика прогнозов -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Всего прогнозов</p>
                    <p class="text-3xl font-bold"><?= $forecast_stats['total_forecasts'] ?? 0 ?></p>
                </div>
                <i class="fas fa-calendar-alt text-4xl opacity-50"></i>
            </div>
        </div>
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Средняя прогнозируемая загрузка</p>
                    <p class="text-3xl font-bold"><?= round($forecast_stats['overall_avg_load'] ?? 0) ?>%</p>
                </div>
                <i class="fas fa-chart-line text-4xl opacity-50"></i>
            </div>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Средняя рекомендуемая цена</p>
                    <p class="text-3xl font-bold"><?= number_format($forecast_stats['overall_avg_price'] ?? 0, 0) ?> ₽</p>
                </div>
                <i class="fas fa-ruble-sign text-4xl opacity-50"></i>
            </div>
        </div>
    </div>
    
    <?php if ($view_type == 'daily'): ?>
        <!-- Почасовой прогноз -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">
                    📊 Прогноз на <?= date('d.m.Y', strtotime($selected_date)) ?>
                </h2>
                <p class="text-sm text-gray-500 mt-1">Почасовой прогноз загрузки и рекомендации по ценам</p>
            </div>
            
            <div class="overflow-x-auto p-6">
                <canvas id="hourlyForecastChart" height="300"></canvas>
            </div>
            
            <div class="border-t px-6 py-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Максимальная загрузка</p>
                        <p class="text-2xl font-bold text-red-600">
                            <?= !empty($daily_forecast) ? round(max(array_column($daily_forecast, 'predicted_load'))) : 0 ?>%
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Минимальная загрузка</p>
                        <p class="text-2xl font-bold text-green-600">
                            <?= !empty($daily_forecast) ? round(min(array_column($daily_forecast, 'predicted_load'))) : 0 ?>%
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Максимальная цена</p>
                        <p class="text-2xl font-bold text-purple-600">
                            <?= !empty($daily_forecast) ? number_format(max(array_column($daily_forecast, 'recommended_price')), 0) : 0 ?> ₽
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Часы с повышенной ценой</p>
                        <p class="text-2xl font-bold text-orange-600">
                            <?= !empty($daily_forecast) ? count(array_filter($daily_forecast, function($h) use ($settings) { 
                                $base = getConfig('base_hourly_price', 200);
                                return $h['recommended_price'] > $base; 
                            })) : 0 ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Таблица с прогнозом -->
        <div class="bg-white rounded-lg shadow mt-6">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">📋 Детальная таблица прогноза</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <｜▁pad▁｜>
                            <th class="text-left py-3 px-4">Время</th>
                            <th class="text-left py-3 px-4">Прогноз загрузки</th>
                            <th class="text-left py-3 px-4">Рекомендуемая цена</th>
                            <th class="text-left py-3 px-4">Изменение</th>
                            <th class="text-left py-3 px-4">Причина</th>
                        数
                    </thead>
                    <tbody>
                        <?php if (empty($daily_forecast)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-chart-line fa-2x mb-2 block"></i>
                                    Нет прогноза на выбранную дату. Нажмите "Сгенерировать прогноз".
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $base_price = getConfig('base_hourly_price', 200);
                            foreach ($daily_forecast as $hour): 
                                $change = round(($hour['recommended_price'] - $base_price) / $base_price * 100);
                                $change_class = $change > 0 ? 'text-red-600' : ($change < 0 ? 'text-green-600' : 'text-gray-600');
                                $change_icon = $change > 0 ? '▲' : ($change < 0 ? '▼' : '●');
                            ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium"><?= sprintf('%02d:00', $hour['hour_of_day']) ?> - <?= sprintf('%02d:00', $hour['hour_of_day'] + 1) ?></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-32 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-500 rounded-full h-2" style="width: <?= $hour['predicted_load'] ?>%"></div>
                                            </div>
                                            <span><?= round($hour['predicted_load']) ?>%</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-semibold"><?= number_format($hour['recommended_price'], 0) ?> ₽</span>
                                        <span class="text-xs text-gray-500">/час</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="<?= $change_class ?> font-semibold">
                                            <?= $change_icon ?> <?= abs($change) ?>%
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?= $hour['price_change_reason'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($view_type == 'weekly'): ?>
        <!-- Недельный прогноз -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">📅 Недельный прогноз</h2>
                <p class="text-sm text-gray-500 mt-1">Прогноз загрузки и цен на предстоящую неделю</p>
            </div>
            
            <div class="p-6">
                <canvas id="weeklyForecastChart" height="300"></canvas>
            </div>
            
            <div class="border-t px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($weekly_forecast as $day): 
                        $day_name = ['', 'ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'][date('N', strtotime($day['forecast_date']))];
                    ?>
                        <div class="border rounded-lg p-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-bold"><?= $day_name ?>, <?= date('d.m', strtotime($day['forecast_date'])) ?></span>
                                <span class="text-sm <?= $day['avg_load'] > 70 ? 'text-red-600' : ($day['avg_load'] < 40 ? 'text-green-600' : 'text-gray-600') ?>">
                                    <?= round($day['avg_load']) ?>% загрузка
                                </span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2 mb-3">
                                <div class="bg-blue-500 rounded-full h-2" style="width: <?= $day['avg_load'] ?>%"></div>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Цена: <?= number_format($day['avg_price'], 0) ?> ₽</span>
                                <span>Диапазон: <?= number_format($day['min_price'], 0) ?> - <?= number_format($day['max_price'], 0) ?> ₽</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-2">
                                <span>🔺 Пиковых часов: <?= $day['peak_hours_count'] ?></span>
                                <span>🔻 Часов спада: <?= $day['low_hours_count'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Рекомендации -->
        <?php if (!empty($recommendations)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg shadow mt-6 p-4">
                <h3 class="text-lg font-bold text-yellow-800 mb-2">💡 Рекомендации по оптимизации</h3>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($recommendations as $rec): ?>
                        <li class="text-yellow-700"><?= $rec ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
    <?php elseif ($view_type == 'comparison' && !empty($comparison_data)): ?>
        <!-- Сравнение с фактом -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">📈 Сравнение прогноза с фактом</h2>
                <p class="text-sm text-gray-500 mt-1">Точность прогноза для <?= date('d.m.Y', strtotime($selected_date)) ?></p>
            </div>
            
            <div class="p-6">
                <canvas id="comparisonChart" height="300"></canvas>
            </div>
            
            <div class="border-t px-6 py-4">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-sm text-gray-500">Средняя ошибка</p>
                        <p class="text-2xl font-bold text-blue-600">
                            <?= round(array_sum(array_column($comparison_data, 'error')) / count($comparison_data), 1) ?>%
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Точность прогноза</p>
                        <p class="text-2xl font-bold text-green-600">
                            <?= round(100 - (array_sum(array_column($comparison_data, 'error')) / count($comparison_data)), 1) ?>%
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Количество часов</p>
                        <p class="text-2xl font-bold text-purple-600"><?= count($comparison_data) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- История точности -->
        <div class="bg-white rounded-lg shadow mt-6">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">📊 История точности прогнозов</h3>
            </div>
            <div class="p-6">
                <canvas id="accuracyChart" height="200"></canvas>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Переключение даты
function changeDate() {
    const date = document.getElementById('forecastDate').value;
    const view = document.getElementById('viewType').value;
    window.location.href = `forecast.php?date=${date}&view=${view}`;
}

// Генерация прогноза
function generateForecast() {
    const date = document.getElementById('forecastDate').value;
    if (confirm(`Сгенерировать прогноз на ${date}?`)) {
        window.location.href = `forecast.php?generate=1&date=${date}&view=daily`;
    }
}

<?php if ($view_type == 'daily' && !empty($daily_forecast)): ?>
// Почасовой прогноз
const hourlyCtx = document.getElementById('hourlyForecastChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($h) { return sprintf('%02d:00', $h['hour_of_day']); }, $daily_forecast)) ?>,
        datasets: [
            {
                label: 'Прогноз загрузки (%)',
                data: <?= json_encode(array_map(function($h) { return round($h['predicted_load']); }, $daily_forecast)) ?>,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Рекомендуемая цена (₽)',
                data: <?= json_encode(array_map(function($h) { return $h['recommended_price']; }, $daily_forecast)) ?>,
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0)',
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
<?php endif; ?>

<?php if ($view_type == 'weekly' && !empty($weekly_forecast)): ?>
// Недельный прогноз
const weeklyCtx = document.getElementById('weeklyForecastChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($d) { 
            return date('d.m', strtotime($d['forecast_date'])); 
        }, $weekly_forecast)) ?>,
        datasets: [
            {
                label: 'Средняя загрузка (%)',
                data: <?= json_encode(array_map(function($d) { return round($d['avg_load']); }, $weekly_forecast)) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
                borderRadius: 5,
                yAxisID: 'y'
            },
            {
                label: 'Средняя цена (₽)',
                data: <?= json_encode(array_map(function($d) { return round($d['avg_price']); }, $weekly_forecast)) ?>,
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
<?php endif; ?>

<?php if ($view_type == 'comparison' && !empty($comparison_data)): ?>
// Сравнение прогноза с фактом
const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
new Chart(comparisonCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($h) { return sprintf('%02d:00', $h['hour_of_day']); }, $comparison_data)) ?>,
        datasets: [
            {
                label: 'Прогноз (%)',
                data: <?= json_encode(array_map(function($h) { return round($h['predicted_load']); }, $comparison_data)) ?>,
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 4
            },
            {
                label: 'Факт (%)',
                data: <?= json_encode(array_map(function($h) { return round($h['actual_load']); }, $comparison_data)) ?>,
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 4
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
            }
        }
    }
});

// История точности
const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
new Chart(accuracyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($a) { return date('d.m', strtotime($a['date'])); }, $accuracy_history)) ?>,
        datasets: [{
            label: 'Ошибка прогноза (%)',
            data: <?= json_encode(array_map(function($a) { return round($a['avg_error']); }, $accuracy_history)) ?>,
            borderColor: 'rgb(168, 85, 247)',
            backgroundColor: 'rgba(168, 85, 247, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Ошибка (%)'
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>