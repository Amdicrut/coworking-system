<?php
// =====================================================
// visit-start.php - Запуск таймера посещения
// Коворкинг-центр: начало визита с выбором тарифа и услуг
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$message = '';
$error = '';

// Получаем список клиентов для выбора
$stmt = $db->query("
    SELECT id, full_name, phone 
    FROM clients 
    WHERE is_active = 1 
    ORDER BY full_name ASC
");
$clients = $stmt->fetchAll();

// Получаем активные тарифы
$stmt = $db->query("
    SELECT * FROM tariffs 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, id ASC
");
$tariffs = $stmt->fetchAll();

// Получаем дополнительные услуги
$stmt = $db->query("
    SELECT * FROM extra_services 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, id ASC
");
$services = $stmt->fetchAll();

// Обработка запуска таймера
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_visit'])) {
    $client_id = (int)$_POST['client_id'];
    $tariff_id = !empty($_POST['tariff_id']) ? (int)$_POST['tariff_id'] : null;
    $selected_services = $_POST['services'] ?? [];
    $start_time = date('Y-m-d H:i:s');
    $hourly_rate = BASE_HOURLY_PRICE;
    
    // Проверка выбора клиента
    if ($client_id <= 0) {
        $error = 'Выберите клиента';
    } else {
        // Получаем динамическую цену если есть
        if ($tariff_id) {
            $current_hour = date('H');
            $current_day = date('N');
            $stmt = $db->prepare("
                SELECT dynamic_price 
                FROM dynamic_prices 
                WHERE tariff_id = ? AND day_of_week = ? AND hour_of_day = ? AND is_active = 1
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tariff_id, $current_day, $current_hour]);
            $dynamic = $stmt->fetch();
            if ($dynamic) {
                $hourly_rate = $dynamic['dynamic_price'];
            } else {
                $stmt = $db->prepare("SELECT price FROM tariffs WHERE id = ?");
                $stmt->execute([$tariff_id]);
                $tariff = $stmt->fetch();
                if ($tariff) {
                    $hourly_rate = $tariff['price'];
                }
            }
        }
        
        // Сохраняем выбранные услуги в JSON
        $services_json = json_encode(array_map('intval', $selected_services));
        
        try {
            $stmt = $db->prepare("
                INSERT INTO visits (client_id, tariff_id, start_time, hourly_rate_applied, extra_services_json, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$client_id, $tariff_id, $start_time, $hourly_rate, $services_json]);
            
            $visit_id = $db->lastInsertId();
            
            // Перенаправление на страницу активных визитов
            header("Location: visits.php?success=started&visit_id=" . $visit_id);
            exit();
            
        } catch (PDOException $e) {
            $error = 'Ошибка запуска таймера: ' . $e->getMessage();
        }
    }
}

// Получение текущей загрузки
$current_load = getCurrentLoad();
$active_visits_count = getActiveVisitsCount();

// Получение рекомендуемого тарифа на основе текущего часа
$current_hour = date('H');
$current_day = date('N');
$recommended_tariff_id = null;
$recommended_price = null;
$stmt = $db->prepare("
    SELECT tariff_id, dynamic_price, reason
    FROM dynamic_prices 
    WHERE day_of_week = ? AND hour_of_day = ? AND is_active = 1
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$current_day, $current_hour]);
$recommended = $stmt->fetch();
if ($recommended) {
    $recommended_tariff_id = $recommended['tariff_id'];
    $recommended_price = $recommended['dynamic_price'];
}

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <!-- Заголовок -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">⏱️ Запуск таймера посещения</h1>
        <p class="text-gray-600 mt-1">Начало нового визита клиента</p>
    </div>
    
    <!-- Сообщения -->
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
            <span><?= $error ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Текущая загрузка -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Текущая загрузка</p>
                    <p class="text-3xl font-bold"><?= $current_load ?>%</p>
                </div>
                <i class="fas fa-chart-line text-4xl opacity-50"></i>
            </div>
            <div class="mt-2 bg-blue-400 rounded-full h-1">
                <div class="bg-white rounded-full h-1" style="width: <?= $current_load ?>%"></div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Активных визитов</p>
                    <p class="text-3xl font-bold"><?= $active_visits_count ?></p>
                </div>
                <i class="fas fa-users text-4xl opacity-50"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow p-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm opacity-90">Текущий час</p>
                    <p class="text-3xl font-bold"><?= date('H:i') ?></p>
                </div>
                <i class="fas fa-clock text-4xl opacity-50"></i>
            </div>
        </div>
    </div>
    
    <!-- Форма запуска таймера -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-bold text-gray-800">📝 Информация о визите</h2>
            <p class="text-sm text-gray-500 mt-1">Заполните данные для начала посещения</p>
        </div>
        
        <form method="POST" class="p-6" id="visitForm">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Выбор клиента -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Клиент <span class="text-red-500">*</span>
                    </label>
                    <select name="client_id" id="clientSelect" required 
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Выберите клиента --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>">
                                <?= htmlspecialchars($client['full_name']) ?> (<?= htmlspecialchars($client['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2 text-right">
                        <a href="client-add.php" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus"></i> Добавить нового клиента
                        </a>
                    </div>
                </div>
                
                <!-- Информация о клиенте (заполняется через AJAX) -->
                <div id="clientInfo" class="bg-gray-50 rounded-lg p-4 hidden">
                    <h3 class="font-semibold text-gray-800 mb-2">Информация о клиенте</h3>
                    <div id="clientDetails" class="text-sm text-gray-600"></div>
                </div>
                
                <!-- Выбор тарифа -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Тариф
                    </label>
                    <select name="tariff_id" id="tariffSelect" 
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
                            onchange="updatePrice()">
                        <option value="">-- Стандартный почасовой --</option>
                        <?php foreach ($tariffs as $tariff): ?>
                            <option value="<?= $tariff['id'] ?>" 
                                    data-price="<?= $tariff['price'] ?>"
                                    data-type="<?= $tariff['tariff_type'] ?>"
                                    <?= ($recommended_tariff_id == $tariff['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tariff['name']) ?> - <?= number_format($tariff['price'], 0) ?> ₽
                                <?= ($tariff['tariff_type'] == 'hourly') ? '/час' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($recommended_price): ?>
                        <p class="text-xs text-green-600 mt-1">
                            <i class="fas fa-star"></i> Рекомендуемая цена на текущий час: <?= number_format($recommended_price, 0) ?> ₽/час
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Текущая цена -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Текущая ставка
                    </label>
                    <div class="text-3xl font-bold text-blue-600" id="currentPriceDisplay">
                        <?= number_format(BASE_HOURLY_PRICE, 0) ?> ₽
                    </div>
                    <p class="text-xs text-gray-500 mt-1">/ час (с учётом динамического ценообразования)</p>
                </div>
                
                <!-- Дополнительные услуги -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Дополнительные услуги
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <?php foreach ($services as $service): ?>
                            <label class="flex items-center space-x-2 p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="services[]" value="<?= $service['id'] ?>" 
                                       class="service-checkbox"
                                       data-price="<?= $service['price'] ?>"
                                       data-type="<?= $service['price_type'] ?>"
                                       onchange="updateTotal()">
                                <div>
                                    <span class="font-medium text-sm"><?= htmlspecialchars($service['name']) ?></span>
                                    <span class="text-xs text-gray-500 block">
                                        <?= number_format($service['price'], 0) ?> ₽
                                        <?php if ($service['price_type'] == 'per_hour'): ?>/час<?php endif; ?>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($services)): ?>
                            <p class="text-gray-500 col-span-full">Нет доступных дополнительных услуг</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Итоговая стоимость -->
                <div class="lg:col-span-2 bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-semibold text-gray-800">Предварительная стоимость</p>
                            <p class="text-xs text-gray-500">*Окончательная стоимость будет рассчитана по завершении визита</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Базовая ставка: <span id="basePriceDisplay"><?= number_format(BASE_HOURLY_PRICE, 0) ?></span> ₽/час</p>
                            <p class="text-2xl font-bold text-purple-600" id="totalDisplay">0 ₽</p>
                            <p class="text-xs text-gray-500" id="servicesTotalDisplay">+ 0 ₽ за услуги</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Кнопки -->
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                <a href="visits.php" class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition">
                    Отмена
                </a>
                <button type="submit" name="start_visit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition flex items-center space-x-2">
                    <i class="fas fa-play"></i>
                    <span>Запустить таймер</span>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Подсказки -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Как это работает</p>
                    <p>1. Выберите клиента<br>2. При необходимости выберите тариф и доп. услуги<br>3. Нажмите "Запустить таймер"<br>4. После завершения визита нажмите "Остановить" в списке активных визитов</p>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-chart-line text-yellow-500 mt-0.5"></i>
                <div class="text-sm text-yellow-800">
                    <p class="font-semibold mb-1">Динамическое ценообразование</p>
                    <p>Цена может меняться в зависимости от загрузки коворкинга. В часы пик цена повышается, в часы спада - понижается.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Базовая цена
let basePrice = <?= BASE_HOURLY_PRICE ?>;
let currentHourlyRate = basePrice;

// Информация о клиенте (AJAX)
document.getElementById('clientSelect').addEventListener('change', function() {
    const clientId = this.value;
    const clientInfoDiv = document.getElementById('clientInfo');
    
    if (!clientId) {
        clientInfoDiv.classList.add('hidden');
        return;
    }
    
    fetch(`api/get-client-info.php?id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clientInfoDiv.classList.remove('hidden');
                document.getElementById('clientDetails').innerHTML = `
                    <div class="grid grid-cols-2 gap-2">
                        <div><span class="text-gray-500">Всего визитов:</span> ${data.total_visits}</div>
                        <div><span class="text-gray-500">Всего потрачено:</span> ${data.total_spent.toLocaleString()} ₽</div>
                        <div><span class="text-gray-500">Последний визит:</span> ${data.last_visit || 'Нет'}</div>
                        <div><span class="text-gray-500">RFM сегмент:</span> <span class="px-1 py-0.5 rounded text-xs ${data.rfm_class}">${data.rfm_segment || 'New'}</span></div>
                    </div>
                `;
            } else {
                clientInfoDiv.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            clientInfoDiv.classList.add('hidden');
        });
});

// Обновление цены при выборе тарифа
function updatePrice() {
    const tariffSelect = document.getElementById('tariffSelect');
    const selectedOption = tariffSelect.options[tariffSelect.selectedIndex];
    const tariffPrice = selectedOption.dataset.price;
    
    if (tariffPrice) {
        currentHourlyRate = parseFloat(tariffPrice);
    } else {
        currentHourlyRate = basePrice;
    }
    
    document.getElementById('currentPriceDisplay').innerHTML = currentHourlyRate.toLocaleString() + ' ₽';
    document.getElementById('basePriceDisplay').innerHTML = currentHourlyRate.toLocaleString();
    updateTotal();
}

// Обновление итоговой суммы (за час)
function updateTotal() {
    const services = document.querySelectorAll('.service-checkbox:checked');
    let servicesTotal = 0;
    
    services.forEach(service => {
        const price = parseFloat(service.dataset.price);
        const type = service.dataset.type;
        
        if (type === 'per_hour') {
            servicesTotal += price;
        } else {
            servicesTotal += price;
        }
    });
    
    const total = currentHourlyRate + servicesTotal;
    
    document.getElementById('totalDisplay').innerHTML = total.toLocaleString() + ' ₽/час';
    document.getElementById('servicesTotalDisplay').innerHTML = `+ ${servicesTotal.toLocaleString()} ₽ за услуги (в час)`;
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    updatePrice();
});
</script>

<?php include 'footer.php'; ?>