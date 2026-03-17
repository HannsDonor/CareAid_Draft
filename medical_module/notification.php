<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['account_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db_config/connection_db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'message' => 'Database unavailable']);
    exit;
}

$account_id = (int)($_SESSION['account_id'] ?? 0);

$action = (string)($_POST['action'] ?? '');
if (!in_array($action, ['mark_read', 'remove'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
if ($notification_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid notification id']);
    exit;
}

if ($action === 'mark_read') {
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ? AND notification_type = 'assistance' AND account_id = ? AND is_deleted = 0 LIMIT 1");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => 'Failed to prepare read update']);
        exit;
    }
} else {
    $stmt = $conn->prepare("UPDATE notifications SET is_deleted = 1 WHERE notification_id = ? AND notification_type = 'assistance' AND account_id = ? AND is_deleted = 0 LIMIT 1");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => 'Failed to prepare remove']);
        exit;
    }
}

$stmt->bind_param('ii', $notification_id, $account_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Failed to process notification']);
    exit;
}

$unread_count = 0;
$cnt_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE notification_type = 'assistance' AND account_id = ? AND is_deleted = 0 AND status = 'unread'");
if ($cnt_stmt) {
    $cnt_stmt->bind_param('i', $account_id);
    $cnt_stmt->execute();
    $cnt_result = $cnt_stmt->get_result();
    if ($cnt_result) {
        $cnt_row = $cnt_result->fetch_assoc();
        $unread_count = (int)($cnt_row['c'] ?? 0);
    }
    $cnt_stmt->close();
}

echo json_encode([
    'ok' => true,
    'action' => $action,
    'unread_count' => $unread_count,
]);
