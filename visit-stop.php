<?php
require_once 'config.php';
require_once 'functions.php';

$visit_id = (int)$_GET['id'];

$db = getDB();
$stmt = $db->prepare("
    SELECT 
        v.*,
        c.full_name as client_name,
        c.phone as client_phone,
        t.name as tariff_name
    FROM visits v
    JOIN clients c ON c.id = v.client_id
    LEFT JOIN tariffs t ON t.id = v.tariff_id
    WHERE v.id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch();

if (!$visit) {
    header("Location: visits.php");
    exit();
}

include 'header.php';
include 'left-menu.php';
?>

<div class="flex-1 p-6 bg-gray-50">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="text-green-500 mb-4">
                <i class="fas fa-check-circle fa-5x"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Визит завершён!</h1>
            <p class="text-gray-600 mb-6">Чек №<?= $visit_id ?> от <?= date('d.m.Y H:i', strtotime($visit['end_time'])) ?></p>
            
            <div class="border-t border-b py-4 mb-4 text-left">
                <div class="flex justify-between py-2">
                    <span>Клиент:</span>
                    <span class="font-semibold"><?= htmlspecialchars($visit['client_name']) ?></span>
                </div>
                <div class="flex justify-between py-2">
                    <span>Длительность:</span>
                    <span><?= round($visit['duration_hours'], 1) ?> часов</span>
                </div>
                <div class="flex justify-between py-2">
                    <span>Тариф:</span>
                    <span><?= htmlspecialchars($visit['tariff_name'] ?? 'Стандартный') ?></span>
                </div>
                <div class="flex justify-between py-2">
                    <span>Ставка:</span>
                    <span><?= number_format($visit['hourly_rate_applied'], 0) ?> ₽/час</span>
                </div>
                <div class="flex justify-between py-2 border-t mt-2 pt-2">
                    <span class="font-bold">Итого:</span>
                    <span class="font-bold text-xl text-purple-600"><?= number_format($visit['total_amount'], 0) ?> ₽</span>
                </div>
            </div>
            
            <div class="flex space-x-3">
                <a href="visits.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg">
                    К списку визитов
                </a>
                <button onclick="window.print()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 rounded-lg">
                    <i class="fas fa-print"></i> Печать
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>