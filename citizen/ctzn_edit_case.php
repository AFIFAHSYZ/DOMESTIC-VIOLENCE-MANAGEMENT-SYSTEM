<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['view_evidence'])) {
    $id = (int)$_GET['view_evidence'];
    $stmt = $pdo->prepare("SELECT file_name, mime_type, attachment FROM evidence WHERE evidence_id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $mime = $row['mime_type'] ?: 'application/octet-stream';
        $filename = $row['file_name'] ?: ('evidence_' . $id);
        $data = $row['attachment'];
        if (is_resource($data)) $data = stream_get_contents($data);

        if (ob_get_length()) ob_end_clean();
        header("Content-Type: $mime");
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    } else {
        header('Content-Type: text/plain');
        echo "Evidence not found (id $id).";
        exit;
    }
}
function nullIfEmpty($value) {
    return ($value === '' || $value === null) ? null : $value;
}

// ==================== AUTH & CASE LOAD ====================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    header("Location: ctzn_cases.php");
    exit();
}
$case_id = intval($_GET['case_id']);

$stmt = $pdo->prepare("SELECT * FROM dv_case WHERE case_id = ? AND reported_by = ?");
$stmt->execute([$case_id, $user_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$case) {
    header("Location: ctzn_cases.php");
    exit();
}

// Fetch victim details
$victimStmt = $pdo->prepare("SELECT * FROM victim WHERE case_id = ?");
$victimStmt->execute([$case_id]);
$victim = $victimStmt->fetch(PDO::FETCH_ASSOC);

// Fetch offender details
$offenderStmt = $pdo->prepare("SELECT * FROM offender WHERE case_id = ?");
$offenderStmt->execute([$case_id]);
$offender = $offenderStmt->fetch(PDO::FETCH_ASSOC);

// Fetch user info for sidebar
$userStmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Get unread message count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

// ==================== EVIDENCE UPLOAD (MULTIPLE) ====================
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['withdraw_case'])) {
        $pdo->prepare("UPDATE dv_case SET status = 'withdrawn' WHERE case_id = ? AND reported_by = ?")
            ->execute([$case_id, $user_id]);
        header("Location: ctzn_cases.php?msg=withdrawn");
        exit();
    }

    // --- Collect and validate form data (your regular validation here) ---
    $abuse_type = $_POST['abuse_type'] ?? '';
    $report_date = $_POST['report_date'] ?? '';
    $address_line1 = $_POST['address_line1'] ?? '';
    $address_line2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $abuse_desc = $_POST['abuse_desc'] ?? '';

    // Victim
    $victim_full_name = $_POST['victim_full_name'] ?? ($victim['full_name'] ?? '');
    $victim_date_of_birth = $_POST['victim_date_of_birth'] ?? ($victim['date_of_birth'] ?? '');
    $victim_gender = $_POST['victim_gender'] ?? ($victim['gender'] ?? '');
    $victim_phone_number = $_POST['victim_phone_number'] ?? ($victim['phone_number'] ?? '');
    $victim_email = $_POST['victim_email'] ?? ($victim['email'] ?? '');
    $victim_notes = $_POST['victim_notes'] ?? ($victim['notes'] ?? '');
    $victim_address_line1 = $_POST['victim_address_line1'] ?? ($victim['address_line1'] ?? '');
    $victim_address_line2 = $_POST['victim_address_line2'] ?? ($victim['address_line2'] ?? '');
    $victim_city = $_POST['victim_city'] ?? ($victim['city'] ?? '');
    $victim_state = $_POST['victim_state'] ?? ($victim['state'] ?? '');
    $victim_postal_code = $_POST['victim_postal_code'] ?? ($victim['postal_code'] ?? '');

    // Offender
    $offender_full_name = $_POST['offender_full_name'] ?? ($offender['full_name'] ?? '');
    $offender_date_of_birth = $_POST['offender_date_of_birth'] ?? ($offender['date_of_birth'] ?? '');
    $offender_gender = $_POST['offender_gender'] ?? ($offender['gender'] ?? '');
    $offender_phone_number = $_POST['offender_phone_number'] ?? ($offender['phone_number'] ?? '');
    $offender_address_line1 = $_POST['offender_address_line1'] ?? ($offender['address_line1'] ?? '');
    $offender_address_line2 = $_POST['offender_address_line2'] ?? ($offender['address_line2'] ?? '');
    $offender_city = $_POST['offender_city'] ?? ($offender['city'] ?? '');
    $offender_state = $_POST['offender_state'] ?? ($offender['state'] ?? '');
    $offender_postal_code = $_POST['offender_postal_code'] ?? ($offender['postal_code'] ?? '');
    $occupation = $_POST['occupation'] ?? ($offender['occupation'] ?? '');
    $relationship_to_victim = $_POST['relationship_to_victim'] ?? ($offender['relationship_to_victim'] ?? '');
    $physical_description = $_POST['physical_description'] ?? ($offender['physical_description'] ?? '');
    $known_violence_history_str = $_POST['known_violence_history'] ?? (
        isset($offender['known_violence_history']) ? (
            $offender['known_violence_history'] === true ? "yes" : ($offender['known_violence_history'] === false ? "no" : "unknown")
        ) : ''
    );
    $notes = $_POST['offender_notes'] ?? ($offender['notes'] ?? '');

    // --- Validation (as in your code) ---
    if (!$abuse_type) $errors[] = 'Incident type is required.';
    if (!$report_date) $errors[] = 'Incident date is required.';
    if (!$address_line1 || !$city || !$state || !$postal_code) $errors[] = 'Incident address is required.';
    if (!$abuse_desc) $errors[] = 'Description of incident is required.';

    if (!$victim_full_name) $errors[] = 'Victim name is required.';
    if (!$offender_full_name) $errors[] = 'Offender name is required.';
// Sanitize empty dates
$report_date = nullIfEmpty($report_date);
$victim_date_of_birth = nullIfEmpty($victim_date_of_birth);
$offender_date_of_birth = nullIfEmpty($offender_date_of_birth);

    // --- Save if no errors ---
    if (empty($errors)) {
$pdo->prepare("UPDATE dv_case SET abuse_type=?, report_date=?, address_line1=?, address_line2=?, city=?, state=?, postal_code=?, abuse_desc=? WHERE case_id=? AND reported_by=?")
    ->execute([
        $abuse_type, $report_date, $address_line1, $address_line2, $city, $state, $postal_code, $abuse_desc, $case_id, $user_id
    ]);

        if ($victim) {
            $pdo->prepare("UPDATE victim SET full_name=?, date_of_birth=?, gender=?, phone_number=?, email=?, notes=?, address_line1=?, address_line2=?, city=?, state=?, postal_code=? WHERE case_id=?")
                ->execute([
                    $victim_full_name, $victim_date_of_birth, $victim_gender, $victim_phone_number, $victim_email, $victim_notes,
                    $victim_address_line1, $victim_address_line2, $victim_city, $victim_state, $victim_postal_code, $case_id
                ]);
        } else {
            $pdo->prepare("INSERT INTO victim (full_name, date_of_birth, gender, phone_number, email, notes, address_line1, address_line2, city, state, postal_code, case_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $victim_full_name, $victim_date_of_birth, $victim_gender, $victim_phone_number, $victim_email, $victim_notes,
                    $victim_address_line1, $victim_address_line2, $victim_city, $victim_state, $victim_postal_code, $case_id
                ]);
        }

        $known_violence_history = $known_violence_history_str === 'yes' ? true : ($known_violence_history_str === 'no' ? false : null);
        if ($offender) {
            $pdo->prepare("UPDATE offender SET full_name=?, date_of_birth=?, gender=?, phone_number=?, address_line1=?, address_line2=?, city=?, state=?, postal_code=?, occupation=?, relationship_to_victim=?, physical_description=?, known_violence_history=?, notes=? WHERE case_id=?")
                ->execute([
                    $offender_full_name, $offender_date_of_birth, $offender_gender, $offender_phone_number,
                    $offender_address_line1, $offender_address_line2, $offender_city, $offender_state, $offender_postal_code,
                    $occupation, $relationship_to_victim, $physical_description, $known_violence_history, $notes, $case_id
                ]);
        } else {
            $pdo->prepare("INSERT INTO offender (full_name, date_of_birth, gender, phone_number, address_line1, address_line2, city, state, postal_code, occupation, relationship_to_victim, physical_description, known_violence_history, notes, case_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $offender_full_name, $offender_date_of_birth, $offender_gender, $offender_phone_number,
                    $offender_address_line1, $offender_address_line2, $offender_city, $offender_state, $offender_postal_code,
                    $occupation, $relationship_to_victim, $physical_description, $known_violence_history, $notes, $case_id
                ]);
        }

        // --- Evidence file upload (multi) ---
        if (
            isset($_FILES['attachment'])
            && is_array($_FILES['attachment']['tmp_name'])
            && count($_FILES['attachment']['tmp_name']) > 0
        ) {
            for ($i = 0; $i < count($_FILES['attachment']['tmp_name']); $i++) {
                if ($_FILES['attachment']['error'][$i] === UPLOAD_ERR_OK && $_FILES['attachment']['tmp_name'][$i]) {
                    $fileData = file_get_contents($_FILES['attachment']['tmp_name'][$i]);
                    $fileName = $_FILES['attachment']['name'][$i];
                    $mimeType = $_FILES['attachment']['type'][$i];

                    $stmtEv = $pdo->prepare(
                        "INSERT INTO evidence (case_id, uploaded_at, attachment, file_name, mime_type)
                         VALUES (?, CURRENT_TIMESTAMP, ?, ?, ?)"
                    );
                    $stmtEv->bindParam(1, $case_id, PDO::PARAM_INT);
                    $stmtEv->bindParam(2, $fileData, PDO::PARAM_LOB);
                    $stmtEv->bindParam(3, $fileName, PDO::PARAM_STR);
                    $stmtEv->bindParam(4, $mimeType, PDO::PARAM_STR);
                    $stmtEv->execute();
                }
            }
        }

        $success = true;
        // Reload data after update
        $stmt->execute([$case_id, $user_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        $victimStmt->execute([$case_id]);
        $victim = $victimStmt->fetch(PDO::FETCH_ASSOC);
        $offenderStmt->execute([$case_id]);
        $offender = $offenderStmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    // First load, use DB values for sticky
    $abuse_type = $case['abuse_type'] ?? '';
    $report_date = $case['report_date'] ?? '';
    $address_line1 = $case['address_line1'] ?? '';
    $address_line2 = $case['address_line2'] ?? '';
    $city = $case['city'] ?? '';
    $state = $case['state'] ?? '';
    $postal_code = $case['postal_code'] ?? '';
    $abuse_desc = $case['abuse_desc'] ?? '';

    $victim_full_name = $victim['full_name'] ?? '';
    $victim_date_of_birth = $victim['date_of_birth'] ?? '';
    $victim_gender = $victim['gender'] ?? '';
    $victim_phone_number = $victim['phone_number'] ?? '';
    $victim_email = $victim['email'] ?? '';
    $victim_notes = $victim['notes'] ?? '';
    $victim_address_line1 = $victim['address_line1'] ?? '';
    $victim_address_line2 = $victim['address_line2'] ?? '';
    $victim_city = $victim['city'] ?? '';
    $victim_state = $victim['state'] ?? '';
    $victim_postal_code = $victim['postal_code'] ?? '';

    $offender_full_name = $offender['full_name'] ?? '';
    $offender_date_of_birth = $offender['date_of_birth'] ?? '';
    $offender_gender = $offender['gender'] ?? '';
    $offender_phone_number = $offender['phone_number'] ?? '';
    $offender_address_line1 = $offender['address_line1'] ?? '';
    $offender_address_line2 = $offender['address_line2'] ?? '';
    $offender_city = $offender['city'] ?? '';
    $offender_state = $offender['state'] ?? '';
    $offender_postal_code = $offender['postal_code'] ?? '';
    $occupation = $offender['occupation'] ?? '';
    $relationship_to_victim = $offender['relationship_to_victim'] ?? '';
    $physical_description = $offender['physical_description'] ?? '';
    $known_violence_history_str = isset($offender['known_violence_history']) ? (
        $offender['known_violence_history'] === true ? "yes" : ($offender['known_violence_history'] === false ? "no" : "unknown")
    ) : '';
    $notes = $offender['notes'] ?? '';
}

// ==================== EVIDENCE LIST FOR UI ====================
$evidence = $pdo->prepare("SELECT evidence_id, file_name, mime_type, uploaded_at FROM evidence WHERE case_id = ?");
$evidence->execute([$case_id]);
$evidence_files = $evidence->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Case | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <?php if ($user['profilepic']): ?>
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
                    alt="Profile" class="user-avatar">
                        <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li ><a href="citizen_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li ><a href="ctzn_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="ctzn_report.php"><i class="fas fa-plus-circle"></i> Report New Case</a></li>
                <li><a href="ctzn_messages.php"><i class="fas fa-envelope"></i> Messages
                <span class="notification-badge"><?= $unread ?></span></a></li>
                <li><a href="ctzn_resources.php"><i class="fas fa-book"></i> Resources</a></li>
                <li><a href="ctzn_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a><li>
            </ul>
        </nav>
    </aside>
    <main class="main-content">
        <header class="content-header">
            <h1>Edit Case</h1>
        </header>
        <section class="content-section">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Case updated successfully!
            </div>
        <?php endif; ?>

        <form action="ctzn_edit_case.php?case_id=<?= $case_id ?>" method="POST" enctype="multipart/form-data" class="form-profile">
            <h3>Case Details</h3>
            <div class="form-group">
                <label for="abuse_type">Incident Type  </label>
                <select class="form-select" id="abuse_type" name="abuse_type" required>
                    <option value="">Select</option>
                    <?php
                    $types = [
                        'Physical Abuse', 'Emotional/Psychological Abuse', 'Verbal Abuse', 'Sexual Abuse',
                        'Financial/Economic Abuse', 'Technological/Digital Abuse', 'Cultural/Spiritual Abuse',
                        'Neglect (caregiving relationships)', 'Other'
                    ];
                    foreach ($types as $type):
                    ?>
                    <option<?= ($case['abuse_type']==$type)?' selected':'' ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="report_date">Date of Incident  </label>
                <input type="datetime-local" class="form-control" id="report_date" name="report_date"
                    value="<?= isset($case['report_date']) && $case['report_date'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($case['report_date']))) : '' ?>" required>
            </div>
            <div class="form-group">
                <label for="address_line1">Street line 1  </label>
                <input type="text" class="form-control" id="address_line1" name="address_line1"
                    value="<?= htmlspecialchars($case['address_line1'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="address_line2">Street line 2</label>
                <input type="text" class="form-control" id="address_line2" name="address_line2"
                    value="<?= htmlspecialchars($case['address_line2'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="city">City  </label>
                <input type="text" class="form-control" id="city" name="city"
                    value="<?= htmlspecialchars($case['city']) ?>" required>
            </div>
            <div class="form-group">
                <label for="state">State  </label>
                <select class="form-control" id="state" name="state" required>
                    <option value="">-- Select State --</option>
                    <?php
                    $states = [
                        "Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan", "Pahang",
                        "Penang", "Perak", "Perlis", "Sabah", "Sarawak", "Selangor",
                        "Terengganu", "Kuala Lumpur", "Labuan", "Putrajaya"
                    ];
                    foreach ($states as $st):
                    ?>
                    <option value="<?= $st ?>"<?= ($case['state']==$st)?' selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="postal_code">Postal Code  </label>
                <input type="text" class="form-control" id="postal_code" name="postal_code"
                    value="<?= htmlspecialchars($case['postal_code']) ?>" required>
            </div>
            <div class="form-group">
                <label for="abuse_desc">Description of Incident  </label>
                <textarea class="form-control" id="abuse_desc" name="abuse_desc" rows="4" required><?= htmlspecialchars($case['abuse_desc'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Existing Evidence Attachments:</label>
                <?php foreach ($evidence_files as $file): ?>
                    <div>
                        <a href="?case_id=<?= $case_id ?>&view_evidence=<?= $file['evidence_id'] ?>" target="_blank">
                            <?= htmlspecialchars($file['file_name'] ?? ('evidence_'.$file['evidence_id'])) ?>
                        </a>
                        <span><?= htmlspecialchars($file['uploaded_at']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($evidence_files)): ?>
                    <div>No evidence uploaded yet.</div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="attachment">Add More Evidence Attachments</label>
                <input type="file" class="form-control" id="attachment" name="attachment[]" accept="image/*,application/pdf" multiple>
                <small>You can upload images or PDF files as evidence.</small>
            </div>
            <h3>Victim Details</h3>
            <div class="form-group">
                <label for="victim_full_name">Name </label>
                <input type="text" class="form-control" id="victim_full_name" name="victim_full_name"
                    value="<?= htmlspecialchars($victim_full_name) ?>" required>
            </div>
            <div class="form-group">
                <label for="victim_date_of_birth">Date of Birth  </label>
                <input type="date" class="form-control" id="victim_date_of_birth" name="victim_date_of_birth"
                    value="<?= $victim_date_of_birth ?>"   >
            </div>
            <div class="form-group">
                <label for="victim_gender">Gender  </label>
                <select class="form-select" id="victim_gender" name="victim_gender"   >
                    <option value="">Select</option>
                    <option value="Female" <?= $victim_gender == "Female" ? "selected" : "" ?>>Female</option>
                    <option value="Male" <?= $victim_gender == "Male" ? "selected" : "" ?>>Male</option>
                    <option value="Prefer not to say" <?= $victim_gender == "Prefer not to say" ? "selected" : "" ?>>Prefer not to say</option>
                </select>
            </div>
            <div class="form-group">
                <label for="victim_phone_number">Phone Number  </label>
                <input type="text" class="form-control" id="victim_phone_number" name="victim_phone_number"
                    value="<?= htmlspecialchars($victim_phone_number) ?>"   >
            </div>
            <div class="form-group">
                <label for="victim_email">Email</label>
                <input type="email" class="form-control" id="victim_email" name="victim_email"
                    value="<?= htmlspecialchars($victim_email) ?>">
            </div>
            <div class="form-group">
                <label for="victim_notes">Notes</label>
                <textarea class="form-control" id="victim_notes" name="victim_notes" rows="2"><?= htmlspecialchars($victim_notes) ?></textarea>
            </div>
            <div class="form-group">
                <label for="victim_address_line1">Street Address 1  </label>
                <input type="text" class="form-control" id="victim_address_line1" name="victim_address_line1"
                    value="<?= htmlspecialchars($victim_address_line1) ?>"   >
            </div>
            <div class="form-group">
                <label for="victim_address_line2">Street Address 2</label>
                <input type="text" class="form-control" id="victim_address_line2" name="victim_address_line2"
                    value="<?= htmlspecialchars($victim_address_line2) ?>">
            </div>
            <div class="form-group">
                <label for="victim_city">City  </label>
                <input type="text" class="form-control" id="victim_city" name="victim_city"
                    value="<?= htmlspecialchars($victim_city) ?>"   >
            </div>
            <div class="form-group">
                <label for="victim_state">State  </label>
                <input type="text" class="form-control" id="victim_state" name="victim_state"
                    value="<?= htmlspecialchars($victim_state) ?>"   >
            </div>
            <div class="form-group">
                <label for="victim_postal_code">Postal Code  </label>
                <input type="text" class="form-control" id="victim_postal_code" name="victim_postal_code"
                    value="<?= htmlspecialchars($victim_postal_code) ?>"   >
            </div>
            <hr>
            <h3>Offender Details</h3>
            <div class="form-group">
                <label for="offender_full_name">Full Name  </label>
                <input type="text" class="form-control" id="offender_full_name" name="offender_full_name"
                    value="<?= htmlspecialchars($offender_full_name) ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_date_of_birth">Date of Birth  </label>
                <input type="date" class="form-control" id="offender_date_of_birth" name="offender_date_of_birth"
                    value="<?= $offender_date_of_birth ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_gender">Gender  </label>
                <select class="form-select" id="offender_gender" name="offender_gender"   >
                    <option value="">Select</option>
                    <option value="Female" <?= $offender_gender == "Female" ? "selected" : "" ?>>Female</option>
                    <option value="Male" <?= $offender_gender == "Male" ? "selected" : "" ?>>Male</option>
                    <option value="Prefer not to say" <?= $offender_gender == "Prefer not to say" ? "selected" : "" ?>>Prefer not to say</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offender_phone_number">Phone Number  </label>
                <input type="text" class="form-control" id="offender_phone_number" name="offender_phone_number"
                    value="<?= htmlspecialchars($offender_phone_number) ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_address_line1">Street Address 1  </label>
                <input type="text" class="form-control" id="offender_address_line1" name="offender_address_line1"
                    value="<?= htmlspecialchars($offender_address_line1) ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_address_line2">Street Address 2</label>
                <input type="text" class="form-control" id="offender_address_line2" name="offender_address_line2"
                    value="<?= htmlspecialchars($offender_address_line2) ?>">
            </div>
            <div class="form-group">
                <label for="offender_city">City  </label>
                <input type="text" class="form-control" id="offender_city" name="offender_city"
                    value="<?= htmlspecialchars($offender_city) ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_state">State  </label>
                <input type="text" class="form-control" id="offender_state" name="offender_state"
                    value="<?= htmlspecialchars($offender_state) ?>"   >
            </div>
            <div class="form-group">
                <label for="offender_postal_code">Postal Code  </label>
                <input type="text" class="form-control" id="offender_postal_code" name="offender_postal_code"
                    value="<?= htmlspecialchars($offender_postal_code) ?>"   >
            </div>
            <div class="form-group">
                <label for="occupation">Occupation  </label>
                <input type="text" class="form-control" id="occupation" name="occupation"
                    value="<?= htmlspecialchars($occupation) ?>"   >
            </div>
            <div class="form-group">
                <label for="relationship_to_victim">Relationship to Victim  </label>
                <input type="text" class="form-control" id="relationship_to_victim" name="relationship_to_victim"
                    value="<?= htmlspecialchars($relationship_to_victim) ?>"   >
            </div>
            <div class="form-group">
                <label for="physical_description">Physical Description</label>
                <input type="text" class="form-control" id="physical_description" name="physical_description"
                    value="<?= htmlspecialchars($physical_description) ?>">
            </div>
            <div class="form-group">
                <label for="known_violence_history">Known Violence History  </label>
                <select class="form-select" id="known_violence_history" name="known_violence_history"   >
                    <option value="">Select</option>
                    <option value="yes"<?= $known_violence_history_str == "yes" ? " selected" : "" ?>>Yes</option>
                    <option value="no"<?= $known_violence_history_str == "no" ? " selected" : "" ?>>No</option>
                    <option value="unknown"<?= $known_violence_history_str == "unknown" ? " selected" : "" ?>>Unknown</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offender_notes">Notes</label>
                <textarea class="form-control" id="offender_notes" name="offender_notes" rows="2"><?= htmlspecialchars($notes) ?></textarea>
            </div>
            <div class="step-buttons">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="ctzn_cases.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <form action="ctzn_edit_case.php?case_id=<?= $case_id ?>" method="POST" style="margin-top:15px;">
            <button type="submit" class="btn btn-danger" name="withdraw_case" onclick="return confirm('Are you sure you want to withdraw this report? This action cannot be undone.');">Withdraw/Cancel Report</button>
        </form>
        </section>
    </main>
</div>
</body>
</html>