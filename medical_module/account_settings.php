<?php
session_start();
include '../db_config/connection_db.php';

if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth_module/login.php');
    exit;
}

$account_id = (int)$_SESSION['account_id'];
$message = '';
$message_type = 'danger';

function get_account_and_profile(mysqli $conn, int $account_id): array {
    $result = [
        'account' => null,
        'profile' => null,
    ];

    $a = $conn->prepare('SELECT account_id, username, role, account_status FROM accounts WHERE account_id = ? LIMIT 1');
    if ($a) {
        $a->bind_param('i', $account_id);
        $a->execute();
        $resA = $a->get_result();
        $result['account'] = $resA ? $resA->fetch_assoc() : null;
        $a->close();
    }

    $p = $conn->prepare('SELECT * FROM barangay_staff_profile WHERE account_id = ? LIMIT 1');
    if ($p) {
        $p->bind_param('i', $account_id);
        $p->execute();
        $resP = $p->get_result();
        $result['profile'] = $resP ? $resP->fetch_assoc() : null;
        $p->close();
    }

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $birth_date     = trim($_POST['birth_date'] ?? '');
    $position       = trim($_POST['position'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $new_password   = trim($_POST['new_password'] ?? '');
    $confirm_pass   = trim($_POST['confirm_password'] ?? '');

    if ($first_name === '' || $last_name === '' || $username === '') {
        $message = 'First Name, Last Name, and Username are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $message = 'Invalid username format.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please provide a valid email address.';
    } elseif ($new_password !== '' && strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_pass) {
        $message = 'Password confirmation does not match.';
    } else {
        $current = get_account_and_profile($conn, $account_id);
        if (empty($current['account'])) {
            $message = 'Account not found.';
        } else {
            $profile_path = $current['profile']['profile_path'] ?? null;

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    $message = 'Profile image upload failed.';
                } else {
                    $tmp_name = $_FILES['profile_picture']['tmp_name'];
                    $img_info = @getimagesize($tmp_name);
                    if ($img_info === false) {
                        $message = 'Uploaded file is not a valid image.';
                    } else {
                        $upload_dir = realpath(__DIR__ . '/../senior_profile_pics');
                        if ($upload_dir === false) {
                            $message = 'Upload directory not found.';
                        } else {
                            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'] ?? '', PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                                $message = 'Allowed image types: jpg, jpeg, png, webp, gif.';
                            } else {
                                $safe_name = 'staff_' . $account_id . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                                $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
                                if (!move_uploaded_file($tmp_name, $target)) {
                                    $message = 'Failed to save uploaded profile image.';
                                } else {
                                    $profile_path = $safe_name;
                                }
                            }
                        }
                    }
                }
            }

            if ($message === '') {
                $dup = $conn->prepare('SELECT account_id FROM accounts WHERE username = ? AND account_id <> ? LIMIT 1');
                if (!$dup) {
                    $message = 'Unable to validate username uniqueness.';
                } else {
                    $dup->bind_param('si', $username, $account_id);
                    $dup->execute();
                    $dup->store_result();
                    if ($dup->num_rows > 0) {
                        $message = 'Username is already taken. Please choose another.';
                    }
                    $dup->close();
                }
            }

            if ($message === '') {
                $conn->begin_transaction();
                try {
                    if ($new_password !== '') {
                        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $acc = $conn->prepare('UPDATE accounts SET username = ?, password_hash = ? WHERE account_id = ? LIMIT 1');
                        if (!$acc) {
                            throw new Exception('Failed to prepare account update.');
                        }
                        $acc->bind_param('ssi', $username, $password_hash, $account_id);
                    } else {
                        $acc = $conn->prepare('UPDATE accounts SET username = ? WHERE account_id = ? LIMIT 1');
                        if (!$acc) {
                            throw new Exception('Failed to prepare account update.');
                        }
                        $acc->bind_param('si', $username, $account_id);
                    }

                    if (!$acc->execute()) {
                        throw new Exception('Failed to update account settings.');
                    }
                    $acc->close();

                    $exists = !empty($current['profile']);
                    if ($exists) {
                        $prof = $conn->prepare('UPDATE barangay_staff_profile SET first_name = ?, last_name = ?, middle_name = ?, birth_date = ?, position = ?, contact_number = ?, email = ?, address = ?, profile_path = ? WHERE account_id = ? LIMIT 1');
                        if (!$prof) {
                            throw new Exception('Failed to prepare profile update.');
                        }
                        $birth = $birth_date !== '' ? $birth_date : null;
                        $prof->bind_param(
                            'sssssssssi',
                            $first_name,
                            $last_name,
                            $middle_name,
                            $birth,
                            $position,
                            $contact_number,
                            $email,
                            $address,
                            $profile_path,
                            $account_id
                        );
                    } else {
                        $prof = $conn->prepare('INSERT INTO barangay_staff_profile (first_name, last_name, middle_name, birth_date, position, contact_number, email, address, profile_path, account_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        if (!$prof) {
                            throw new Exception('Failed to prepare profile insert.');
                        }
                        $birth = $birth_date !== '' ? $birth_date : null;
                        $prof->bind_param(
                            'sssssssssi',
                            $first_name,
                            $last_name,
                            $middle_name,
                            $birth,
                            $position,
                            $contact_number,
                            $email,
                            $address,
                            $profile_path,
                            $account_id
                        );
                    }

                    if (!$prof->execute()) {
                        throw new Exception('Failed to save staff profile details.');
                    }
                    $prof->close();

                    $conn->commit();
                    $_SESSION['username'] = $username;
                    $message = 'Account settings updated successfully.';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = $e->getMessage();
                }
            }
        }
    }
}

$data = get_account_and_profile($conn, $account_id);
$account = $data['account'] ?? [];
$profile = $data['profile'] ?? [];

$first_name_val = htmlspecialchars($profile['first_name'] ?? '');
$middle_name_val = htmlspecialchars($profile['middle_name'] ?? '');
$last_name_val = htmlspecialchars($profile['last_name'] ?? '');
$birth_date_val = htmlspecialchars($profile['birth_date'] ?? '');
$position_val = htmlspecialchars($profile['position'] ?? '');
$contact_val = htmlspecialchars($profile['contact_number'] ?? '');
$email_val = htmlspecialchars($profile['email'] ?? '');
$address_val = htmlspecialchars($profile['address'] ?? '');
$gender_val = htmlspecialchars($profile['gender'] ?? 'Not specified');
$username_val = htmlspecialchars($account['username'] ?? ($_SESSION['username'] ?? ''));
$role_val = htmlspecialchars($account['role'] ?? 'health_worker');
$status_val = htmlspecialchars($account['account_status'] ?? 'active');
$profile_pic_name = $profile['profile_path'] ?? '';
$profile_pic_url = $profile_pic_name !== '' ? '../senior_profile_pics/' . rawurlencode($profile_pic_name) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Settings - CareAid</title>
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
.settings-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,.07);
}
.settings-card .card-body { padding: 16px; }
.settings-card .form-label {
    font-size: .82rem;
    margin-bottom: .2rem;
}
.settings-card .form-control,
.settings-card .form-select {
    padding: .35rem .55rem;
    font-size: .9rem;
    height: 38px;
    line-height: 1.2;
}
.settings-card textarea.form-control {
    height: 38px;
    min-height: 38px;
    resize: vertical;
}
.avatar-wrap {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #e9ecef;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color .2s ease, transform .2s ease;
}
.avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.avatar-wrap:hover {
    border-color: #0d6efd;
    transform: translateY(-1px);
}
</style>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-gear-fill me-2 text-primary"></i>Account Settings</h5>
            <small class="text-muted">View and edit your profile and account credentials</small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card settings-card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="avatar-wrap" id="avatarTrigger" title="Click to change profile picture">
                    <?php if ($profile_pic_url !== ''): ?>
                        <img id="profilePreviewImg" src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <img id="profilePreviewImg" src="" alt="Profile Picture" class="d-none">
                        <i id="profilePreviewIcon" class="bi bi-person-circle fs-1 text-secondary"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold"><?php echo trim($first_name_val . ' ' . $last_name_val) !== '' ? trim($first_name_val . ' ' . $last_name_val) : 'Staff Profile'; ?></h6>
                    <div class="text-muted small">Position: <?php echo $position_val !== '' ? $position_val : 'Not set'; ?></div>
                    <div class="text-muted small">Status: <?php echo $status_val; ?></div>
                </div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="card-header bg-white fw-semibold py-2">
                <i class="bi bi-person-gear me-1"></i> Edit Profile Details
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-2">
                        <input type="hidden" name="action" value="save_profile">
                        <input type="file" id="profilePictureInput" name="profile_picture" class="d-none" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo $first_name_val; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="<?php echo $middle_name_val; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo $last_name_val; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Birth Date</label>
                            <input type="date" name="birth_date" class="form-control" value="<?php echo $birth_date_val; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" value="<?php echo $contact_val; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <input type="text" class="form-control" value="<?php echo $gender_val; ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $email_val; ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $address_val; ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?php echo $username_val; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                </form>
            </div>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const trigger = document.getElementById('avatarTrigger');
    const fileInput = document.getElementById('profilePictureInput');
    const previewImg = document.getElementById('profilePreviewImg');
    const previewIcon = document.getElementById('profilePreviewIcon');

    if (!trigger || !fileInput) {
        return;
    }

    trigger.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            if (previewImg) {
                previewImg.src = event.target && event.target.result ? event.target.result : '';
                previewImg.classList.remove('d-none');
            }
            if (previewIcon) {
                previewIcon.classList.add('d-none');
            }
        };
        reader.readAsDataURL(file);
    });
})();
</script>
</body>
</html>
