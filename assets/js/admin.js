// assets/js/admin.js
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.querySelector('.admin-hamburger');
    const sidebar = document.querySelector('.admin-sidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.createElement('div');

    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Inject Desktop Toggle Button
    if (mainContent) {
        const desktopToggle = document.createElement('button');
        desktopToggle.innerHTML = '<i class="fas fa-bars"></i>';
        desktopToggle.className = 'desktop-sidebar-toggle';
        desktopToggle.title = 'Basculer le menu';
        mainContent.insertBefore(desktopToggle, mainContent.firstChild);

        desktopToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            // Save state in localStorage so it persists across pages
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
        });

        // Restore state from localStorage on load immediately
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 992) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    }

    // --- NOTIFICATION SYSTEM INTEGRATION ---
    const fetchNotifications = async () => {
        try {
            const res = await fetch('../api/notifications.php?action=list');
            if(!res.ok) return;
            const data = await res.json();
            if(data.success) {
                updateNotificationUI(data.unread_count, data.notifications);
            }
        } catch(e) {}
    };

    const markAsRead = async (id = null) => {
        try {
            const action = id ? 'mark_read' : 'mark_all_read';
            const formData = new FormData();
            if(id) formData.append('id', id);
            
            await fetch(`../api/notifications.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            fetchNotifications(); // Refresh 
        } catch(e) {}
    };

    let dropdownContainer;

    const updateNotificationUI = (count, notifications) => {
        let bell = document.getElementById('notification-bell');
        
        // If bell doesn't exist, inject it smartly
        if(!bell) {
            bell = document.createElement('div');
            bell.id = 'notification-bell';
            bell.innerHTML = '<i class="fas fa-bell"></i><div id="notification-badge" style="display:none;">0</div>';
            
            const mobileHeader = document.querySelector('.admin-mobile-header');
            if(window.innerWidth <= 992 && mobileHeader) {
                // Ensure mobile header has space
                mobileHeader.style.justifyContent = 'space-between';
                bell.style.marginRight = '10px';
                mobileHeader.insertBefore(bell, hamburger); // Place right next to hamburger
            } else if(mainContent) {
                // Desktop - Absolute positioning to float at top right of content
                bell.style.position = 'absolute';
                bell.style.top = '30px';
                bell.style.right = '40px';
                bell.style.zIndex = '1050';
                bell.style.background = 'white';
                bell.style.boxShadow = '0 2px 10px rgba(0,0,0,0.05)';
                mainContent.style.position = 'relative'; // Anchor relative bounds
                mainContent.appendChild(bell);
            }

            // Create dropdown
            dropdownContainer = document.createElement('div');
            dropdownContainer.id = 'notification-dropdown';
            bell.appendChild(dropdownContainer);

            bell.addEventListener('click', (e) => {
                if(e.target.closest('.notification-item') || e.target.closest('a')) return;
                dropdownContainer.classList.toggle('active');
            });
            
            // Close if clicked outside
            document.addEventListener('click', (e) => {
                if(bell && !bell.contains(e.target)) {
                    dropdownContainer.classList.remove('active');
                }
            });
        }

        const badge = document.getElementById('notification-badge');
        if(count > 0) {
            badge.innerText = count > 9 ? '9+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        let html = `
            <div class="notification-header">
                <h3>Notifications</h3>
                <a onclick="window.samUtils_markAllRead(event)">Tout marquer lu</a>
            </div>
            <div class="notification-list">
        `;

        if(notifications.length === 0) {
            html += `<div class="notification-empty">Aucune notification.</div>`;
        } else {
            notifications.forEach(n => {
                const isUnread = n.is_read == 0;
                const unreadClass = isUnread ? 'unread' : '';
                html += `
                    <div class="notification-item ${unreadClass}" onclick="window.samUtils_readNotif(event, ${n.id})">
                        <div class="notif-title">
                            <span>${n.title}</span>
                            ${isUnread ? '<i class="fas fa-circle" style="color:var(--primary-blue); font-size:0.5rem; margin-top:4px;"></i>' : ''}
                        </div>
                        <div class="notif-message">${n.message}</div>
                        <div class="notif-time">${new Date(n.created_at).toLocaleString('fr-FR')}</div>
                    </div>
                `;
            });
        }
        html += `</div>`;
        dropdownContainer.innerHTML = html;
    };

    window.samUtils_markAllRead = (e) => {
        e.preventDefault();
        e.stopPropagation();
        markAsRead(null);
    };

    window.samUtils_readNotif = (e, notifId) => {
        if(e.target.closest('.notification-item').classList.contains('unread')) {
            markAsRead(notifId);
        }
        dropdownContainer.classList.remove('active');

        // Extract notification data from the clicked element
        const item = e.target.closest('.notification-item');
        const title = item.querySelector('.notif-title span').innerText;
        const message = item.querySelector('.notif-message').innerText;
        const time = item.querySelector('.notif-time').innerText;

        // Show Native Modal
        showNotificationModal(title, message, time);
    };

    // Inject Notification Modal
    const injectNotificationModal = () => {
        if(document.getElementById('sys-notif-modal')) return;
        const modalHtml = `
            <div id="sys-notif-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
                <div style="background:white; width:90%; max-width:500px; border-radius:15px; box-shadow:0 15px 35px rgba(0,0,0,0.2); overflow:hidden; animation:notifModalIn 0.3s ease-out;">
                    <div style="background:linear-gradient(135deg, rgb(2, 54, 103), var(--primary-blue, #03117a)); padding:20px; color:white; display:flex; justify-content:space-between; align-items:center;">
                        <h3 id="sys-notif-modal-title" style="margin:0; font-size:1.1rem; font-weight:700;"></h3>
                        <button onclick="document.getElementById('sys-notif-modal').style.display='none'" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>
                    </div>
                    <div style="padding:25px;">
                        <div id="sys-notif-modal-time" style="font-size:0.8rem; color:#888; margin-bottom:15px;"></div>
                        <div id="sys-notif-modal-message" style="font-size:1rem; color:#333; line-height:1.6;"></div>
                    </div>
                    <div style="padding:15px 25px; background:#f9fafb; text-align:right; border-top:1px solid #eee;">
                        <button onclick="document.getElementById('sys-notif-modal').style.display='none'" style="padding:10px 20px; background:rgb(2, 54, 103); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">Fermer</button>
                    </div>
                </div>
            </div>
            <style>
                @keyframes notifModalIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
            </style>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Close on outside click
        document.getElementById('sys-notif-modal').addEventListener('click', function(e) {
            if(e.target === this) {
                this.style.display = 'none';
            }
        });
    };

    const showNotificationModal = (title, message, time) => {
        injectNotificationModal();
        document.getElementById('sys-notif-modal-title').innerText = title;
        document.getElementById('sys-notif-modal-message').innerText = message;
        document.getElementById('sys-notif-modal-time').innerText = time;
        document.getElementById('sys-notif-modal').style.display = 'flex';
    };

    // Polling INIT
    fetchNotifications();
    setInterval(fetchNotifications, 30000); 

});
