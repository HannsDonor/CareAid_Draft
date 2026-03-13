<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

// Load illnesses from DB for health record editing.
$illness_priority_map = [];
$illness_grouped = [];
$illness_sql = "SELECT illness_name, category, risk_level FROM illnesses ORDER BY category ASC, risk_level DESC, illness_name ASC";
$illness_res = $conn->query($illness_sql);
if ($illness_res) {
    while ($ill = $illness_res->fetch_assoc()) {
        $name = trim((string)($ill['illness_name'] ?? ''));
        $category = trim((string)($ill['category'] ?? 'Uncategorized'));
        $risk_level = (int)($ill['risk_level'] ?? 1);

        if ($name === '') {
            continue;
        }
        if ($risk_level < 1 || $risk_level > 5) {
            $risk_level = 1;
        }

        $illness_priority_map[$name] = $risk_level;
        if (!isset($illness_grouped[$category])) {
            $illness_grouped[$category] = [];
        }
        $illness_grouped[$category][] = [
            'name' => $name,
            'risk_level' => $risk_level,
        ];
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
    if ($risk === 'High' || $risk === 'Critical') {
        return '<span class="badge bg-danger risk-badge">' . htmlspecialchars($risk) . '</span>';
    }
    if ($risk === 'Moderate') {
        return '<span class="badge bg-warning text-dark risk-badge">Moderate</span>';
    }
    return '<span class="badge bg-success risk-badge">Low</span>';
}

function resolve_profile_image_name(array $senior): string {
    // Use profile_path column for stored image filename/path.
    $raw = '';
    if (!empty($senior['profile_path'])) {
        $raw = (string)$senior['profile_path'];
    }

    if ($raw === '') {
        return '';
    }

    $raw = str_replace('\\', '/', $raw);
    return basename($raw);
}

function render_illness_options(array $grouped): string {
    if (empty($grouped)) {
        return '<option value="">No illnesses available</option>';
    }

    $html = '<option value="">Select Illness</option>';
    foreach ($grouped as $category => $items) {
        $html .= '<optgroup label="' . htmlspecialchars($category) . '">';
        foreach ($items as $item) {
            $label = $item['name'] . ' (Risk ' . (int)$item['risk_level'] . ')';
            $html .= '<option value="' . htmlspecialchars($item['name']) . '">' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</optgroup>';
    }

    return $html;
}

function priority_to_risk_label(int $priority): string {
    if ($priority >= 5) return 'Critical';
    if ($priority === 4) return 'High';
    if ($priority >= 2) return 'Moderate';
    return 'Low';
}

function recalculate_senior_priority_level(mysqli $conn, int $senior_id, array $illness_priority_map): bool {
    if ($senior_id <= 0) {
        return false;
    }

    $max_priority = 1;

    $sel = $conn->prepare("SELECT chronic_conditions FROM health_records WHERE senior_id = ?");
    if (!$sel) {
        return false;
    }

    $sel->bind_param('i', $senior_id);
    $sel->execute();
    $res = $sel->get_result();

    while ($row = $res->fetch_assoc()) {
        $condition = trim((string)($row['chronic_conditions'] ?? ''));
        $priority = (int)($illness_priority_map[$condition] ?? 1);
        if ($priority < 1 || $priority > 5) {
            $priority = 1;
        }
        if ($priority > $max_priority) {
            $max_priority = $priority;
        }
    }
    $sel->close();

    $upd = $conn->prepare("UPDATE senior_profiles SET priority_level = ? WHERE senior_id = ?");
    if (!$upd) {
        return false;
    }

    $upd->bind_param('ii', $max_priority, $senior_id);
    $ok = $upd->execute();
    $upd->close();

    return $ok;
}

function build_redirect_url(int $senior_id, string $search, string $gender, string $alive, int $priority_min, string $tab, string $flag = '', string $msg = ''): string {
    $qs = 'senior_id=' . $senior_id
        . '&tab=' . urlencode($tab)
        . '&search=' . urlencode($search)
        . '&gender=' . urlencode($gender)
        . '&alive=' . urlencode($alive)
        . '&priority_min=' . $priority_min;

    if ($flag !== '') {
        $qs .= '&' . $flag . '=1';
    }
    if ($msg !== '') {
        $qs .= '&msg=' . urlencode($msg);
    }

    return 'senior_list.php?' . $qs;
}

function normalize_risk_level(string $risk): string {
    $risk = trim($risk);
    if (in_array($risk, ['Low', 'Moderate', 'High', 'Critical'], true)) {
        return $risk;
    }
    return 'Low';
}

// Handle profile update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $senior_id = (int)($_POST['senior_id'] ?? 0);

    $search_return = trim($_POST['search_return'] ?? '');
    $gender_return = $_POST['gender_return'] ?? '';
    $alive_return = $_POST['alive_return'] ?? '';
    $priority_return = (int)($_POST['priority_min_return'] ?? 0);
    $tab_return = $_POST['tab_return'] ?? 'profile';

    if (!in_array($gender_return, ['', 'Male', 'Female'], true)) $gender_return = '';
    if (!in_array($alive_return, ['', 'yes', 'no'], true)) $alive_return = '';
    if ($priority_return < 0 || $priority_return > 5) $priority_return = 0;
    if (!in_array($tab_return, ['profile', 'health', 'checkups'], true)) $tab_return = 'profile';

    if ($senior_id <= 0) {
        header('Location: senior_list.php?err=1&msg=' . urlencode('Invalid senior profile.'));
        exit;
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birth_date = trim($_POST['birth_date'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_alive = $_POST['is_alive'] ?? 'yes';

    if ($first_name === '' || $last_name === '') {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'First name and last name are required.'));
        exit;
    }

    if (!in_array($gender, ['Male', 'Female'], true)) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Please select a valid gender.'));
        exit;
    }

    if (!in_array($is_alive, ['yes', 'no'], true)) {
        $is_alive = 'yes';
    }

    if ($birth_date !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$d || $d->format('Y-m-d') !== $birth_date) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Invalid birth date format.'));
            exit;
        }
    } else {
        $birth_date = null;
    }

    $current_picture = trim($_POST['current_picture'] ?? '');
    $profile_path = $current_picture;

    if (isset($_FILES['profile_path']) && $_FILES['profile_path']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_path']['error'] !== UPLOAD_ERR_OK) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Image upload failed.'));
            exit;
        }

        $tmp = $_FILES['profile_path']['tmp_name'];
        $size = (int)$_FILES['profile_path']['size'];
        $orig_name = $_FILES['profile_path']['name'] ?? '';
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_ext, true)) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Allowed image types: JPG, PNG, WEBP.'));
            exit;
        }

        if ($size > 5 * 1024 * 1024) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Image must be 5MB or smaller.'));
            exit;
        }

        $img_info = @getimagesize($tmp);
        if ($img_info === false) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Invalid image file.'));
            exit;
        }

        $upload_dir = realpath(__DIR__ . '/../senior_profile_pics');
        if ($upload_dir === false) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Image upload folder is missing.'));
            exit;
        }

        $filename = 'senior_' . $senior_id . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $target = $upload_dir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Could not save uploaded image.'));
            exit;
        }

        if ($current_picture !== '') {
            $old_path = $upload_dir . DIRECTORY_SEPARATOR . basename($current_picture);
            if (is_file($old_path) && basename($old_path) !== $filename) {
                @unlink($old_path);
            }
        }

        $profile_path = $filename;
    }

    $upd = $conn->prepare(
        "UPDATE senior_profiles
         SET first_name = ?,
             middle_name = ?,
             last_name = ?,
             gender = ?,
             birth_date = ?,
             contact_number = ?,
             emergency_contact = ?,
             address = ?,
             is_alive = ?,
             profile_path = ?
         WHERE senior_id = ?"
    );

    if (!$upd) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to prepare update query.'));
        exit;
    }

    $upd->bind_param(
        'ssssssssssi',
        $first_name,
        $middle_name,
        $last_name,
        $gender,
        $birth_date,
        $contact_number,
        $emergency_contact,
        $address,
        $is_alive,
        $profile_path,
        $senior_id
    );

    $ok = $upd->execute();
    $upd->close();

    if (!$ok) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to update profile.'));
        exit;
    }

    header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'updated', 'Profile updated successfully.'));
    exit;
}

// Handle health record add/update/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_health_record', 'edit_health_record', 'delete_health_record'], true)) {
    $action = $_POST['action'];
    $senior_id = (int)($_POST['senior_id'] ?? 0);
    $record_id = (int)($_POST['health_record_id'] ?? 0);

    $search_return = trim($_POST['search_return'] ?? '');
    $gender_return = $_POST['gender_return'] ?? '';
    $alive_return = $_POST['alive_return'] ?? '';
    $priority_return = (int)($_POST['priority_min_return'] ?? 0);
    $tab_return = 'health';

    if (!in_array($gender_return, ['', 'Male', 'Female'], true)) $gender_return = '';
    if (!in_array($alive_return, ['', 'yes', 'no'], true)) $alive_return = '';
    if ($priority_return < 0 || $priority_return > 5) $priority_return = 0;

    if ($senior_id <= 0 || ($action !== 'add_health_record' && $record_id <= 0)) {
        header('Location: senior_list.php?err=1&msg=' . urlencode('Invalid health record action.'));
        exit;
    }

    if ($action === 'add_health_record') {
        $condition = trim($_POST['chronic_conditions'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($condition === '') {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Condition is required.'));
            exit;
        }

        if (!isset($illness_priority_map[$condition])) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Please select a valid illness from the list.'));
            exit;
        }

        $risk_level = priority_to_risk_label((int)$illness_priority_map[$condition]);

        $ins_hr = $conn->prepare(
            "INSERT INTO health_records (senior_id, chronic_conditions, notes, risk_level)
             VALUES (?, ?, ?, ?)"
        );

        if (!$ins_hr) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to prepare health record insert query.'));
            exit;
        }

        $ins_hr->bind_param('isss', $senior_id, $condition, $notes, $risk_level);
        $ok = $ins_hr->execute();
        $ins_hr->close();

        if (!$ok) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to add health record.'));
            exit;
        }

        if (!recalculate_senior_priority_level($conn, $senior_id, $illness_priority_map)) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Health record added, but failed to recalculate priority level.'));
            exit;
        }

        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'updated', 'Health record added successfully.'));
        exit;
    }

    if ($action === 'delete_health_record') {
        $del = $conn->prepare("DELETE FROM health_records WHERE health_record_id = ? AND senior_id = ?");
        if (!$del) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to prepare delete query.'));
            exit;
        }

        $del->bind_param('ii', $record_id, $senior_id);
        $ok = $del->execute();
        $affected = $del->affected_rows;
        $del->close();

        if (!$ok || $affected < 1) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Health record could not be deleted.'));
            exit;
        }

        if (!recalculate_senior_priority_level($conn, $senior_id, $illness_priority_map)) {
            header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Health record deleted, but failed to recalculate priority level.'));
            exit;
        }

        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'updated', 'Health record deleted successfully.'));
        exit;
    }

    $condition = trim($_POST['chronic_conditions'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($condition === '') {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Condition is required.'));
        exit;
    }

    if (!isset($illness_priority_map[$condition])) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Please select a valid illness from the list.'));
        exit;
    }

    $risk_level = priority_to_risk_label((int)$illness_priority_map[$condition]);

    $upd_hr = $conn->prepare(
        "UPDATE health_records
         SET chronic_conditions = ?,
             notes = ?,
             risk_level = ?
         WHERE health_record_id = ? AND senior_id = ?"
    );

    if (!$upd_hr) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to prepare health record update query.'));
        exit;
    }

    $upd_hr->bind_param('sssii', $condition, $notes, $risk_level, $record_id, $senior_id);
    $ok = $upd_hr->execute();
    $upd_hr->close();

    if (!$ok) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Failed to update health record.'));
        exit;
    }

    if (!recalculate_senior_priority_level($conn, $senior_id, $illness_priority_map)) {
        header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'err', 'Health record updated, but failed to recalculate priority level.'));
        exit;
    }

    header('Location: ' . build_redirect_url($senior_id, $search_return, $gender_return, $alive_return, $priority_return, $tab_return, 'updated', 'Health record updated successfully.'));
    exit;
}

$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';
$alive = $_GET['alive'] ?? '';
$priority_min = (int)($_GET['priority_min'] ?? 0);
$selected_id = (int)($_GET['senior_id'] ?? 0);
$tab = $_GET['tab'] ?? 'profile';

if (!in_array($gender, ['', 'Male', 'Female'], true)) $gender = '';
if (!in_array($alive, ['', 'yes', 'no'], true)) $alive = '';
if ($priority_min < 0 || $priority_min > 5) $priority_min = 0;
if (!in_array($tab, ['profile', 'health', 'checkups'], true)) $tab = 'profile';

$flash_updated = isset($_GET['updated']);
$flash_err = isset($_GET['err']);
$flash_msg = trim($_GET['msg'] ?? '');

$sql = "SELECT senior_id, first_name, middle_name, last_name, gender, birth_date, is_alive, priority_level
        FROM senior_profiles WHERE 1=1";
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)";
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($gender !== '') {
    $sql .= " AND gender = ?";
    $types .= 's';
    $params[] = $gender;
}
if ($alive !== '') {
    $sql .= " AND is_alive = ?";
    $types .= 's';
    $params[] = $alive;
}
if ($priority_min > 0) {
    $sql .= " AND priority_level >= ?";
    $types .= 'i';
    $params[] = $priority_min;
}

$sql .= " ORDER BY COALESCE(priority_level, 1) DESC, last_name ASC, first_name ASC";

$stmt = $conn->prepare($sql);
$senior_rows = [];
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $senior_rows[] = $row;
    }
    $stmt->close();
}

$selected_senior = null;
$health_records = [];
$checkups = [];

if ($selected_id > 0) {
    $sp = $conn->prepare(
        "SELECT sp.*, a.username, a.account_status
         FROM senior_profiles sp
         LEFT JOIN accounts a ON a.account_id = sp.account_id
         WHERE sp.senior_id = ? LIMIT 1"
    );

    if ($sp) {
        $sp->bind_param('i', $selected_id);
        $sp->execute();
        $selected_senior = $sp->get_result()->fetch_assoc();
        $sp->close();
    }

    if ($selected_senior) {
        $hr = $conn->prepare(
            "SELECT health_record_id, chronic_conditions, notes, risk_level, created_at
             FROM health_records WHERE senior_id = ? ORDER BY created_at DESC"
        );
        if ($hr) {
            $hr->bind_param('i', $selected_id);
            $hr->execute();
            $res = $hr->get_result();
            while ($row = $res->fetch_assoc()) {
                $health_records[] = $row;
            }
            $hr->close();
        }

        $ck = $conn->prepare(
            "SELECT blood_pressure, blood_sugar, heart_rate, risk_level, checkup_date, notes
             FROM checkups WHERE senior_id = ? ORDER BY checkup_date DESC"
        );
        if ($ck) {
            $ck->bind_param('i', $selected_id);
            $ck->execute();
            $res = $ck->get_result();
            while ($row = $res->fetch_assoc()) {
                $checkups[] = $row;
            }
            $ck->close();
        }
    }
}

$qs_filter = 'search=' . urlencode($search)
    . '&gender=' . urlencode($gender)
    . '&alive=' . urlencode($alive)
    . '&priority_min=' . $priority_min;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Senior List - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.main-wrap { min-height: 100vh; background: #f0f2f5; }
.topbar { background: #fff; padding: 14px 28px; border-bottom: 1px solid #e3e6ef; display: flex; align-items: center; justify-content: space-between; }
.content-area { padding: 28px; }

.senior-list-item { border-left: 4px solid transparent; transition: background .12s; }
.senior-list-item.active { background: #eef5ff; border-left-color: #0d6efd; color: #0d6efd; }
.senior-list-item.active .fw-semibold { color: #0d6efd; }
.senior-list-item:hover:not(.active) { background: #f8f9ff; }
.senior-list-scroll { max-height: 360px; overflow-y: auto; }

.detail-card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.07); }
.profile-pic-lg { width: 90px; height: 90px; object-fit: cover; border-radius: 50%; border: 3px solid #dee2e6; }
.profile-pic-placeholder { width: 90px; height: 90px; border-radius: 50%; border: 3px solid #dee2e6; background: #e9ecef; display:flex; align-items:center; justify-content:center; font-size: 2.2rem; color: #adb5bd; }

.profile-field { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: 10px 12px; height: 100%; }
.info-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: 2px; }
.info-value { font-weight: 600; font-size: .95rem; color: #212529; word-break: break-word; }

/* Keep detail area compact and avoid side scrolling */
.compact-profile .card-body { padding: 14px; }
.compact-profile .row { --bs-gutter-x: .75rem; --bs-gutter-y: .75rem; }
.compact-profile .profile-field { padding: 8px 10px; }
.compact-profile .info-value { font-size: .9rem; }
.col-lg-8, .detail-card, .table-responsive { overflow-x: hidden; }

.modal-profile-pic { width: 72px; height: 72px; object-fit: cover; border-radius: 50%; border: 2px solid #dee2e6; }
.bg-pink { background-color: #e83e8c !important; color: #fff !important; }
.risk-badge { display: inline-block; min-width: 90px; text-align: center; }
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">Senior List</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <?php if ($flash_updated): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?php echo htmlspecialchars($flash_msg !== '' ? $flash_msg : 'Profile updated successfully.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_err): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?php echo htmlspecialchars($flash_msg !== '' ? $flash_msg : 'Unable to update profile.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-1"></i> Search and Filter</div>
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-12">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-6">
                                <select name="gender" class="form-select form-select-sm">
                                    <option value="">All Genders</option>
                                    <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="alive" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="yes" <?php echo $alive === 'yes' ? 'selected' : ''; ?>>Alive</option>
                                    <option value="no" <?php echo $alive === 'no' ? 'selected' : ''; ?>>Deceased</option>
                                </select>
                            </div>
                            <div class="col-8">
                                <select name="priority_min" class="form-select form-select-sm">
                                    <option value="0" <?php echo $priority_min === 0 ? 'selected' : ''; ?>>Any Priority</option>
                                    <option value="1" <?php echo $priority_min === 1 ? 'selected' : ''; ?>>Priority >= 1</option>
                                    <option value="2" <?php echo $priority_min === 2 ? 'selected' : ''; ?>>Priority >= 2</option>
                                    <option value="3" <?php echo $priority_min === 3 ? 'selected' : ''; ?>>Priority >= 3</option>
                                    <option value="4" <?php echo $priority_min === 4 ? 'selected' : ''; ?>>Priority >= 4</option>
                                    <option value="5" <?php echo $priority_min === 5 ? 'selected' : ''; ?>>Priority 5 only</option>
                                </select>
                            </div>
                            <div class="col-4 d-grid">
                                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i> Apply</button>
                            </div>
                            <?php if ($search || $gender || $alive || $priority_min): ?>
                            <div class="col-12">
                                <a href="senior_list.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Clear Filters</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-people me-1"></i> Seniors</span>
                        <span class="badge bg-secondary"><?php echo count($senior_rows); ?></span>
                    </div>
                    <div class="list-group list-group-flush senior-list-scroll">
                        <?php if (empty($senior_rows)): ?>
                            <div class="p-4 text-center text-muted"><i class="bi bi-search fs-3 d-block mb-2"></i>No seniors found.</div>
                        <?php else: ?>
                            <?php foreach ($senior_rows as $s): ?>
                                <?php $is_active = ((int)$s['senior_id'] === $selected_id); ?>
                                <a class="list-group-item list-group-item-action senior-list-item <?php echo $is_active ? 'active' : ''; ?>" href="?senior_id=<?php echo (int)$s['senior_id']; ?>&tab=profile&<?php echo $qs_filter; ?>">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ($s['middle_name'] ? ' ' . $s['middle_name'] : '')); ?></div>
                                    <div class="mt-1">
                                        <?php echo priority_badge((int)$s['priority_level']); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if (!$selected_senior): ?>
                    <div class="card detail-card">
                        <div class="card-body text-center p-5 text-muted">
                            <i class="bi bi-person-lines-fill" style="font-size:52px;"></i>
                            <h5 class="mt-3 fw-semibold">Select a Senior</h5>
                            <p class="mb-0">Choose a senior from the list to view profile, health records, and checkup history.</p>
                        </div>
                    </div>
                <?php else:
                    $qs_detail = 'senior_id=' . $selected_id . '&' . $qs_filter;
                    $pic_name = resolve_profile_image_name($selected_senior);
                    $pic_path = '../senior_profile_pics/' . $pic_name;
                    $has_pic = ($pic_name !== '') && is_file(__DIR__ . '/../senior_profile_pics/' . $pic_name);
                    $account_status = strtolower((string)($selected_senior['account_status'] ?? ''));
                    $account_status_class = $account_status === 'active' ? 'bg-success' : ($account_status === 'inactive' ? 'bg-secondary' : 'bg-warning text-dark');
                    $gender_value = $selected_senior['gender'] ?? '';
                    $gender_badge_class = $gender_value === 'Male' ? 'bg-primary' : ($gender_value === 'Female' ? 'bg-pink' : 'bg-light text-dark border');
                ?>
                    <div class="card detail-card mb-3">
                        <div class="card-body d-flex align-items-center gap-4">
                            <?php if ($has_pic): ?>
                                <img src="<?php echo htmlspecialchars($pic_path); ?>" class="profile-pic-lg" alt="Profile">
                            <?php else: ?>
                                <div class="profile-pic-placeholder"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars(trim(($selected_senior['first_name'] ?? '') . ' ' . ($selected_senior['middle_name'] ?? '') . ' ' . ($selected_senior['last_name'] ?? ''))); ?></h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php echo priority_badge((int)($selected_senior['priority_level'] ?? 1)); ?>
                                    <span class="badge bg-<?php echo ($selected_senior['is_alive'] ?? 'yes') === 'yes' ? 'success' : 'secondary'; ?>"><?php echo ($selected_senior['is_alive'] ?? 'yes') === 'yes' ? 'Alive' : 'Deceased'; ?></span>
                                    <span class="badge <?php echo $account_status_class; ?>"><?php echo htmlspecialchars($account_status !== '' ? ucfirst($account_status) : 'No Account Status'); ?></span>
                                    <span class="badge <?php echo $gender_badge_class; ?>"><?php echo htmlspecialchars($gender_value !== '' ? $gender_value : '-'); ?></span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="senior_checkup.php?senior_id=<?php echo $selected_id; ?>" class="btn btn-sm btn-primary"><i class="bi bi-activity me-1"></i> New Checkup</a>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item"><a class="nav-link <?php echo $tab === 'profile' ? 'active' : ''; ?>" href="?<?php echo $qs_detail; ?>&tab=profile"><i class="bi bi-person-fill me-1"></i>Profile</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $tab === 'health' ? 'active' : ''; ?>" href="?<?php echo $qs_detail; ?>&tab=health"><i class="bi bi-journal-medical me-1"></i>Health Records<?php if (count($health_records)) echo ' <span class="badge bg-secondary ms-1">' . count($health_records) . '</span>'; ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $tab === 'checkups' ? 'active' : ''; ?>" href="?<?php echo $qs_detail; ?>&tab=checkups"><i class="bi bi-clipboard2-pulse me-1"></i>Checkups<?php if (count($checkups)) echo ' <span class="badge bg-secondary ms-1">' . count($checkups) . '</span>'; ?></a></li>
                    </ul>

                    <?php if ($tab === 'profile'): ?>
                    <div class="card detail-card compact-profile">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Profile Details</span>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><div class="profile-field"><div class="info-label">First Name</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['first_name'] ?? '-'); ?></div></div></div>
                                <div class="col-md-4"><div class="profile-field"><div class="info-label">Middle Name</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['middle_name'] ?? '-'); ?></div></div></div>
                                <div class="col-md-4"><div class="profile-field"><div class="info-label">Last Name</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['last_name'] ?? '-'); ?></div></div></div>
                                <div class="col-md-6"><div class="profile-field"><div class="info-label">Gender</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['gender'] ?? '-'); ?></div></div></div>
                                <div class="col-md-6"><div class="profile-field"><div class="info-label">Birth Date</div><div class="info-value"><?php if (!empty($selected_senior['birth_date'])) { $dob = new DateTime($selected_senior['birth_date']); $age = $dob->diff(new DateTime())->y; echo htmlspecialchars($dob->format('F j, Y')) . ' <span class="text-muted fw-normal">(' . $age . ' yrs)</span>'; } else { echo '-'; } ?></div></div></div>
                                <div class="col-md-6"><div class="profile-field"><div class="info-label">Contact Number</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['contact_number'] ?? '-'); ?></div></div></div>
                                <div class="col-md-6"><div class="profile-field"><div class="info-label">Emergency Contact</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['emergency_contact'] ?? '-'); ?></div></div></div>
                                <div class="col-12"><div class="profile-field"><div class="info-label">Address</div><div class="info-value"><?php echo htmlspecialchars($selected_senior['address'] ?? '-'); ?></div></div></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($tab === 'health'): ?>
                    <div class="card detail-card">
                        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-journal-medical me-1"></i> Health Records</span>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addHealthRecordModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Illness
                            </button>
                        </div>
                        <?php if (empty($health_records)): ?>
                            <div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-2 d-block mb-2"></i>No health records on file.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Chronic Conditions</th><th>Risk Level</th><th>Notes</th><th>Recorded At</th><th>Edit</th><th>Delete</th></tr></thead>
                                <tbody>
                                    <?php foreach ($health_records as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars(trim((string)($row['chronic_conditions'] ?? '-'))); ?></td>
                                        <td><?php echo risk_badge($row['risk_level'] ?? ''); ?></td>
                                        <td class="text-muted" style="max-width:220px;white-space:pre-wrap;"><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary edit-health-record-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editHealthRecordModal"
                                                data-record-id="<?php echo (int)($row['health_record_id'] ?? 0); ?>"
                                                data-condition="<?php echo htmlspecialchars(trim((string)($row['chronic_conditions'] ?? '')), ENT_QUOTES); ?>"
                                                data-risk="<?php echo htmlspecialchars($row['risk_level'] ?? 'Low', ENT_QUOTES); ?>"
                                                data-notes="<?php echo htmlspecialchars($row['notes'] ?? '', ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this health record?');">
                                                <input type="hidden" name="action" value="delete_health_record">
                                                <input type="hidden" name="senior_id" value="<?php echo $selected_id; ?>">
                                                <input type="hidden" name="health_record_id" value="<?php echo (int)($row['health_record_id'] ?? 0); ?>">
                                                <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="gender_return" value="<?php echo htmlspecialchars($gender); ?>">
                                                <input type="hidden" name="alive_return" value="<?php echo htmlspecialchars($alive); ?>">
                                                <input type="hidden" name="priority_min_return" value="<?php echo $priority_min; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="modal fade" id="editHealthRecordModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Edit Health Record</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_health_record">
                                            <input type="hidden" name="senior_id" value="<?php echo $selected_id; ?>">
                                            <input type="hidden" name="health_record_id" id="editHealthRecordId" value="">
                                            <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                                            <input type="hidden" name="gender_return" value="<?php echo htmlspecialchars($gender); ?>">
                                            <input type="hidden" name="alive_return" value="<?php echo htmlspecialchars($alive); ?>">
                                            <input type="hidden" name="priority_min_return" value="<?php echo $priority_min; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Condition *</label>
                                                <select class="form-select" name="chronic_conditions" id="editConditionInput" required>
                                                    <?php echo render_illness_options($illness_grouped); ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Risk Level</label>
                                                <select class="form-select" id="editRiskLevelInput" disabled>
                                                    <option value="Low">Low</option>
                                                    <option value="Moderate">Moderate</option>
                                                    <option value="High">High</option>
                                                    <option value="Critical">Critical</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" id="editNotesInput" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="modal fade" id="addHealthRecordModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> Add Illness</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="add_health_record">
                                            <input type="hidden" name="senior_id" value="<?php echo $selected_id; ?>">
                                            <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                                            <input type="hidden" name="gender_return" value="<?php echo htmlspecialchars($gender); ?>">
                                            <input type="hidden" name="alive_return" value="<?php echo htmlspecialchars($alive); ?>">
                                            <input type="hidden" name="priority_min_return" value="<?php echo $priority_min; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Condition *</label>
                                                <select class="form-select" name="chronic_conditions" id="addConditionInput" required>
                                                    <?php echo render_illness_options($illness_grouped); ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Risk Level</label>
                                                <select class="form-select" id="addRiskLevelInput" disabled>
                                                    <option value="Low">Low</option>
                                                    <option value="Moderate">Moderate</option>
                                                    <option value="High">High</option>
                                                    <option value="Critical">Critical</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Add</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($tab === 'checkups'): ?>
                    <div class="card detail-card">
                        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-clipboard2-pulse me-1"></i> Checkup History</span>
                            <a href="senior_checkup.php?senior_id=<?php echo $selected_id; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> New Checkup</a>
                        </div>
                        <?php if (empty($checkups)): ?>
                            <div class="card-body text-center text-muted py-5"><i class="bi bi-clipboard2-x fs-2 d-block mb-2"></i>No checkup records found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Date</th><th>Blood Pressure</th><th>Blood Sugar</th><th>Heart Rate</th><th>Risk Level</th><th>Notes</th></tr></thead>
                                <tbody>
                                    <?php foreach ($checkups as $row): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row['checkup_date'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['blood_pressure'] ?? '-'); ?></td>
                                        <td><?php echo $row['blood_sugar'] !== null ? htmlspecialchars((string)$row['blood_sugar']) . ' mg/dL' : '-'; ?></td>
                                        <td><?php echo $row['heart_rate'] !== null ? htmlspecialchars((string)$row['heart_rate']) . ' bpm' : '-'; ?></td>
                                        <td><?php echo risk_badge($row['risk_level'] ?? ''); ?></td>
                                        <td class="text-muted" style="max-width:200px;white-space:pre-wrap;"><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Update Senior Profile</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="update_profile">
                                        <input type="hidden" name="senior_id" value="<?php echo $selected_id; ?>">
                                        <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                                        <input type="hidden" name="gender_return" value="<?php echo htmlspecialchars($gender); ?>">
                                        <input type="hidden" name="alive_return" value="<?php echo htmlspecialchars($alive); ?>">
                                        <input type="hidden" name="priority_min_return" value="<?php echo $priority_min; ?>">
                                        <input type="hidden" name="tab_return" value="profile">
                                        <input type="hidden" name="current_picture" value="<?php echo htmlspecialchars($pic_name); ?>">

                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <img
                                                id="modalProfilePreview"
                                                src="<?php echo $has_pic ? htmlspecialchars($pic_path) : ''; ?>"
                                                class="modal-profile-pic <?php echo $has_pic ? '' : 'd-none'; ?>"
                                                alt="Current profile picture"
                                            >
                                            <div id="modalProfilePlaceholder" class="profile-pic-placeholder <?php echo $has_pic ? 'd-none' : ''; ?>" style="width:72px;height:72px;font-size:1.8rem;"><i class="bi bi-person-fill"></i></div>
                                            <div class="flex-grow-1">
                                                <label class="form-label mb-1">Profile Picture</label>
                                                <input type="file" class="form-control" id="profilePathInput" name="profile_path" accept=".jpg,.jpeg,.png,.webp">
                                                <small class="text-muted">Optional. JPG, PNG, WEBP up to 5MB.</small>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($selected_senior['first_name'] ?? ''); ?>"></div>
                                            <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($selected_senior['middle_name'] ?? ''); ?>"></div>
                                            <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($selected_senior['last_name'] ?? ''); ?>"></div>

                                            <div class="col-md-4"><label class="form-label">Gender *</label><select name="gender" class="form-select" required><option value="Male" <?php echo ($selected_senior['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo ($selected_senior['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option></select></div>
                                            <div class="col-md-4"><label class="form-label">Birth Date</label><input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($selected_senior['birth_date'] ?? ''); ?>"></div>
                                            <div class="col-md-4"><label class="form-label">Status</label><select name="is_alive" class="form-select"><option value="yes" <?php echo ($selected_senior['is_alive'] ?? 'yes') === 'yes' ? 'selected' : ''; ?>>Alive</option><option value="no" <?php echo ($selected_senior['is_alive'] ?? 'yes') === 'no' ? 'selected' : ''; ?>>Deceased</option></select></div>

                                            <div class="col-md-6"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($selected_senior['contact_number'] ?? ''); ?>"></div>
                                            <div class="col-md-6"><label class="form-label">Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($selected_senior['emergency_contact'] ?? ''); ?>"></div>

                                            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($selected_senior['address'] ?? ''); ?></textarea></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const fileInput = document.getElementById('profilePathInput');
const previewImg = document.getElementById('modalProfilePreview');
const placeholder = document.getElementById('modalProfilePlaceholder');

if (fileInput && previewImg && placeholder) {
    fileInput.addEventListener('change', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            return;
        }

        const blobUrl = URL.createObjectURL(file);
        previewImg.src = blobUrl;
        previewImg.classList.remove('d-none');
        placeholder.classList.add('d-none');
    });
}

const editButtons = document.querySelectorAll('.edit-health-record-btn');
const editRecordIdInput = document.getElementById('editHealthRecordId');
const editConditionInput = document.getElementById('editConditionInput');
const editRiskLevelInput = document.getElementById('editRiskLevelInput');
const editNotesInput = document.getElementById('editNotesInput');
const addConditionInput = document.getElementById('addConditionInput');
const addRiskLevelInput = document.getElementById('addRiskLevelInput');
const illnessRiskMap = <?php echo json_encode($illness_priority_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function priorityToRiskLabel(priority) {
    const p = Number(priority || 1);
    if (p >= 5) return 'Critical';
    if (p === 4) return 'High';
    if (p >= 2) return 'Moderate';
    return 'Low';
}

function setSelectByTrimmedValue(selectEl, value) {
    const target = (value || '').trim();
    for (const opt of selectEl.options) {
        if ((opt.value || '').trim() === target) {
            selectEl.value = opt.value;
            return true;
        }
    }
    selectEl.value = '';
    return false;
}

if (addConditionInput && addRiskLevelInput) {
    addConditionInput.addEventListener('change', function () {
        const selected = this.value || '';
        const priority = Object.prototype.hasOwnProperty.call(illnessRiskMap, selected) ? illnessRiskMap[selected] : 1;
        addRiskLevelInput.value = priorityToRiskLabel(priority);
    });
}

if (editButtons.length && editRecordIdInput && editConditionInput && editRiskLevelInput && editNotesInput) {
    editConditionInput.addEventListener('change', function () {
        const selected = this.value || '';
        const priority = Object.prototype.hasOwnProperty.call(illnessRiskMap, selected) ? illnessRiskMap[selected] : 1;
        editRiskLevelInput.value = priorityToRiskLabel(priority);
    });

    editButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
            editRecordIdInput.value = this.getAttribute('data-record-id') || '';
            const condition = (this.getAttribute('data-condition') || '').trim();
            setSelectByTrimmedValue(editConditionInput, condition);

            const priority = Object.prototype.hasOwnProperty.call(illnessRiskMap, condition) ? illnessRiskMap[condition] : 1;
            editRiskLevelInput.value = priorityToRiskLabel(priority);

            editNotesInput.value = this.getAttribute('data-notes') || '';
        });
    });
}
</script>
</body>
</html>
