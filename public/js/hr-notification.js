// public/js/hr-notification.js

const HRNotification = {
    // Configuration
    config: {
        apiBaseUrl: '/api/notifications',
        refreshInterval: 30000, // 30 detik
        soundEnabled: true
    },

    // Initialize
    init() {
        this.setupEchoListener();
        this.fetchUnreadNotifications();
        this.setAutoRefresh();
        this.attachEventListeners();
    },

    setupEchoListener() {
        console.log('Checking window.Echo:', window.Echo);
        if (!window.Echo) {
            console.warn('Waiting for Laravel Echo to initialize...');
            setTimeout(() => this.setupEchoListener(), 500);
            return;
        }

        window.Echo.private('hr.contract-notifications')
            .listen('.ContractExpiring', (response) => {
                console.log('New contract expiring notification:', response);
                
                const { data } = response;
                
                // Tambah notifikasi baru ke panel
                this.addNotificationToPanel(data);
                
                // Update badge
                this.updateUnreadBadge();
                
                // Play sound
                if (this.config.soundEnabled) {
                    this.playNotificationSound();
                }
                
                // Show browser notification
                this.showBrowserNotification(data);
            });
    },

    // Fetch unread notifications dari API
    fetchUnreadNotifications() {
        fetch(this.config.apiBaseUrl + '/unread', {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderNotifications(data.recent);
                this.updateUnreadBadge(data.unread_count);
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
    },

    // Render notifications ke panel
    renderNotifications(notifications) {
        const panel = document.getElementById('notificationPanel');
        
        if (!notifications || notifications.length === 0) {
            panel.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p class="mb-0">Tidak ada notifikasi</p>
                </div>
            `;
            return;
        }

        panel.innerHTML = notifications.map(notif => this.createNotificationItem(notif)).join('');
    },

    // Create notification item HTML
    createNotificationItem(notif) {
        const statusClass = notif.status === 'unread' ? 'unread' : '';
        
        return `
            <div class="notification-item ${statusClass}" data-id="${notif.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="employee-name">${notif.employee_name}</div>
                        <small class="text-muted d-block">NPK: ${notif.npk}</small>
                        <div class="remaining-days mt-1">
                            <i class="fas fa-exclamation-circle"></i>
                            ${notif.days_remaining} hari lagi
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">${notif.formatted_date}</small>
                        ${notif.status === 'unread' ? '<span class="badge bg-danger">Baru</span>' : ''}
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Berakhir: ${notif.end_date_formatted}</small>
            </div>
        `;
    },

    // Tambah notifikasi baru ke panel
    addNotificationToPanel(notif) {
        const panel = document.getElementById('notificationPanel');
        
        // Remove empty state jika ada
        const emptyState = panel.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        // Insert di paling atas
        const newItem = document.createElement('div');
        newItem.innerHTML = this.createNotificationItem(notif);
        panel.prepend(newItem.firstChild);

        // Limit max items di panel (keep last 5)
        const items = panel.querySelectorAll('.notification-item');
        if (items.length > 5) {
            items[items.length - 1].remove();
        }
    },

    // Update unread badge
    updateUnreadBadge(count = null) {
        if (count === null) {
            // Fetch dari API
            fetch(this.config.apiBaseUrl + '/unread', {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateUnreadBadge(data.unread_count);
                }
            });
            return;
        }

        const badge = document.getElementById('unreadBadge');
        const countSpan = document.getElementById('unreadCount');
        
        if (count > 0) {
            badge.style.display = 'inline-block';
            countSpan.textContent = count;
        } else {
            badge.style.display = 'none';
        }
    },

    // Mark notification as read
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
                // Update UI
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                }
                
                // Update badge
                this.updateUnreadBadge();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    },

    // Mark all as read
    markAllAsRead() {
        fetch(this.config.apiBaseUrl + '/read-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                });
                
                this.updateUnreadBadge(0);
            }
        });
    },

    // Attach event listeners
    attachEventListeners() {
        // Click notification item
        document.addEventListener('click', (e) => {
            const notifItem = e.target.closest('.notification-item');
            if (notifItem) {
                const id = notifItem.dataset.id;
                this.markAsRead(id);
                // Optional: redirect ke detail contract
                // window.location.href = `/hr/contracts/${contractId}`;
            }
        });

        // Mark all as read button
        const markAllBtn = document.getElementById('markAllAsReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
    },

    // Auto refresh notifications
    setAutoRefresh() {
        setInterval(() => {
            this.fetchUnreadNotifications();
        }, this.config.refreshInterval);
    },

    // Play notification sound
    playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.play().catch(error => {
            // Mute jika browser block autoplay
            console.log('Audio autoplay blocked:', error);
        });
    },

    // Show browser notification
    showBrowserNotification(data) {
        if (!('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'granted') {
            new Notification('Notifikasi Kontrak', {
                body: `Kontrak ${data.employee_name} akan habis dalam ${data.days_remaining} hari`,
                icon: '/images/notification-icon.png',
                tag: 'contract-expiring'
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('Notifikasi Kontrak', {
                        body: `Kontrak ${data.employee_name} akan habis dalam ${data.days_remaining} hari`,
                        icon: '/images/notification-icon.png',
                        tag: 'contract-expiring'
                    });
                }
            });
        }
    },

    // Helper: Get CSRF token
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    // Helper: Get auth token
    getAuthToken() {
        // Jika menggunakan API token auth
        return document.querySelector('meta[name="api-token"]')?.content || '';
    }
};

// Initialize ketika DOM ready
document.addEventListener('DOMContentLoaded', () => {
    HRNotification.init();
});