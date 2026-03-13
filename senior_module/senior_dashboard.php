<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
	header('Location: ../auth_module/login.php');
	exit;
}

$account_id = (int)($_SESSION['account_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? 'Senior');
$role = (string)($_SESSION['role'] ?? '');

if ($role !== 'senior') {
	header('Location: ../auth_module/login.php');
	exit;
}

$senior = null;
$checkups = [];
$latest_checkup = null;
$health_record_count = 0;
$active_announcements = [];

$profile_stmt = $conn->prepare(
	"SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, contact_number, emergency_contact, address, profile_path, is_alive, priority_level
	 FROM senior_profiles
	 WHERE account_id = ?
	 LIMIT 1"
);

if ($profile_stmt) {
	$profile_stmt->bind_param('i', $account_id);
	$profile_stmt->execute();
	$senior = $profile_stmt->get_result()->fetch_assoc();
	$profile_stmt->close();
}

if ($senior) {
	$senior_id = (int)$senior['senior_id'];

	$latest_stmt = $conn->prepare(
		"SELECT checkup_date, blood_pressure, blood_sugar, heart_rate, risk_level, notes
		 FROM checkups
		 WHERE senior_id = ?
		 ORDER BY checkup_date DESC
		 LIMIT 1"
	);
	if ($latest_stmt) {
		$latest_stmt->bind_param('i', $senior_id);
		$latest_stmt->execute();
		$latest_checkup = $latest_stmt->get_result()->fetch_assoc();
		$latest_stmt->close();
	}

	$checkup_stmt = $conn->prepare(
		"SELECT checkup_date, blood_pressure, blood_sugar, heart_rate, risk_level, notes
		 FROM checkups
		 WHERE senior_id = ?
		 ORDER BY checkup_date DESC
		 LIMIT 8"
	);
	if ($checkup_stmt) {
		$checkup_stmt->bind_param('i', $senior_id);
		$checkup_stmt->execute();
		$res = $checkup_stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$checkups[] = $row;
		}
		$checkup_stmt->close();
	}

	$hr_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM health_records WHERE senior_id = ?");
	if ($hr_stmt) {
		$hr_stmt->bind_param('i', $senior_id);
		$hr_stmt->execute();
		$health_record_count = (int)($hr_stmt->get_result()->fetch_assoc()['c'] ?? 0);
		$hr_stmt->close();
	}
}

$ann_sql = "SELECT a.announcement_id, a.title, a.message, a.posted_at, a.expires_at,
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
						WHERE a.status = 'active'
							AND (a.expires_at IS NULL OR a.expires_at = '0000-00-00' OR a.expires_at >= CURDATE())
						ORDER BY a.posted_at DESC
						LIMIT 5";
$ann_res = $conn->query($ann_sql);
if ($ann_res) {
		while ($row = $ann_res->fetch_assoc()) {
				$active_announcements[] = $row;
		}
}

function priority_badge(int $level): string {
	if ($level >= 5) return '<span class="badge bg-danger">Priority 5</span>';
	if ($level === 4) return '<span class="badge bg-warning text-dark">Priority 4</span>';
	if ($level === 3) return '<span class="badge bg-info text-dark">Priority 3</span>';
	if ($level === 2) return '<span class="badge bg-secondary">Priority 2</span>';
	return '<span class="badge bg-success">Priority 1</span>';
}

function risk_badge(string $risk): string {
	if ($risk === 'Critical' || $risk === 'High') {
		return '<span class="badge bg-danger">' . htmlspecialchars($risk) . '</span>';
	}
	if ($risk === 'Moderate') {
		return '<span class="badge bg-warning text-dark">Moderate</span>';
	}
	if ($risk === 'Low') {
		return '<span class="badge bg-success">Low</span>';
	}
	return '<span class="badge bg-secondary">N/A</span>';
}

function age_from_birth(?string $birth): string {
	if (!$birth) return 'N/A';
	try {
		return (string)((new DateTime($birth))->diff(new DateTime())->y);
	} catch (Throwable $e) {
		return 'N/A';
	}
}

function profile_img_path(array $senior): string {
	$raw = trim((string)($senior['profile_path'] ?? ''));
	if ($raw === '') return '';
	$name = basename(str_replace('\\', '/', $raw));
	$abs = __DIR__ . '/../senior_profile_pics/' . $name;
	if (!is_file($abs)) return '';
	return '../senior_profile_pics/' . $name;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Senior Dashboard - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
:root {
	--brand-blue: #1f5d8a;
	--brand-mint: #2b9e8f;
	--brand-sand: #f4efe6;
	--text-deep: #173042;
}
body {
	margin: 0;
	min-height: 100vh;
	font-family: "Segoe UI", Tahoma, sans-serif;
	background:
		radial-gradient(circle at 10% 20%, rgba(43, 158, 143, 0.12) 0, transparent 45%),
		radial-gradient(circle at 90% 15%, rgba(31, 93, 138, 0.12) 0, transparent 42%),
		linear-gradient(180deg, #f8fafc 0%, var(--brand-sand) 100%);
	color: var(--text-deep);
}
.dash-wrap { max-width: 1180px; margin: 0 auto; padding: 22px 18px 32px; }
.topbar {
	background: #fff;
	border-radius: 16px;
	padding: 14px 18px;
	box-shadow: 0 10px 30px rgba(18, 38, 56, 0.08);
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 14px;
}
.hero {
	margin-top: 16px;
	background: linear-gradient(135deg, var(--brand-blue), var(--brand-mint));
	color: #fff;
	border-radius: 18px;
	padding: 20px;
	box-shadow: 0 16px 28px rgba(25, 65, 96, 0.25);
}
.hero-grid {
	display: grid;
	grid-template-columns: 92px 1fr;
	gap: 16px;
	align-items: center;
}
.avatar {
	width: 92px;
	height: 92px;
	border-radius: 50%;
	object-fit: cover;
	border: 3px solid rgba(255,255,255,.75);
}
.avatar-fallback {
	width: 92px;
	height: 92px;
	border-radius: 50%;
	border: 3px solid rgba(255,255,255,.75);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 40px;
	background: rgba(255,255,255,.18);
}
.card-soft {
	border: 0;
	border-radius: 16px;
	box-shadow: 0 10px 26px rgba(18, 38, 56, 0.08);
}
.stat-icon {
	width: 42px;
	height: 42px;
	border-radius: 11px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 1.2rem;
}
.data-label { color: #5f7283; font-size: .8rem; }
.table thead th { white-space: nowrap; font-size: .82rem; }
.table tbody td { font-size: .88rem; vertical-align: middle; }

@media (max-width: 768px) {
	.hero-grid { grid-template-columns: 1fr; text-align: center; }
	.avatar, .avatar-fallback { margin: 0 auto; }
}
</style>
</head>
<body>
<div class="dash-wrap">
	<div class="topbar">
		<div>
			<h5 class="mb-0 fw-bold">Senior Dashboard</h5>
			<small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
		</div>
		<div class="d-flex align-items-center gap-2">
			<span class="badge text-bg-light border"><i class="bi bi-person-check me-1"></i><?php echo htmlspecialchars($username); ?></span>
			<a href="../auth_module/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
		</div>
	</div>

	<?php if (!$senior): ?>
	<div class="card card-soft mt-4">
		<div class="card-body text-center py-5">
			<i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
			<h5 class="fw-semibold">Senior Profile Not Found</h5>
			<p class="text-muted mb-0">Your account is active, but no linked senior profile is available yet. Please contact a health worker.</p>
		</div>
	</div>
	<?php else: ?>
	<?php $img = profile_img_path($senior); ?>
	<section class="hero">
		<div class="hero-grid">
			<?php if ($img !== ''): ?>
				<img src="<?php echo htmlspecialchars($img); ?>" class="avatar" alt="Profile">
			<?php else: ?>
				<div class="avatar-fallback"><i class="bi bi-person-fill"></i></div>
			<?php endif; ?>
			<div>
				<h4 class="mb-1 fw-bold"><?php echo htmlspecialchars(trim(($senior['first_name'] ?? '') . ' ' . ($senior['middle_name'] ?? '') . ' ' . ($senior['last_name'] ?? ''))); ?></h4>
				<div class="d-flex flex-wrap gap-2 mb-2">
					<?php echo priority_badge((int)($senior['priority_level'] ?? 1)); ?>
					<span class="badge <?php echo (($senior['is_alive'] ?? 'yes') === 'yes') ? 'bg-success' : 'bg-secondary'; ?>">
						<?php echo (($senior['is_alive'] ?? 'yes') === 'yes') ? 'Active Profile' : 'Inactive Profile'; ?>
					</span>
				</div>
				<small class="opacity-75">Welcome back. This page summarizes your profile details and latest health checkups.</small>
			</div>
		</div>
	</section>

	<div class="row g-3 mt-1">
		<div class="col-lg-4">
			<div class="card card-soft h-100">
				<div class="card-header bg-white fw-semibold">Profile Details</div>
				<div class="card-body">
					<div class="mb-2"><span class="data-label d-block">Age</span><strong><?php echo htmlspecialchars(age_from_birth($senior['birth_date'] ?? null)); ?></strong></div>
					<div class="mb-2"><span class="data-label d-block">Gender</span><strong><?php echo htmlspecialchars((string)($senior['gender'] ?? 'N/A')); ?></strong></div>
					<div class="mb-2"><span class="data-label d-block">Birth Date</span><strong><?php echo htmlspecialchars((string)($senior['birth_date'] ?? 'N/A')); ?></strong></div>
					<div class="mb-2"><span class="data-label d-block">Contact Number</span><strong><?php echo htmlspecialchars((string)($senior['contact_number'] ?? 'N/A')); ?></strong></div>
					<div class="mb-2"><span class="data-label d-block">Emergency Contact</span><strong><?php echo htmlspecialchars((string)($senior['emergency_contact'] ?? 'N/A')); ?></strong></div>
					<div><span class="data-label d-block">Address</span><strong><?php echo htmlspecialchars((string)($senior['address'] ?? 'N/A')); ?></strong></div>
				</div>
			</div>
		</div>

		<div class="col-lg-8">
			<div class="card card-soft h-100">
				<div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
					<span><i class="bi bi-megaphone-fill text-danger me-1"></i>Barangay Announcements</span>
					<span class="badge bg-secondary"><?php echo count($active_announcements); ?></span>
				</div>
				<div class="card-body">
					<?php if (empty($active_announcements)): ?>
						<div class="text-center text-muted py-5">
							<i class="bi bi-inbox fs-2 d-block mb-2"></i>
							No active announcements at the moment.
						</div>
					<?php else: ?>
						<div class="d-flex flex-column gap-3">
							<?php foreach ($active_announcements as $ann): ?>
								<?php
									$ann_pic = trim((string)($ann['picture_path'] ?? ''));
									$ann_pic_url = $ann_pic !== '' ? '../announcement_pictures/' . basename(str_replace('\\', '/', $ann_pic)) : '';
								?>
								<div class="border rounded p-3">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars((string)($ann['title'] ?? 'Announcement')); ?></h6>
										<small class="text-muted"><?php echo date('M d, Y h:i A', strtotime((string)$ann['posted_at'])); ?></small>
									</div>
									<p class="mb-2 text-muted"><?php echo nl2br(htmlspecialchars((string)($ann['message'] ?? ''))); ?></p>
									<?php if ($ann_pic_url !== ''): ?>
										<img src="<?php echo htmlspecialchars($ann_pic_url); ?>" class="img-fluid rounded mb-2" alt="Announcement">
										<?php if (trim((string)($ann['caption'] ?? '')) !== ''): ?>
											<small class="text-muted d-block"><?php echo htmlspecialchars((string)$ann['caption']); ?></small>
										<?php endif; ?>
									<?php endif; ?>
									<small class="text-muted">Expires: <?php echo htmlspecialchars((string)($ann['expires_at'] ?? 'No expiry')); ?></small>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
