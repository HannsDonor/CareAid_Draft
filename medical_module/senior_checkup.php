<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

$message = '';
$message_type = 'success';

// ── Handle checkup form submission ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checkup'])) {
    $senior_id      = (int) ($_POST['senior_id'] ?? 0);
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $blood_sugar    = trim($_POST['blood_sugar'] ?? '');
    $heart_rate     = trim($_POST['heart_rate'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');
    $risk_level     = trim($_POST['risk_level'] ?? 'Low');

    // Validate allowed risk_level values
    if (!in_array($risk_level, ['Low', 'Moderate', 'High'], true)) {
        $risk_level = 'Low';
    }

    if ($senior_id <= 0) {
        $message = "Invalid senior selected.";
        $message_type = 'danger';
    } elseif (empty($blood_pressure) && empty($blood_sugar) && empty($heart_rate)) {
        $message = "Please enter at least one measurement (blood pressure, blood sugar, or heart rate).";
        $message_type = 'danger';
    } else {
        $bp_val  = !empty($blood_pressure) ? $blood_pressure : null;
        $bs_val  = !empty($blood_sugar)    ? (float) $blood_sugar : null;
        $hr_val  = !empty($heart_rate)     ? (int) $heart_rate   : null;
        $notes_val = !empty($notes)        ? $notes : null;

        $stmt = $conn->prepare("INSERT INTO checkups (senior_id, blood_pressure, blood_sugar, heart_rate, risk_level, notes, checkup_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $message = "Prepare failed: " . $conn->error;
            $message_type = 'danger';
        } else {
            $stmt->bind_param("isdiss", $senior_id, $bp_val, $bs_val, $hr_val, $risk_level, $notes_val);
            if ($stmt->execute()) {
                $message = "Checkup recorded successfully!";
                $message_type = 'success';
            } else {
                $message = "Error saving checkup: " . $stmt->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// ── URL parameters ─────────────────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$senior_risk = (int)($_GET['senior_risk'] ?? 0);
$view_senior = (int) ($_GET['senior_id'] ?? 0);
$senior_page = (int)($_GET['page'] ?? 1);
$active_tab  = $_GET['tab'] ?? 'checkup';
if (!in_array($active_tab, ['checkup', 'records'], true)) $active_tab = 'checkup';
if ($senior_risk < 0 || $senior_risk > 5) $senior_risk = 0;
if ($senior_page < 1) $senior_page = 1;

// ── Fetch senior list ──────────────────────────────────────────────────────────
$seniors = [];
if ($search !== '' && $senior_risk > 0) {
    $like = '%' . $search . '%';
    $sql = "SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, profile_path, is_alive, priority_level
            FROM senior_profiles
            WHERE (first_name LIKE ? OR last_name LIKE ? OR middle_name LIKE ?)
              AND priority_level = ?
            ORDER BY last_name, first_name";
    $s = $conn->prepare($sql);
    $s->bind_param("sssi", $like, $like, $like, $senior_risk);
} elseif ($search !== '') {
    $like = '%' . $search . '%';
    $sql = "SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, profile_path, is_alive, priority_level
            FROM senior_profiles
            WHERE first_name LIKE ? OR last_name LIKE ? OR middle_name LIKE ?
            ORDER BY last_name, first_name";
    $s = $conn->prepare($sql);
    $s->bind_param("sss", $like, $like, $like);
} elseif ($senior_risk > 0) {
    $sql = "SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, profile_path, is_alive, priority_level
            FROM senior_profiles
            WHERE priority_level = ?
            ORDER BY last_name, first_name";
    $s = $conn->prepare($sql);
    $s->bind_param("i", $senior_risk);
} else {
    $sql = "SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, profile_path, is_alive, priority_level
            FROM senior_profiles
            ORDER BY last_name, first_name";
    $s = $conn->prepare($sql);
}
$s->execute();
$result = $s->get_result();
while ($row = $result->fetch_assoc()) {
    $seniors[] = $row;
}
$s->close();

$seniors_per_page = 4;
$total_seniors = count($seniors);
$total_pages = max(1, (int)ceil($total_seniors / $seniors_per_page));
if ($senior_page > $total_pages) {
    $senior_page = $total_pages;
}
$senior_offset = ($senior_page - 1) * $seniors_per_page;
$paged_seniors = array_slice($seniors, $senior_offset, $seniors_per_page);

function seniorListQueryString(string $search, int $senior_risk, int $page): string {
    $params = [];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($senior_risk > 0) {
        $params['senior_risk'] = $senior_risk;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return http_build_query($params);
}

// ── Fetch selected senior detail ───────────────────────────────────────────────
$senior   = null;
$h_records = [];
$checkups  = [];

if ($view_senior > 0) {
    // Profile
    $ps = $conn->prepare("SELECT sp.*, a.username, a.account_status
        FROM senior_profiles sp
        LEFT JOIN accounts a ON a.account_id = sp.account_id
        WHERE sp.senior_id = ?");
    $ps->bind_param("i", $view_senior);
    $ps->execute();
    $senior = $ps->get_result()->fetch_assoc();
    $ps->close();

    if ($senior) {
        // Health records
        $hs = $conn->prepare("SELECT * FROM health_records WHERE senior_id = ? ORDER BY created_at DESC");
        $hs->bind_param("i", $view_senior);
        $hs->execute();
        $hr = $hs->get_result();
        while ($row = $hr->fetch_assoc()) {
            $h_records[] = $row;
        }
        $hs->close();

        // Past checkups
        $cs = $conn->prepare("SELECT * FROM checkups WHERE senior_id = ? ORDER BY checkup_date DESC LIMIT 20");
        $cs->bind_param("i", $view_senior);
        $cs->execute();
        $cr = $cs->get_result();
        while ($row = $cr->fetch_assoc()) {
            $checkups[] = $row;
        }
        $cs->close();
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function priorityBadge($level) {
    $map = [5 => ['Critical','danger'], 4 => ['High','warning'], 3 => ['Moderate','info'], 2 => ['Low-Moderate','secondary'], 1 => ['Normal','success']];
    [$label, $color] = $map[$level] ?? ['Unknown','secondary'];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}
function riskBadge($risk) {
    $map = ['Low'=>'success','Moderate'=>'warning','High'=>'danger','Critical'=>'danger'];
    $color = $map[$risk] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">{$risk}</span>";
}
function age($dob) {
    return $dob ? (int) (new DateTime($dob))->diff(new DateTime())->y : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Senior Checkup — CareAid</title>
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
.senior-card {
    cursor: pointer;
    border-left: 4px solid transparent;
    transition: background .15s, border-color .15s;
}
.senior-card:hover  { background: #f8f9fa; }
.senior-card.active { background: #e8f0fe; border-left-color: #667eea; }
.senior-picker-card {
    display: flex;
    flex-direction: column;
}
.senior-list-scroll {
    overflow: visible;
}
.avatar {
    width: 44px; height: 44px; border-radius: 50%;
    object-fit: cover; background: #dee2e6;
}
.avatar-placeholder {
    width: 44px; height: 44px; border-radius: 50%;
    background: #dee2e6; display:flex; align-items:center; justify-content:center;
    font-size: 20px; color: #adb5bd;
}
/* ── Live risk indicator ─────────────────────────────────────────────────── */
#risk-indicator {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: .5px;
    transition: color .25s;
}
.guideline-table td, .guideline-table th { font-size: .82rem; }
/* ── Pulse animation for critical ───────────────────────────────────────── */
@keyframes pulse-red { 0%,100%{opacity:1} 50%{opacity:.5} }
.pulse-red { animation: pulse-red 1s infinite; }
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">

    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-primary"></i>Senior Checkup</h5>
            <small class="text-muted">Record and review senior vitals</small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mb-3" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$senior): ?>
<!-- ── Senior search / picker ─────────────────────────────── -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-search text-primary"></i> Search Senior
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-8 col-md-6">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name…"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <select name="senior_risk" class="form-select">
                    <option value="0" <?php echo $senior_risk === 0 ? 'selected' : ''; ?>>All Risk Levels</option>
                    <option value="1" <?php echo $senior_risk === 1 ? 'selected' : ''; ?>>Risk Level 1</option>
                    <option value="2" <?php echo $senior_risk === 2 ? 'selected' : ''; ?>>Risk Level 2</option>
                    <option value="3" <?php echo $senior_risk === 3 ? 'selected' : ''; ?>>Risk Level 3</option>
                    <option value="4" <?php echo $senior_risk === 4 ? 'selected' : ''; ?>>Risk Level 4</option>
                    <option value="5" <?php echo $senior_risk === 5 ? 'selected' : ''; ?>>Risk Level 5</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if ($search || $senior_risk > 0): ?>
                <a href="?" class="btn btn-outline-secondary ms-1"><i class="bi bi-x"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($search || $senior_risk > 0): ?>
        <p class="mt-2 mb-0 text-muted small">
            <?php echo $total_seniors; ?> result(s)
            <?php if ($search): ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
            <?php if ($senior_risk > 0): ?> in <strong>Risk Level <?php echo $senior_risk; ?></strong><?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($paged_seniors)): ?>
<div class="card shadow-sm border-0 senior-picker-card">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-people-fill text-secondary"></i> Select a Senior
    </div>
    <div class="list-group list-group-flush senior-list-scroll">
        <?php foreach ($paged_seniors as $s): ?>
        <?php
            $full = htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']);
            $pic  = $s['profile_path'] ? '../senior_profile_pics/' . htmlspecialchars($s['profile_path']) : '';
            $list_query = seniorListQueryString($search, $senior_risk, $senior_page);
        ?>
        <a href="?senior_id=<?php echo $s['senior_id']; ?>&tab=checkup<?php echo $list_query !== '' ? '&' . $list_query : ''; ?>"
           class="list-group-item list-group-item-action d-flex align-items-center gap-3 senior-card">
            <?php if ($pic): ?>
            <img src="<?php echo $pic; ?>" class="avatar" alt="">
            <?php else: ?>
            <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <div class="fw-semibold"><?php echo $full; ?></div>
                <div class="d-flex gap-1 flex-wrap mt-1">
                    <?php echo priorityBadge((int)$s['priority_level']); ?>
                    <span class="badge bg-<?php echo $s['is_alive']==='yes'?'success':'secondary'; ?>"><?php echo $s['is_alive']==='yes'?'Alive':'Deceased'; ?></span>
                </div>
            </div>
            <i class="bi bi-chevron-right text-muted"></i>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">Page <?php echo $senior_page; ?> of <?php echo $total_pages; ?></small>
        <nav aria-label="Senior list pages">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $senior_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(seniorListQueryString($search, $senior_risk, max(1, $senior_page - 1))); ?>">Previous</a>
                </li>
                <?php for ($page_num = 1; $page_num <= $total_pages; $page_num++): ?>
                <li class="page-item <?php echo $page_num === $senior_page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(seniorListQueryString($search, $senior_risk, $page_num)); ?>"><?php echo $page_num; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $senior_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo htmlspecialchars(seniorListQueryString($search, $senior_risk, min($total_pages, $senior_page + 1))); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card shadow-sm border-0 senior-picker-card">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-people-fill text-secondary"></i> Select a Senior
    </div>
    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted">
        <i class="bi bi-person-lines-fill fs-1 d-block mb-3" style="color:#667eea;opacity:.4;"></i>
        <h5>Select a Senior</h5>
        <p class="small mb-0">Search for a senior above to record a checkup.</p>
    </div>
</div>
<?php endif; ?>

<?php else: ?>

<!-- ── Tab navigation ─────────────────────────────────────── -->
<?php $base = '?senior_id='.$view_senior.($search?'&search='.urlencode($search):'').($senior_risk > 0 ? '&senior_risk='.(int)$senior_risk : '').($senior_page > 1 ? '&page='.(int)$senior_page : ''); ?>

<!-- Senior name bar -->
<div class="d-flex align-items-center gap-3 mb-3">
    <i class="bi bi-person-circle fs-3 text-primary"></i>
    <div>
        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($senior['first_name'].' '.$senior['middle_name'].' '.$senior['last_name']); ?></h5>
        <div class="d-flex gap-1 flex-wrap mt-1">
            <?php echo priorityBadge((int)$senior['priority_level']); ?>
            <span class="badge bg-<?php echo $senior['is_alive']==='yes'?'success':'secondary'; ?>"><?php echo $senior['is_alive']==='yes'?'Alive':'Deceased'; ?></span>
        </div>
    </div>
    <a href="?<?php echo htmlspecialchars(seniorListQueryString($search, $senior_risk, $senior_page)); ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-arrow-left"></i> Back to list</a>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab==='checkup'?'active':''; ?>"
           href="<?php echo $base; ?>&tab=checkup">
            <i class="bi bi-activity"></i> Checkup
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab==='records'?'active':''; ?>"
           href="<?php echo $base; ?>&tab=records">
            <i class="bi bi-clock-history"></i> Checkup Records
            <?php if ($checkups): ?>
            <span class="badge bg-secondary ms-1"><?php echo count($checkups); ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>



<!-- ════════════════ TAB: CHECKUP ════════════════ -->
<?php if ($active_tab === 'checkup'): ?>
<div class="row g-4">

    <!-- ── New checkup form ─────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-activity text-primary"></i> New Checkup
                <span class="text-muted fw-normal ms-1" style="font-size:.85rem;">
                    — <?php echo htmlspecialchars($senior['first_name'].' '.$senior['last_name']); ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="?senior_id=<?php echo $view_senior; ?>&tab=checkup<?php echo $search?'&search='.urlencode($search):''; ?><?php echo $senior_risk > 0 ? '&senior_risk='.(int)$senior_risk : ''; ?>">
                    <input type="hidden" name="senior_id" value="<?php echo $view_senior; ?>">
                    <input type="hidden" name="submit_checkup" value="1">

                    <!-- Blood Pressure -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-droplet-half text-danger"></i> Blood Pressure
                            <small class="text-muted fw-normal">(systolic/diastolic, e.g. 120/80)</small>
                        </label>
                        <input type="text" name="blood_pressure" id="inp_bp" class="form-control"
                               placeholder="e.g. 120/80" oninput="updateRisk()" autocomplete="off">
                    </div>

                    <!-- Blood Sugar -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-moisture text-warning"></i> Blood Sugar
                            <small class="text-muted fw-normal">(mg/dL)</small>
                        </label>
                        <input type="number" name="blood_sugar" id="inp_bs" class="form-control"
                               placeholder="e.g. 95" min="0" step="0.01" oninput="updateRisk()">
                    </div>

                    <!-- Heart Rate -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-heart-pulse text-danger"></i> Heart Rate
                            <small class="text-muted fw-normal">(bpm)</small>
                        </label>
                        <input type="number" name="heart_rate" id="inp_hr" class="form-control"
                               placeholder="e.g. 72" min="0" oninput="updateRisk()">
                    </div>

                    <!-- Live Risk Indicator -->
                    <div class="mb-3 p-3 rounded border" id="risk-box" style="background:#f8f9fa;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold text-muted">Computed Risk Level:</span>
                            <span id="risk-indicator" class="text-secondary">—</span>
                        </div>
                        <div id="risk-reason" class="text-muted mt-1" style="font-size:.8rem;"></div>
                    </div>
                    <input type="hidden" name="risk_level" id="risk_level_input" value="Low">

                    <!-- Notes -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-journal-text"></i> Notes
                        </label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional observations…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle-fill"></i> Save Checkup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Guidelines ───────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle-fill text-info"></i> Reference Guidelines
                <span class="badge bg-secondary ms-2" style="font-size:.7rem;">Informational only</span>
            </div>
            <div class="card-body p-2">
                <p class="text-muted small px-2 mb-2">
                    These are general reference ranges. They are <strong>not prescriptive</strong> and should not replace professional medical advice.
                </p>

                <!-- Blood Pressure table -->
                <p class="fw-semibold mb-1 px-2 small"><i class="bi bi-droplet-half text-danger"></i> Blood Pressure (mmHg)</p>
                <table class="table table-sm table-bordered guideline-table mb-3">
                    <thead class="table-light"><tr><th>Category</th><th>Systolic</th><th>Diastolic</th><th>Risk</th></tr></thead>
                    <tbody>
                        <tr><td>Normal</td><td>&lt; 120</td><td>&lt; 80</td><td><span class="badge bg-success">Low</span></td></tr>
                        <tr><td>Elevated</td><td>120–129</td><td>&lt; 80</td><td><span class="badge bg-warning text-dark">Moderate</span></td></tr>
                        <tr><td>High Stage 1</td><td>130–139</td><td>80–89</td><td><span class="badge bg-warning text-dark">Moderate</span></td></tr>
                        <tr><td>High Stage 2</td><td>≥ 140</td><td>≥ 90</td><td><span class="badge bg-danger">High</span></td></tr>
                    </tbody>
                </table>

                <!-- Blood Sugar table -->
                <p class="fw-semibold mb-1 px-2 small"><i class="bi bi-moisture text-warning"></i> Blood Sugar (mg/dL, fasting)</p>
                <table class="table table-sm table-bordered guideline-table mb-3">
                    <thead class="table-light"><tr><th>Category</th><th>Range</th><th>Risk</th></tr></thead>
                    <tbody>
                        <tr><td>Normal</td><td>70 – 99</td><td><span class="badge bg-success">Low</span></td></tr>
                        <tr><td>Pre-diabetic</td><td>100 – 125</td><td><span class="badge bg-warning text-dark">Moderate</span></td></tr>
                        <tr><td>Diabetic</td><td>≥ 126</td><td><span class="badge bg-danger">High</span></td></tr>
                        <tr><td>Hypoglycemia</td><td>&lt; 70</td><td><span class="badge bg-danger">High</span></td></tr>
                    </tbody>
                </table>

                <!-- Heart Rate table -->
                <p class="fw-semibold mb-1 px-2 small"><i class="bi bi-heart-pulse text-danger"></i> Heart Rate (bpm, resting)</p>
                <table class="table table-sm table-bordered guideline-table mb-0">
                    <thead class="table-light"><tr><th>Category</th><th>Range</th><th>Risk</th></tr></thead>
                    <tbody>
                        <tr><td>Normal</td><td>60 – 100</td><td><span class="badge bg-success">Low</span></td></tr>
                        <tr><td>Slightly Low / High</td><td>50–59 or 101–110</td><td><span class="badge bg-warning text-dark">Moderate</span></td></tr>
                        <tr><td>Abnormal</td><td>&lt; 50 or &gt; 110</td><td><span class="badge bg-danger">High</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div><!-- /row -->
<?php endif; ?>

<!-- ════════════════ TAB: CHECKUP RECORDS ════════════════ -->
<?php if ($active_tab === 'records'): ?>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history text-primary"></i>
        Checkup Records — <?php echo htmlspecialchars($senior['first_name'].' '.$senior['last_name']); ?>
    </div>
    <?php if (empty($checkups)): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-clipboard-x fs-1 d-block mb-2 opacity-25"></i>
        No checkup records on file for this senior.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Blood Pressure</th>
                    <th>Blood Sugar (mg/dL)</th>
                    <th>Heart Rate (bpm)</th>
                    <th>Risk Level</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($checkups as $i => $ck): ?>
            <tr>
                <td class="text-muted small"><?php echo $i + 1; ?></td>
                <td class="small"><?php echo date('M d, Y H:i', strtotime($ck['checkup_date'])); ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($ck['blood_pressure'] ?? '—'); ?></td>
                <td><?php echo $ck['blood_sugar'] !== null ? $ck['blood_sugar'] : '—'; ?></td>
                <td><?php echo $ck['heart_rate'] !== null ? $ck['heart_rate'] : '—'; ?></td>
                <td><?php echo riskBadge($ck['risk_level']); ?></td>
                <td class="text-muted small"><?php echo htmlspecialchars($ck['notes'] ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; // senior selected ?>
    </div><!-- /content-area -->
</div><!-- /mednav-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Live risk calculation ─────────────────────────────────────────────────────
function updateRisk() {
    const bpRaw = document.getElementById('inp_bp').value.trim();
    const bs    = parseFloat(document.getElementById('inp_bs').value);
    const hr    = parseInt(document.getElementById('inp_hr').value);

    let risks   = [];   // collect individual risks
    let reasons = [];

    // Blood Pressure
    const bpMatch = bpRaw.match(/^(\d+)\s*\/\s*(\d+)$/);
    if (bpMatch) {
        const sys = parseInt(bpMatch[1]);
        const dia = parseInt(bpMatch[2]);
        if (sys >= 140 || dia >= 90) {
            risks.push('High');
            reasons.push('BP: High Stage 2 (≥ 140/90)');
        } else if ((sys >= 130 && sys <= 139) || (dia >= 80 && dia <= 89)) {
            risks.push('Moderate');
            reasons.push('BP: High Stage 1 (130–139 / 80–89)');
        } else if (sys >= 120 && sys <= 129 && dia < 80) {
            risks.push('Moderate');
            reasons.push('BP: Elevated (120–129 / <80)');
        } else {
            risks.push('Low');
            reasons.push('BP: Normal (<120/80)');
        }
    }

    // Blood Sugar
    if (!isNaN(bs)) {
        if (bs < 70 || bs >= 126) {
            risks.push('High');
            reasons.push(bs < 70 ? 'Sugar: Hypoglycemia (<70)' : 'Sugar: Diabetic range (≥126)');
        } else if (bs >= 100) {
            risks.push('Moderate');
            reasons.push('Sugar: Pre-diabetic range (100–125)');
        } else {
            risks.push('Low');
            reasons.push('Sugar: Normal (70–99)');
        }
    }

    // Heart Rate
    if (!isNaN(hr)) {
        if (hr < 50 || hr > 110) {
            risks.push('High');
            reasons.push(hr < 50 ? 'HR: Too low (<50 bpm)' : 'HR: Too high (>110 bpm)');
        } else if ((hr >= 50 && hr <= 59) || (hr >= 101 && hr <= 110)) {
            risks.push('Moderate');
            reasons.push('HR: Slightly outside normal range');
        } else {
            risks.push('Low');
            reasons.push('HR: Normal (60–100 bpm)');
        }
    }

    const indicator = document.getElementById('risk-indicator');
    const riskBox   = document.getElementById('risk-box');
    const riskInput = document.getElementById('risk_level_input');
    const reasonEl  = document.getElementById('risk-reason');

    if (risks.length === 0) {
        indicator.textContent = '—';
        indicator.className   = 'text-secondary';
        riskBox.style.background = '#f8f9fa';
        riskInput.value = 'Low';
        reasonEl.textContent = '';
        return;
    }

    // Highest risk wins
    let final = 'Low';
    if (risks.includes('High'))     final = 'High';
    else if (risks.includes('Moderate')) final = 'Moderate';

    riskInput.value = final;
    indicator.textContent = final;
    reasonEl.textContent  = reasons.join(' · ');

    if (final === 'High') {
        indicator.className      = 'text-danger pulse-red';
        riskBox.style.background = '#fff5f5';
    } else if (final === 'Moderate') {
        indicator.className      = 'text-warning fw-bold';
        riskBox.style.background = '#fffbea';
    } else {
        indicator.className      = 'text-success fw-bold';
        riskBox.style.background = '#f0fff4';
    }
}
</script>
</body>
</html>
