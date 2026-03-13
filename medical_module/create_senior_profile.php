<?php
session_start();
include '../db_config/connection_db.php';

$message = '';

// Load illnesses from DB (single source of truth for illness names and risk scale).
$illness_priority_map = [];
$illness_grouped = [];
$illness_sql = "SELECT illness_id, illness_name, category, risk_level FROM illnesses ORDER BY category ASC, risk_level DESC, illness_name ASC";
$illness_res = $conn->query($illness_sql);
if ($illness_res) {
    while ($ill = $illness_res->fetch_assoc()) {
        $name = trim((string)($ill['illness_name'] ?? ''));
        $category = trim((string)($ill['category'] ?? 'Uncategorized'));
        $risk_level = (int)($ill['risk_level'] ?? 1);
        if ($risk_level < 1 || $risk_level > 5) {
            $risk_level = 1;
        }

        if ($name === '') {
            continue;
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

function renderIllnessOptions(array $grouped): string {
    if (empty($grouped)) {
        return '<option value="">No illnesses available</option>';
    }

    $html = '<option value="">Select Illness Type</option>';
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

function getIllnessPriority(string $illness, array $priorityMap): int {
    return isset($priorityMap[$illness]) ? (int)$priorityMap[$illness] : 1;
}

function mapPriorityToRiskLevel($priority) {
    if ($priority >= 5) {
        return 'Critical';
    }
    if ($priority === 4) {
        return 'High';
    }
    if ($priority >= 2) {
        return 'Moderate';
    }

    return 'Low';
}

// Check if user is logged in (optional)
if (!isset($_SESSION['account_id'])) {
    header("Location: ../auth_module/login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name']);
    $password = trim($_POST['password']);
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $gender = $_POST['gender'] ?? null;
    if (!in_array($gender, ['Male', 'Female'], true)) {
        $gender = null;
    }
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $alive_status = $_POST['alive_status'] ?? '1';
    $is_alive = $alive_status === '1' ? 'yes' : 'no';
    $has_illness = isset($_POST['has_illness']) ? 1 : 0;

    // Handle multiple illnesses (only accept illnesses defined in DB table).
    $illness_types = [];
    if(isset($_POST['illness_type']) && is_array($_POST['illness_type'])) {
        foreach($_POST['illness_type'] as $illness) {
            $illness_name = trim((string)$illness);
            if($illness_name !== '' && isset($illness_priority_map[$illness_name])) {
                $illness_types[] = $illness_name;
            }
        }
    }

    // Handle file upload
    $profile_path = null;
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../senior_profile_pics/';
        $file_name = time() . '_' . basename($_FILES['profile_picture']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['profile_picture']['tmp_name']);
        if($check !== false) {
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_path = $file_name;
            }
        }
    }

    // Auto-calculate highest priority level from all illnesses.
    $priority_level = 1;
    if ($has_illness && !empty($illness_types)) {
        foreach ($illness_types as $illness) {
            $current_priority = getIllnessPriority($illness, $illness_priority_map);
            if ($current_priority > $priority_level) {
                $priority_level = $current_priority;
            }
        }
    }

    if(empty($first_name) || empty($last_name) || empty($password)) {
        $message = "First Name, Last Name, and Password are required!";
    } elseif ($address === '') {
        $message = "Address is required.";
    } elseif ($contact_number === '' || $emergency_contact === '') {
        $message = "Contact Number and Emergency Contact are required.";
    } elseif (!preg_match('/^\d{11}$/', $contact_number)) {
        $message = "Contact Number must be numeric and exactly 11 digits.";
    } elseif (!preg_match('/^\d{11}$/', $emergency_contact)) {
        $message = "Emergency Contact must be numeric and exactly 11 digits.";
    } elseif ($gender === null) {
        $message = "Gender is required.";
    } elseif ($birth_date === null) {
        $message = "Birth Date is required.";
    } else {
        $dob = DateTime::createFromFormat('Y-m-d', $birth_date);
        $today = new DateTime('today');
        if (!$dob || $dob->format('Y-m-d') !== $birth_date || $dob > $today) {
            $message = "Please enter a valid Birth Date.";
        } else {
            $age = $dob->diff($today)->y;
            if ($age < 60) {
                $message = "Senior age must be at least 60 years old.";
            }
        }
    }

    if (empty($message)) {
        // Generate username from first_name + middle_name + last_name
        $base_username = strtolower($first_name . $middle_name . $last_name);
        $base_username = preg_replace('/[^a-z0-9]/', '', $base_username);
        if ($base_username === '') {
            $base_username = 'senior';
        }

        $username = $base_username;
        $check_stmt = $conn->prepare("SELECT account_id FROM accounts WHERE username = ? LIMIT 1");
        if (!$check_stmt) {
            $message = "Error preparing username check: " . $conn->error;
        }

        if (empty($message)) {
            // Ensure generated username is unique.
            while (true) {
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows === 0) {
                    break;
                }

                $username = $base_username . rand(100, 999);
                $check_stmt->free_result();
            }
            $check_stmt->close();
        }
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        if (empty($message)) {
            // Start transaction for ordered inserts: accounts -> senior_profiles -> health_records
            $conn->begin_transaction();

            try {
                // 1) Create account first and retrieve account_id
                $role = 'senior';
                $account_status = 'active';
                $account_stmt = $conn->prepare("INSERT INTO accounts (username, password_hash, role, account_status) VALUES (?, ?, ?, ?)");
                if (!$account_stmt) {
                    throw new Exception("Account prepare failed: " . $conn->error);
                }
                $account_stmt->bind_param("ssss", $username, $password_hash, $role, $account_status);
                if (!$account_stmt->execute()) {
                    throw new Exception("Account insert failed: " . $account_stmt->error);
                }
                $account_id = (int) $conn->insert_id;
                if ($account_id <= 0) {
                    throw new Exception("Failed to retrieve created account_id.");
                }
                $account_stmt->close();

                // 2) Create senior profile using account_id and retrieve senior_id
                $profile_stmt = $conn->prepare("INSERT INTO senior_profiles 
                    (account_id, first_name, last_name, middle_name, birth_date, gender, address, contact_number, emergency_contact, profile_path, priority_level, is_alive) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$profile_stmt) {
                    throw new Exception("Senior profile prepare failed: " . $conn->error);
                }
                $profile_stmt->bind_param("isssssssssis", $account_id, $first_name, $last_name, $middle_name, $birth_date, $gender, $address, $contact_number, $emergency_contact, $profile_path, $priority_level, $is_alive);
                if (!$profile_stmt->execute()) {
                    throw new Exception("Senior profile insert failed: " . $profile_stmt->error);
                }
                // Prefer statement insert_id, then always resolve latest senior_id for this account.
                $senior_id = (int) $profile_stmt->insert_id;
                $senior_id_found = false;

                $senior_id_stmt = $conn->prepare("SELECT senior_id FROM senior_profiles WHERE account_id = ? ORDER BY created_at DESC, senior_id DESC LIMIT 1");
                if (!$senior_id_stmt) {
                    throw new Exception("Senior ID lookup prepare failed: " . $conn->error);
                }
                $senior_id_stmt->bind_param("i", $account_id);
                if (!$senior_id_stmt->execute()) {
                    throw new Exception("Senior ID lookup failed: " . $senior_id_stmt->error);
                }
                $senior_id_stmt->bind_result($fetched_senior_id);
                if ($senior_id_stmt->fetch()) {
                    $senior_id = (int) $fetched_senior_id;
                    $senior_id_found = true;
                }
                $senior_id_stmt->close();

                if (!$senior_id_found && $senior_id <= 0) {
                    throw new Exception("Failed to retrieve created senior_id.");
                }
                $profile_stmt->close();

                // 3) Register health records (optional), one row per illness.
                if ($has_illness && !empty($illness_types)) {
                    $health_stmt = $conn->prepare("INSERT INTO health_records (senior_id, chronic_conditions, notes, risk_level) VALUES (?, ?, ?, ?)");
                    if (!$health_stmt) {
                        throw new Exception("Health record prepare failed: " . $conn->error);
                    }

                    foreach ($illness_types as $illness) {
                        $illness_priority = getIllnessPriority($illness, $illness_priority_map);
                        $risk_level = mapPriorityToRiskLevel($illness_priority);
                        $notes = null;

                        $health_stmt->bind_param("isss", $senior_id, $illness, $notes, $risk_level);
                        if (!$health_stmt->execute()) {
                            throw new Exception("Health record insert failed: " . $health_stmt->error);
                        }
                    }

                    $health_stmt->close();
                }

                $conn->commit();
                $message = "Senior profile, account, and health records saved successfully! Username: " . $username . " (Account ID: " . $account_id . ", Senior ID: " . $senior_id . ", Priority Level: " . $priority_level . ")";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error creating profile: " . $e->getMessage();
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
<title>Create Senior Profile</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
    body {
        min-height: 100vh;
        margin: 0;
    }
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
    .content-area {
        padding: 28px;
    }
    .detail-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 4px 18px rgba(0,0,0,.07);
    }
    .form-section {
        background: #fff;
        padding: 18px;
        border-radius: 10px;
        border: 1px solid #e9ecef;
        height: 100%;
    }
    .form-section h3 {
        margin: 0 0 14px 0;
        color: #212529;
        font-size: 1rem;
        font-weight: 600;
    }
    .picture-preview {
        width: 200px;
        height: 200px;
        margin: 20px auto;
        border: 2px dashed #ced4da;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8f9fa;
        position: relative;
    }
    .picture-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }
    .picture-preview.has-image img {
        display: block;
    }
    .picture-preview.has-image .placeholder-icon {
        display: none;
    }
    .placeholder-icon {
        font-size: 50px;
        opacity: 0.3;
    }
    .illness-section {
        background: #fff;
        border: 1px solid #e9ecef;
    }
    .illness-table-wrapper {
        max-height: 220px;
        overflow-y: auto;
    }
    .illness-entry {
        margin-bottom: 14px;
        padding: 15px;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        position: relative;
    }
    .illness-entry:not(:last-child) {
        border-bottom: 2px solid #f0f0f0;
    }
    .illness-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .illness-entry-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: #333;
        margin: 0;
    }
    .btn-remove-illness {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        font-size: 22px;
        padding: 0 8px;
        line-height: 1;
    }
    .btn-remove-illness:hover {
        color: #c82333;
    }
    .btn-add-more-illness {
        background: #fff;
        color: #0d6efd;
        border: 1px solid #0d6efd;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
    }
    .btn-add-more-illness:hover {
        background: #0d6efd;
        color: #fff;
    }
    .btn-add-more-illness:disabled {
        background: #f1f3f5;
        color: #6c757d;
        border-color: #dee2e6;
        cursor: not-allowed;
    }
    .password-section {
        background: #fff;
        border: 1px solid #e9ecef;
    }
    .submit-button {
        background: #0d6efd;
        border: 1px solid #0d6efd;
        color: #fff;
    }
    .submit-button:hover {
        background: #0b5ed7;
        border-color: #0a58ca;
    }
</style>
<script>
    let illnessCount = 1;
    const illnessPriorityMap = <?php echo json_encode($illness_priority_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const illnessGroups = <?php echo json_encode($illness_grouped, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function getIllnessPriorityClient(illness) {
        if (Object.prototype.hasOwnProperty.call(illnessPriorityMap, illness)) {
            return Number(illnessPriorityMap[illness]) || 1;
        }
        return 1;
    }



    function getPriorityLabel(priority) {
        if (priority >= 5) {
            return 'Priority 5 (Critical)';
        }
        if (priority === 4) {
            return 'Priority 4 (High)';
        }
        if (priority === 3) {
            return 'Priority 3 (Chronic)';
        }
        if (priority === 2) {
            return 'Priority 2 (Moderate)';
        }
        return 'Priority 1 (Low)';
    }

    function updatePriorityDisplay() {
        var illnessCheckbox = document.getElementById('has_illness');
        var priorityWrap = document.getElementById('priority_display_wrap');
        var priorityText = document.getElementById('priority_display_text');
        if (!illnessCheckbox || !priorityWrap || !priorityText) {
            return;
        }

        if (!illnessCheckbox.checked) {
            priorityWrap.style.display = 'none';
            priorityText.textContent = 'Priority 1 (Low)';
            return;
        }

        var checkboxes = document.querySelectorAll('input[name="illness_type[]"]:checked');
        var highest = 1;
        var hasSelectedIllness = false;

        checkboxes.forEach(function(checkbox) {
            var illness = (checkbox.value || '').trim();
            if (illness !== '') {
                hasSelectedIllness = true;
                var p = getIllnessPriorityClient(illness);
                if (p > highest) {
                    highest = p;
                }
            }
        });

        priorityWrap.style.display = hasSelectedIllness ? 'block' : 'none';
        priorityText.textContent = getPriorityLabel(highest);
    }
    
    function toggleIllnessType() {
        var checkbox = document.getElementById('has_illness');
        var illnessField = document.getElementById('illness_type_field');
        if(checkbox.checked) {
            illnessField.style.display = 'block';
            updateAddButtonState();
        } else {
            illnessField.style.display = 'none';
        }
        updatePriorityDisplay();
    }
    
    function addIllnessEntry() {
        var container = document.getElementById('illness_entries_container');
        var currentCount = container.querySelectorAll('.illness-entry').length;
        
        // Limit to 3 illness entries
        if (currentCount >= 3) {
            alert('You can add up to 3 illnesses maximum');
            return;
        }
        
        illnessCount++;
        var entryNum = currentCount + 1;
        var newEntry = document.createElement('div');
        newEntry.className = 'illness-entry';
        newEntry.id = 'illness_entry_' + illnessCount;
        
        var tableHTML = '<thead class="table-light sticky-top"><tr><th style="width: 40px;"></th><th>Illness Name</th><th style="width: 100px;">Risk Level</th></tr></thead><tbody class="illness-list-body">';
        
        Object.keys(illnessGroups || {}).forEach(function(category) {
            tableHTML += '<tr class="table-group-divider"><td colspan="3" style="background-color: #f5f5f5; font-weight: bold; padding: 6px 12px; font-size: 0.85em;">' + htmlEscape(category) + '</td></tr>';
            (illnessGroups[category] || []).forEach(function(item) {
                tableHTML += '<tr><td><input type="checkbox" name="illness_type[]" value="' + htmlEscape(item.name) + '" class="form-check-input illness-checkbox" onchange="updatePriorityDisplay()"></td><td>' + htmlEscape(item.name) + '</td><td><span class="badge bg-info" style="font-size: 0.8rem;">Risk ' + item.risk_level + '</span></td></tr>';
            });
        });
        tableHTML += '</tbody>';
        
        newEntry.innerHTML = `
            <div class="illness-entry-header">
                <p class="illness-entry-title">Illness #${entryNum}</p>
                <button type="button" class="btn-remove-illness" onclick="removeIllnessEntry(${illnessCount})" title="Remove">×</button>
            </div>
            <div class="table-responsive illness-table-wrapper">
                <table class="table table-hover mb-0 table-sm">
                    ${tableHTML}
                </table>
            </div>
        `;
        
        container.appendChild(newEntry);
        updateAddButtonState();
        updatePriorityDisplay();
    }
    
    function removeIllnessEntry(id) {
        var entry = document.getElementById('illness_entry_' + id);
        if (entry) {
            entry.remove();
        }
        updateAddButtonState();
        updatePriorityDisplay();
    }
    
    function updateAddButtonState() {
        var container = document.getElementById('illness_entries_container');
        var addBtn = document.getElementById('btn_add_more');
        var currentCount = container.querySelectorAll('.illness-entry').length;
        
        if (currentCount >= 3) {
            addBtn.disabled = true;
        } else {
            addBtn.disabled = false;
        }
        
        // Show/hide remove buttons
        var entries = container.querySelectorAll('.illness-entry');
        entries.forEach(function(entry, index) {
            var removeBtn = entry.querySelector('.btn-remove-illness');
            if (entries.length > 1) {
                removeBtn.style.display = 'block';
            } else {
                removeBtn.style.display = 'none';
            }
        });
    }
    
    function htmlEscape(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    function previewImage(input) {
        var preview = document.getElementById('image_preview');
        var previewContainer = document.getElementById('preview_container');
        
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.classList.add('has-image');
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function calculateAgeFromBirthDate(value) {
        if (!value) {
            return null;
        }

        var dob = new Date(value + 'T00:00:00');
        if (Number.isNaN(dob.getTime())) {
            return null;
        }

        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        var monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        return age >= 0 ? age : null;
    }

    function updateBirthDateAgeDisplay() {
        var birthDateInput = document.getElementById('birth_date_input');
        var ageText = document.getElementById('birth_date_age_text');
        if (!birthDateInput || !ageText) {
            return;
        }

        var age = calculateAgeFromBirthDate(birthDateInput.value);
        if (age === null) {
            ageText.textContent = 'Total Age: -';
            return;
        }

        ageText.textContent = 'Total Age: ' + age + ' years old';
    }

    function enforceSeniorAgeConstraint() {
        var birthDateInput = document.getElementById('birth_date_input');
        if (!birthDateInput) {
            return true;
        }

        var age = calculateAgeFromBirthDate(birthDateInput.value);
        if (age === null) {
            birthDateInput.setCustomValidity('Birth Date is required.');
            return false;
        }

        if (age < 60) {
            birthDateInput.setCustomValidity('Senior must be 60 years old or above.');
            return false;
        }

        birthDateInput.setCustomValidity('');
        return true;
    }

    function enforceNumeric11Length(input) {
        if (!input) {
            return;
        }
        input.value = (input.value || '').replace(/\D/g, '').slice(0, 11);
    }

    document.addEventListener('DOMContentLoaded', function() {
        updatePriorityDisplay();
        updateBirthDateAgeDisplay();

        var birthDateInput = document.getElementById('birth_date_input');
        if (birthDateInput) {
            birthDateInput.addEventListener('input', updateBirthDateAgeDisplay);
            birthDateInput.addEventListener('change', updateBirthDateAgeDisplay);
            birthDateInput.addEventListener('input', enforceSeniorAgeConstraint);
            birthDateInput.addEventListener('change', enforceSeniorAgeConstraint);
        }

        var contactInput = document.getElementById('contact_number_input');
        var emergencyInput = document.getElementById('emergency_contact_input');

        if (contactInput) {
            enforceNumeric11Length(contactInput);
            contactInput.addEventListener('input', function() {
                enforceNumeric11Length(contactInput);
            });
        }

        if (emergencyInput) {
            enforceNumeric11Length(emergencyInput);
            emergencyInput.addEventListener('input', function() {
                enforceNumeric11Length(emergencyInput);
            });
        }

        var createSeniorForm = document.getElementById('create_senior_form');
        if (createSeniorForm) {
            createSeniorForm.addEventListener('submit', function(e) {
                if (!enforceSeniorAgeConstraint()) {
                    e.preventDefault();
                    var bd = document.getElementById('birth_date_input');
                    if (bd) {
                        bd.reportValidity();
                    }
                }
            });
        }
    });
</script>
</head>
<body>
<?php include 'medical_navigation.php'; ?>

<div class="mednav-main main-wrap">
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">Senior Registration</h5>
            <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
        </div>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small>
    </div>

    <div class="content-area">
        <?php if($message): ?>
            <div class="alert <?php echo strpos($message,'successfully')!==false?'alert-success':'alert-danger'; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card detail-card">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus-fill me-1"></i> Create Senior Profile & Account
            </div>
            <div class="card-body p-4">
                <form id="create_senior_form" method="POST" action="" enctype="multipart/form-data">
            
            <div class="row g-4 mb-4">
                <!-- LEFT COLUMN: Personal Information -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h3><i class="bi bi-person-fill"></i> Personal Information</h3>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required placeholder="Enter first name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required placeholder="Enter last name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Birth Date</label>
                            <input type="date" id="birth_date_input" name="birth_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-60 years')); ?>" max="<?php echo date('Y-m-d', strtotime('-60 years')); ?>" required>
                            <small id="birth_date_age_text" class="text-muted d-block mt-1">Total Age: -</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold d-block">Gender</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="gender_male" value="Male" required>
                                <label class="form-check-label" for="gender_male">Male</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="gender_female" value="Female">
                                <label class="form-check-label" for="gender_female">Female</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold d-block">Alive Status</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="alive_status" id="alive_yes" value="1" checked>
                                <label class="form-check-label" for="alive_yes">Alive</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="alive_status" id="alive_no" value="0">
                                <label class="form-check-label" for="alive_no">Deceased</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Contact & Profile Picture -->
                <div class="col-md-6">
                    <div class="form-section">
                        <h3><i class="bi bi-telephone-fill"></i> Contact Information</h3>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Enter full address" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Number</label>
                            <input type="text" id="contact_number_input" name="contact_number" class="form-control" placeholder="11-digit contact number" inputmode="numeric" maxlength="11" minlength="11" pattern="\d{11}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Emergency Contact</label>
                            <input type="text" id="emergency_contact_input" name="emergency_contact" class="form-control" placeholder="11-digit emergency contact" inputmode="numeric" maxlength="11" minlength="11" pattern="\d{11}" required>
                        </div>

                        <div class="mb-3 text-center">
                            <label class="form-label fw-bold">Profile Picture</label>
                            <div class="picture-preview" id="preview_container">
                                <img id="image_preview" src="" alt="Preview">
                                <i class="bi bi-camera-fill placeholder-icon"></i>
                            </div>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewImage(this)">
                            <small class="text-muted d-block mt-2">Upload a profile photo (JPG, PNG, etc.)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-section illness-section">
                    <h3><i class="bi bi-heart-pulse-fill"></i> Health Information</h3>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" id="has_illness" name="has_illness" class="form-check-input" onchange="toggleIllnessType()">
                        <label class="form-check-label fw-bold" for="has_illness">
                            This person has an illness
                        </label>
                    </div>

                    <div id="illness_type_field" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Illness(es) - Select up to 3</label>
                            <div id="illness_entries_container">
                                <div class="illness-entry" id="illness_entry_1">
                                    <div class="illness-entry-header">
                                        <p class="illness-entry-title">Illness #1</p>
                                        <button type="button" class="btn-remove-illness" onclick="removeIllnessEntry(1)" style="display: none;" id="btn_remove_1" title="Remove">×</button>
                                    </div>
                                    <div class="table-responsive illness-table-wrapper">
                                        <table class="table table-hover mb-0 table-sm">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th style="width: 40px;"></th>
                                                    <th>Illness Name</th>
                                                    <th style="width: 100px;">Risk Level</th>
                                                </tr>
                                            </thead>
                                            <tbody class="illness-list-body">
                                                <?php foreach ($illness_grouped as $category => $items): ?>
                                                    <tr class="table-group-divider">
                                                        <td colspan="3" style="background-color: #f5f5f5; font-weight: bold; padding: 6px 12px; font-size: 0.85em;"><?php echo htmlspecialchars($category); ?></td>
                                                    </tr>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" name="illness_type[]" value="<?php echo htmlspecialchars($item['name']); ?>" class="form-check-input illness-checkbox" onchange="updatePriorityDisplay()">
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                            <td><span class="badge bg-info" style="font-size: 0.8rem;">Risk <?php echo (int)$item['risk_level']; ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-add-more-illness" id="btn_add_more" onclick="addIllnessEntry()">
                                <i class="bi bi-plus-circle-fill"></i> Add Illness
                            </button>
                            <div id="priority_display_wrap" class="alert alert-info mt-3 mb-0" style="display: none; border-left: 4px solid #17a2b8;">
                                <strong>Priority Level:</strong>
                                <span id="priority_display_text">Priority 1 (Low)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PASSWORD SECTION - Full Width at Bottom -->
            <div class="mb-4">
                <div class="form-section password-section">
                    <h3><i class="bi bi-shield-lock-fill"></i> Account Security</h3>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Create a secure password">
                        <small class="text-muted">Username will be auto-generated from name (firstname + middlename + lastname)</small>
                    </div>
                </div>
            </div>

                    <button type="submit" class="btn btn-lg w-100 submit-button">
                        <i class="bi bi-check-circle-fill"></i> Create Senior Profile & Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>