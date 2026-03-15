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
.fab-wrap {
	position: fixed;
	right: 18px;
	bottom: 18px;
	z-index: 1040;
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 10px;
}
.fab-actions {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 9px;
	pointer-events: none;
}
.fab-action {
	border: 0;
	border-radius: 999px;
	padding: 9px 13px;
	width: 156px;
	color: #fff;
	font-weight: 600;
	font-size: .82rem;
	box-shadow: 0 10px 22px rgba(23, 48, 66, 0.2);
	display: inline-flex;
	align-items: center;
	justify-content: flex-start;
	gap: 7px;
	transform: translateY(10px) scale(.92);
	opacity: 0;
	visibility: hidden;
	transition: transform .18s ease, opacity .18s ease, visibility .18s ease;
}
.fab-action-assist {
	background: linear-gradient(135deg, #c96a2f, #e49b47);
}
.fab-action-guide {
	background: linear-gradient(135deg, var(--brand-blue), var(--brand-mint));
}
.fab-action-settings {
	background: linear-gradient(135deg, #6c757d, #495057);
}
.fab-main {
	border: 0;
	border-radius: 50%;
	width: 54px;
	height: 54px;
	background: linear-gradient(135deg, #1f5d8a, #2b9e8f);
	color: #fff;
	font-size: 1.32rem;
	box-shadow: 0 14px 30px rgba(23, 48, 66, 0.24);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	transition: transform .18s ease, box-shadow .18s ease;
}
.fab-main:hover {
	transform: translateY(-2px);
	box-shadow: 0 18px 34px rgba(23, 48, 66, 0.28);
}
.fab-wrap.is-open .fab-action {
	transform: translateY(0) scale(1);
	opacity: 1;
	visibility: visible;
	pointer-events: auto;
}
.fab-wrap.is-open .fab-main {
	transform: rotate(45deg);
}
.quick-guide-modal .modal-content {
	border: 0;
	border-radius: 18px;
	overflow: hidden;
	box-shadow: 0 18px 42px rgba(18, 38, 56, 0.2);
}
.quick-guide-modal .modal-header {
	background: linear-gradient(135deg, var(--brand-blue), var(--brand-mint));
	color: #fff;
	border-bottom: 0;
}
.quick-guide-modal .btn-close {
	filter: invert(1);
}
.quick-guide-frame {
	display: block;
	width: 100%;
	height: min(70vh, 620px);
	border: 0;
	background: #fff;
}

@media (max-width: 768px) {
	.hero-grid { grid-template-columns: 1fr; text-align: center; }
	.avatar, .avatar-fallback { margin: 0 auto; }
	.fab-wrap {
		right: 12px;
		bottom: 12px;
	}
	.fab-action {
		width: 148px;
		font-size: .78rem;
		padding: 8px 12px;
	}
}
</style>
</head>
<body>
<?php $snav_show_bottom = false; include 'senior_nav.php'; ?>
<div class="dash-wrap">

	<?php if (!$senior): ?>
	<div class="card card-soft mt-4">
		<div class="card-body text-center py-5">
			<i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
			<h5 class="fw-semibold" data-i18n="profileNotFoundTitle">Senior Profile Not Found</h5>
			<p class="text-muted mb-0" data-i18n="profileNotFoundBody">Your account is active, but no linked senior profile is available yet. Please contact a health worker.</p>
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
				<small class="opacity-75" data-i18n="welcomeSummary">Welcome back. This page summarizes your profile details and latest health checkups.</small>
			</div>
		</div>
	</section>

	<div class="row g-3 mt-1">
		<div class="col-12">
			<div class="card card-soft h-100">
				<div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
					<span><i class="bi bi-megaphone-fill text-danger me-1"></i><span data-i18n="barangayAnnouncements">Barangay Announcements</span></span>
					<span class="badge bg-secondary"><?php echo count($active_announcements); ?></span>
				</div>
				<div class="card-body">
					<?php if (empty($active_announcements)): ?>
						<div class="text-center text-muted py-5">
							<i class="bi bi-inbox fs-2 d-block mb-2"></i>
							<span data-i18n="noActiveAnnouncements">No active announcements at the moment.</span>
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
									<small class="text-muted"><span data-i18n="expiresPrefix">Expires:</span> <?php echo htmlspecialchars((string)($ann['expires_at'] ?? 'No expiry')); ?></small>
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

<div class="fab-wrap" id="quickFabWrap">
	<div class="fab-actions">
		<button type="button" class="fab-action fab-action-assist" data-bs-toggle="modal" data-bs-target="#requestAssistanceModal" aria-label="Request Assistance">
			<i class="bi bi-life-preserver"></i>
			<span data-i18n="fabAssistance">Assistance</span>
		</button>
		<button type="button" class="fab-action fab-action-guide" data-bs-toggle="modal" data-bs-target="#quickGuidanceModal" aria-label="Quick Check Up">
			<i class="bi bi-heart-pulse-fill"></i>
			<span data-i18n="fabQuickCheck">Quick Check</span>
		</button>
		<button type="button" class="fab-action fab-action-settings" data-bs-toggle="modal" data-bs-target="#displaySettingsModal" aria-label="Settings">
			<i class="bi bi-gear-fill"></i>
			<span data-i18n="fabSettings">Settings</span>
		</button>
	</div>
	<button type="button" class="fab-main" id="quickFabMain" aria-label="Open quick actions" aria-expanded="false" aria-controls="quickFabWrap">
		<i class="bi bi-plus-lg"></i>
	</button>
</div>

<div class="modal fade quick-guide-modal" id="requestAssistanceModal" tabindex="-1" aria-labelledby="requestAssistanceModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-md">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title fw-bold" id="requestAssistanceModalLabel" data-i18n="requestAssistanceTitle">Request Assistance</h5>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0">
				<iframe src="assistance_request.php" title="Request assistance" class="quick-guide-frame"></iframe>
			</div>
		</div>
	</div>
</div>

<div class="modal fade quick-guide-modal" id="quickGuidanceModal" tabindex="-1" aria-labelledby="quickGuidanceModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title fw-bold" id="quickGuidanceModalLabel" data-i18n="quickCheckTitle">Quick Check Up</h5>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0">
				<iframe src="quick_guidance.php" title="Quick check up" class="quick-guide-frame"></iframe>
			</div>
		</div>
	</div>
</div>

<div class="modal fade quick-guide-modal" id="displaySettingsModal" tabindex="-1" aria-labelledby="displaySettingsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title fw-bold" id="displaySettingsModalLabel" data-i18n="settingsTitle">Settings</h5>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label for="fontSizeRange" class="form-label fw-semibold" data-i18n="fontSizeLabel">Text size</label>
					<input type="range" class="form-range" min="90" max="125" step="5" value="100" id="fontSizeRange">
					<div class="small text-muted"><span data-i18n="fontSizeCurrent">Current:</span> <span id="fontSizeValue">100%</span></div>
				</div>
				<div>
					<label for="languageSelect" class="form-label fw-semibold" data-i18n="languageLabel">Language</label>
					<select id="languageSelect" class="form-select">
						<option value="en">English</option>
						<option value="tl">Tagalog</option>
					</select>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
const quickFabWrap = document.getElementById('quickFabWrap');
const quickFabMain = document.getElementById('quickFabMain');
const fontSizeRange = document.getElementById('fontSizeRange');
const fontSizeValue = document.getElementById('fontSizeValue');
const languageSelect = document.getElementById('languageSelect');

const i18nMap = {
	en: {
		profileNotFoundTitle: 'Senior Profile Not Found',
		profileNotFoundBody: 'Your account is active, but no linked senior profile is available yet. Please contact a health worker.',
		welcomeSummary: 'Welcome back. This page summarizes your profile details and latest health checkups.',
		barangayAnnouncements: 'Barangay Announcements',
		noActiveAnnouncements: 'No active announcements at the moment.',
		expiresPrefix: 'Expires:',
		fabAssistance: 'Assistance',
		fabQuickCheck: 'Quick Check',
		fabSettings: 'Settings',
		requestAssistanceTitle: 'Request Assistance',
		quickCheckTitle: 'Quick Check Up',
		settingsTitle: 'Settings',
		fontSizeLabel: 'Text size',
		fontSizeCurrent: 'Current:',
		languageLabel: 'Language'
	},
	tl: {
		profileNotFoundTitle: 'Walang Nakitang Senior Profile',
		profileNotFoundBody: 'Aktibo ang iyong account ngunit wala pang nakalink na senior profile. Makipag-ugnayan sa health worker.',
		welcomeSummary: 'Maligayang pagbabalik. Ipinapakita sa pahinang ito ang buod ng iyong profile at pinakabagong checkup.',
		barangayAnnouncements: 'Mga Anunsyo ng Barangay',
		noActiveAnnouncements: 'Wala pang aktibong anunsyo sa ngayon.',
		expiresPrefix: 'Magtatapos:',
		fabAssistance: 'Tulong',
		fabQuickCheck: 'Mabilis na Check',
		fabSettings: 'Settings',
		requestAssistanceTitle: 'Humiling ng Tulong',
		quickCheckTitle: 'Mabilis na Check Up',
		settingsTitle: 'Settings',
		fontSizeLabel: 'Laki ng Teksto',
		fontSizeCurrent: 'Kasalukuyan:',
		languageLabel: 'Wika'
	}
};

function applyFontScale(scale) {
	const safeScale = Number(scale) || 100;
	document.documentElement.style.fontSize = safeScale + '%';
	if (fontSizeValue) {
		fontSizeValue.textContent = safeScale + '%';
	}
	if (fontSizeRange && String(fontSizeRange.value) !== String(safeScale)) {
		fontSizeRange.value = String(safeScale);
	}
	localStorage.setItem('seniorFontScale', String(safeScale));
}

function applyLanguage(lang) {
	const safeLang = i18nMap[lang] ? lang : 'en';
	const dict = i18nMap[safeLang];
	document.querySelectorAll('[data-i18n]').forEach(function (element) {
		const key = element.getAttribute('data-i18n');
		if (key && Object.prototype.hasOwnProperty.call(dict, key)) {
			element.textContent = dict[key];
		}
	});
	if (languageSelect) {
		languageSelect.value = safeLang;
	}
	localStorage.setItem('seniorLanguage', safeLang);
}

if (quickFabWrap && quickFabMain) {
	quickFabMain.addEventListener('click', function () {
		const isOpen = quickFabWrap.classList.toggle('is-open');
		quickFabMain.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});

	document.addEventListener('click', function (event) {
		if (!quickFabWrap.contains(event.target)) {
			quickFabWrap.classList.remove('is-open');
			quickFabMain.setAttribute('aria-expanded', 'false');
		}
	});

	quickFabWrap.querySelectorAll('.fab-action').forEach(function (action) {
		action.addEventListener('click', function () {
			quickFabWrap.classList.remove('is-open');
			quickFabMain.setAttribute('aria-expanded', 'false');
		});
	});
}

if (fontSizeRange) {
	fontSizeRange.addEventListener('input', function () {
		applyFontScale(fontSizeRange.value);
	});
}

if (languageSelect) {
	languageSelect.addEventListener('change', function () {
		applyLanguage(languageSelect.value);
	});
}

const savedScale = localStorage.getItem('seniorFontScale') || '100';
const savedLang = localStorage.getItem('seniorLanguage') || 'en';
applyFontScale(savedScale);
applyLanguage(savedLang);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
