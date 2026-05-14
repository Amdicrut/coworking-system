<?php
// =====================================================
// Функции для дашборда (добавить в существующий functions.php)
// =====================================================

// Получить данные для графика загрузки сегодня
function getDashboardChartData() {
    $db = getDB();
    $hours = [];
    $loads = [];
    
    for ($i = 8; $i <= 22; $i++) {
        $hours[] = $i . ':00';
        
        $stmt = $db->prepare("
            SELECT AVG(load_ratio) as avg_load 
            FROM load_history 
            WHERE hour_of_day = ? AND record_date = CURDATE()
        ");
        $stmt->execute([$i]);
        $result = $stmt->fetch();
        $loads[] = round($result['avg_load'] ?? rand(30, 70), 0);
    }
    
    // RFM сегменты для круговой диаграммы
    $stmt = $db->query("
        SELECT rfm_segment, COUNT(*) as count 
        FROM clients 
        WHERE rfm_segment IS NOT NULL 
        GROUP BY rfm_segment
    ");
    $segments = [];
    while ($row = $stmt->fetch()) {
        $segments[$row['rfm_segment']] = $row['count'];
    }
    
    return [
        'hours' => $hours,
        'today_loads' => $loads,
        'rfm_segments' => $segments
    ];
}

// Получить среднюю загрузку за сегодня
function getAverageDailyLoad() {
    $db = getDB();
    $stmt = $db->query("
        SELECT AVG(load_ratio) as avg_load 
        FROM load_history 
        WHERE record_date = CURDATE()
    ");
    $result = $stmt->fetch();
    return round($result['avg_load'] ?? 0);
}

// Получить выручку за месяц
function getMonthRevenue() {
    $db = getDB();
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM visits 
        WHERE status = 'completed' 
        AND MONTH(end_time) = MONTH(CURDATE()) 
        AND YEAR(end_time) = YEAR(CURDATE())
    ");
    $result = $stmt->fetch();
    return $result['total'];
}

// Получить топ клиентов по RFM
function getTopRFMClients($limit = 5) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, full_name, total_visits, total_spent, rfm_segment, r_score, f_score, m_score
        FROM clients 
        WHERE rfm_segment IS NOT NULL
        ORDER BY (r_score + f_score + m_score) DESC, total_spent DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Получить прогноз на неделю
function getWeekForecast() {
    $db = getDB();
    $days = [];
    $loads = [];
    $prices = [];
    
    for ($i = 0; $i <= 6; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $days[] = date('D', strtotime($date));
        
        $stmt = $db->prepare("
            SELECT AVG(predicted_load) as avg_load, AVG(recommended_price) as avg_price
            FROM load_forecasts 
            WHERE forecast_date = ?
        ");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        
        $loads[] = round($result['avg_load'] ?? 50);
        $prices[] = round(($result['avg_price'] ?? 200) / 10);
    }
    
    return [
        'days' => $days,
        'loads' => $loads,
        'prices' => $prices
    ];
}

// Получить рекомендации по ценам (следующие 4 часа)
function getPriceRecommendations() {
    $db = getDB();
    $current_hour = date('H');
    $recommendations = [];
    
    $base_price = BASE_HOURLY_PRICE;
    
    for ($i = 0; $i <= 3; $i++) {
        $hour = ($current_hour + $i) % 24;
        $time_slot = $hour . ':00-' . ($hour + 1) . ':00';
        
        $stmt = $db->prepare("
            SELECT predicted_load 
            FROM load_forecasts 
            WHERE forecast_date = CURDATE() AND hour_of_day = ?
        ");
        $stmt->execute([$hour]);
        $result = $stmt->fetch();
        
        $forecast = $result['predicted_load'] ?? 50;
        
        if ($forecast >= 80) {
            $new_price = $base_price * 1.3;
            $action = 'up';
            $change = '+30%';
        } elseif ($forecast <= 30) {
            $new_price = $base_price * 0.7;
            $action = 'down';
            $change = '-30%';
        } else {
            $new_price = $base_price;
            $action = 'same';
            $change = '0%';
        }
        
        $recommendations[] = [
            'time_slot' => $time_slot,
            'forecast' => round($forecast),
            'old_price' => $base_price,
            'new_price' => round($new_price),
            'action' => $action,
            'change' => $change
        ];
    }
    
    return $recommendations;
}

// Получить последние визиты
function getRecentVisits($limit = 5) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT v.*, c.full_name 
        FROM visits v
        JOIN clients c ON c.id = v.client_id
        WHERE v.status = 'active'
        ORDER BY v.start_time DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Получить часы пик
function getPeakHours() {
    $db = getDB();
    $stmt = $db->query("
        SELECT hour_of_day as hour, AVG(load_ratio) as load
        FROM load_history
        WHERE record_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY hour_of_day
        ORDER BY load DESC
        LIMIT 5
    ");
    return $stmt->fetchAll();
}

// Получение статистики клиентов по RFM
function getRFMStatistics() {
    $db = getDB();
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN rfm_segment = 'Champions' THEN 1 ELSE 0 END) as champions,
            SUM(CASE WHEN rfm_segment = 'Loyal' THEN 1 ELSE 0 END) as loyal,
            SUM(CASE WHEN rfm_segment = 'Potential' THEN 1 ELSE 0 END) as potential,
            SUM(CASE WHEN rfm_segment = 'At Risk' THEN 1 ELSE 0 END) as at_risk
        FROM clients
    ");
    return $stmt->fetch();
}


// Проверка существования клиента по телефону
function clientExistsByPhone($phone, $exclude_id = null) {
    $db = getDB();
    if ($exclude_id) {
        $stmt = $db->prepare("SELECT id FROM clients WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $exclude_id]);
    } else {
        $stmt = $db->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmt->execute([$phone]);
    }
    return $stmt->fetch() !== false;
}

// Проверка существования клиента по email
function clientExistsByEmail($email, $exclude_id = null) {
    if (empty($email)) return false;
    $db = getDB();
    if ($exclude_id) {
        $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
        $stmt->execute([$email, $exclude_id]);
    } else {
        $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
    }
    return $stmt->fetch() !== false;
}

// Подсчёт новых клиентов за месяц
function getNewClientsThisMonth() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM clients WHERE MONTH(registration_date) = MONTH(NOW())");
    return $stmt->fetch()['count'];
}
?>