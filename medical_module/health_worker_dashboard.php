<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

$account_id = (int)($_SESSION['account_id'] ?? 0);

function scalar_value(mysqli $conn, string $sql, int $default = 0): int {
    $res = $conn->query($sql);
    if (!$res) {
        return $default;
    }
    $row = $res->fetch_assoc();
    if (!$row) {
        return $default;
    }
    $val = reset($row);
    return (int)($val ?? $default);
}

function percent(int $part, int $whole): int {
    if ($whole <= 0) {
        return 0;
    }
    return (int)round(($part / $whole) * 100);
}

// Seniors and demographics
$totalSeniors    = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles");
$aliveCount      = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE is_alive = 'yes'");
$deceasedCount   = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE is_alive = 'no'");
$maleCount       = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE gender = 'Male'");
$femaleCount     = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE gender = 'Female'");
$highPriority    = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE priority_level >= 4");
$criticalPriority = scalar_value($conn, "SELECT COUNT(*) FROM senior_profiles WHERE priority_level = 5");

// Checkups
$totalCheckups = scalar_value($conn, "SELECT COUNT(*) FROM checkups");
$thisMonth = scalar_value(
    $conn,
    "SELECT COUNT(*) FROM checkups WHERE MONTH(checkup_date) = MONTH(CURDATE()) AND YEAR(checkup_date) = YEAR(CURDATE())"
);
$lastMonth = scalar_value(
    $conn,
    "SELECT COUNT(*) FROM checkups WHERE MONTH(checkup_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(checkup_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)"
);
$highRiskCount = scalar_value($conn, "SELECT COUNT(DISTINCT senior_id) FROM checkups WHERE risk_level IN ('High','Critical')");

// Health records and illnesses
$totalHealthRecords = scalar_value($conn, "SELECT COUNT(*) FROM health_records");
$seniorsWithHealthRecords = scalar_value($conn, "SELECT COUNT(DISTINCT senior_id) FROM health_records");
$illnessCatalogCount = scalar_value($conn, "SELECT COUNT(*) FROM illnesses");
$illnessCategoryCount = scalar_value($conn, "SELECT COUNT(DISTINCT category) FROM illnesses WHERE category IS NOT NULL AND category <> ''");

// Assistance and notifications
$totalAssistanceRequests = scalar_value($conn, "SELECT COUNT(*) FROM assistance_requests");
$pendingAssistance = scalar_value($conn, "SELECT COUNT(*) FROM assistance_requests WHERE status = 'pending'");
$inProgressAssistance = scalar_value($conn, "SELECT COUNT(*) FROM assistance_requests WHERE status = 'in_progress'");
$completedAssistance = scalar_value($conn, "SELECT COUNT(*) FROM assistance_requests WHERE status = 'completed'");
$unreadNotifications = 0;
$notif_cnt_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE notification_type = 'assistance' AND account_id = ? AND is_deleted = 0 AND status = 'unread'");
if ($notif_cnt_stmt) {
    $notif_cnt_stmt->bind_param('i', $account_id);
    $notif_cnt_stmt->execute();
    $notif_cnt_res = $notif_cnt_stmt->get_result();
    if ($notif_cnt_res) {
        $notif_cnt_row = $notif_cnt_res->fetch_assoc();
        $unreadNotifications = (int)($notif_cnt_row['c'] ?? 0);
    }
    $notif_cnt_stmt->close();
}

// Priority distribution
$priorityDist = [];
$pdRes = $conn->query("SELECT priority_level, COUNT(*) AS cnt FROM senior_profiles GROUP BY priority_level ORDER BY priority_level DESC");
if ($pdRes) {
    while ($r = $pdRes->fetch_assoc()) {
        $priorityDist[(int)$r['priority_level']] = (int)$r['cnt'];
    }
}

// Risk breakdown
$riskBreakdown = [];
$rbRes = $conn->query("SELECT risk_level, COUNT(*) AS cnt FROM checkups GROUP BY risk_level ORDER BY FIELD(risk_level,'Critical','High','Moderate','Low')");
if ($rbRes) {
    while ($r = $rbRes->fetch_assoc()) {
        $riskBreakdown[(string)$r['risk_level']] = (int)$r['cnt'];
    }
}

// Assistance status breakdown
$assistBreakdown = [
    'pending' => 0,
    'accepted' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
$abRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM assistance_requests GROUP BY status");
if ($abRes) {
    while ($r = $abRes->fetch_assoc()) {
        $status = strtolower((string)($r['status'] ?? ''));
        if (array_key_exists($status, $assistBreakdown)) {
            $assistBreakdown[$status] = (int)$r['cnt'];
        }
    }
}

// Monthly checkup trend (last 6 months)
$monthlyCheckups = [];
$mcRes = $conn->query(
    "SELECT DATE_FORMAT(checkup_date, '%Y-%m') AS ym, COUNT(*) AS cnt
     FROM checkups
     WHERE checkup_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(checkup_date, '%Y-%m')
     ORDER BY ym ASC"
);
if ($mcRes) {
    while ($r = $mcRes->fetch_assoc()) {
        $monthlyCheckups[] = [
            'ym' => (string)$r['ym'],
            'cnt' => (int)$r['cnt'],
        ];
    }
}

// Top illnesses
$topIllnesses = [];
$tiRes = $conn->query(
    "SELECT chronic_conditions, COUNT(*) AS cnt
     FROM health_records
     WHERE chronic_conditions IS NOT NULL AND chronic_conditions <> ''
     GROUP BY chronic_conditions
     ORDER BY cnt DESC, chronic_conditions ASC
     LIMIT 6"
);
if ($tiRes) {
    while ($r = $tiRes->fetch_assoc()) {
        $topIllnesses[] = [
            'name' => (string)$r['chronic_conditions'],
            'cnt' => (int)$r['cnt'],
        ];
    }
}

$monthlyPeak = 0;
foreach ($monthlyCheckups as $m) {
    if ($m['cnt'] > $monthlyPeak) {
        $monthlyPeak = $m['cnt'];
    }
}

$topIllnessPeak = 0;
foreach ($topIllnesses as $ti) {
    if ($ti['cnt'] > $topIllnessPeak) {
        $topIllnessPeak = $ti['cnt'];
    }
}

$monthDelta = $thisMonth - $lastMonth;
$monthDeltaLabel = $monthDelta > 0 ? '+' . $monthDelta : (string)$monthDelta;
$avgCheckupsPerSenior = $totalSeniors > 0 ? round($totalCheckups / $totalSeniors, 2) : 0;
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
.analytics-card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.bar-mini { height: 8px; border-radius: 6px; background: #e9ecef; overflow: hidden; }
.bar-mini > span { display: block; height: 100%; }
.kpi-category {
    margin-bottom: 18px;
}
.kpi-category h6 {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #6c757d;
    margin-bottom: 10px;
}
.node-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 3px 14px rgba(0,0,0,.07);
}
.node-card .card-body {
    padding: 12px 14px;
}
.node-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex: 0 0 38px;
}
.node-label {
    font-size: .72rem;
    color: #6c757d;
    line-height: 1.1;
    margin-bottom: 1px;
}
.node-value {
    font-size: 1.2rem;
    line-height: 1.1;
    font-weight: 700;
}
.node-sub {
    font-size: .68rem;
    color: #8a8f98;
}
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

        <!-- Compact KPI Nodes by Category (Columns) -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-category mb-0">
                    <h6>Seniors</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div><div><div class="node-label">Total Seniors</div><div class="node-value"><?php echo $totalSeniors; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-success bg-opacity-10 text-success"><i class="bi bi-heart-pulse-fill"></i></div><div><div class="node-label">Alive / Deceased</div><div class="node-value"><?php echo $aliveCount; ?> / <?php echo $deceasedCount; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-gender-male"></i></div><div><div class="node-label">Male</div><div class="node-value"><?php echo $maleCount; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-pink bg-opacity-10 text-danger"><i class="bi bi-gender-female"></i></div><div><div class="node-label">Female</div><div class="node-value"><?php echo $femaleCount; ?></div></div></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kpi-category mb-0">
                    <h6>Checkups</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-info bg-opacity-10 text-info"><i class="bi bi-clipboard2-pulse-fill"></i></div><div><div class="node-label">Total Checkups</div><div class="node-value"><?php echo $totalCheckups; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar-check"></i></div><div><div class="node-label">This Month</div><div class="node-value"><?php echo $thisMonth; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-shield-exclamation"></i></div><div><div class="node-label">High/Critical Risk</div><div class="node-value"><?php echo $highRiskCount; ?></div></div></div></div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kpi-category mb-0">
                    <h6>Requests</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-clipboard2-check"></i></div><div><div class="node-label">All Requests</div><div class="node-value"><?php echo $totalAssistanceRequests; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="node-label">Pending</div><div class="node-value"><?php echo $pendingAssistance; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-info bg-opacity-10 text-info"><i class="bi bi-arrow-repeat"></i></div><div><div class="node-label">In Progress</div><div class="node-value"><?php echo $inProgressAssistance; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div><div><div class="node-label">Completed</div><div class="node-value"><?php echo $completedAssistance; ?></div></div></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kpi-category mb-0">
                    <h6>Records</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-success bg-opacity-10 text-success"><i class="bi bi-journal-medical"></i></div><div><div class="node-label">Health Records</div><div class="node-value"><?php echo $totalHealthRecords; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-check-fill"></i></div><div><div class="node-label">Seniors w/ Records</div><div class="node-value"><?php echo $seniorsWithHealthRecords; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-info bg-opacity-10 text-info"><i class="bi bi-capsule-pill"></i></div><div><div class="node-label">Illness Catalog</div><div class="node-value"><?php echo $illnessCatalogCount; ?></div></div></div></div>
                        </div>
                        <div class="col-12">
                            <div class="card node-card"><div class="card-body d-flex align-items-center gap-2"><div class="node-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-bell-fill"></i></div><div><div class="node-label">Unread Alerts</div><div class="node-value"><?php echo $unreadNotifications; ?></div></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Main analytics -->
        <div class="row g-4">

            <!-- Priority distribution -->
            <div class="col-lg-4">
                <div class="card analytics-card h-100">
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
                <div class="card analytics-card h-100">
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

            <!-- Assistance status breakdown -->
            <div class="col-lg-4">
                <div class="card analytics-card h-100">
                    <div class="card-header bg-white fw-semibold">Assistance Workflow Status</div>
                    <div class="card-body">
                        <?php
                        $assistMeta = [
                            'pending' => ['Pending', 'warning'],
                            'accepted' => ['Accepted', 'primary'],
                            'in_progress' => ['In Progress', 'info'],
                            'completed' => ['Completed', 'success'],
                            'cancelled' => ['Cancelled', 'secondary'],
                        ];
                        foreach ($assistMeta as $key => $meta):
                            $cnt = (int)($assistBreakdown[$key] ?? 0);
                            $pct = percent($cnt, $totalAssistanceRequests);
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="badge bg-<?php echo $meta[1]; ?>"><?php echo $meta[0]; ?></span>
                                <small class="text-muted"><?php echo $cnt; ?> (<?php echo $pct; ?>%)</small>
                            </div>
                            <div class="progress"><div class="progress-bar bg-<?php echo $meta[1]; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Row 5: Trend and top conditions -->
        <div class="row g-4 mt-1">
            <div class="col-lg-7">
                <div class="card analytics-card h-100">
                    <div class="card-header bg-white fw-semibold">Monthly Checkup Trend (Last 6 Months)</div>
                    <div class="card-body">
                        <?php if (empty($monthlyCheckups)): ?>
                            <div class="text-muted">No monthly checkup trend data available yet.</div>
                        <?php else: ?>
                            <?php foreach ($monthlyCheckups as $m):
                                $bar = $monthlyPeak > 0 ? (int)round(($m['cnt'] / $monthlyPeak) * 100) : 0;
                                $label = date('M Y', strtotime($m['ym'] . '-01'));
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
                                    <small class="text-muted"><?php echo $m['cnt']; ?> checkups</small>
                                </div>
                                <div class="bar-mini"><span class="bg-primary" style="width: <?php echo $bar; ?>%"></span></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card analytics-card h-100">
                    <div class="card-header bg-white fw-semibold">Top Chronic Conditions</div>
                    <div class="card-body">
                        <?php if (empty($topIllnesses)): ?>
                            <div class="text-muted">No chronic condition records yet.</div>
                        <?php else: ?>
                            <?php foreach ($topIllnesses as $item):
                                $bar = $topIllnessPeak > 0 ? (int)round(($item['cnt'] / $topIllnessPeak) * 100) : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1 gap-2">
                                    <span class="small fw-semibold text-truncate"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <small class="text-muted"><?php echo $item['cnt']; ?></small>
                                </div>
                                <div class="bar-mini"><span class="bg-success" style="width: <?php echo $bar; ?>%"></span></div>
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
