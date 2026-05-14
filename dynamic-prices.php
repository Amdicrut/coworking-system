<?php
// =====================================================
// dynamic-prices.php - Динамическое ценообразование
// Коворкинг-центр: управление ценами, рекомендации, настройки
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $peak_threshold = (int)$_POST['peak_threshold'];
    $low_threshold = (int)$_POST['low_threshold'];
    $peak_multiplier = (float)$_POST['peak_multiplier'];
    $low_multiplier = (float)$_POST['low_multiplier'];
    $base_price = (int)$_POST['base_price'];
    
    try {
        $stmt = $db->prepare("UPDATE system_config SET config_value = ? WHERE config_key = 'peak_load_threshold'");
        $stmt->execute([$peak_threshold]);
        
        $stmt = $db->prepare("UPDATE system_config SET config_value = ? WHERE config_key = 'low_load_threshold'");
        $stmt->execute([$low_threshold]);
        
        $stmt = $db->prepare("UPDATE system_config SET config_value = ? WHERE config_key = 'peak_multiplier'");
        $stmt->execute([$peak_multiplier]);
        
        $stmt = $db->prepare("UPDATE system_config SET config_value = ? WHERE config_key = 'low_multiplier'");
        $stmt->execute([$low_multiplier]);
        
        $stmt = $db->prepare("UPDATE system_config SET config_value = ? WHERE config_key = 'base_hourly_price'");
        $stmt->execute([$base_price]);
        
        $message = 'Настройки динамического ценообразования сохранены';
    } catch (PDOException $e) {
        $error = 'Ошибка сохранения: ' . $e->getMessage();
    }
}

// Обработка применения рекомендованных цен
if (isset($_GET['apply_recommendations'])) {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    try {
        // Копируем рекомендованные цены в таблицу dynamic_prices
        $stmt = $db->prepare("
            INSERT INTO dynamic_prices (tariff_id, day_of_week, hour_of_day, base_price, dynamic_price, multiplier, reason, applied_from, is_active)
            SELECT 
                1 as tariff_id,
                DAYOFWEEK(forecast_date) as day_of_week,
                hour_of_day,
                (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') as base_price,
                recommended_price,
                recommended_price / (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') as multiplier,
                price_change_reason,
                forecast_date,
                1
            FROM load_forecasts
            WHERE forecast_date = ?
            ON DUPLICATE KEY UPDATE
                dynamic_price = VALUES(dynamic_price),
                multiplier = VALUES(multiplier),
                reason = VALUES(reason),
                applied_to = NULL,
                is_active = 1
        ");
        $stmt->execute([$date]);
        
        $message = 'Рекомендованные цены применены на ' . date('d.m.Y', strtotime($date));
    } catch (PDOException $e) {
        $error = 'Ошибка применения цен: ' . $e->getMessage();
    }
}

// Получение текущих настроек
$stmt = $db->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('peak_load_threshold', 'low_load_threshold', 'peak_multiplier', 'low_multiplier', 'base_hourly_price')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['config_key']] = $row['config_value'];
}

// Получение рекомендаций по ценам на сегодня
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT 
        hour_of_day,
        predicted_load,
        recommended_price,
        price_change_reason,
        CASE 
            WHEN recommended_price > (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') THEN 'up'
            WHEN recommended_price < (SELECT config_value FROM system_config WHERE config_key = 'base_hourly_price') THEN 'down'
            ELSE 'same'
        END as action
    FROM load_forecasts
    WHERE forecast_date = ?
    ORDER BY hour_of_day ASC
");
$stmt->execute([$today]);
$today_recommendations = $stmt->fetchAll();

// Получение рекомендаций на неделю
$stmt = $db->prepare("
    SELECT 
        forecast_date,
        AVG(predicted_load) as avg_load,
        AVG(recommended_price) as avg_price,
        MIN(recommended_price) as min_price,
        MAX(recommended_price) as max_price
    FROM load_forecasts
    WHERE forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY forecast_date
    ORDER BY forecast_date ASC
");
$stmt->execute();
$week_forecast = $stmt->fetchAll();

// Получение истории изменения цен
$stmt = $db->query("
    SELECT 
        dp.*,
        DATE(dp.created_at) as applied_date,
        t.name as tariff_name
    FROM dynamic_prices dp
    LEFT JOIN tariffs t ON t.id = dp.tariff_id
    WHERE dp.is_active = 1
    ORDER BY dp.created_at DESC
    LIMIT 20
");
$price_history = $stmt->fetchAll();

// Получение часов пик для рекомендаций
$stmt = $db->query("
    SELECT 
        hour_of_day,
        AVG(load_ratio) as avg_load,
        AVG(revenue_from_slot) as avg_revenue
    FROM load_history
    WHERE record_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY hour_of_day
    ORDER BY avg_load DESC
    LIMIT 5
");
$peak_hours = $stmt->fetchAll();

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">💰 Динамическое ценообразование</h1>
        <p class="text-gray-600 mt-1">Управление ценами на основе прогноза загрузки</p>
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
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Настройки ценообразования -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h2 class="text-xl font-bold text-gray-800">⚙️ Настройки ценообразования</h2>
                    <p class="text-sm text-gray-500 mt-1">Параметры для расчёта динамических цен</p>
                </div>
                
                <form method="POST" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Базовая цена часа (₽)
                            </label>
                            <input type="number" 
                                   name="base_price" 
                                   value="<?= $settings['base_hourly_price'] ?? 200 ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Исходная цена до применения коэффициентов</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Порог пиковой загрузки (%)
                            </label>
                            <input type="number" 
                                   name="peak_threshold" 
                                   value="<?= $settings['peak_load_threshold'] ?? 80 ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">При превышении цены повышаются</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Порог низкой загрузки (%)
                            </label>
                            <input type="number" 
                                   name="low_threshold" 
                                   value="<?= $settings['low_load_threshold'] ?? 30 ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">При снижении цены понижаются</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Множитель для пиковых часов
                            </label>
                            <input type="number" 
                                   name="peak_multiplier" 
                                   step="0.05"
                                   value="<?= $settings['peak_multiplier'] ?? 1.3 ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Цена × множитель (например, 1.3 = +30%)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Множитель для часов спада
                            </label>
                            <input type="number" 
                                   name="low_multiplier" 
                                   step="0.05"
                                   value="<?= $settings['low_multiplier'] ?? 0.7 ?>" 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Цена × множитель (например, 0.7 = -30%)</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" name="save_settings" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition flex items-center space-x-2">
                            <i class="fas fa-save"></i>
                            <span>Сохранить настройки</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Рекомендации на сегодня -->
            <div class="bg-white rounded-lg shadow mt-6">
                <div class="border-b px-6 py-4">
                    <h2 class="text-xl font-bold text-gray-800">📊 Рекомендации на сегодня (<?= date('d.m.Y') ?>)</h2>
                    <p class="text-sm text-gray-500 mt-1">На основе прогноза загрузки</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Время</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Прогноз загрузки</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Рекомендуемая цена</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Изменение</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Причина</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($today_recommendations)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        <i class="fas fa-chart-line fa-2x mb-2 block"></i>
                                        Нет рекомендаций на сегодня. Запустите прогнозирование.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($today_recommendations as $rec): 
                                    $base_price = $settings['base_hourly_price'] ?? 200;
                                    $change_percent = round(($rec['recommended_price'] - $base_price) / $base_price * 100);
                                    $change_class = $rec['action'] == 'up' ? 'text-red-600' : ($rec['action'] == 'down' ? 'text-green-600' : 'text-gray-600');
                                ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4 font-medium"><?= sprintf('%02d:00', $rec['hour_of_day']) ?> - <?= sprintf('%02d:00', $rec['hour_of_day'] + 1) ?></td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-blue-500 rounded-full h-2" style="width: <?= $rec['predicted_load'] ?>%"></div>
                                                </div>
                                                <span><?= round($rec['predicted_load']) ?>%</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="font-semibold"><?= number_format($rec['recommended_price'], 0) ?> ₽</span>
                                            <span class="text-xs text-gray-500">/час</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="<?= $change_class ?> font-semibold">
                                                <?php if ($rec['action'] == 'up'): ?>
                                                    ▲ +<?= abs($change_percent) ?>%
                                                <?php elseif ($rec['action'] == 'down'): ?>
                                                    ▼ <?= abs($change_percent) ?>%
                                                <?php else: ?>
                                                    ● 0%
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600">
                                            <?= $rec['price_change_reason'] ?? ($rec['action'] == 'up' ? 'Высокая загрузка' : ($rec['action'] == 'down' ? 'Низкая загрузка' : 'Стандартная цена')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="border-t px-6 py-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Рекомендации обновляются ежедневно
                    </div>
                    <a href="?apply_recommendations=1&date=<?= date('Y-m-d') ?>" 
                       onclick="return confirm('Применить рекомендованные цены на сегодня?')"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition text-sm">
                        <i class="fas fa-check-circle mr-1"></i>Применить рекомендации
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Боковая панель -->
        <div class="space-y-6">
            
            <!-- Формула расчёта -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow p-6 text-white">
                <h3 class="text-lg font-bold mb-3">📐 Формула расчёта</h3>
                <div class="text-sm space-y-2">
                    <p>Если прогноз ≥ <?= $settings['peak_load_threshold'] ?? 80 ?>%:</p>
                    <p class="font-mono bg-blue-700 bg-opacity-50 p-2 rounded">
                        Цена = Базовая × <?= $settings['peak_multiplier'] ?? 1.3 ?>
                    </p>
                    <p>Если прогноз ≤ <?= $settings['low_load_threshold'] ?? 30 ?>%:</p>
                    <p class="font-mono bg-blue-700 bg-opacity-50 p-2 rounded">
                        Цена = Базовая × <?= $settings['low_multiplier'] ?? 0.7 ?>
                    </p>
                    <p>Иначе:</p>
                    <p class="font-mono bg-blue-700 bg-opacity-50 p-2 rounded">
                        Цена = Базовая
                    </p>
                </div>
            </div>
            
            <!-- Часы пик -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">⏰ Часы с максимальной загрузкой</h3>
                <div class="space-y-3">
                    <?php foreach ($peak_hours as $hour): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium"><?= sprintf('%02d:00', $hour['hour_of_day']) ?></span>
                                <span class="text-gray-600"><?= round($hour['avg_load']) ?>%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-red-500 rounded-full h-2" style="width: <?= $hour['avg_load'] ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Прогноз на неделю -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">📅 Прогноз на неделю</h3>
                <div class="space-y-3">
                    <?php foreach ($week_forecast as $day): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium"><?= date('D', strtotime($day['forecast_date'])) ?></span>
                                <span class="text-purple-600 font-semibold"><?= number_format($day['avg_price'], 0) ?> ₽</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>загрузка: <?= round($day['avg_load']) ?>%</span>
                                <span>диапазон: <?= number_format($day['min_price'], 0) ?> - <?= number_format($day['max_price'], 0) ?> ₽</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-500 rounded-full h-2" style="width: <?= $day['avg_load'] ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-center">
                    <a href="forecast.php" class="text-blue-600 hover:text-blue-800 text-sm">Подробный прогноз →</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- История изменений цен -->
    <div class="mt-6">
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">📜 История изменения цен</h2>
                <p class="text-sm text-gray-500 mt-1">Последние применённые рекомендации</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Дата применения</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Время слота</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Тариф</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Базовая цена</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Динамическая цена</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Множитель</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Причина</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($price_history)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-history fa-2x mb-2 block"></i>
                                    История изменений цен пуста
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($price_history as $history): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm"><?= date('d.m.Y', strtotime($history['applied_date'])) ?></td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= sprintf('%02d:00', $history['hour_of_day']) ?> - <?= sprintf('%02d:00', $history['hour_of_day'] + 1) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm"><?= htmlspecialchars($history['tariff_name'] ?? 'Стандартный') ?></td>
                                    <td class="py-3 px-4 text-right text-sm"><?= number_format($history['base_price'], 0) ?> ₽</td>
                                    <td class="py-3 px-4 text-right text-sm font-semibold text-purple-600">
                                        <?= number_format($history['dynamic_price'], 0) ?> ₽
                                    </td>
                                    <td class="py-3 px-4 text-right text-sm">×<?= $history['multiplier'] ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?= $history['reason'] ?? '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Автоматическое обновление рекомендаций каждые 5 минут (опционально)
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php include 'footer.php'; ?>