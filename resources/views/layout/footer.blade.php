<!-- Footer -->
<footer class="sticky-footer bg-white">
    <div class="container my-auto">
        <div class="copyright text-center my-auto">
            <span>Copyright &copy; PT. Chutex International Indonesia - {{ date('Y') }}</span>
        </div>
    </div>
</footer>
<!-- End of Footer -->

</div>
<!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
<i class="fas fa-angle-up"></i>
</a>

<!-- Bootstrap core JavaScript-->
<script src="{{asset('vendor/jquery/jquery.min.js')}}"></script>
<script src="{{asset('vendor/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<script src="{{asset('vendor/jquery/select2.min.js')}}"></script>


<!-- Core plugin JavaScript-->
<script src="{{asset('vendor/jquery-easing/jquery.easing.min.js')}}"></script>

<!-- Custom scripts for all pages-->
<script src="{{asset('js/sb-admin-2.min.js')}}"></script>

<!-- Page level plugins -->
<script src="{{asset('vendor/chart.js/Chart.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    const NotificationManager = {
        config: {
            apiBaseUrl: '/api/notifications',
            refreshInterval: 30000,
            soundEnabled: true
        },

        init() {
            console.log('NotificationManager initialized');
            this.loadNotifications();
            this.setupEchoListener();

            // Auto refresh setiap 30 detik
            setInterval(() => this.loadNotifications(), this.config.refreshInterval);
        },

        setupEchoListener() {
            // console.log('Checking window.Echo:', window.Echo);
            if (!window.Echo) {
                console.warn('Waiting for Laravel Echo to initialize...');
                setTimeout(() => this.setupEchoListener(), 500);
                return;
            }

            try {
                window.Echo.private('hr.contract-notifications')
                    .listen('.ContractExpiring', (response) => {
                        console.log('New contract expiring notification received:', response);

                        const { data } = response;

                        // Play sound
                        this.playNotificationSound();

                        // Browser notification
                        this.showBrowserNotification(data);

                        // Update UI (Refresh list and badge)
                        this.loadNotifications();
                    });

                console.log('Echo listener registered successfully');
            } catch (error) {
                console.error('Error setting up Echo listener:', error);
            }
        },

        loadNotifications() {
            fetch(`${this.config.apiBaseUrl}?per_page=50`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.updateBadge(data.data);
                    this.renderQuickPreview(data.data);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
        },

        renderQuickPreview(notifications) {
            const quickPanel = document.getElementById('quickNotificationPanel');

            if (!notifications || notifications.length === 0) {
                quickPanel.innerHTML = `
                    <div class="dropdown-item text-center text-muted py-3">
                        <i class="fas fa-check-circle mr-2 text-success"></i><br/>
                        <small>Semua kontrak karyawan aman</small>
                    </div>
                `;
                return;
            }

            const unreadCount = notifications.filter(n => n.status === 'unread').length;

            const preview = notifications.slice(0, 5).map(notif => `
                <a class="dropdown-item d-flex align-items-center" href="#" onclick="NotificationManager.markAsRead(${notif.id}); event.preventDefault()">
                    <div class="mr-3">
                        <div class="bg-light rounded-circle p-3">
                            ${notif.days_remaining <= 3
                                ? '<i class="fas fa-exclamation-triangle text-danger"></i>'
                                : '<i class="fas fa-calendar-check text-warning"></i>'}
                        </div>
                    </div>
                    <div class="flex-grow-1" style="white-space: normal;">
                        <div class="small font-weight-bold text-dark">
                            ${notif.employee_name} <span class="text-muted">(${notif.npk})</span>
                        </div>
                        <div class="small text-muted mt-1">
                            Kontrak akan habis pada <b>${notif.end_date_formatted}</b> (Tersisa ${notif.days_remaining} hari)
                        </div>
                    </div>
                    ${notif.status === 'unread' ? '<div class="ml-2"><span class="badge badge-danger badge-counter">Baru</span></div>' : ''}
                </a>
            `).join('');

            // Add "Mark All as Read" button at the bottom if there are unread items
            const markAllBtn = unreadCount > 0 ? `
                <a class="dropdown-item text-center small text-primary bg-light" href="#" onclick="NotificationManager.markAllAsRead(); event.preventDefault()">
                    <i class="fas fa-check-double mr-1"></i> Tandai Semua Sudah Dibaca
                </a>
            ` : '';

            quickPanel.innerHTML = preview + markAllBtn;
        },

        updateBadge(notifications) {
            // Kita bisa langsung hitung unreadCount dari parameter notifications
            // Tanpa perlu fetch '/unread' terpisah jika kita sudah punya data
            if (notifications) {
                const unreadCount = notifications.filter(n => n.status === 'unread').length;
                const badge = document.getElementById('unreadBadge');
                const count = document.getElementById('unreadCount');

                if (unreadCount > 0) {
                    badge.style.display = 'inline-block';
                    count.textContent = unreadCount;
                } else {
                    badge.style.display = 'none';
                }
            } else {
                // Fallback fetch if no data provided
                fetch(`${this.config.apiBaseUrl}/unread`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('unreadBadge');
                    const count = document.getElementById('unreadCount');
                    if (data.success && data.unread_count > 0) {
                        badge.style.display = 'inline-block';
                        count.textContent = data.unread_count;
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
            }
        },

        markAsRead(notificationId) {
            fetch(`${this.config.apiBaseUrl}/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.loadNotifications();
                }
            })
            .catch(error => console.error('Error marking as read:', error));
        },

        markAllAsRead() {
            if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) return;

            fetch(`${this.config.apiBaseUrl}/read-all`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.loadNotifications();
                }
            })
            .catch(error => console.error('Error marking all as read:', error));
        },

        playNotificationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 800;
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('Audio notification not available');
            }
        },

        showBrowserNotification(data) {
            if (!('Notification' in window)) return;

            const title = 'Notifikasi Kontrak Habis';
            const options = {
                body: `Kontrak ${data.employee_name} akan habis dalam ${data.days_remaining} hari`,
                icon: '/images/notification-icon.png',
                tag: 'contract-expiring',
                requireInteraction: true
            };

            if (Notification.permission === 'granted') {
                new Notification(title, options);
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification(title, options);
                    }
                });
            }
        },

        getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        getAuthToken() {
            return document.querySelector('meta[name="api-token"]')?.content || '';
        }
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        NotificationManager.init();
    });

    // Request browser notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
</script>