<?php
// =====================================================
// API: Получение информации о клиенте
// =====================================================

require_once '../config.php';

// Проверяем наличие параметра id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID клиента не указан']);
    exit();
}

$client_id = (int)$_GET['id'];

if ($client_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID клиента']);
    exit();
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            id, 
            full_name, 
            phone, 
            total_visits, 
            total_spent, 
            rfm_segment,
            DATE_FORMAT(last_visit_date, '%d.%m.%Y') as last_visit
        FROM clients 
        WHERE id = ?
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if ($client) {
        // Цвета для RFM сегментов
        $rfm_colors = [
            'Champions' => 'bg-yellow-100 text-yellow-800',
            'Loyal' => 'bg-green-100 text-green-800',
            'Potential' => 'bg-blue-100 text-blue-800',
            'Promising' => 'bg-purple-100 text-purple-800',
            'Regular' => 'bg-gray-100 text-gray-800',
            'At Risk' => 'bg-red-100 text-red-800',
            'Lost' => 'bg-gray-300 text-gray-700'
        ];
        
        echo json_encode([
            'success' => true,
            'total_visits' => (int)($client['total_visits'] ?? 0),
            'total_spent' => (float)($client['total_spent'] ?? 0),
            'last_visit' => $client['last_visit'] ?? 'Нет',
            'rfm_segment' => $client['rfm_segment'] ?? 'New',
            'rfm_class' => $rfm_colors[$client['rfm_segment']] ?? 'bg-gray-100 text-gray-800'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Клиент не найден']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>