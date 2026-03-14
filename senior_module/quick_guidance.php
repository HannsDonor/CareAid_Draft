<?php
session_start();

if (!isset($_SESSION['account_id'])) {
	header('Location: ../auth_module/login.php');
	exit;
}

$role = (string)($_SESSION['role'] ?? '');

if ($role !== 'senior') {
	header('Location: ../auth_module/login.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quick Guidance - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
:root {
	--brand-blue: #1f5d8a;
	--brand-mint: #2b9e8f;
	--brand-sand: #f4efe6;
	--text-deep: #173042;
	--soft-border: rgba(23, 48, 66, 0.08);
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
.page-wrap {
	max-width: 920px;
	margin: 0 auto;
	padding: 18px;
}
.guide-card {
	background: rgba(255, 255, 255, 0.98);
	border: 1px solid rgba(31, 93, 138, 0.1);
	border-radius: 20px;
	padding: 18px;
	box-shadow: var(--soft-shadow);
	backdrop-filter: blur(10px);
}
.guide-risk {
	border-radius: 16px;
	padding: 14px;
	background: #f8f9fa;
	border: 1px solid rgba(0, 0, 0, 0.05);
	transition: background .18s ease, border-color .18s ease;
}
.guide-level {
	font-size: 1.3rem;
	font-weight: 700;
	letter-spacing: .02em;
}
.guide-item {
	border: 1px solid var(--soft-border);
	border-radius: 14px;
	padding: 10px 12px;
	background: #fff;
	font-size: .92rem;
	height: 100%;
	transition: background .18s ease, border-color .18s ease;
}
.guide-item strong {
	display: block;
	font-size: .78rem;
	color: #5f7283;
	text-transform: uppercase;
	letter-spacing: .05em;
	margin-bottom: 4px;
}
.guide-list {
	margin: 0;
	padding-left: 18px;
	color: #4f6272;
	font-size: .92rem;
}
.guide-list li + li {
	margin-top: 6px;
}
.pulse-red {
	animation: pulse-red 1s infinite;
}
@keyframes pulse-red {
	0%,100% { opacity: 1; }
	50% { opacity: .55; }
}
@media (max-width: 768px) {
	.page-wrap {
		padding: 12px;
	}
}
</style>
</head>
<body>
<div class="page-wrap">
	<section class="guide-card">
		<div class="row g-3 mb-3">
			<div class="col-12">
				<label for="guide_bp" class="form-label mb-1 fw-semibold">Blood Pressure</label>
				<input type="text" class="form-control form-control-lg" id="guide_bp" placeholder="Example: 120/80" inputmode="numeric">
			</div>
			<div class="col-md-6">
				<label for="guide_bs" class="form-label mb-1 fw-semibold">Blood Sugar</label>
				<input type="number" class="form-control form-control-lg" id="guide_bs" placeholder="mg/dL" min="0" step="0.1">
			</div>
			<div class="col-md-6">
				<label for="guide_hr" class="form-label mb-1 fw-semibold">Heart Rate</label>
				<input type="number" class="form-control form-control-lg" id="guide_hr" placeholder="bpm" min="0" step="1">
			</div>
		</div>

		<div class="guide-risk mb-3" id="guideRiskBox">
			<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
				<div>
					<div class="text-muted small">Overall Guidance</div>
					<div class="guide-level text-secondary" id="guideRiskLevel">No reading yet</div>
				</div>
				<span class="badge bg-secondary" id="guideRiskBadge">Waiting</span>
			</div>
			<div class="small text-muted mt-2" id="guideRiskReason">Enter one or more readings to see guidance.</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-12">
				<div class="guide-item" id="guideBpItem">
					<strong>Blood Pressure</strong>
					<span>Waiting for input.</span>
				</div>
			</div>
			<div class="col-md-6">
				<div class="guide-item" id="guideBsItem">
					<strong>Blood Sugar</strong>
					<span>Waiting for input.</span>
				</div>
			</div>
			<div class="col-md-6">
				<div class="guide-item" id="guideHrItem">
					<strong>Heart Rate</strong>
					<span>Waiting for input.</span>
				</div>
			</div>
		</div>

		<ul class="guide-list mb-0" id="guideTips">
			<li>Normal values still need routine monitoring.</li>
			<li>If you feel unwell, contact a health worker even if readings look okay.</li>
		</ul>

		<div class="d-flex justify-content-end mt-3">
			<button type="button" class="btn btn-outline-secondary" id="guideReset">Clear</button>
		</div>
	</section>
</div>

<script>
const guideBpInput = document.getElementById('guide_bp');
const guideBsInput = document.getElementById('guide_bs');
const guideHrInput = document.getElementById('guide_hr');
const guideReset = document.getElementById('guideReset');

function classifyBloodPressure(bpRaw) {
	if (!bpRaw) {
		return { state: 'empty', risk: null, summary: 'Waiting for input.' };
	}

	const bpMatch = bpRaw.match(/^(\d+)\s*\/\s*(\d+)$/);
	if (!bpMatch) {
		return { state: 'invalid', risk: null, summary: 'Use the format systolic/diastolic, like 120/80.' };
	}

	const systolic = parseInt(bpMatch[1], 10);
	const diastolic = parseInt(bpMatch[2], 10);

	if (systolic >= 140 || diastolic >= 90) {
		return { state: 'valid', risk: 'High', summary: 'High Stage 2 (140/90 or higher).' };
	}
	if ((systolic >= 130 && systolic <= 139) || (diastolic >= 80 && diastolic <= 89)) {
		return { state: 'valid', risk: 'Moderate', summary: 'High Stage 1 (130-139 / 80-89).' };
	}
	if (systolic >= 120 && systolic <= 129 && diastolic < 80) {
		return { state: 'valid', risk: 'Moderate', summary: 'Elevated (120-129 / below 80).' };
	}
	return { state: 'valid', risk: 'Low', summary: 'Normal range (below 120/80).' };
}

function classifyBloodSugar(rawValue) {
	if (rawValue === '') {
		return { state: 'empty', risk: null, summary: 'Waiting for input.' };
	}

	const bloodSugar = parseFloat(rawValue);
	if (Number.isNaN(bloodSugar)) {
		return { state: 'invalid', risk: null, summary: 'Enter a numeric blood sugar value.' };
	}

	if (bloodSugar < 70) {
		return { state: 'valid', risk: 'High', summary: 'Low blood sugar: below 70 mg/dL.' };
	}
	if (bloodSugar >= 126) {
		return { state: 'valid', risk: 'High', summary: 'Diabetic range: 126 mg/dL or higher.' };
	}
	if (bloodSugar >= 100) {
		return { state: 'valid', risk: 'Moderate', summary: 'Pre-diabetic range: 100-125 mg/dL.' };
	}
	return { state: 'valid', risk: 'Low', summary: 'Normal range: 70-99 mg/dL.' };
}

function classifyHeartRate(rawValue) {
	if (rawValue === '') {
		return { state: 'empty', risk: null, summary: 'Waiting for input.' };
	}

	const heartRate = parseInt(rawValue, 10);
	if (Number.isNaN(heartRate)) {
		return { state: 'invalid', risk: null, summary: 'Enter a numeric heart rate.' };
	}

	if (heartRate < 50) {
		return { state: 'valid', risk: 'High', summary: 'Too low: below 50 bpm.' };
	}
	if (heartRate > 110) {
		return { state: 'valid', risk: 'High', summary: 'Too high: above 110 bpm.' };
	}
	if ((heartRate >= 50 && heartRate <= 59) || (heartRate >= 101 && heartRate <= 110)) {
		return { state: 'valid', risk: 'Moderate', summary: 'Slightly outside the usual 60-100 bpm range.' };
	}
	return { state: 'valid', risk: 'Low', summary: 'Normal range: 60-100 bpm.' };
}

function setGuideItem(elementId, result) {
	const element = document.getElementById(elementId);
	if (!element) {
		return;
	}

	const span = element.querySelector('span');
	span.textContent = result.summary;
	element.style.borderColor = 'rgba(23, 48, 66, 0.08)';
	element.style.background = '#fff';

	if (result.risk === 'High') {
		element.style.borderColor = 'rgba(220, 53, 69, 0.3)';
		element.style.background = '#fff5f5';
	} else if (result.risk === 'Moderate') {
		element.style.borderColor = 'rgba(255, 193, 7, 0.35)';
		element.style.background = '#fffbea';
	} else if (result.risk === 'Low') {
		element.style.borderColor = 'rgba(25, 135, 84, 0.22)';
		element.style.background = '#f0fff4';
	}

	if (result.state === 'invalid') {
		element.style.borderColor = 'rgba(13, 110, 253, 0.24)';
		element.style.background = '#f4f8ff';
	}
}

function updateGuideTips(level) {
	const tips = document.getElementById('guideTips');
	const items = {
		High: [
			'Consider contacting a health worker soon, especially if symptoms are present.',
			'Recheck the reading after resting and using proper measurement technique.'
		],
		Moderate: [
			'Monitor again later today and watch for changes or symptoms.',
			'Keep following your usual medication, food, and hydration plan.'
		],
		Low: [
			'Values look closer to normal, but continue routine monitoring.',
			'Stay active, hydrated, and follow your scheduled checkups.'
		],
		Waiting: [
			'Normal values still need routine monitoring.',
			'If you feel unwell, contact a health worker even if readings look okay.'
		]
	};

	tips.innerHTML = items[level].map(function (item) {
		return '<li>' + item + '</li>';
	}).join('');
}

function updateQuickGuidance() {
	const bpResult = classifyBloodPressure(guideBpInput.value.trim());
	const bsResult = classifyBloodSugar(guideBsInput.value.trim());
	const hrResult = classifyHeartRate(guideHrInput.value.trim());
	const results = [bpResult, bsResult, hrResult];
	const validResults = results.filter(function (result) {
		return result.risk !== null;
	});

	setGuideItem('guideBpItem', bpResult);
	setGuideItem('guideBsItem', bsResult);
	setGuideItem('guideHrItem', hrResult);

	const levelEl = document.getElementById('guideRiskLevel');
	const badgeEl = document.getElementById('guideRiskBadge');
	const reasonEl = document.getElementById('guideRiskReason');
	const riskBox = document.getElementById('guideRiskBox');

	if (validResults.length === 0) {
		levelEl.textContent = 'No reading yet';
		levelEl.className = 'guide-level text-secondary';
		badgeEl.className = 'badge bg-secondary';
		badgeEl.textContent = 'Waiting';
		reasonEl.textContent = 'Enter one or more readings to see guidance.';
		riskBox.style.background = '#f8f9fa';
		riskBox.style.borderColor = 'rgba(0, 0, 0, 0.05)';
		updateGuideTips('Waiting');
		return;
	}

	let overall = 'Low';
	if (validResults.some(function (result) { return result.risk === 'High'; })) {
		overall = 'High';
	} else if (validResults.some(function (result) { return result.risk === 'Moderate'; })) {
		overall = 'Moderate';
	}

	levelEl.textContent = overall;
	badgeEl.textContent = overall;
	reasonEl.textContent = validResults.map(function (result) {
		return result.summary;
	}).join(' ');

	if (overall === 'High') {
		levelEl.className = 'guide-level text-danger pulse-red';
		badgeEl.className = 'badge bg-danger';
		riskBox.style.background = '#fff5f5';
		riskBox.style.borderColor = 'rgba(220, 53, 69, 0.18)';
	} else if (overall === 'Moderate') {
		levelEl.className = 'guide-level text-warning';
		badgeEl.className = 'badge bg-warning text-dark';
		riskBox.style.background = '#fffbea';
		riskBox.style.borderColor = 'rgba(255, 193, 7, 0.28)';
	} else {
		levelEl.className = 'guide-level text-success';
		badgeEl.className = 'badge bg-success';
		riskBox.style.background = '#f0fff4';
		riskBox.style.borderColor = 'rgba(25, 135, 84, 0.22)';
	}

	updateGuideTips(overall);
}

guideReset.addEventListener('click', function () {
	guideBpInput.value = '';
	guideBsInput.value = '';
	guideHrInput.value = '';
	updateQuickGuidance();
});

[guideBpInput, guideBsInput, guideHrInput].forEach(function (input) {
	input.addEventListener('input', updateQuickGuidance);
});

updateQuickGuidance();
</script>
</body>
</html>