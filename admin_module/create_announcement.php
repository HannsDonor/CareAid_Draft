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

$form_title = '';
$form_message = '';
$form_status = 'active';
$form_expires_at = '';
$form_caption = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$form_title = trim($_POST['title'] ?? '');
	$form_message = trim($_POST['message'] ?? '');
	$form_status = trim($_POST['status'] ?? 'active');
	$form_expires_at = trim($_POST['expires_at'] ?? '');
	$form_caption = trim($_POST['caption'] ?? '');

	if (!in_array($form_status, ['active', 'inactive'], true)) {
		$form_status = 'active';
	}

	if ($form_title === '' || $form_message === '') {
		$flash_type = 'danger';
		$flash_msg = 'Title and message are required.';
	} else {
		$expires_db = null;
		if ($form_expires_at !== '') {
			$dt = DateTime::createFromFormat('Y-m-d', $form_expires_at);
			if (!$dt || $dt->format('Y-m-d') !== $form_expires_at) {
				$flash_type = 'danger';
				$flash_msg = 'Invalid expiration date format.';
			} else {
				$expires_db = $form_expires_at;
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
				$ins->bind_param('issss', $admin_account_id, $form_title, $form_message, $expires_db, $form_status);
				$ok = $ins->execute();
				$announcement_id = (int)$ins->insert_id;
				$ins->close();

				if (!$ok || $announcement_id <= 0) {
					$flash_type = 'danger';
					$flash_msg = 'Failed to save announcement.';
				} else {
					$upload_count = 0;
					$failed_count = 0;
					$allowed = ['jpg', 'jpeg', 'png', 'webp'];
					$upload_dir = __DIR__ . '/../announcement_pictures';

					if (!is_dir($upload_dir)) {
						@mkdir($upload_dir, 0777, true);
					}

					if (isset($_FILES['pictures']) && is_array($_FILES['pictures']['name'] ?? null)) {
						$total_files = count($_FILES['pictures']['name']);
						for ($i = 0; $i < $total_files; $i++) {
							$error = (int)($_FILES['pictures']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
							if ($error === UPLOAD_ERR_NO_FILE) {
								continue;
							}

							if ($error !== UPLOAD_ERR_OK) {
								$failed_count++;
								continue;
							}

							$tmp = (string)($_FILES['pictures']['tmp_name'][$i] ?? '');
							$size = (int)($_FILES['pictures']['size'][$i] ?? 0);
							$orig = (string)($_FILES['pictures']['name'][$i] ?? '');
							$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

							if ($tmp === '' || $size <= 0 || $size > 5 * 1024 * 1024) {
								$failed_count++;
								continue;
							}
							if (!in_array($ext, $allowed, true) || @getimagesize($tmp) === false) {
								$failed_count++;
								continue;
							}
							if (!is_dir($upload_dir)) {
								$failed_count++;
								continue;
							}

							$filename = 'announcement_' . $announcement_id . '_' . time() . '_' . $i . '_' . mt_rand(1000, 9999) . '.' . $ext;
							$target = $upload_dir . DIRECTORY_SEPARATOR . $filename;

							if (!move_uploaded_file($tmp, $target)) {
								$failed_count++;
								continue;
							}

							$pic_ins = $conn->prepare(
								"INSERT INTO announcement_pictures (announcement_id, picture_path, caption, uploaded_at)
								 VALUES (?, ?, ?, NOW())"
							);
							if ($pic_ins) {
								$pic_path = $filename;
								$pic_ins->bind_param('iss', $announcement_id, $pic_path, $form_caption);
								if ($pic_ins->execute()) {
									$upload_count++;
								} else {
									$failed_count++;
								}
								$pic_ins->close();
							} else {
								$failed_count++;
							}
						}
					}

					$flash_type = 'success';
					$flash_msg = 'Announcement posted successfully.';
					if ($upload_count > 0) {
						$flash_msg .= ' Uploaded images: ' . $upload_count . '.';
					}
					if ($failed_count > 0) {
						$flash_msg .= ' Some images were skipped: ' . $failed_count . '.';
					}

					$form_title = '';
					$form_message = '';
					$form_status = 'active';
					$form_expires_at = '';
					$form_caption = '';
				}
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Announcement - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700&display=swap" rel="stylesheet">
<style>
:root {
	--brand-blue: #2d3a8c;
	--brand-violet: #764ba2;
	--surface: #f4f6fb;
	--text-main: #1e2247;
	--text-soft: #5f668f;
	--card-shadow: 0 14px 34px rgba(31, 45, 93, 0.08);
}

body {
	font-family: 'Manrope', sans-serif;
	background:
		radial-gradient(circle at 10% 10%, rgba(45, 58, 140, 0.08), transparent 28%),
		radial-gradient(circle at 90% 90%, rgba(118, 75, 162, 0.08), transparent 32%),
		var(--surface);
	color: var(--text-main);
}

.sidebar {
	width: 240px;
	min-height: 100vh;
	background: linear-gradient(160deg, #2d3a8c 0%, #764ba2 100%);
	color: #fff;
	position: fixed;
	top: 0;
	left: 0;
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
}
.topbar {
	background: rgba(255, 255, 255, 0.85);
	backdrop-filter: blur(10px);
	padding: 14px 28px;
	border-bottom: 1px solid #e3e6ef;
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.content-area { padding: 28px; }
.card-soft {
	border: none;
	border-radius: 20px;
	box-shadow: var(--card-shadow);
}
.page-hero {
	background: linear-gradient(135deg, rgba(45,58,140,.96) 0%, rgba(118,75,162,.94) 100%);
	color: #fff;
	border-radius: 20px;
	padding: 22px 24px;
	position: relative;
	overflow: hidden;
	margin-bottom: 18px;
}
.page-hero::after {
	content: '';
	position: absolute;
	top: -45px;
	right: -45px;
	width: 170px;
	height: 170px;
	background: rgba(255,255,255,.12);
	border-radius: 50%;
}
.page-hero h5 {
	font-family: 'Plus Jakarta Sans', sans-serif;
	font-weight: 700;
	letter-spacing: .2px;
	margin-bottom: 6px;
}
.page-hero p {
	margin: 0;
	opacity: .9;
}
.section-title {
	font-family: 'Plus Jakarta Sans', sans-serif;
	font-weight: 700;
	color: var(--text-main);
	letter-spacing: .2px;
}
.section-sub {
	color: var(--text-soft);
	font-size: .9rem;
}
.editor-card .card-body {
	padding: 24px;
}
.preview-card .card-body {
	padding: 18px;
}
.input-label {
	font-size: .82rem;
	font-weight: 700;
	color: #37407a;
	text-transform: uppercase;
	letter-spacing: .3px;
	margin-bottom: 6px;
}
.form-control,
.form-select {
	border-radius: 12px;
	padding: .62rem .8rem;
	border-color: #d9deef;
	font-size: .95rem;
}
.form-control:focus,
.form-select:focus {
	box-shadow: 0 0 0 .18rem rgba(45,58,140,.15);
	border-color: #9aa8df;
}
textarea.form-control {
	min-height: 150px;
}
.image-preview {
	width: 100%;
	height: 260px;
	object-fit: cover;
	border-radius: 10px;
	border: 1px solid #dee2e6;
	display: block;
}
.preview-wrap {
	border: 1px dashed #c8d0ea;
	border-radius: 14px;
	padding: 12px;
	background: linear-gradient(180deg, #ffffff 0%, #f6f8ff 100%);
}
.preview-toolbar {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	margin-bottom: 10px;
}
.upload-plus-btn {
	width: 42px;
	height: 42px;
	border-radius: 50%;
	border: 1px solid #b9c5ef;
	background: #ffffff;
	color: #2d3a8c;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 1.35rem;
	font-weight: 700;
	line-height: 1;
	cursor: pointer;
	box-shadow: 0 6px 16px rgba(45, 58, 140, 0.16);
	transition: transform .16s ease, box-shadow .16s ease;
	z-index: 2;
}
.upload-plus-btn:hover {
	transform: translateY(-1px) scale(1.04);
	box-shadow: 0 10px 20px rgba(45, 58, 140, 0.2);
}
.preview-empty {
	height: 260px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 10px;
	border: 1px solid #e5e9f7;
	color: #6c757d;
	background: #fbfcff;
}
.preview-controls {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-top: 10px;
}
.preview-controls .btn {
	min-width: 90px;
	border-radius: 10px;
}
.sticky-panel {
	position: sticky;
	top: 90px;
}
.btn {
	border-radius: 10px;
}
.btn-primary {
	background: linear-gradient(135deg, #2d3a8c 0%, #4858bb 100%);
	border: none;
}
.btn-primary:hover {
	background: linear-gradient(135deg, #25327e 0%, #3d4ea9 100%);
}

@media (max-width: 991.98px) {
	.main-wrap {
		margin-left: 0;
	}
	.sidebar {
		position: static;
		width: 100%;
		min-height: auto;
	}
	.sticky-panel {
		position: static;
	}
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
			<h5 class="mb-0 fw-bold">Create Announcement</h5>
			<small class="text-muted">Input announcement details and optional image</small>
		</div>
		<div class="d-flex gap-2">
			<a href="announcement.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Menu</a>
			<a href="../auth_module/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
		</div>
	</div>

	<div class="content-area">
		<div class="page-hero">
			<h5><i class="bi bi-megaphone-fill me-2"></i>Announcement Composer</h5>
			<p>Create clear barangay updates with one or more supporting photos, then publish in one step.</p>
		</div>

		<?php if ($flash_msg !== ''): ?>
			<div class="alert alert-<?php echo htmlspecialchars($flash_type ?: 'info'); ?> alert-dismissible fade show" role="alert">
				<?php echo htmlspecialchars($flash_msg); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<form method="POST" enctype="multipart/form-data" class="row g-3">
			<div class="col-lg-7">
				<div class="card card-soft editor-card h-100">
					<div class="card-body">
						<div class="mb-3">
							<div class="section-title">Announcement Details</div>
							<div class="section-sub">Write the announcement information shown to seniors and staff.</div>
						</div>
						<div class="row g-3">
							<div class="col-md-8">
								<label for="title" class="input-label">Title *</label>
						<input type="text" id="title" name="title" class="form-control" maxlength="150" value="<?php echo htmlspecialchars($form_title); ?>" required>
							</div>

							<div class="col-md-4">
								<label for="status" class="input-label">Status *</label>
						<select id="status" name="status" class="form-select" required>
							<option value="active" <?php echo $form_status === 'active' ? 'selected' : ''; ?>>Active</option>
							<option value="inactive" <?php echo $form_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
						</select>
							</div>

							<div class="col-12">
								<label for="message" class="input-label">Message *</label>
						<textarea id="message" name="message" class="form-control" rows="5" required><?php echo htmlspecialchars($form_message); ?></textarea>
							</div>

							<div class="col-md-5">
								<label for="expires_at" class="input-label">Expires At</label>
						<input type="date" id="expires_at" name="expires_at" class="form-control" value="<?php echo htmlspecialchars($form_expires_at); ?>">
							</div>

							<div class="col-md-7">
								<label for="caption" class="input-label">Image Caption</label>
								<input type="text" id="caption" name="caption" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($form_caption); ?>" placeholder="Optional text for all uploaded images">
							</div>

							<div class="col-12">
								<label for="pictures" class="input-label">Images</label>
								<input type="file" id="pictures" name="pictures[]" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/*" multiple>
								<div class="form-text mt-0">Use the <strong>+</strong> button in the slideshow panel to add one or more images. Max 5MB each.</div>
							</div>
						</div>

						<div class="d-flex justify-content-end gap-2 mt-4">
							<button type="reset" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i> Clear</button>
							<button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i> Publish Announcement</button>
						</div>
					</div>
				</div>
			</div>

			<div class="col-lg-5">
				<div class="card card-soft preview-card sticky-panel">
					<div class="card-body">
						<div class="mb-3">
							<div class="section-title">Live Image Slideshow</div>
							<div class="section-sub">Preview selected photos before publishing.</div>
						</div>
						<div class="preview-wrap">
							<div class="preview-toolbar">
								<label for="pictures" class="upload-plus-btn" title="Add images">+</label>
							</div>
							<div id="previewEmpty" class="preview-empty">
								<div class="text-center">
									<i class="bi bi-images fs-3 d-block mb-1"></i>
									Select one or more images to preview slideshow.
								</div>
							</div>
							<img id="imagePreview" class="image-preview d-none" alt="Selected image preview">
							<div class="preview-controls">
								<button type="button" id="prevSlide" class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Prev</button>
								<div class="small text-muted" id="slideCounter">No image selected</div>
								<button type="button" id="nextSlide" class="btn btn-outline-secondary btn-sm" disabled>Next <i class="bi bi-chevron-right"></i></button>
							</div>
							<div class="small text-muted mt-2" id="selectedFileCount">Selected files: 0</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const pictureInput = document.getElementById('pictures');
const imagePreview = document.getElementById('imagePreview');
const previewEmpty = document.getElementById('previewEmpty');
const prevSlideBtn = document.getElementById('prevSlide');
const nextSlideBtn = document.getElementById('nextSlide');
const slideCounter = document.getElementById('slideCounter');
const selectedFileCount = document.getElementById('selectedFileCount');

let previewUrls = [];
let selectedFiles = [];
let currentIndex = 0;
let slideTimer = null;

function clearPreviewUrls() {
	for (let i = 0; i < previewUrls.length; i++) {
		URL.revokeObjectURL(previewUrls[i]);
	}
	previewUrls = [];
}

function stopSlideshow() {
	if (slideTimer) {
		window.clearInterval(slideTimer);
		slideTimer = null;
	}
}

function updatePreview() {
	selectedFileCount.textContent = 'Selected files: ' + selectedFiles.length;

	if (previewUrls.length === 0) {
		imagePreview.classList.add('d-none');
		imagePreview.removeAttribute('src');
		previewEmpty.classList.remove('d-none');
		slideCounter.textContent = 'No image selected';
		prevSlideBtn.disabled = true;
		nextSlideBtn.disabled = true;
		stopSlideshow();
		return;
	}

	if (currentIndex < 0) {
		currentIndex = previewUrls.length - 1;
	}
	if (currentIndex >= previewUrls.length) {
		currentIndex = 0;
	}

	previewEmpty.classList.add('d-none');
	imagePreview.classList.remove('d-none');
	imagePreview.src = previewUrls[currentIndex];
	slideCounter.textContent = (currentIndex + 1) + ' / ' + previewUrls.length;
	prevSlideBtn.disabled = previewUrls.length <= 1;
	nextSlideBtn.disabled = previewUrls.length <= 1;
}

function startSlideshow() {
	stopSlideshow();
	if (previewUrls.length <= 1) {
		return;
	}
	slideTimer = window.setInterval(function () {
		currentIndex++;
		updatePreview();
	}, 3000);
}

function syncInputFiles() {
	if (typeof DataTransfer === 'undefined') {
		return;
	}
	const dt = new DataTransfer();
	for (let i = 0; i < selectedFiles.length; i++) {
		dt.items.add(selectedFiles[i]);
	}
	pictureInput.files = dt.files;
}

pictureInput.addEventListener('change', function () {
	stopSlideshow();

	const files = this.files ? Array.from(this.files) : [];
	for (let i = 0; i < files.length; i++) {
		const file = files[i];
		if (file && file.type && file.type.indexOf('image/') === 0) {
			selectedFiles.push(file);
			previewUrls.push(URL.createObjectURL(file));
		}
	}

	syncInputFiles();
	if (previewUrls.length > 0 && currentIndex >= previewUrls.length) {
		currentIndex = 0;
	}

	updatePreview();
	startSlideshow();
});

prevSlideBtn.addEventListener('click', function () {
	if (previewUrls.length <= 1) {
		return;
	}
	currentIndex--;
	updatePreview();
	startSlideshow();
});

nextSlideBtn.addEventListener('click', function () {
	if (previewUrls.length <= 1) {
		return;
	}
	currentIndex++;
	updatePreview();
	startSlideshow();
});

document.querySelector('form').addEventListener('reset', function () {
	window.setTimeout(function () {
		stopSlideshow();
		clearPreviewUrls();
		selectedFiles = [];
		currentIndex = 0;
		syncInputFiles();
		updatePreview();
	}, 0);
});

window.addEventListener('beforeunload', function () {
	stopSlideshow();
	clearPreviewUrls();
});
</script>
</body>
</html>
