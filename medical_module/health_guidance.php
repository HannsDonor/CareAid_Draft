<?php
session_start();

if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth_module/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Guidance - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.main-wrap { min-height: 100vh; background: #f0f2f5; }
.topbar {
    background: #fff;
    padding: 14px 28px;
    border-bottom: 1px solid #e3e6ef;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.content-area { padding: 28px; }
.info-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,.07);
}
.level-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-journal-medical me-2 text-primary"></i>Health Guidance</h5>
            <small class="text-muted">Brief guide for interpreting Risk Level and Priority Level</small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card info-card h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-activity text-danger me-1"></i>Risk Level (Per Checkup)
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Risk Level shows the current concern based on a senior's latest vital signs during a checkup (blood pressure, blood sugar, and heart rate).</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0"><span class="level-dot bg-success"></span><strong>Low:</strong> Vitals are within normal ranges; continue routine monitoring.</li>
                            <li class="list-group-item px-0"><span class="level-dot bg-warning"></span><strong>Moderate:</strong> Early warning values; monitor more closely and provide health reminders.</li>
                            <li class="list-group-item px-0"><span class="level-dot bg-danger"></span><strong>High:</strong> Abnormal values needing prompt follow-up and possible referral.</li>
                            <li class="list-group-item px-0"><span class="level-dot" style="background:#7a1f1f;"></span><strong>Critical:</strong> Severe findings that may require immediate medical action.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card info-card h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-exclamation-triangle text-warning me-1"></i>Priority Level (Profile Status)
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Priority Level is a profile-level triage score based on known illnesses and health history. It helps teams decide who needs attention first.</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0"><strong>Priority 1:</strong> Stable profile, routine schedule.</li>
                            <li class="list-group-item px-0"><strong>Priority 2:</strong> Mild concern, regular follow-up advised.</li>
                            <li class="list-group-item px-0"><strong>Priority 3:</strong> Moderate concern, shorter follow-up interval.</li>
                            <li class="list-group-item px-0"><strong>Priority 4:</strong> High concern, prioritize for checkup and intervention.</li>
                            <li class="list-group-item px-0"><strong>Priority 5:</strong> Highest concern, handle urgently and monitor closely.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card info-card mt-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle text-primary me-1"></i>How These Two Levels Work Together
            </div>
            <div class="card-body">
                <p class="mb-2">Use both values to guide action planning:</p>
                <ul class="mb-0">
                    <li>Risk Level tells you what is happening now in the latest checkup.</li>
                    <li>Priority Level tells you who should be scheduled first across all seniors.</li>
                    <li>When both are high, escalate quickly and coordinate immediate follow-up.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>