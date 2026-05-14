<?php
// =====================================================
// client-add.php - Добавление нового клиента
// Коворкинг-центр: регистрация клиента с валидацией
// =====================================================

require_once 'config.php';
require_once 'functions.php';

$message = '';
$error = '';
$form_data = [];

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Получаем данные из формы
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Сохраняем для отображения в случае ошибки
    $form_data = [
        'full_name' => $full_name,
        'phone' => $phone,
        'email' => $email,
        'birthday' => $birthday,
        'notes' => $notes
    ];
    
    // Валидация
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Введите ФИО клиента';
    }
    
    if (empty($phone)) {
        $errors[] = 'Введите номер телефона';
    } elseif (!preg_match('/^[\+\d\s\-\(\)]{10,20}$/', $phone)) {
        $errors[] = 'Введите корректный номер телефона';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email';
    }
    
    if (!empty($birthday) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        $errors[] = 'Неверный формат даты рождения';
    }
    
    // Проверка на дубликат телефона
    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, full_name FROM clients WHERE phone = ?");
        $stmt->execute([$phone]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Клиент с таким номером телефона уже существует';
        }
    }
    
    // Проверка на дубликат email
    if (empty($errors) && !empty($email)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Клиент с таким email уже существует';
        }
    }
    
    // Если ошибок нет - сохраняем
    if (empty($errors)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO clients (full_name, phone, email, birthday, notes, registration_date) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$full_name, $phone, $email, $birthday ?: null, $notes])) {
                $client_id = $db->lastInsertId();
                
                // Перенаправление с сообщением об успехе
                header("Location: client-view.php?id=" . $client_id . "&success=added");
                exit();
            } else {
                $error = 'Ошибка при сохранении клиента';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Получение статистики для отображения на странице
$db = getDB();
$stmt = $db->query("SELECT COUNT(*) as total FROM clients");
$total_clients = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM clients WHERE MONTH(registration_date) = MONTH(NOW())");
$new_this_month = $stmt->fetch()['total'];

include 'header.php';
include 'left-menu.php';
?>

<!-- Основной контент -->
<div class="flex-1 p-6 bg-gray-50">
    
    <div class="max-w-4xl mx-auto">
        
        <!-- Заголовок -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">➕ Добавление клиента</h1>
                <p class="text-gray-600 mt-1">Заполните информацию о новом клиенте</p>
            </div>
            <a href="clients.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Назад к списку</span>
            </a>
        </div>
        
        <!-- Сообщения об ошибках -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-start">
                <div class="flex items-start space-x-2">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span><?= $error ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Статистика -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow p-4 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm opacity-90">Всего клиентов</p>
                        <p class="text-3xl font-bold"><?= $total_clients ?></p>
                    </div>
                    <i class="fas fa-users text-4xl opacity-50"></i>
                </div>
            </div>
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm opacity-90">Новых за месяц</p>
                        <p class="text-3xl font-bold"><?= $new_this_month ?></p>
                    </div>
                    <i class="fas fa-user-plus text-4xl opacity-50"></i>
                </div>
            </div>
        </div>
        
        <!-- Форма добавления -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-xl font-bold text-gray-800">📝 Информация о клиенте</h2>
                <p class="text-sm text-gray-500 mt-1">Поля, отмеченные *, обязательны для заполнения</p>
            </div>
            
            <form method="POST" class="p-6" id="clientForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- ФИО -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ФИО <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="full_name" 
                               value="<?= htmlspecialchars($form_data['full_name'] ?? '') ?>" 
                               required
                               placeholder="Иванов Иван Иванович"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Полное имя клиента</p>
                    </div>
                    
                    <!-- Телефон -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Телефон <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" 
                               name="phone" 
                               value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" 
                               required
                               placeholder="+7 (999) 123-45-67"
                               id="phoneInput"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Пример: +7 (999) 123-45-67</p>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Email
                        </label>
                        <input type="email" 
                               name="email" 
                               value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" 
                               placeholder="client@example.com"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Для отправки уведомлений</p>
                    </div>
                    
                    <!-- Дата рождения -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Дата рождения
                        </label>
                        <input type="date" 
                               name="birthday" 
                               value="<?= htmlspecialchars($form_data['birthday'] ?? '') ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Для поздравлений и акций</p>
                    </div>
                    
                    <!-- Регистрация (автоматически) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Дата регистрации
                        </label>
                        <input type="text" 
                               value="<?= date('d.m.Y H:i') ?>" 
                               disabled
                               class="w-full bg-gray-100 border border-gray-300 rounded-lg px-4 py-2 text-gray-500">
                        <p class="text-xs text-gray-500 mt-1">Заполнится автоматически</p>
                    </div>
                    
                    <!-- Примечания -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Примечания
                        </label>
                        <textarea name="notes" 
                                  rows="3" 
                                  placeholder="Дополнительная информация о клиенте..."
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($form_data['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <!-- Кнопки -->
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="clients.php" class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition">
                        Отмена
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center space-x-2">
                        <i class="fas fa-save"></i>
                        <span>Сохранить клиента</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Подсказки -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">После добавления клиента:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Вы сможете редактировать его данные</li>
                        <li>Клиенту будет присвоен RFM-сегмент после первого посещения</li>
                        <li>Вы сможете запустить таймер для этого клиента</li>
                        <li>История визитов будет автоматически сохраняться</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Быстрый старт -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-lightbulb text-yellow-500 mt-0.5"></i>
                <div class="text-sm text-yellow-800">
                    <p class="font-semibold mb-1">💡 Совет для быстрого добавления:</p>
                    <p>Используйте горячие клавиши: <kbd class="px-1 bg-yellow-100 rounded">Tab</kbd> для перехода между полями, <kbd class="px-1 bg-yellow-100 rounded">Enter</kbd> для отправки формы.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript для улучшения UX -->
<script>
// Автоматическое форматирование телефона
const phoneInput = document.getElementById('phoneInput');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length === 11) {
            let formatted = '+7 (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
            e.target.value = formatted;
        }
    });
}

// Предупреждение при уходе со страницы с несохранёнными данными
let formChanged = false;
const form = document.getElementById('clientForm');
if (form) {
    form.querySelectorAll('input, textarea').forEach(field => {
        field.addEventListener('change', () => { formChanged = true; });
    });
    
    window.addEventListener('beforeunload', (e) => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'У вас есть несохранённые изменения. Вы уверены, что хотите уйти?';
            return e.returnValue;
        }
    });
    
    form.addEventListener('submit', () => {
        formChanged = false;
    });
}

// Фокус на первое поле
document.addEventListener('DOMContentLoaded', () => {
    const firstInput = document.querySelector('input[name="full_name"]');
    if (firstInput) firstInput.focus();
});
</script>

<?php include 'footer.php'; ?>