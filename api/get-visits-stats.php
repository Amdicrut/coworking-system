<?php
require_once '../config.php';

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        DATE(end_time) as visit_date,
        COUNT(*) as visit_count,
        COALESCE(SUM(total_amount), 0) as total_revenue
    FROM visits
    WHERE status = 'completed' 
        AND DATE(end_time) BETWEEN ? AND ?
    GROUP BY DATE(end_time)
    ORDER BY visit_date ASC
");
$stmt->execute([$date_from, $date_to]);
$data = $stmt->fetchAll();

$dates = [];
$counts = [];
$revenues = [];

foreach ($data as $row) {
    $dates[] = date('d.m', strtotime($row['visit_date']));
    $counts[] = $row['visit_count'];
    $revenues[] = $row['total_revenue'];
}

echo json_encode([
    'dates' => $dates,
    'counts' => $counts,
    'revenues' => $revenues
]);
?>