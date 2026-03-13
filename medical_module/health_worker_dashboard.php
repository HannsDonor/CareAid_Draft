<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

// --- Statistics ---
$totalSeniors  = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles")->fetch_assoc()['c'] ?? 0;
$aliveCount    = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE is_alive = 'yes'")->fetch_assoc()['c'] ?? 0;
$deceasedCount = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE is_alive = 'no'")->fetch_assoc()['c'] ?? 0;
$maleCount     = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE gender = 'Male'")->fetch_assoc()['c'] ?? 0;
$femaleCount   = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE gender = 'Female'")->fetch_assoc()['c'] ?? 0;
$highPriority  = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE priority_level >= 4")->fetch_assoc()['c'] ?? 0;
$totalCheckups = $conn->query("SELECT COUNT(*) AS c FROM checkups")->fetch_assoc()['c'] ?? 0;
$highRiskCount = $conn->query("SELECT COUNT(DISTINCT senior_id) AS c FROM checkups WHERE risk_level IN ('High','Critical')")->fetch_assoc()['c'] ?? 0;

// Priority distribution
$priorityDist = [];
$pdRes = $conn->query("SELECT priority_level, COUNT(*) AS cnt FROM senior_profiles GROUP BY priority_level ORDER BY priority_level DESC");
if ($pdRes) {
    while ($r = $pdRes->fetch_assoc()) {
        $priorityDist[(int)$r['priority_level']] = (int)$r['cnt'];
    }
}

// Checkups this month vs last month
$thisMonth = $conn->query("SELECT COUNT(*) AS c FROM checkups WHERE MONTH(checkup_date)=MONTH(CURDATE()) AND YEAR(checkup_date)=YEAR(CURDATE())")->fetch_assoc()['c'] ?? 0;
$lastMonth = $conn->query("SELECT COUNT(*) AS c FROM checkups WHERE MONTH(checkup_date)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(checkup_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)")->fetch_assoc()['c'] ?? 0;

// Risk level breakdown from checkups
$riskBreakdown = [];
$rbRes = $conn->query("SELECT risk_level, COUNT(*) AS cnt FROM checkups GROUP BY risk_level ORDER BY FIELD(risk_level,'Critical','High','Moderate','Low')");
if ($rbRes) {
    while ($r = $rbRes->fetch_assoc()) {
        $riskBreakdown[$r['risk_level']] = (int)$r['cnt'];
    }
}

// Recent 5 checkups
$recentCheckups = [];
$rcRes = $conn->query(
    "SELECT c.checkup_date, c.blood_pressure, c.heart_rate, c.blood_sugar, c.risk_level,
            sp.first_name, sp.last_name
     FROM checkups c
     JOIN senior_profiles sp ON sp.senior_id = c.senior_id
     ORDER BY c.checkup_date DESC LIMIT 5"
);
if ($rcRes) {
    while ($r = $rcRes->fetch_assoc()) {
        $recentCheckups[] = $r;
    }
}

function risk_badge(string $risk): string {
    if ($risk === 'High' || $risk === 'Critical') return '<span class="badge bg-danger">' . htmlspecialchars($risk) . '</span>';
    if ($risk === 'Moderate') return '<span class="badge bg-warning text-dark">Moderate</span>';
    return '<span class="badge bg-success">Low</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Worker Dashboard — CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.main-wrap {
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
.stat-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,.08);
}
.stat-icon { width: 52px; height: 52px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
.progress { height: 10px; border-radius: 6px; }
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">Health Worker Dashboard</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">

        <!-- Row 1: Senior overview stats -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Total Seniors</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $totalSeniors; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Alive</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $aliveCount; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-moon-stars-fill"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Deceased</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $deceasedCount; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">High Priority (4–5)</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $highPriority; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Checkup & risk stats -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Total Checkups</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $totalCheckups; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Checkups This Month</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $thisMonth; ?></div>
                            <div class="text-muted" style="font-size:.72rem;">Last month: <?php echo $lastMonth; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-shield-exclamation"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">High / Critical Risk</div>
                            <div class="fw-bold" style="font-size:1.8rem;"><?php echo $highRiskCount; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-gender-ambiguous"></i></div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Gender Breakdown</div>
                            <div class="fw-semibold" style="font-size:1rem;">
                                <i class="bi bi-gender-male text-primary"></i> <?php echo $maleCount; ?> &nbsp;
                                <i class="bi bi-gender-female text-danger"></i> <?php echo $femaleCount; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Priority distribution + Risk breakdown + Recent checkups -->
        <div class="row g-4">

            <!-- Priority distribution -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Priority Level Distribution</div>
                    <div class="card-body">
                        <?php
                        $pLevels = [5 => ['label'=>'Priority 5','color'=>'danger'], 4 => ['label'=>'Priority 4','color'=>'warning'], 3 => ['label'=>'Priority 3','color'=>'info'], 2 => ['label'=>'Priority 2','color'=>'secondary'], 1 => ['label'=>'Priority 1','color'=>'success']];
                        foreach ($pLevels as $lvl => $meta):
                            $cnt = $priorityDist[$lvl] ?? 0;
                            $pct = $totalSeniors > 0 ? round($cnt / $totalSeniors * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="badge bg-<?php echo $meta['color']; ?>"><?php echo $meta['label']; ?></span>
                                <small class="text-muted"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</small>
                            </div>
                            <div class="progress"><div class="progress-bar bg-<?php echo $meta['color']; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Risk level breakdown -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Checkup Risk Breakdown</div>
                    <div class="card-body">
                        <?php
                        $riskMeta = ['Critical'=>'danger','High'=>'danger','Moderate'=>'warning','Low'=>'success'];
                        foreach ($riskMeta as $label => $color):
                            $cnt = $riskBreakdown[$label] ?? 0;
                            $pct = $totalCheckups > 0 ? round($cnt / $totalCheckups * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span>
                                <small class="text-muted"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</small>
                            </div>
                            <div class="progress"><div class="progress-bar bg-<?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent checkups -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Recent Checkups</div>
                    <div class="list-group list-group-flush" style="overflow-y:auto;">
                        <?php if (empty($recentCheckups)): ?>
                            <div class="p-4 text-center text-muted">No checkups recorded yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentCheckups as $row): ?>
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></span>
                                    <?php echo risk_badge($row['risk_level'] ?? ''); ?>
                                </div>
                                <small class="text-muted">
                                    BP: <?php echo htmlspecialchars($row['blood_pressure'] ?? '-'); ?> &bull;
                                    HR: <?php echo $row['heart_rate'] !== null ? htmlspecialchars((string)$row['heart_rate']) : '-'; ?> &bull;
                                    <?php echo htmlspecialchars($row['checkup_date']); ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
