<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
	header('Location: ../auth_module/login.php');
	exit;
}

$account_id = (int)($_SESSION['account_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
if ($role !== 'senior') {
	header('Location: ../auth_module/login.php');
	exit;
}

$success_message = '';
$error_message = '';
$senior_id = 0;

$form_assist_type = strtolower(trim((string)($_POST['assist_type'] ?? '')));
$form_medical_mode = trim((string)($_POST['medical_mode'] ?? ''));
$form_sched_date = trim((string)($_POST['sched_date'] ?? ''));
$form_sched_time = trim((string)($_POST['sched_time'] ?? ''));

$profile_stmt = $conn->prepare(
	"SELECT senior_id
	 FROM senior_profiles
	 WHERE account_id = ?
	 LIMIT 1"
);

if ($profile_stmt) {
	$profile_stmt->bind_param('i', $account_id);
	$profile_stmt->execute();
	$profile_stmt->bind_result($resolved_senior_id);
	if ($profile_stmt->fetch()) {
		$senior_id = (int)$resolved_senior_id;
	}
	$profile_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$valid_types = ['medical', 'financial', 'barangay'];
	$is_medical = $form_assist_type === 'medical';
	$sub_type = '';
	$request_date = $form_sched_date;
	$request_time = $form_sched_time !== '' ? $form_sched_time . ':00' : '';

	if ($senior_id <= 0) {
		$error_message = 'Senior profile not found for the logged-in account.';
	} elseif (!in_array($form_assist_type, $valid_types, true)) {
		$error_message = 'Please select a valid assistance type.';
	} elseif ($is_medical && $form_medical_mode === '') {
		$error_message = 'Please select a medical assistance type.';
	} elseif ($is_medical && ($form_sched_date === '' || $form_sched_time === '')) {
		$error_message = 'Please provide your preferred date and time.';
	} else {
		$sub_type = $is_medical ? $form_medical_mode : ucfirst($form_assist_type);

		if (!$is_medical) {
			$request_date = date('Y-m-d');
			$request_time = date('H:i:s');
		}

		$conn->begin_transaction();
		$insert_stmt = $conn->prepare(
			"INSERT INTO assistance_requests (senior_id, request_type, sub_type, status, request_date, request_time)
			 VALUES (?, ?, ?, 'pending', ?, ?)"
		);

		if (!$insert_stmt) {
			$conn->rollback();
			$error_message = 'Failed to prepare assistance request.';
		} else {
			$insert_stmt->bind_param('issss', $senior_id, $form_assist_type, $sub_type, $request_date, $request_time);
			if (!$insert_stmt->execute()) {
				$insert_stmt->close();
				$conn->rollback();
				$error_message = 'Failed to submit your request. Please try again.';
			} else {
				$insert_stmt->close();

				$display_date = date('F j, Y', strtotime($request_date));
				$display_time = date('g:i A', strtotime($request_time));

				if ($is_medical) {
					$notification_title = 'Medical Assistance Request Submitted';
					$notification_message = 'Medical request (' . $sub_type . ') scheduled for ' . $display_date . ' at ' . $display_time . '. Status: pending.';
				} else {
					$notification_title = ucfirst($form_assist_type) . ' Assistance Request Submitted';
					$notification_message = ucfirst($form_assist_type) . ' assistance request submitted on ' . $display_date . ' at ' . $display_time . '. Status: pending.';
				}

				$notif_stmt = $conn->prepare(
					"INSERT INTO notifications (senior_id, notification_type, assistance_type, title, message, status, created_at)
					 VALUES (?, 'assistance', ?, ?, ?, 'unread', NOW())"
				);

				if (!$notif_stmt) {
					$conn->rollback();
					$error_message = 'Request saved but notification could not be prepared.';
				} else {
					$notif_stmt->bind_param('isss', $senior_id, $form_assist_type, $notification_title, $notification_message);
					if (!$notif_stmt->execute()) {
						$notif_stmt->close();
						$conn->rollback();
						$error_message = 'Request saved but notification could not be created.';
					} else {
						$notif_stmt->close();
						$conn->commit();
						$success_message = 'Request submitted successfully. Status is pending.';
						$form_assist_type = '';
						$form_medical_mode = '';
						$form_sched_date = '';
						$form_sched_time = '';
					}
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
<title>Request Assistance - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
:root {
	--brand-blue: #1f5d8a;
	--brand-mint: #2b9e8f;
	--brand-sand: #f4efe6;
	--text-deep: #173042;
	--text-soft: #5f7283;
	--soft-border: rgba(23, 48, 66, 0.1);
	--soft-shadow: 0 18px 38px rgba(18, 38, 56, 0.12);
}
body {
	margin: 0;
	min-height: 100vh;
	font-family: "Segoe UI", Tahoma, sans-serif;
	background:
		radial-gradient(circle at 15% 20%, rgba(43, 158, 143, 0.12) 0, transparent 42%),
		radial-gradient(circle at 85% 10%, rgba(31, 93, 138, 0.12) 0, transparent 36%),
		linear-gradient(180deg, #f8fafc 0%, var(--brand-sand) 100%);
	color: var(--text-deep);
}
.wrap {
	padding: 16px;
}
.card-soft {
	background: rgba(255, 255, 255, 0.98);
	border: 1px solid rgba(31, 93, 138, 0.1);
	border-radius: 20px;
	padding: 16px;
	box-shadow: var(--soft-shadow);
	backdrop-filter: blur(10px);
}
.option {
	border: 1px solid var(--soft-border);
	border-radius: 14px;
	padding: 10px 12px;
	background: #fff;
	transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
}
.option:has(input:checked) {
	border-color: rgba(31, 93, 138, 0.35);
	background: #edf6fb;
	box-shadow: 0 0 0 3px rgba(31, 93, 138, 0.08);
}
.option input {
	margin-top: .3rem;
}
.option .form-check {
	display: flex;
	align-items: flex-start;
	gap: 10px;
}
.option-title {
	font-weight: 700;
	font-size: .95rem;
	color: var(--text-deep);
}
.option-desc {
	font-size: .83rem;
	color: var(--text-soft);
}
.medical-mode-wrap {
	display: none;
	margin-top: 10px;
	padding: 10px 12px;
	border-radius: 12px;
	background: #eef6fb;
	border: 1px solid rgba(31, 93, 138, 0.18);
}
.schedule-wrap {
	display: none;
	margin-top: 10px;
}
.schedule-wrap .form-label {
	font-size: .82rem;
	margin-bottom: 3px;
}
.schedule-wrap .form-control {
	font-size: .88rem;
	width: 100%;
}
.section-title {
	font-size: .94rem;
	font-weight: 700;
	color: var(--text-deep);
	margin-bottom: 10px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.btn-brand {
	border: 0;
	color: #fff;
	font-weight: 600;
	background: linear-gradient(135deg, var(--brand-blue), var(--brand-mint));
	box-shadow: 0 10px 24px rgba(23, 48, 66, 0.2);
}
.btn-brand:hover {
	color: #fff;
	filter: brightness(.98);
}
@media (max-width: 768px) {
	.wrap {
		padding: 12px;
	}
}
</style>
</head>
<body>
<div class="wrap">
	<div class="card-soft">
		<?php if ($success_message !== ''): ?>
			<div class="alert alert-success mb-3" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
		<?php endif; ?>
		<?php if ($error_message !== ''): ?>
			<div class="alert alert-danger mb-3" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
		<?php endif; ?>
		<form id="assistForm" method="post">
			<div class="section-title"><i class="bi bi-life-preserver"></i>Select Assistance Type</div>
			<div class="d-flex flex-column gap-2">
				<label class="option">
					<div class="form-check">
						<input class="form-check-input" type="radio" name="assist_type" id="assist_financial" value="financial" <?php echo $form_assist_type === 'financial' ? 'checked' : ''; ?> required>
						<div>
							<div class="option-title">Financial Assistance</div>
							<div class="option-desc">Support for medicine costs, food allowance, or similar needs.</div>
						</div>
					</div>
				</label>

				<label class="option">
					<div class="form-check">
						<input class="form-check-input" type="radio" name="assist_type" id="assist_medical" value="medical" <?php echo $form_assist_type === 'medical' ? 'checked' : ''; ?> required>
						<div>
							<div class="option-title">Medical Assistance</div>
							<div class="option-desc">Help with checkup scheduling, referral, or urgent health follow-up.</div>
							<div class="medical-mode-wrap" id="medicalModeWrap">
								<label for="medical_mode" class="form-label mb-1 fw-semibold" style="font-size:.82rem">Medical Assistance Type</label>
								<select class="form-select form-select-sm" id="medical_mode" name="medical_mode">
									<option value="">Select option</option>
									<option value="House Visit Request" <?php echo $form_medical_mode === 'House Visit Request' ? 'selected' : ''; ?>>House Visit Request</option>
									<option value="Walk In" <?php echo $form_medical_mode === 'Walk In' ? 'selected' : ''; ?>>Walk In</option>
								</select>
								<div class="schedule-wrap" id="scheduleWrap">
									<div class="d-flex flex-column flex-sm-row gap-2 mt-2">
										<div class="flex-fill">
											<label for="sched_date" class="form-label" style="font-size:.82rem">Preferred Date</label>
											<input type="date" class="form-control form-control-sm" id="sched_date" name="sched_date" value="<?php echo htmlspecialchars($form_sched_date); ?>">
										</div>
										<div class="flex-fill">
											<label for="sched_time" class="form-label" style="font-size:.82rem">Preferred Time</label>
											<input type="time" class="form-control form-control-sm" id="sched_time" name="sched_time" value="<?php echo htmlspecialchars($form_sched_time); ?>">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</label>

				<label class="option">
					<div class="form-check">
						<input class="form-check-input" type="radio" name="assist_type" id="assist_barangay" value="barangay" <?php echo $form_assist_type === 'barangay' ? 'checked' : ''; ?> required>
						<div>
							<div class="option-title">Barangay Assistance</div>
							<div class="option-desc">Other support requests handled by the barangay care team.</div>
						</div>
					</div>
				</label>
			</div>

			<div class="mt-3 d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-outline-secondary" id="clearAssist">Clear</button>
				<button type="submit" class="btn btn-brand">Submit Request</button>
			</div>
		</form>
	</div>
</div>

<script>
const assistForm = document.getElementById('assistForm');
const clearAssist = document.getElementById('clearAssist');
const medicalModeWrap = document.getElementById('medicalModeWrap');
const medicalMode = document.getElementById('medical_mode');
const scheduleWrap = document.getElementById('scheduleWrap');
const schedDate = document.getElementById('sched_date');
const schedTime = document.getElementById('sched_time');
const assistTypeInputs = document.querySelectorAll('input[name="assist_type"]');

function syncScheduleVisibility() {
	const hasMode = medicalMode.value !== '';
	scheduleWrap.style.display = hasMode ? 'block' : 'none';
	schedDate.required = hasMode;
	schedTime.required = hasMode;
	if (!hasMode) {
		schedDate.value = '';
		schedTime.value = '';
	}
}

function syncMedicalModeVisibility() {
	const selectedType = new FormData(assistForm).get('assist_type');
	const isMedical = selectedType === 'medical';
	medicalModeWrap.style.display = isMedical ? 'block' : 'none';
	medicalMode.required = isMedical;
	if (!isMedical) {
		medicalMode.value = '';
		syncScheduleVisibility();
	}
}

medicalMode.addEventListener('change', syncScheduleVisibility);

assistForm.addEventListener('submit', function (event) {
	const formData = new FormData(assistForm);
	const selected = formData.get('assist_type');
	if (!selected) {
		event.preventDefault();
		assistForm.reportValidity();
		return;
	}
	if (selected === 'medical' && !medicalMode.value) {
		event.preventDefault();
		assistForm.reportValidity();
		return;
	}
	if (selected === 'medical' && medicalMode.value && (!schedDate.value || !schedTime.value)) {
		event.preventDefault();
		assistForm.reportValidity();
		return;
	}
});

clearAssist.addEventListener('click', function () {
	assistForm.reset();
	syncMedicalModeVisibility();
	syncScheduleVisibility();
});

assistTypeInputs.forEach(function (input) {
	input.addEventListener('change', syncMedicalModeVisibility);
});

syncMedicalModeVisibility();
</script>
</body>
</html>