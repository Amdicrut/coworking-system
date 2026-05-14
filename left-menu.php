<?php
// Левое меню навигации - не содержит открывающих/закрывающих div-ов
?>
<div class="w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white shadow-xl">
    <div class="p-6 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-building text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold">Коворкинг</h1>
                <p class="text-xs text-gray-400">Аналитика и управление</p>
            </div>
        </div>
    </div>
    
    <nav class="p-4 space-y-1">
        <a href="index.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-700' : '' ?>">
            <i class="fas fa-chart-line w-5"></i>
            <span>Дашборд</span>
        </a>
        
        <a href="clients.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-users w-5"></i>
            <span>Клиенты</span>
        </a>
        
        <a href="visits.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-clock w-5"></i>
            <span>Активные визиты</span>
            <?php 
            $active_count = getActiveVisitsCount();
            if ($active_count > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-0.5"><?= $active_count ?></span>
            <?php endif; ?>
        </a>
        
        <a href="visits-history.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-history w-5"></i>
            <span>История</span>
        </a>
        
        <div class="border-t border-gray-700 my-4"></div>
        
        <p class="text-xs text-gray-500 px-4 py-2 uppercase tracking-wider">Аналитика</p>
        
        <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-chart-bar w-5"></i>
            <span>Все графики</span>
        </a>
        
        <a href="forecast.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-cloud-sun w-5"></i>
            <span>Прогноз загрузки</span>
        </a>
        
        <a href="dynamic-prices.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-tags w-5"></i>
            <span>Динамические цены</span>
        </a>
        
        <a href="rfm-report.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-chart-pie w-5"></i>
            <span>RFM-сегменты</span>
        </a>
        
        <div class="border-t border-gray-700 my-4"></div>
        
        <p class="text-xs text-gray-500 px-4 py-2 uppercase tracking-wider">Управление</p>
        
        <a href="tariffs.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-ticket-alt w-5"></i>
            <span>Тарифы</span>
        </a>
        
        <a href="services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition-all duration-200">
            <i class="fas fa-cube w-5"></i>
            <span>Доп. услуги</span>
        </a>
    </nav>
</div>