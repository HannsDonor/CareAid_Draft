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

$status_message = '';
$status_message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_status') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $new_status = strtolower(trim((string)($_POST['status'] ?? '')));
    $allowed_statuses = ['accepted', 'pending', 'cancelled'];

    if ($request_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
        $status_message = 'Invalid status update request.';
        $status_message_type = 'danger';
    } else {
        $conn->begin_transaction();

        $req_stmt = $conn->prepare(
            "SELECT ar.request_id, ar.status, ar.request_type, ar.sub_type, ar.request_date, ar.request_time,
                    sp.account_id, sp.first_name, sp.last_name
             FROM assistance_requests ar
             LEFT JOIN senior_profiles sp ON sp.senior_id = ar.senior_id
             WHERE ar.request_id = ?
             LIMIT 1"
        );

        if (!$req_stmt) {
            $conn->rollback();
            $status_message = 'Unable to prepare status lookup.';
            $status_message_type = 'danger';
        } else {
            $req_stmt->bind_param('i', $request_id);
            $req_stmt->execute();
            $req_res = $req_stmt->get_result();
            $request_row = $req_res ? $req_res->fetch_assoc() : null;
            $req_stmt->close();

            if (!$request_row) {
                $conn->rollback();
                $status_message = 'Assistance request not found.';
                $status_message_type = 'danger';
            } else {
                $old_status = strtolower((string)($request_row['status'] ?? ''));
                $status_changed = false;

                $upd_stmt = $conn->prepare("UPDATE assistance_requests SET status = ? WHERE request_id = ? LIMIT 1");

                if (!$upd_stmt) {
                    $conn->rollback();
                    $status_message = 'Unable to prepare status update.';
                    $status_message_type = 'danger';
                    $updated_ok = false;
                } else {
                    $upd_stmt->bind_param('si', $new_status, $request_id);
                    $updated_ok = $upd_stmt->execute();
                    $upd_stmt->close();
                    $status_changed = $updated_ok && ($old_status !== $new_status);
                }

                if (!$updated_ok) {
                    $conn->rollback();
                    $status_message = 'Failed to update request status.';
                    $status_message_type = 'danger';
                } else {
                        $sender_account_id = (int)($request_row['account_id'] ?? 0);
                        $should_notify = false;
                        if ($sender_account_id > 0) {
                            if (($new_status === 'accepted' || $new_status === 'cancelled') && $status_changed) {
                                $should_notify = true;
                            }
                        }

                        if ($should_notify) {
                            $sender_name = trim((string)($request_row['first_name'] ?? '') . ' ' . (string)($request_row['last_name'] ?? ''));
                            $display_name = $sender_name !== '' ? $sender_name : ('Account #' . $sender_account_id);
                            $request_type = strtolower((string)($request_row['request_type'] ?? 'medical'));
                            if (!in_array($request_type, ['medical', 'financial', 'barangay'], true)) {
                                $request_type = 'medical';
                            }

                            if ($new_status === 'accepted') {
                                $notif_title = 'Assistance Request Accepted';
                                $notif_message = 'Your ' . $request_type . ' assistance request has been accepted.';
                            } elseif ($new_status === 'cancelled') {
                                $notif_title = 'Assistance Request Cancelled';
                                $notif_message = 'Your ' . $request_type . ' assistance request has been cancelled.';
                            } else {
                                $notif_title = 'Assistance Request Update';
                                $notif_message = 'Your ' . $request_type . ' assistance request status is now ' . $new_status . '.';
                            }

                            $notif_stmt = $conn->prepare(
                                "INSERT INTO notifications (account_id, notification_type, assistance_type, title, message)
                                 VALUES (?, 'assistance', ?, ?, ?)"
                            );

                            if (!$notif_stmt) {
                                $conn->rollback();
                                $status_message = 'Status updated but sender notification could not be prepared.';
                                $status_message_type = 'danger';
                            } else {
                                $notif_stmt->bind_param('isss', $sender_account_id, $request_type, $notif_title, $notif_message);
                                if (!$notif_stmt->execute()) {
                                    $notif_stmt->close();
                                    $conn->rollback();
                                    $status_message = 'Status updated but sender notification could not be created.';
                                    $status_message_type = 'danger';
                                } else {
                                    $notif_stmt->close();
                                    $conn->commit();
                                    $status_message = 'Request status updated and notification sent to ' . $display_name . '.';
                                    $status_message_type = 'success';
                                }
                            }
                        } else {
                            $conn->commit();
                            $status_message = 'Request status updated successfully.';
                            $status_message_type = 'success';
                        }
                }
            }
        }
    }
}

$requests = [];
$request_error = '';

$request_sql = "SELECT ar.request_id, ar.senior_id, ar.request_type, ar.sub_type, ar.status, ar.request_date, ar.request_time,
                       sp.account_id, sp.first_name, sp.last_name, sp.address
                FROM assistance_requests ar
                LEFT JOIN senior_profiles sp_map ON sp_map.senior_id = ar.senior_id
                LEFT JOIN senior_profiles sp ON sp.account_id = sp_map.account_id
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

$notif_sql = "SELECT n.notification_id, n.account_id, n.notification_type, n.assistance_type, n.title, n.message, n.status, n.created_at,
                     sp.first_name, sp.last_name
              FROM notifications n
                            LEFT JOIN senior_profiles sp ON sp.account_id = n.account_id
              WHERE n.notification_type = 'assistance'
                                AND n.is_deleted = 0
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

function status_dropdown(int $request_id, string $current_status): string {
    $normalized = strtolower(trim($current_status));
    if ($normalized === 'rejected') {
        $normalized = 'cancelled';
    }
    if (!in_array($normalized, ['accepted', 'pending', 'cancelled'], true)) {
        $normalized = 'pending';
    }

    $accepted_selected = $normalized === 'accepted' ? ' selected' : '';
    $pending_selected = $normalized === 'pending' ? ' selected' : '';
    $cancelled_selected = $normalized === 'cancelled' ? ' selected' : '';

    $status_class = 'status-select status-pending';
    if ($normalized === 'accepted') {
        $status_class = 'status-select status-accepted';
    } elseif ($normalized === 'cancelled') {
        $status_class = 'status-select status-cancelled';
    }

    return '<form method="post" class="status-form">'
        . '<input type="hidden" name="action" value="update_status">'
        . '<input type="hidden" name="request_id" value="' . $request_id . '">'
        . '<div class="status-stack">'
        . '<select name="status" class="form-select form-select-sm ' . $status_class . '">'
        . '<option value="pending"' . $pending_selected . '>Pending</option>'
        . '<option value="accepted"' . $accepted_selected . '>Accepted</option>'
        . '<option value="cancelled"' . $cancelled_selected . '>Cancelled</option>'
        . '</select>'
        . '</div>'
        . '<div class="button-stack">'
        . '<button type="submit" class="btn btn-sm btn-primary status-btn"><i class="bi bi-check2-circle me-1"></i>Save</button>'
        . '</div>'
        . '</form>';
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
.status-col { width: 180px; }
.status-cell { width: 180px; }
.cell-ellipsis {
    max-width: 360px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.status-form {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 5px;
    min-width: 150px;
    background: #f6f8fb;
    border: 1px solid #dfe5ee;
    border-radius: 8px;
    padding: 5px;
}
.status-stack {
    display: grid;
    gap: 4px;
}
.button-stack {
    display: flex;
    gap: 4px;
    align-items: center;
}
.status-select {
    min-width: 100px;
    font-weight: 600;
}
.status-pending {
    background: #f3f6fb;
    border-color: #cfd8e6;
    color: #35506f;
}
.status-accepted {
    background: #e8f4ef;
    border-color: #b8dbc9;
    color: #2f6a4c;
}
.status-cancelled {
    background: #f8ecef;
    border-color: #e6c3cc;
    color: #7a3a46;
}
.status-btn {
    white-space: nowrap;
    align-self: center;
    padding: .26rem .5rem;
}
@media (max-width: 768px) {
    .content-area { padding: 16px; }
    .status-col,
    .status-cell {
        width: auto;
    }
    .status-form {
        grid-template-columns: 1fr;
        min-width: 135px;
    }
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
                <?php if ($status_message !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($status_message_type); ?> mb-3"><?php echo htmlspecialchars($status_message); ?></div>
                <?php endif; ?>
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
                                    <th>Address</th>
                                    <th>Sub Type</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th class="status-col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?php echo (int)$r['request_id']; ?></td>
                                        <td>
                                            <?php
                                            $name = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
                                            echo htmlspecialchars($name !== '' ? $name : ('Account #' . (int)($r['account_id'] ?? 0)));
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($r['address'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($r['sub_type'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$r['request_date']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('g:i A', strtotime((string)$r['request_time']))); ?></td>
                                        <td class="status-cell"><?php echo status_dropdown((int)$r['request_id'], (string)$r['status']); ?></td>
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
                                            echo htmlspecialchars($name !== '' ? $name : ('Account #' . (int)$n['account_id']));
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