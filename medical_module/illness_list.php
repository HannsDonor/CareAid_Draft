<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

function illness_priority_badge(int $level): string {
    if ($level >= 5) return '<span class="badge bg-danger">Risk Level 5</span>';
    if ($level === 4) return '<span class="badge bg-warning text-dark">Risk Level 4</span>';
    if ($level === 3) return '<span class="badge bg-info text-dark">Risk Level 3</span>';
    if ($level === 2) return '<span class="badge bg-secondary">Risk Level 2</span>';
    return '<span class="badge bg-success">Risk Level 1</span>';
}

function build_redirect_url(string $search, string $category, string $flag = '', string $msg = ''): string {
    $qs = 'search=' . urlencode($search) . '&category=' . urlencode($category);

    if ($flag !== '') {
        $qs .= '&' . $flag . '=1';
    }
    if ($msg !== '') {
        $qs .= '&msg=' . urlencode($msg);
    }

    return 'illness_list.php?' . $qs;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_illness', 'edit_illness', 'delete_illness'], true)) {
    $action = $_POST['action'];
    $search_return = trim($_POST['search_return'] ?? '');
    $category_return = trim($_POST['category_return'] ?? '');
    $illness_id = (int)($_POST['illness_id'] ?? 0);

    if ($action === 'delete_illness') {
        if ($illness_id <= 0) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Invalid illness selected.'));
            exit;
        }

        $del = $conn->prepare("DELETE FROM illnesses WHERE illness_id = ?");
        if (!$del) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Failed to prepare delete query.'));
            exit;
        }

        $del->bind_param('i', $illness_id);
        $ok = $del->execute();
        $affected = $del->affected_rows;
        $del->close();

        if (!$ok || $affected < 1) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Unable to delete illness. It may be in use.'));
            exit;
        }

        header('Location: ' . build_redirect_url($search_return, $category_return, 'updated', 'Illness deleted successfully.'));
        exit;
    }

    $illness_name = trim($_POST['illness_name'] ?? '');
    $category_name = trim($_POST['category_name'] ?? '');
    $risk_level = (int)($_POST['risk_level'] ?? 1);

    if ($illness_name === '') {
        header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Illness name is required.'));
        exit;
    }

    if ($category_name === '') {
        $category_name = 'Uncategorized';
    }

    if ($risk_level < 1 || $risk_level > 5) {
        $risk_level = 1;
    }

    if ($action === 'add_illness') {
        $dup = $conn->prepare("SELECT illness_id FROM illnesses WHERE illness_name = ? LIMIT 1");
        if ($dup) {
            $dup->bind_param('s', $illness_name);
            $dup->execute();
            $dup->store_result();
            $exists = $dup->num_rows > 0;
            $dup->close();
            if ($exists) {
                header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Illness name already exists.'));
                exit;
            }
        }

        $ins = $conn->prepare("INSERT INTO illnesses (illness_name, category, risk_level) VALUES (?, ?, ?)");
        if (!$ins) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Failed to prepare insert query.'));
            exit;
        }

        $ins->bind_param('ssi', $illness_name, $category_name, $risk_level);
        $ok = $ins->execute();
        $ins->close();

        if (!$ok) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Failed to add illness.'));
            exit;
        }

        header('Location: ' . build_redirect_url($search_return, $category_return, 'updated', 'Illness added successfully.'));
        exit;
    }

    if ($illness_id <= 0) {
        header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Invalid illness selected.'));
        exit;
    }

    $dup = $conn->prepare("SELECT illness_id FROM illnesses WHERE illness_name = ? AND illness_id <> ? LIMIT 1");
    if ($dup) {
        $dup->bind_param('si', $illness_name, $illness_id);
        $dup->execute();
        $dup->store_result();
        $exists = $dup->num_rows > 0;
        $dup->close();
        if ($exists) {
            header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Illness name already exists.'));
            exit;
        }
    }

    $upd = $conn->prepare("UPDATE illnesses SET illness_name = ?, category = ?, risk_level = ? WHERE illness_id = ?");
    if (!$upd) {
        header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Failed to prepare update query.'));
        exit;
    }

    $upd->bind_param('ssii', $illness_name, $category_name, $risk_level, $illness_id);
    $ok = $upd->execute();
    $upd->close();

    if (!$ok) {
        header('Location: ' . build_redirect_url($search_return, $category_return, 'err', 'Failed to update illness.'));
        exit;
    }

    header('Location: ' . build_redirect_url($search_return, $category_return, 'updated', 'Illness updated successfully.'));
    exit;
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$flash_updated = isset($_GET['updated']);
$flash_err = isset($_GET['err']);
$flash_msg = trim($_GET['msg'] ?? '');

$categories = [];
$cat_res = $conn->query("SELECT DISTINCT category FROM illnesses WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($cat_res) {
    while ($row = $cat_res->fetch_assoc()) {
        $categories[] = (string)$row['category'];
    }
}

$sql = "SELECT illness_id, illness_name, category, risk_level FROM illnesses WHERE 1=1";
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $sql .= " AND illness_name LIKE ?";
    $types .= 's';
    $params[] = $like;
}

if ($category !== '') {
    $sql .= " AND category = ?";
    $types .= 's';
    $params[] = $category;
}

$sql .= " ORDER BY category ASC, risk_level DESC, illness_name ASC";

$illness_rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $illness_rows[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>List of Illness - CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
.main-wrap { min-height: 100vh; background: #f0f2f5; }
.topbar { background: #fff; padding: 14px 28px; border-bottom: 1px solid #e3e6ef; display: flex; align-items: center; justify-content: space-between; }
.content-area { padding: 28px; }
.detail-card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.07); }
.filter-card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.06); }
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">List of Illness</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <?php if ($flash_updated): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?php echo htmlspecialchars($flash_msg !== '' ? $flash_msg : 'Illness updated successfully.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_err): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?php echo htmlspecialchars($flash_msg !== '' ? $flash_msg : 'Unable to complete illness action.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card filter-card mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel me-1"></i> Search and Filter</div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Search Illness</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search illness name...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Apply</button>
                    </div>
                    <?php if ($search !== '' || $category !== ''): ?>
                    <div class="col-12">
                        <a href="illness_list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card detail-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clipboard2-heart me-1"></i> Illnesses</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary"><?php echo count($illness_rows); ?></span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addIllnessModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Illness
                    </button>
                </div>
            </div>

            <?php if (empty($illness_rows)): ?>
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                    No illnesses found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Illness Name</th>
                                <th>Category</th>
                                <th>Risk Level</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($illness_rows as $row): ?>
                            <tr>
                                <td class="text-muted small"><?php echo (int)($row['illness_id'] ?? 0); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars((string)($row['illness_name'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['category'] ?? 'Uncategorized')); ?></td>
                                <td><?php echo illness_priority_badge((int)($row['risk_level'] ?? 1)); ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary edit-illness-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editIllnessModal"
                                        data-illness-id="<?php echo (int)($row['illness_id'] ?? 0); ?>"
                                        data-illness-name="<?php echo htmlspecialchars((string)($row['illness_name'] ?? ''), ENT_QUOTES); ?>"
                                        data-category="<?php echo htmlspecialchars((string)($row['category'] ?? 'Uncategorized'), ENT_QUOTES); ?>"
                                        data-risk-level="<?php echo (int)($row['risk_level'] ?? 1); ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this illness?');">
                                        <input type="hidden" name="action" value="delete_illness">
                                        <input type="hidden" name="illness_id" value="<?php echo (int)($row['illness_id'] ?? 0); ?>">
                                        <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                                        <input type="hidden" name="category_return" value="<?php echo htmlspecialchars($category); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="modal fade" id="addIllnessModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> Add Illness</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_illness">
                            <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="category_return" value="<?php echo htmlspecialchars($category); ?>">

                            <div class="mb-3">
                                <label class="form-label">Illness Name *</label>
                                <input type="text" name="illness_name" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <input type="text" name="category_name" class="form-control" list="illnessCategoryOptions" required>
                            </div>

                            <div>
                                <label class="form-label">Risk Level</label>
                                <select name="risk_level" class="form-select">
                                    <option value="1">Risk Level 1</option>
                                    <option value="2">Risk Level 2</option>
                                    <option value="3">Risk Level 3</option>
                                    <option value="4">Risk Level 4</option>
                                    <option value="5">Risk Level 5</option>
                                </select>
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

        <div class="modal fade" id="editIllnessModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Edit Illness</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit_illness">
                            <input type="hidden" name="illness_id" id="editIllnessId" value="">
                            <input type="hidden" name="search_return" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="category_return" value="<?php echo htmlspecialchars($category); ?>">

                            <div class="mb-3">
                                <label class="form-label">Illness Name *</label>
                                <input type="text" name="illness_name" id="editIllnessName" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <input type="text" name="category_name" id="editCategoryName" class="form-control" list="illnessCategoryOptions" required>
                            </div>

                            <div>
                                <label class="form-label">Risk Level</label>
                                <select name="risk_level" id="editRiskLevel" class="form-select">
                                    <option value="1">Risk Level 1</option>
                                    <option value="2">Risk Level 2</option>
                                    <option value="3">Risk Level 3</option>
                                    <option value="4">Risk Level 4</option>
                                    <option value="5">Risk Level 5</option>
                                </select>
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

        <datalist id="illnessCategoryOptions">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const editIllnessButtons = document.querySelectorAll('.edit-illness-btn');
const editIllnessId = document.getElementById('editIllnessId');
const editIllnessName = document.getElementById('editIllnessName');
const editCategoryName = document.getElementById('editCategoryName');
const editRiskLevel = document.getElementById('editRiskLevel');

if (editIllnessButtons.length && editIllnessId && editIllnessName && editCategoryName && editRiskLevel) {
    editIllnessButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
            editIllnessId.value = this.getAttribute('data-illness-id') || '';
            editIllnessName.value = this.getAttribute('data-illness-name') || '';
            editCategoryName.value = this.getAttribute('data-category') || '';
            editRiskLevel.value = this.getAttribute('data-risk-level') || '1';
        });
    });
}
</script>
</body>
</html>