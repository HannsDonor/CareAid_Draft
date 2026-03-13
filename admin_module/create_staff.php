<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth_module/login.php");
    exit;
}

$message      = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name     = trim($_POST['first_name']     ?? '');
    $last_name      = trim($_POST['last_name']      ?? '');
    $middle_name    = trim($_POST['middle_name']    ?? '');
    $birth_date     = !empty($_POST['birth_date'])  ? $_POST['birth_date'] : null;
    $position       = trim($_POST['position']       ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email']          ?? '');
    $address        = trim($_POST['address']        ?? '');
    $password       = trim($_POST['password']       ?? '');

    // Handle profile picture upload
    $profile_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../senior_profile_pics/';   // shared uploads folder
        $file_name  = time() . '_' . basename($_FILES['profile_picture']['name']);
        $target     = $upload_dir . $file_name;
        $check      = getimagesize($_FILES['profile_picture']['tmp_name']);
        if ($check !== false && move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            $profile_path = $file_name;
        }
    }

    if (empty($first_name) || empty($last_name) || empty($password)) {
        $message = "First Name, Last Name, and Password are required.";
    } else {
        // ── Generate unique username ───────────────────────────────────────
        $base_username = strtolower($first_name . $middle_name . $last_name);
        $base_username = preg_replace('/[^a-z0-9]/', '', $base_username);
        if ($base_username === '') {
            $base_username = 'staff';
        }

        $username   = $base_username;
        $chk = $conn->prepare("SELECT account_id FROM accounts WHERE username = ? LIMIT 1");
        if (!$chk) {
            $message = "Error preparing username check: " . $conn->error;
        }

        if (empty($message)) {
            while (true) {
                $chk->bind_param("s", $username);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows === 0) { break; }
                $username = $base_username . rand(100, 999);
                $chk->free_result();
            }
            $chk->close();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // ── Transaction: accounts → barangay_staff_profile ────────────
            $conn->begin_transaction();
            try {
                // 1) Create account
                $role   = 'health_worker';
                $status = 'active';
                $a = $conn->prepare("INSERT INTO accounts (username, password_hash, role, account_status) VALUES (?, ?, ?, ?)");
                if (!$a) { throw new Exception("Account prepare failed: " . $conn->error); }
                $a->bind_param("ssss", $username, $password_hash, $role, $status);
                if (!$a->execute()) { throw new Exception("Account insert failed: " . $a->error); }
                $account_id = (int) $conn->insert_id;
                if ($account_id <= 0) { throw new Exception("Failed to retrieve account_id."); }
                $a->close();

                // 2) Create staff profile
                $p = $conn->prepare("INSERT INTO barangay_staff_profile
                    (account_id, first_name, last_name, middle_name, birth_date, position, contact_number, email, address, profile_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$p) { throw new Exception("Staff profile prepare failed: " . $conn->error); }
                $p->bind_param("isssssssss",
                    $account_id, $first_name, $last_name, $middle_name,
                    $birth_date, $position, $contact_number, $email,
                    $address, $profile_path
                );
                if (!$p->execute()) { throw new Exception("Staff profile insert failed: " . $p->error); }
                $staff_id = (int) $conn->insert_id;

                // Fallback ID lookup
                if ($staff_id <= 0) {
                    $fl = $conn->prepare("SELECT staff_id FROM barangay_staff_profile WHERE account_id = ? ORDER BY staff_id DESC LIMIT 1");
                    if (!$fl) { throw new Exception("Staff ID lookup prepare failed: " . $conn->error); }
                    $fl->bind_param("i", $account_id);
                    $fl->execute();
                    $fl->bind_result($staff_id);
                    $fl->fetch();
                    $fl->close();
                }
                $p->close();

                $conn->commit();
                $message      = "Staff profile created successfully! Username: <strong>" . htmlspecialchars($username) . "</strong> (Staff ID: {$staff_id}, Account ID: {$account_id})";
                $message_type = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Barangay Staff — CareAid</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
body { background: #f0f2f5; min-height: 100vh; }
.page-card {
    max-width: 900px;
    margin: 36px auto;
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    overflow: hidden;
}
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 28px 32px;
}
.section-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #667eea;
    border-bottom: 2px solid #667eea;
    padding-bottom: 6px;
    margin-bottom: 18px;
}
.avatar-wrap {
    width: 130px; height: 130px;
    border-radius: 50%;
    border: 3px dashed #667eea;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    background: #f8f9fa;
    margin: 0 auto 12px;
    cursor: pointer;
}
.avatar-wrap img { width: 100%; height: 100%; object-fit: cover; display: none; }
.avatar-wrap.has-img img           { display: block; }
.avatar-wrap.has-img .avatar-icon  { display: none; }
.avatar-icon { font-size: 48px; color: #ced4da; }
</style>
</head>
<body>

<div class="page-card card">
    <!-- Header -->
    <div class="page-header d-flex align-items-center gap-3">
        <i class="bi bi-person-badge-fill fs-3"></i>
        <div>
            <h5 class="mb-0 fw-bold">Add Barangay Staff</h5>
            <small class="opacity-75">Creates an account and staff profile in one step.</small>
        </div>
        <a href="admin_dashboard.php" class="btn btn-sm btn-light ms-auto">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card-body p-4">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- Profile picture -->
            <div class="text-center mb-4">
                <div class="avatar-wrap" id="avatar_wrap" onclick="document.getElementById('profile_picture').click()">
                    <img id="avatar_preview" src="" alt="Preview">
                    <i class="bi bi-camera-fill avatar-icon"></i>
                </div>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                       class="d-none" onchange="previewAvatar(this)">
                <small class="text-muted d-block">Click to upload profile photo</small>
            </div>

            <!-- Personal Information -->
            <p class="section-title"><i class="bi bi-person-fill me-1"></i>Personal Information</p>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required placeholder="First name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Middle name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required placeholder="Last name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Birth Date</label>
                    <input type="date" name="birth_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Position / Role</label>
                    <select name="position" class="form-select">
                        <option value="">Select Position</option>
                        <option value="Health Worker">Health Worker</option>
                        <option value="Finance Officer">Finance Officer</option>
                        <option value="Barangay Admin">Barangay Admin</option>
                    </select>
                </div>
            </div>

            <!-- Contact Information -->
            <p class="section-title"><i class="bi bi-telephone-fill me-1"></i>Contact Information</p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" placeholder="+63 9XX XXX XXXX">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea>
                </div>
            </div>

            <!-- Account Security -->
            <p class="section-title"><i class="bi bi-shield-lock-fill me-1"></i>Account Security</p>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="password_field" class="form-control" required placeholder="Create a password">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                            <i class="bi bi-eye" id="eye_icon"></i>
                        </button>
                    </div>
                    <small class="text-muted">Username auto-generated: firstname + middlename + lastname</small>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle-fill me-1"></i> Create Staff Account
                </button>
            </div>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatar_preview').src = e.target.result;
            document.getElementById('avatar_wrap').classList.add('has-img');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassword() {
    const f = document.getElementById('password_field');
    const i = document.getElementById('eye_icon');
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
