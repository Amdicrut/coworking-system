<?php
// =====================================================
// config.php - Конфигурация проекта
// Коворкинг-центр с аналитикой и динамическими ценами
// =====================================================

// Отображение ошибок (для разработки)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'coworking_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Базовые настройки коворкинга
define('TOTAL_SEATS', 50);                    // Всего мест
define('BASE_HOURLY_PRICE', 200);             // Базовая цена часа (руб)
define('PEAK_LOAD_THRESHOLD', 80);            // Порог пиковой загрузки (%)
define('LOW_LOAD_THRESHOLD', 30);             // Порог низкой загрузки (%)
define('PEAK_MULTIPLIER', 1.3);               // Множитель в часы пик
define('LOW_MULTIPLIER', 0.7);                // Множитель в часы спада

// RFM настройки
define('RFM_RECENCY_HIGH', 7);                // Дней для высокого R балла
define('RFM_RECENCY_MID', 30);                // Дней для среднего R балла
define('RFM_FREQUENCY_HIGH', 10);             // Визитов для высокого F балла
define('RFM_FREQUENCY_MID', 3);               // Визитов для среднего F балла
define('RFM_MONETARY_HIGH', 10000);           // Рублей для высокого M балла
define('RFM_MONETARY_MID', 3000);             // Рублей для среднего M балла

// Настройки времени
date_default_timezone_set('Europe/Moscow');

// Сессия
session_start();

// Подключение к БД
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Вспомогательные функции
function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// Получить значение из конфига БД
function getConfig($key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['config_value'];
    }
    return $default;
}

// =====================================================
// Базовые функции для работы с данными
// =====================================================

// Подсчёт активных клиентов
function getTotalClients() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM clients WHERE is_active = 1");
    $result = $stmt->fetch();
    return $result['count'];
}

// Активные визиты сейчас
function getActiveVisitsCount() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM visits WHERE status = 'active'");
    $result = $stmt->fetch();
    return $result['count'];
}

// Выручка сегодня
function getTodayRevenue() {
    $db = getDB();
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM visits 
        WHERE status = 'completed' 
        AND DATE(end_time) = CURDATE()
    ");
    $result = $stmt->fetch();
    return $result['total'];
}

// Текущая загрузка в %
function getCurrentLoad() {
    $active = getActiveVisitsCount();
    $total = TOTAL_SEATS;
    
    if ($total == 0) return 0;
    
    $load = round(($active / $total) * 100);
    return min($load, 100);
}
?>