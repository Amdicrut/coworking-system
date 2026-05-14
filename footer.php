    </div> <!-- Закрываем flex контейнер -->
    
    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            toast.className = `toast-notification ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3`;
            toast.innerHTML = `
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function formatNumber(num, decimals = 0) {
            return new Intl.NumberFormat('ru-RU').format(num);
        }
        
        // Глобальная функция для обработки ошибок AJAX
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
        });
    </script>
</body>
</html>