<?php
$_snav_page = basename($_SERVER['PHP_SELF']);
$_snav_username = htmlspecialchars((string)($_SESSION['username'] ?? 'Senior'));
$_snav_show_bottom = isset($snav_show_bottom) ? (bool)$snav_show_bottom : true;
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
	<div class="snav-user">
		<i class="bi bi-person-circle"></i>
		<?php echo $_snav_username; ?>
	</div>
</header>

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
