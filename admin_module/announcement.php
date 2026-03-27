<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: ../auth_module/login.php');
	exit;
}

$counts = [
	'total' => 0,
	'active' => 0,
	'inactive' => 0,
];

$count_sql = "SELECT
				COUNT(*) AS total_count,
				SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
				SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
			 FROM announcements";
$count_res = $conn->query($count_sql);
if ($count_res && ($row = $count_res->fetch_assoc())) {
	$counts['total'] = (int)($row['total_count'] ?? 0);
	$counts['active'] = (int)($row['active_count'] ?? 0);
	$counts['inactive'] = (int)($row['inactive_count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcement Module - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.sidebar {
	width: 240px;
	min-height: 100vh;
	background: linear-gradient(160deg, #2d3a8c 0%, #764ba2 100%);
	color: #fff;
	position: fixed;
	top: 0; left: 0;
	display: flex;
	flex-direction: column;
	z-index: 100;
}
.sidebar .brand {
	padding: 24px 20px 16px;
	border-bottom: 1px solid rgba(255,255,255,.15);
}
.sidebar .brand h6 { font-size: .75rem; opacity: .6; margin-bottom: 2px; }
.sidebar .brand h5 { font-size: 1rem; font-weight: 700; margin: 0; }
.sidebar .nav-link {
	color: rgba(255,255,255,.75);
	padding: 10px 20px;
	border-radius: 0;
	font-size: .88rem;
	display: flex;
	align-items: center;
	gap: 10px;
	transition: background .15s, color .15s;
}
.sidebar .nav-link:hover,
.sidebar .nav-link.active {
	background: rgba(255,255,255,.15);
	color: #fff;
}
.sidebar-footer {
	margin-top: auto;
	padding: 16px 20px;
	border-top: 1px solid rgba(255,255,255,.15);
	font-size: .8rem;
	opacity: .65;
}
.main-wrap {
	margin-left: 240px;
	min-height: 100vh;
	background: #f0f2f5;
}
.topbar {
	background: #fff;
	padding: 14px 28px;
	border-bottom: 1px solid #e3e6ef;
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.content-area { padding: 28px; }
.card-soft {
	border: none;
	border-radius: 14px;
	box-shadow: 0 4px 18px rgba(0,0,0,.07);
}
.menu-card {
	transition: transform .2s ease, box-shadow .2s ease;
}
.menu-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 10px 28px rgba(0,0,0,.12);
}
.menu-icon {
	width: 52px;
	height: 52px;
	border-radius: 12px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 1.4rem;
}
</style>
</head>
<body>
<aside class="sidebar">
	<div class="brand">
		<h6>CAREAID SYSTEM</h6>
		<h5><i class="bi bi-heart-pulse-fill me-1"></i> Admin Portal</h5>
	</div>
	<nav class="mt-2 d-flex flex-column gap-1">
		<a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
		<a class="nav-link" href="../medical_module/create_senior_profile.php"><i class="bi bi-person-plus-fill"></i> Register Senior</a>
		<a class="nav-link" href="manage_seniors.php"><i class="bi bi-people-fill"></i> Manage Seniors</a>
		<a class="nav-link" href="../medical_module/senior_checkup.php"><i class="bi bi-activity"></i> Senior Checkup</a>
		<a class="nav-link" href="create_staff.php"><i class="bi bi-person-badge-fill"></i> Barangay Staff</a>
		<a class="nav-link" href="manage_accounts.php"><i class="bi bi-shield-lock-fill"></i> Accounts</a>
		<a class="nav-link active" href="announcement.php"><i class="bi bi-megaphone-fill"></i> Announcements</a>
		<a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-fill"></i> Reports</a>
	</nav>
	<div class="sidebar-footer">
		Logged in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?></strong>
	</div>
</aside>

<div class="main-wrap">
	<div class="topbar">
		<div>
			<h5 class="mb-0 fw-bold">Announcement Module</h5>
			<small class="text-muted">Choose what you want to do first</small>
		</div>
		<a href="../auth_module/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
	</div>

	<div class="content-area">
		<div class="row g-3 mb-4">
			<div class="col-md-4">
				<div class="card card-soft p-3">
					<div class="text-muted small">Total Announcements</div>
					<div class="fs-4 fw-bold"><?php echo $counts['total']; ?></div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card card-soft p-3">
					<div class="text-muted small">Active</div>
					<div class="fs-4 fw-bold text-success"><?php echo $counts['active']; ?></div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card card-soft p-3">
					<div class="text-muted small">Inactive</div>
					<div class="fs-4 fw-bold text-secondary"><?php echo $counts['inactive']; ?></div>
				</div>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-md-6 col-xl-3">
				<div class="card card-soft menu-card h-100 p-3">
					<div class="menu-icon bg-primary-subtle text-primary mb-3"><i class="bi bi-plus-circle"></i></div>
					<h6 class="fw-semibold">Create Announcement</h6>
					<p class="text-muted small mb-3">Post a new announcement with optional image.</p>
					<a href="create_announcement.php" class="btn btn-primary btn-sm">Open</a>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card card-soft menu-card h-100 p-3">
					<div class="menu-icon bg-info-subtle text-info mb-3"><i class="bi bi-eye"></i></div>
					<h6 class="fw-semibold">View Announcements</h6>
					<p class="text-muted small mb-3">See announcement history and details.</p>
					<a href="view_announcements.php" class="btn btn-info btn-sm text-white">Open</a>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card card-soft menu-card h-100 p-3">
					<div class="menu-icon bg-warning-subtle text-warning mb-3"><i class="bi bi-pencil-square"></i></div>
					<h6 class="fw-semibold">Edit Announcement</h6>
					<p class="text-muted small mb-3">Select and update an existing announcement.</p>
					<a href="edit_announcement.php" class="btn btn-warning btn-sm">Open</a>
				</div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card card-soft menu-card h-100 p-3">
					<div class="menu-icon bg-danger-subtle text-danger mb-3"><i class="bi bi-trash"></i></div>
					<h6 class="fw-semibold">Delete Announcement</h6>
					<p class="text-muted small mb-3">Remove announcements you no longer need.</p>
					<a href="delete_announcement.php" class="btn btn-danger btn-sm">Open</a>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
