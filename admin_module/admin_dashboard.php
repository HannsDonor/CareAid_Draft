<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth_module/login.php");
    exit;
}

/* ── Dashboard counts ──────────────────────────────────────────────────── */
$totalSeniors  = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles")->fetch_assoc()['c'] ?? 0;
$totalAccounts = $conn->query("SELECT COUNT(*) AS c FROM accounts")->fetch_assoc()['c'] ?? 0;
$highRisk      = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE priority_level >= 4")->fetch_assoc()['c'] ?? 0;
$aliveCount    = $conn->query("SELECT COUNT(*) AS c FROM senior_profiles WHERE is_alive = 'yes'")->fetch_assoc()['c'] ?? 0;

$recentSeniors = $conn->query(
    "SELECT sp.first_name, sp.middle_name, sp.last_name, sp.priority_level, sp.is_alive, sp.gender, sp.created_at
     FROM senior_profiles sp
     ORDER BY sp.senior_id DESC LIMIT 8"
);

$priorityMap = [
    5 => ['Critical',      'danger'],
    4 => ['High',          'warning'],
    3 => ['Moderate',      'info'],
    2 => ['Low-Moderate',  'secondary'],
    1 => ['Normal',        'success'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
/* ── Sidebar ─────────────────────────────────────────────────────────── */
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
.sidebar .nav-link i { font-size: 1rem; width: 18px; }
.sidebar-footer {
    margin-top: auto;
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,.15);
    font-size: .8rem;
    opacity: .65;
}

/* ── Main content area ───────────────────────────────────────────────── */
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

/* ── Stat cards ──────────────────────────────────────────────────────── */
.stat-card {
    border: none;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(0,0,0,.08);
    transition: transform .18s, box-shadow .18s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,.13);
}
.stat-icon {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}

/* ── Quick-action buttons ────────────────────────────────────────────── */
.action-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    text-decoration: none;
    color: #343a40;
    padding: 18px 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    font-size: .85rem;
    font-weight: 600;
    text-align: center;
    transition: border-color .2s, background .2s, color .2s, transform .18s;
    background: #fff;
}
.action-card:hover {
    border-color: #667eea;
    background: #667eea;
    color: #fff;
    transform: translateY(-2px);
}
.action-card i { font-size: 1.7rem; }
</style>
</head>
<body>

<!-- ══════════════════════ SIDEBAR ══════════════════════ -->
<aside class="sidebar">
    <div class="brand">
        <h6>CAREAID SYSTEM</h6>
        <h5><i class="bi bi-heart-pulse-fill me-1"></i> Admin Portal</h5>
    </div>
    <nav class="mt-2 d-flex flex-column gap-1">
        <a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="../medical_module/create_senior_profile.php"><i class="bi bi-person-plus-fill"></i> Register Senior</a>
        <a class="nav-link" href="manage_seniors.php"><i class="bi bi-people-fill"></i> Manage Seniors</a>
        <a class="nav-link" href="../medical_module/senior_checkup.php"><i class="bi bi-activity"></i> Senior Checkup</a>
        <a class="nav-link" href="create_staff.php"><i class="bi bi-person-badge-fill"></i> Barangay Staff</a>
        <a class="nav-link" href="manage_accounts.php"><i class="bi bi-shield-lock-fill"></i> Accounts</a>
        <a class="nav-link" href="announcement.php"><i class="bi bi-megaphone-fill"></i> Announcements</a>
        <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart-fill"></i> Reports</a>
    </nav>
    <div class="sidebar-footer">
        Logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
    </div>
</aside>

<!-- ══════════════════════ MAIN ══════════════════════ -->
<div class="main-wrap">

    <!-- Top bar -->
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">Dashboard</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="../auth_module/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <div class="content-area">

        <!-- ── Stat cards ───────────────────────────── -->
        <div class="row g-4 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Total Seniors</div>
                            <div class="fw-bold" style="font-size:1.7rem;line-height:1;"><?php echo $totalSeniors; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Active / Alive</div>
                            <div class="fw-bold" style="font-size:1.7rem;line-height:1;"><?php echo $aliveCount; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">High Priority</div>
                            <div class="fw-bold" style="font-size:1.7rem;line-height:1;"><?php echo $highRisk; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:.78rem;">Total Accounts</div>
                            <div class="fw-bold" style="font-size:1.7rem;line-height:1;"><?php echo $totalAccounts; ?></div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /stat cards -->

        <!-- ── Quick actions ─────────────────────────── -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="../medical_module/create_senior_profile.php" class="action-card h-100">
                            <i class="bi bi-person-plus-fill text-primary"></i>
                            Register Senior
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="../medical_module/senior_checkup.php" class="action-card h-100">
                            <i class="bi bi-activity text-danger"></i>
                            Senior Checkup
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="manage_seniors.php" class="action-card h-100">
                            <i class="bi bi-people-fill text-success"></i>
                            Manage Seniors
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="create_staff.php" class="action-card h-100">
                            <i class="bi bi-person-badge-fill text-purple" style="color:#764ba2;"></i>
                            Add Barangay Staff
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="manage_accounts.php" class="action-card h-100">
                            <i class="bi bi-shield-lock-fill text-warning"></i>
                            Manage Accounts
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="announcement.php" class="action-card h-100">
                            <i class="bi bi-megaphone-fill text-danger"></i>
                            Announcements
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="reports.php" class="action-card h-100">
                            <i class="bi bi-bar-chart-fill text-info"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Recent seniors table ──────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
                <span class="fw-semibold"><i class="bi bi-clock-history text-secondary me-1"></i> Recently Registered Seniors</span>
                <a href="manage_seniors.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Priority Level</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentSeniors && $recentSeniors->num_rows > 0): ?>
                    <?php while ($row = $recentSeniors->fetch_assoc()):
                        $lvl   = (int) $row['priority_level'];
                        [$plabel, $pcolor] = $priorityMap[$lvl] ?? ['Unknown', 'secondary'];
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['gender'] ?? '—'); ?></td>
                        <td><span class="badge bg-<?php echo $pcolor; ?>"><?php echo $plabel; ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $row['is_alive'] === 'yes' ? 'success' : 'secondary'; ?>">
                                <?php echo $row['is_alive'] === 'yes' ? 'Alive' : 'Deceased'; ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No seniors registered yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content-area -->
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>