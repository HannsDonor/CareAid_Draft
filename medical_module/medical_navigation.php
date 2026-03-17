<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_nav_current = basename($_SERVER['PHP_SELF']);
$mednav_account_id = (int)($_SESSION['account_id'] ?? 0);

$mednav_notifications = [];
$mednav_notification_error = '';

if (!isset($conn) || !($conn instanceof mysqli)) {
    @include '../db_config/connection_db.php';
}

if (isset($conn) && ($conn instanceof mysqli) && $mednav_account_id > 0) {
    $mednav_notif_stmt = $conn->prepare(
        "SELECT n.notification_id, n.title, n.message, n.status, n.created_at,
                sp.first_name, sp.last_name
         FROM notifications n
         LEFT JOIN senior_profiles sp ON sp.account_id = n.account_id
         WHERE n.notification_type = 'assistance'
           AND n.account_id = ?
           AND n.is_deleted = 0
         ORDER BY n.created_at DESC, n.notification_id DESC
         LIMIT 8"
    );

    if ($mednav_notif_stmt) {
        $mednav_notif_stmt->bind_param('i', $mednav_account_id);
        $mednav_notif_stmt->execute();
        $mednav_notif_res = $mednav_notif_stmt->get_result();
        if ($mednav_notif_res) {
            while ($row = $mednav_notif_res->fetch_assoc()) {
                $mednav_notifications[] = $row;
            }
        }
        $mednav_notif_stmt->close();
    } else {
        $mednav_notification_error = 'Notifications are unavailable.';
    }
}

$mednav_unread_count = 0;
foreach ($mednav_notifications as $notification) {
    if (strtolower((string)($notification['status'] ?? '')) === 'unread') {
        $mednav_unread_count++;
    }
}

function _nav_link(string $href, string $icon, string $label, string $current): string {
    $base = basename($href);
    $active = ($base === $current) ? ' active' : '';
    return sprintf(
        '<a class="nav-link%s" href="%s"><i class="bi bi-%s"></i> %s</a>',
        $active,
        htmlspecialchars($href),
        $icon,
        $label
    );
}
?>
<style>
.mednav-sidebar {
    width: 240px;
    height: 100vh;
    background: linear-gradient(160deg, #2d3a8c 0%, #764ba2 100%);
    color: #fff;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
}
.mednav-sidebar .brand {
    padding: 18px 20px 12px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}
.mednav-sidebar .brand h6 { font-size: .75rem; opacity: .6; margin-bottom: 2px; }
.mednav-sidebar .brand h5 { font-size: 1rem; font-weight: 700; margin: 0; }
.mednav-notif-wrap {
    padding: 10px 14px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}
.mednav-notif-btn {
    width: 100%;
    border: 1px solid rgba(255,255,255,.28);
    border-radius: 10px;
    background: rgba(255,255,255,.1);
    color: #fff;
    padding: 8px 10px;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .84rem;
}
.mednav-notif-btn:hover { background: rgba(255,255,255,.16); }
.mednav-notif-item {
    cursor: pointer;
    position: relative;
}
.mednav-notif-item:hover {
    background: #f8f9fb;
}
.mednav-remove-btn {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 0;
    background: #f1f3f5;
    color: #6c757d;
    font-size: .82rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.mednav-remove-btn:hover {
    background: #e9ecef;
    color: #212529;
}
.mednav-sidebar .nav-link {
    color: rgba(255,255,255,.75);
    padding: 8px 20px;
    font-size: .88rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 0;
    transition: background .15s;
}
.mednav-sidebar .nav-link:hover,
.mednav-sidebar .nav-link.active {
    background: rgba(255,255,255,.15);
    color: #fff;
}
.mednav-sidebar .nav-section {
    padding: 6px 20px 3px;
    font-size: .7rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .45;
    margin-top: 6px;
}
.mednav-footer {
    margin-top: auto;
    padding: 12px 20px;
    border-top: 1px solid rgba(255,255,255,.15);
    font-size: .8rem;
    opacity: .65;
}
.mednav-main { margin-left: 240px; }

@media (min-width: 992px) {
    .mednav-sidebar nav {
        gap: .25rem !important;
    }
}
</style>

<aside class="mednav-sidebar">
    <div class="brand">
        <h6>CAREAID SYSTEM</h6>
        <h5><i class="bi bi-heart-pulse-fill me-1"></i> Health Worker Portal</h5>
    </div>

    <div class="mednav-notif-wrap">
        <button type="button" class="mednav-notif-btn" data-bs-toggle="modal" data-bs-target="#medNavNotificationsModal">
            <span><i class="bi bi-bell-fill me-2"></i>Notifications</span>
            <span id="mednavUnreadBadge" class="badge bg-light text-dark"><?php echo (int)$mednav_unread_count; ?></span>
        </button>
    </div>

    <nav class="mt-2 d-flex flex-column gap-1">
        <div class="nav-section">Overview</div>
        <?php echo _nav_link('health_worker_dashboard.php', 'speedometer2', 'Dashboard', $_nav_current); ?>

        <div class="nav-section">Seniors</div>
        <?php echo _nav_link('senior_list.php', 'person-lines-fill', 'Senior List', $_nav_current); ?>
        <?php echo _nav_link('create_senior_profile.php', 'person-plus-fill', 'Senior Registration', $_nav_current); ?>

        <div class="nav-section">Medical</div>
        <?php echo _nav_link('senior_checkup.php', 'activity', 'Senior Checkup', $_nav_current); ?>
        <?php echo _nav_link('medical_assistance_request.php', 'clipboard2-check-fill', 'Medical Assistance Request', $_nav_current); ?>
        <?php echo _nav_link('illness_list.php', 'clipboard2-heart', 'List of Illness', $_nav_current); ?>

        <div class="nav-section">Guidelines</div>
        <?php echo _nav_link('health_guidance.php', 'journal-medical', 'Health Guidance', $_nav_current); ?>

        <div class="nav-section">Profile Management</div>
        <?php echo _nav_link('account_settings.php', 'gear-fill', 'Account Settings', $_nav_current); ?>
    </nav>

    <div class="mednav-footer">
        Logged in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?></strong><br>
        <a href="../auth_module/logout.php" class="text-white-50 text-decoration-none mt-1 d-inline-block">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>

<div class="modal fade" id="medNavNotificationsModal" tabindex="-1" aria-labelledby="medNavNotificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medNavNotificationsModalLabel"><i class="bi bi-bell me-2"></i> Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($mednav_notification_error !== ''): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($mednav_notification_error); ?></div>
                <?php elseif (count($mednav_notifications) === 0): ?>
                    <div class="text-muted">No notifications yet.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="mednavNotificationsList">
                        <?php foreach ($mednav_notifications as $notif): ?>
                            <?php
                                $full_name = trim((string)($notif['first_name'] ?? '') . ' ' . (string)($notif['last_name'] ?? ''));
                                $created_at = (string)($notif['created_at'] ?? '');
                                $when = $created_at !== '' ? date('M j, Y g:i A', strtotime($created_at)) : '';
                                $is_read = strtolower((string)($notif['status'] ?? '')) === 'read';
                            ?>
                            <div
                                class="list-group-item px-0 mednav-notif-item"
                                data-notification-id="<?php echo (int)($notif['notification_id'] ?? 0); ?>"
                                data-read="<?php echo $is_read ? '1' : '0'; ?>"
                            >
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($notif['title'] ?? 'Notification')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string)($notif['message'] ?? '')); ?></div>
                                        <?php if ($full_name !== ''): ?>
                                            <div class="small text-secondary mt-1"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($full_name); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge mednav-status-badge <?php echo $is_read ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo $is_read ? 'Read' : 'Unread'; ?></span>
                                        <button type="button" class="mednav-remove-btn" title="Remove notification" aria-label="Remove notification">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if ($when !== ''): ?>
                                    <div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($when); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const unreadBadge = document.getElementById('mednavUnreadBadge');
    const notifList = document.getElementById('mednavNotificationsList');
    const items = document.querySelectorAll('.mednav-notif-item');
    if (!items.length) {
        return;
    }

    function setUnreadCount(value) {
        if (!unreadBadge) {
            return;
        }
        unreadBadge.textContent = String(Math.max(0, Number(value) || 0));
    }

    items.forEach(function (item) {
        const removeBtn = item.querySelector('.mednav-remove-btn');

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const notificationId = item.dataset.notificationId;
                if (!notificationId) {
                    return;
                }

                const body = new URLSearchParams({
                    action: 'remove',
                    notification_id: notificationId
                });

                fetch('../medical_module/notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString(),
                    credentials: 'same-origin'
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }

                    item.remove();
                    if (notifList && notifList.querySelectorAll('.mednav-notif-item').length === 0) {
                        notifList.innerHTML = '';
                        const emptyState = document.createElement('div');
                        emptyState.className = 'text-muted';
                        emptyState.textContent = 'No notifications yet.';
                        notifList.appendChild(emptyState);
                    }

                    if (typeof data.unread_count !== 'undefined') {
                        setUnreadCount(data.unread_count);
                    }
                })
                .catch(function () {
                    // Keep silent to avoid noisy UI in shared include.
                });
            });
        }

        item.addEventListener('click', function () {
            const notificationId = item.dataset.notificationId;
            if (!notificationId) {
                return;
            }

            if (item.dataset.read === '1') {
                return;
            }

            const body = new URLSearchParams({
                action: 'mark_read',
                notification_id: notificationId
            });

            fetch('../medical_module/notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString(),
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }

                item.dataset.read = '1';
                const statusBadge = item.querySelector('.mednav-status-badge');
                if (statusBadge) {
                    statusBadge.textContent = 'Read';
                    statusBadge.classList.remove('bg-warning', 'text-dark');
                    statusBadge.classList.add('bg-success');
                }

                if (typeof data.unread_count !== 'undefined') {
                    setUnreadCount(data.unread_count);
                }
            })
            .catch(function () {
                // Keep silent to avoid noisy UI in shared include.
            });
        });
    });
})();
</script>
