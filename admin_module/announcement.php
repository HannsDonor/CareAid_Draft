<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
	header('Location: ../auth_module/login.php');
	exit;
}

$admin_account_id = (int)($_SESSION['account_id'] ?? 0);
$flash_type = '';
$flash_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_announcement') {
	$title = trim($_POST['title'] ?? '');
	$message = trim($_POST['message'] ?? '');
	$expires_at = trim($_POST['expires_at'] ?? '');
	$status = trim($_POST['status'] ?? 'active');
	$caption = trim($_POST['caption'] ?? '');

	if (!in_array($status, ['active', 'inactive'], true)) {
		$status = 'active';
	}

	if ($title === '' || $message === '') {
		$flash_type = 'danger';
		$flash_msg = 'Title and message are required.';
	} else {
		if ($expires_at === '') {
			$expires_at = null;
		} else {
			$dt = DateTime::createFromFormat('Y-m-d', $expires_at);
			if (!$dt || $dt->format('Y-m-d') !== $expires_at) {
				$flash_type = 'danger';
				$flash_msg = 'Invalid expiration date format.';
			}
		}

		if ($flash_msg === '') {
			$ins = $conn->prepare(
				"INSERT INTO announcements (admin_account_id, title, message, posted_at, expires_at, status)
				 VALUES (?, ?, ?, NOW(), ?, ?)"
			);

			if (!$ins) {
				$flash_type = 'danger';
				$flash_msg = 'Failed to prepare announcement insert.';
			} else {
				$ins->bind_param('issss', $admin_account_id, $title, $message, $expires_at, $status);
				$ok = $ins->execute();
				$announcement_id = (int)$ins->insert_id;
				$ins->close();

				if (!$ok || $announcement_id <= 0) {
					$flash_type = 'danger';
					$flash_msg = 'Failed to save announcement.';
				} else {
					// Optional announcement image upload.
					if (isset($_FILES['picture']) && ($_FILES['picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
						if (($_FILES['picture']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
							$tmp = $_FILES['picture']['tmp_name'];
							$size = (int)($_FILES['picture']['size'] ?? 0);
							$orig = (string)($_FILES['picture']['name'] ?? '');
							$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
							$allowed = ['jpg', 'jpeg', 'png', 'webp'];

							if ($size <= 5 * 1024 * 1024 && in_array($ext, $allowed, true) && @getimagesize($tmp) !== false) {
								$upload_dir = __DIR__ . '/../announcement_pictures';
								if (!is_dir($upload_dir)) {
									@mkdir($upload_dir, 0777, true);
								}

								if (is_dir($upload_dir)) {
									$filename = 'announcement_' . $announcement_id . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
									$target = $upload_dir . DIRECTORY_SEPARATOR . $filename;
									if (move_uploaded_file($tmp, $target)) {
										$pic_path = $filename;
										$pic_ins = $conn->prepare(
											"INSERT INTO announcement_pictures (announcement_id, picture_path, caption, uploaded_at)
											 VALUES (?, ?, ?, NOW())"
										);
										if ($pic_ins) {
											$pic_ins->bind_param('iss', $announcement_id, $pic_path, $caption);
											$pic_ins->execute();
											$pic_ins->close();
										}
									}
								}
							}
						}
					}

					$flash_type = 'success';
					$flash_msg = 'Announcement posted successfully.';
				}
			}
		}
	}
}

$announcements = [];
$list_sql = "SELECT a.announcement_id, a.title, a.message, a.posted_at, a.expires_at, a.status,
					ap.picture_path, ap.caption
			 FROM announcements a
			 LEFT JOIN announcement_pictures ap
			   ON ap.picture_id = (
					SELECT ap2.picture_id
					FROM announcement_pictures ap2
					WHERE ap2.announcement_id = a.announcement_id
					ORDER BY ap2.uploaded_at DESC
					LIMIT 1
			   )
			 ORDER BY a.posted_at DESC";
$res = $conn->query($list_sql);
if ($res) {
	while ($row = $res->fetch_assoc()) {
		$announcements[] = $row;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - CareAid</title>
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
.announcement-pic {
	width: 100%;
	max-height: 210px;
	object-fit: cover;
	border-radius: 10px;
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
			<h5 class="mb-0 fw-bold">Announcements</h5>
			<small class="text-muted">Post updates for senior accounts</small>
		</div>
		<a href="../auth_module/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
	</div>

	<div class="content-area">
		<?php if ($flash_msg !== ''): ?>
			<div class="alert alert-<?php echo htmlspecialchars($flash_type ?: 'info'); ?> alert-dismissible fade show" role="alert">
				<?php echo htmlspecialchars($flash_msg); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<div class="card card-soft mb-4">
			<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-1"></i> Post Announcement</div>
			<div class="card-body">
				<form method="POST" enctype="multipart/form-data" class="row g-3">
					<input type="hidden" name="action" value="add_announcement">

					<div class="col-md-8">
						<label class="form-label">Title *</label>
						<input type="text" name="title" class="form-control" maxlength="150" required>
					</div>

					<div class="col-md-4">
						<label class="form-label">Status *</label>
						<select name="status" class="form-select" required>
							<option value="active">Active</option>
							<option value="inactive">Inactive</option>
						</select>
					</div>

					<div class="col-12">
						<label class="form-label">Message *</label>
						<textarea name="message" class="form-control" rows="4" required></textarea>
					</div>

					<div class="col-md-4">
						<label class="form-label">Expires At</label>
						<input type="date" name="expires_at" class="form-control">
					</div>

					<div class="col-md-4">
						<label class="form-label">Picture</label>
						<input type="file" name="picture" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
					</div>

					<div class="col-md-4">
						<label class="form-label">Picture Caption</label>
						<input type="text" name="caption" class="form-control" maxlength="255">
					</div>

					<div class="col-12 d-flex justify-content-end">
						<button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Publish Announcement</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card card-soft">
			<div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
				<span><i class="bi bi-megaphone me-1"></i> Published Announcements</span>
				<span class="badge bg-secondary"><?php echo count($announcements); ?></span>
			</div>
			<div class="card-body">
				<?php if (empty($announcements)): ?>
					<div class="text-center text-muted py-4">
						<i class="bi bi-inbox fs-2 d-block mb-2"></i>
						No announcements posted yet.
					</div>
				<?php else: ?>
					<div class="row g-3">
						<?php foreach ($announcements as $a): ?>
							<?php
								$pic_name = trim((string)($a['picture_path'] ?? ''));
								$pic_url = $pic_name !== '' ? '../announcement_pictures/' . basename(str_replace('\\', '/', $pic_name)) : '';
								$is_active = ((string)($a['status'] ?? 'inactive') === 'active');
							?>
							<div class="col-md-6 col-xl-4">
								<div class="border rounded p-3 h-100 bg-white">
									<div class="d-flex align-items-center justify-content-between mb-2">
										<span class="badge <?php echo $is_active ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span>
										<small class="text-muted"><?php echo date('M d, Y h:i A', strtotime((string)$a['posted_at'])); ?></small>
									</div>
									<h6 class="fw-semibold mb-2"><?php echo htmlspecialchars((string)($a['title'] ?? 'Untitled')); ?></h6>
									<p class="text-muted small mb-2"><?php echo nl2br(htmlspecialchars((string)($a['message'] ?? ''))); ?></p>
									<p class="small mb-2"><strong>Expires:</strong> <?php echo htmlspecialchars((string)($a['expires_at'] ?? 'No expiry')); ?></p>
									<?php if ($pic_url !== ''): ?>
										<img src="<?php echo htmlspecialchars($pic_url); ?>" class="announcement-pic mb-2" alt="Announcement image">
										<?php if (trim((string)($a['caption'] ?? '')) !== ''): ?>
											<div class="small text-muted"><?php echo htmlspecialchars((string)$a['caption']); ?></div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
