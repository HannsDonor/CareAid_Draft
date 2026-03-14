<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth_module/login.php');
    exit;
}

$role = (string)($_SESSION['role'] ?? '');
if (!in_array($role, ['health_worker', 'admin'], true)) {
    header('Location: ../auth_module/login.php');
    exit;
}

$requests = [];
$request_error = '';

$request_sql = "SELECT ar.request_id, ar.senior_id, ar.request_type, ar.sub_type, ar.status, ar.request_date, ar.request_time,
                       sp.first_name, sp.last_name
                FROM assistance_requests ar
                LEFT JOIN senior_profiles sp ON sp.senior_id = ar.senior_id
                ORDER BY ar.request_date DESC, ar.request_time DESC, ar.request_id DESC";

$request_res = $conn->query($request_sql);
if ($request_res) {
    while ($row = $request_res->fetch_assoc()) {
        $requests[] = $row;
    }
} else {
    $request_error = 'Unable to load assistance requests table.';
}

/*
$notifications = [];
$notification_error = '';

$notif_sql = "SELECT n.notification_id, n.senior_id, n.notification_type, n.assistance_type, n.title, n.message, n.status, n.created_at,
                     sp.first_name, sp.last_name
              FROM notifications n
              LEFT JOIN senior_profiles sp ON sp.senior_id = n.senior_id
              WHERE n.notification_type = 'assistance'
              ORDER BY n.created_at DESC, n.notification_id DESC";

$notif_res = $conn->query($notif_sql);
if ($notif_res) {
    while ($row = $notif_res->fetch_assoc()) {
        $notifications[] = $row;
    }
} else {
    $notification_error = 'Unable to load notifications table.';
}
*/

function req_badge(string $status): string {
    $status = strtolower(trim($status));
    $cls = 'secondary';
    if ($status === 'pending') {
        $cls = 'warning text-dark';
    } elseif ($status === 'accepted') {
        $cls = 'primary';
    } elseif ($status === 'in_progress') {
        $cls = 'info text-dark';
    } elseif ($status === 'completed') {
        $cls = 'success';
    } elseif ($status === 'cancelled') {
        $cls = 'danger';
    }
    return '<span class="badge bg-' . $cls . '">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

/*
function notif_badge(string $status): string {
    $status = strtolower(trim($status));
    if ($status === 'read') {
        return '<span class="badge bg-success">Read</span>';
    }
    return '<span class="badge bg-warning text-dark">Unread</span>';
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Assistance Requests — CareAid</title>
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
.card-box { border: 0; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.table thead th { font-size: .76rem; text-transform: uppercase; letter-spacing: .03em; }
.cell-ellipsis {
    max-width: 360px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 768px) {
    .content-area { padding: 16px; }
}
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">Medical Assistance Requests</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <div class="card card-box mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard2-pulse me-2"></i>Assistance Requests</span>
                <span class="badge bg-secondary"><?php echo count($requests); ?> total</span>
            </div>
            <div class="card-body">
                <?php if ($request_error !== ''): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($request_error); ?></div>
                <?php elseif (count($requests) === 0): ?>
                    <div class="text-muted">No assistance requests found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Senior</th>
                                    <th>Type</th>
                                    <th>Sub Type</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?php echo (int)$r['request_id']; ?></td>
                                        <td>
                                            <?php
                                            $name = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
                                            echo htmlspecialchars($name !== '' ? $name : ('Senior #' . (int)$r['senior_id']));
                                            ?>
                                        </td>
                                        <td class="text-capitalize"><?php echo htmlspecialchars((string)$r['request_type']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['sub_type'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$r['request_date']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('g:i A', strtotime((string)$r['request_time']))); ?></td>
                                        <td><?php echo req_badge((string)$r['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!--
        <div class="card card-box">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell me-2"></i>Assistance Notifications</span>
                <span class="badge bg-secondary"><?php echo count($notifications); ?> total</span>
            </div>
            <div class="card-body">
                <?php if ($notification_error !== ''): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($notification_error); ?></div>
                <?php elseif (count($notifications) === 0): ?>
                    <div class="text-muted">No assistance notifications found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Senior</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $n): ?>
                                    <tr>
                                        <td><?php echo (int)$n['notification_id']; ?></td>
                                        <td>
                                            <?php
                                            $name = trim((string)($n['first_name'] ?? '') . ' ' . (string)($n['last_name'] ?? ''));
                                            echo htmlspecialchars($name !== '' ? $name : ('Senior #' . (int)$n['senior_id']));
                                            ?>
                                        </td>
                                        <td class="text-capitalize"><?php echo htmlspecialchars((string)$n['assistance_type']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$n['title']); ?></td>
                                        <td class="cell-ellipsis" title="<?php echo htmlspecialchars((string)$n['message']); ?>"><?php echo htmlspecialchars((string)$n['message']); ?></td>
                                        <td><?php echo notif_badge((string)$n['status']); ?></td>
                                        <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$n['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        -->
    </div>
</div>
</body>
</html>