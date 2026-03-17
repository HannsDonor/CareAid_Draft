<?php
$_snav_page = basename($_SERVER['PHP_SELF']);
$_snav_username = htmlspecialchars((string)($_SESSION['username'] ?? 'Senior'));
$_snav_show_bottom = isset($snav_show_bottom) ? (bool)$snav_show_bottom : true;

$_snav_notifications = [];
$_snav_notification_error = '';
$_snav_unread_count = 0;
$_snav_account_id = (int)($_SESSION['account_id'] ?? 0);

if (!isset($conn) || !($conn instanceof mysqli)) {
	@include '../db_config/connection_db.php';
}

if (isset($conn) && ($conn instanceof mysqli) && $_snav_account_id > 0) {
	$_snav_notif_stmt = $conn->prepare(
		"SELECT notification_id, title, message, notification_type, assistance_type, status, created_at
		 FROM notifications
		 WHERE account_id = ?
		   AND is_deleted = 0
		 ORDER BY created_at DESC, notification_id DESC
		 LIMIT 8"
	);

	if ($_snav_notif_stmt) {
		$_snav_notif_stmt->bind_param('i', $_snav_account_id);
		$_snav_notif_stmt->execute();
		$_snav_notif_result = $_snav_notif_stmt->get_result();
		if ($_snav_notif_result) {
			while ($_snav_row = $_snav_notif_result->fetch_assoc()) {
				$_snav_notifications[] = $_snav_row;
			}
		}
		$_snav_notif_stmt->close();
	} else {
		$_snav_notification_error = 'Notifications are unavailable.';
	}
}

foreach ($_snav_notifications as $_snav_notification) {
	if (strtolower((string)($_snav_notification['status'] ?? '')) === 'unread') {
		$_snav_unread_count++;
	}
}
?>
<style>
.snav-header {
	position: fixed;
	top: 0; left: 0; right: 0;
	z-index: 1030;
	background: #fff;
	box-shadow: 0 2px 12px rgba(18, 38, 56, 0.1);
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 18px;
	height: 58px;
}
.snav-brand {
	font-weight: 700;
	font-size: 1.05rem;
	color: #1f5d8a;
	display: flex;
	align-items: center;
	gap: 6px;
}
.snav-user {
	font-size: .82rem;
	color: #5f7283;
	display: flex;
	align-items: center;
	gap: 5px;
	max-width: 140px;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
.snav-logout-top {
	width: 34px;
	height: 34px;
	border-radius: 50%;
	border: 1px solid rgba(224, 90, 90, 0.25);
	background: #fff;
	color: #e05a5a;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	text-decoration: none;
	font-size: .95rem;
}
.snav-logout-top:hover {
	background: #fff5f5;
	color: #b02a37;
}
.snav-right {
	display: flex;
	align-items: center;
	gap: 12px;
}
.snav-notif {
	position: relative;
}
.snav-notif-btn {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	border: 1px solid rgba(31, 93, 138, 0.15);
	background: #fff;
	color: #1f5d8a;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 1rem;
}
.snav-notif-btn:hover {
	background: #f3f8fc;
}
.snav-notif-badge {
	position: absolute;
	top: -4px;
	right: -5px;
	font-size: .66rem;
	padding: .2rem .34rem;
}
.snav-notif-item {
	cursor: pointer;
}
.snav-notif-item:hover {
	background: #f8f9fb;
}
.snav-notif-remove {
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
.snav-notif-remove:hover {
	background: #e9ecef;
	color: #212529;
}
.snav-bottom {
	position: fixed;
	bottom: 0; left: 0; right: 0;
	z-index: 1030;
	background: #fff;
	border-top: 1px solid rgba(23, 48, 66, 0.08);
	display: flex;
	justify-content: space-around;
	align-items: stretch;
	height: 62px;
	box-shadow: 0 -4px 16px rgba(18, 38, 56, 0.08);
}
.snav-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 3px;
	text-decoration: none;
	color: #a0aec0;
	font-size: .67rem;
	font-weight: 600;
	letter-spacing: .01em;
	transition: color .15s ease;
	padding: 6px 4px;
	border: none;
	background: none;
	cursor: pointer;
}
.snav-item i {
	font-size: 1.35rem;
	line-height: 1;
}
.snav-item.active {
	color: #1f5d8a;
}
.snav-item:not(.snav-logout):hover {
	color: #1f5d8a;
}
.snav-logout {
	color: #e05a5a;
}
.snav-logout:hover {
	color: #b02a37;
}
body {
	padding-top: 58px !important;
	padding-bottom: <?php echo $_snav_show_bottom ? '68px' : '12px'; ?> !important;
}
</style>

<header class="snav-header">
	<div class="snav-brand">
		<i class="bi bi-heart-pulse-fill" style="color:#2b9e8f;"></i>
		CareAid
	</div>
	<div class="snav-right">
		<div class="snav-notif">
			<button type="button" class="snav-notif-btn" data-bs-toggle="modal" data-bs-target="#snavNotificationsModal" aria-label="Notifications">
				<i class="bi bi-bell-fill"></i>
			</button>
			<span id="snavUnreadBadge" class="badge rounded-pill bg-danger snav-notif-badge"><?php echo (int)$_snav_unread_count; ?></span>
		</div>
		<div class="snav-user">
			<i class="bi bi-person-circle"></i>
			<?php echo $_snav_username; ?>
		</div>
		<a href="../auth_module/logout.php" class="snav-logout-top" title="Logout" aria-label="Logout">
			<i class="bi bi-box-arrow-right"></i>
		</a>
	</div>
</header>

<div class="modal fade" id="snavNotificationsModal" tabindex="-1" aria-labelledby="snavNotificationsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="snavNotificationsModalLabel"><i class="bi bi-bell me-2"></i>Notifications</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<?php if ($_snav_notification_error !== ''): ?>
					<div class="alert alert-danger mb-0"><?php echo htmlspecialchars($_snav_notification_error); ?></div>
				<?php elseif (count($_snav_notifications) === 0): ?>
					<div class="text-muted">No notifications yet.</div>
				<?php else: ?>
					<div class="list-group list-group-flush" id="snavNotificationsList">
						<?php foreach ($_snav_notifications as $_snav_notif): ?>
							<?php
								$_snav_created_at = (string)($_snav_notif['created_at'] ?? '');
								$_snav_when = $_snav_created_at !== '' ? date('M j, Y g:i A', strtotime($_snav_created_at)) : '';
								$_snav_type = ucfirst((string)($_snav_notif['assistance_type'] ?? $_snav_notif['notification_type'] ?? 'Notification'));
								$_snav_is_read = strtolower((string)($_snav_notif['status'] ?? '')) === 'read';
							?>
							<div
								class="list-group-item px-0 snav-notif-item"
								data-notification-id="<?php echo (int)($_snav_notif['notification_id'] ?? 0); ?>"
								data-read="<?php echo $_snav_is_read ? '1' : '0'; ?>"
							>
								<div class="d-flex justify-content-between align-items-start gap-2">
									<div>
										<div class="fw-semibold"><?php echo htmlspecialchars((string)($_snav_notif['title'] ?? 'Notification')); ?></div>
										<div class="small text-muted"><?php echo htmlspecialchars((string)($_snav_notif['message'] ?? '')); ?></div>
										<div class="small text-secondary mt-1"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($_snav_type); ?></div>
									</div>
									<div class="d-flex align-items-center gap-2">
										<span class="badge snav-status-badge <?php echo $_snav_is_read ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo $_snav_is_read ? 'Read' : 'Unread'; ?></span>
										<button type="button" class="snav-notif-remove" title="Remove notification" aria-label="Remove notification">
											<i class="bi bi-x-lg"></i>
										</button>
									</div>
								</div>
								<?php if ($_snav_when !== ''): ?>
									<div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($_snav_when); ?></div>
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
	const unreadBadge = document.getElementById('snavUnreadBadge');
	const notifList = document.getElementById('snavNotificationsList');
	const items = document.querySelectorAll('.snav-notif-item');
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
		const removeBtn = item.querySelector('.snav-notif-remove');

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
					if (notifList && notifList.querySelectorAll('.snav-notif-item').length === 0) {
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
				const statusBadge = item.querySelector('.snav-status-badge');
				if (statusBadge) {
					statusBadge.classList.remove('bg-warning', 'text-dark');
					statusBadge.classList.add('bg-success');
					statusBadge.textContent = 'Read';
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

<?php if ($_snav_show_bottom): ?>
<nav class="snav-bottom">
	<a href="senior_dashboard.php" class="snav-item <?php echo $_snav_page === 'senior_dashboard.php' ? 'active' : ''; ?>">
		<i class="bi bi-house-fill"></i>
		<span>Home</span>
	</a>
	<a href="assistance_request.php" class="snav-item <?php echo $_snav_page === 'assistance_request.php' ? 'active' : ''; ?>">
		<i class="bi bi-life-preserver"></i>
		<span>Assistance</span>
	</a>
	<a href="quick_guidance.php" class="snav-item <?php echo $_snav_page === 'quick_guidance.php' ? 'active' : ''; ?>">
		<i class="bi bi-heart-pulse"></i>
		<span>Quick Check</span>
	</a>
	<a href="../auth_module/logout.php" class="snav-item snav-logout">
		<i class="bi bi-box-arrow-right"></i>
		<span>Logout</span>
	</a>
</nav>
<?php endif; ?>
