<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'conn.php'; 

$step = 'choose_role';
$errors = [];
$selectedRole = $_POST['role'] ?? '';

if (isset($_POST['register_submit'])) {
    $step = 'submit';
}

if ($step === 'submit') {
    // Validate common fields
    $required = ['full_name', 'dob', 'email', 'phone_num', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace("_", " ", $field)) . " is required.";
        }
    }

    // Validate role selected
    if (empty($selectedRole)) {
        $errors[] = "Role is required.";
    }

    // Password match check
    if (!empty($_POST['password']) && $_POST['password'] !== $_POST['confirm_password']) {
        $errors[] = "Passwords do not match.";
    }

    // Validate role-specific required fields
    if ($selectedRole === 'citizen') {
        foreach (['citizen_address_line1', 'citizen_city', 'citizen_postcode', 'state'] as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace("_", " ", $field)) . " is required for Citizen.";
            }
        }
    } elseif ($selectedRole === 'police') {
        foreach (['badge_id', 'branch'] as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace("_", " ", $field)) . " is required for Police.";
            }
        }
        if (!isset($_FILES['verification_doc']) || $_FILES['verification_doc']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Verification document is required for Police.";
        }
    } elseif ($selectedRole === 'lawyer') {
        if (empty($_POST['bar_registration_no'])) {
            $errors[] = "Bar Registration No is required for Lawyer.";
        }
        if (!isset($_FILES['lawyer_verification_doc']) || $_FILES['lawyer_verification_doc']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Verification document is required for Lawyer.";
        }
    } else {
        $errors[] = "Invalid role selected.";
    }

    // If no errors, process registration
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $u_is_adm = 0;
            $u_is_ctzn = 0;
            $u_is_lenf = 0;
            $u_is_lrep = 0;
            if ($selectedRole === 'citizen') {
                $u_is_ctzn = 1;
            } elseif ($selectedRole === 'police') {
                $u_is_lenf = 1;
            } elseif ($selectedRole === 'lawyer') {
                $u_is_lrep = 1;
            }

            // Path to default profile picture in your server
$defaultImagePath = __DIR__ . '/images/default-avatar.jpg';
            $defaultImageContent = file_get_contents($defaultImagePath);
            if ($defaultImageContent === false) {
                throw new Exception("Failed to read default profile picture.");
            }

            $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO SYS_USER (
                full_name, dob, race, email, phone_num, ic, passport_num, password, profilepic, U_IS_ADM, U_IS_CTZN, U_IS_LENF, U_IS_LREP
            ) VALUES (
                :full_name, :dob, :race, :email, :phone_num, :ic, :passport_num, :password, :profilepic, :u_is_adm, :u_is_ctzn, :u_is_lenf, :u_is_lrep
            )";

            $stmt = $pdo->prepare($sql);

            // Assign values to variables for bindParam
            $full_name = $_POST['full_name'];
            $dob = $_POST['dob'];
            $race = $_POST['race'];
$email = strtolower($_POST['email']);
            $phone_num = $_POST['phone_num'];
            $ic = $_POST['ic'] ?? null;
            $passport_num = $_POST['passport_num'] ?? null;

            $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
            $stmt->bindParam(':dob', $dob, PDO::PARAM_STR);
            $stmt->bindParam(':race', $race, PDO::PARAM_STR);
$stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone_num', $phone_num, PDO::PARAM_STR);
            $stmt->bindParam(':ic', $ic, PDO::PARAM_STR);
            $stmt->bindParam(':passport_num', $passport_num, PDO::PARAM_STR);
            $stmt->bindParam(':password', $passwordHash, PDO::PARAM_STR);

            // Store binary as LOB (bindValue is fine here, not reference)
            $stmt->bindValue(':profilepic', $defaultImageContent, PDO::PARAM_LOB);

            $stmt->bindParam(':u_is_adm', $u_is_adm, PDO::PARAM_INT);
            $stmt->bindParam(':u_is_ctzn', $u_is_ctzn, PDO::PARAM_INT);
            $stmt->bindParam(':u_is_lenf', $u_is_lenf, PDO::PARAM_INT);
            $stmt->bindParam(':u_is_lrep', $u_is_lrep, PDO::PARAM_INT);

            $stmt->execute();

            $userId = $pdo->lastInsertId();

            // Handle role-specific inserts + file uploads
            if ($selectedRole === 'citizen') {
                $sqlCitizen = "INSERT INTO CITIZEN_DETAIL (user_id, address_line1, address_line2, city, postal_code, state) 
                               VALUES (:user_id, :line1, :line2, :city, :postal_code, :state)";
                $stmtCitizen = $pdo->prepare($sqlCitizen);
                $stmtCitizen->execute([
                    ':user_id' => $userId,
                    ':line1' => $_POST['citizen_address_line1'],
                    ':line2' => $_POST['citizen_address_line2'] ?? '',
                    ':city' => $_POST['citizen_city'],
                    ':postal_code' => $_POST['citizen_postcode'],
                    ':state' => $_POST['state'],
                ]);
            } elseif ($selectedRole === 'police') {
                // Read uploaded file as binary
                if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['error'] === UPLOAD_ERR_OK) {
                    $fileContent = file_get_contents($_FILES['verification_doc']['tmp_name']);
                    if ($fileContent === false) {
                        throw new Exception("Failed to read police verification document.");
                    }

                    $sqlPolice = "INSERT INTO LAWENFORCEMENT_DETAIL (user_id, badge_id, branch, verification_doc) 
                                VALUES (:user_id, :badge_id, :branch, :verification_doc)";
                    $stmtPolice = $pdo->prepare($sqlPolice);
                    // Assign to variables for bindParam
                    $pol_user_id = $userId;
                    $pol_badge_id = $_POST['badge_id'];
                    $pol_branch = $_POST['branch'];
                    $pol_fileContent = $fileContent;

                    $stmtPolice->bindParam(':user_id', $pol_user_id, PDO::PARAM_INT);
                    $stmtPolice->bindParam(':badge_id', $pol_badge_id, PDO::PARAM_STR);
                    $stmtPolice->bindParam(':branch', $pol_branch, PDO::PARAM_STR);
                    $stmtPolice->bindValue(':verification_doc', $pol_fileContent, PDO::PARAM_LOB);
                    $stmtPolice->execute();
                } else {
                    throw new Exception("Failed to upload police verification document.");
                }
            } elseif ($selectedRole === 'lawyer') {
                if (isset($_FILES['lawyer_verification_doc']) && $_FILES['lawyer_verification_doc']['error'] === UPLOAD_ERR_OK) {
                    $fileContent = file_get_contents($_FILES['lawyer_verification_doc']['tmp_name']);
                    if ($fileContent === false) {
                        throw new Exception("Failed to read lawyer verification document.");
                    }

                    $sqlLawyer = "INSERT INTO LEGALREP_DETAIL (user_id, bar_registration_no, law_firm_name, verification_doc) 
                                VALUES (:user_id, :bar_no, :firm_name, :verification_doc)";
                    $stmtLawyer = $pdo->prepare($sqlLawyer);
                    // Assign to variables for bindParam
                    $law_user_id = $userId;
                    $law_bar_no = $_POST['bar_registration_no'];
                    $law_firm_name = $_POST['law_firm_name'] ?? '';
                    $law_fileContent = $fileContent;

                    $stmtLawyer->bindParam(':user_id', $law_user_id, PDO::PARAM_INT);
                    $stmtLawyer->bindParam(':bar_no', $law_bar_no, PDO::PARAM_STR);
                    $stmtLawyer->bindParam(':firm_name', $law_firm_name, PDO::PARAM_STR);
                    $stmtLawyer->bindValue(':verification_doc', $law_fileContent, PDO::PARAM_LOB);
                    $stmtLawyer->execute();
                } else {
                    throw new Exception("Failed to upload lawyer verification document.");
                }
            }

            $pdo->commit();

            if (in_array($selectedRole, ['police', 'lawyer'])) {
                // Use $_ENV everywhere for consistency
                $smtpUser = $_ENV['SMTP_USER'] ?? null;
                $smtpPass = $_ENV['SMTP_PASS'] ?? null;
                $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;

                if (!$smtpUser || !$smtpPass || !$adminEmail) {
                    die("One or more required environment variables are not set or not loaded!");
                }

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom($smtpUser, 'DVMS Registration');
                    $mail->addAddress($adminEmail);

                    $mail->isHTML(true);
                    $mail->Subject = "New {$selectedRole} registration requires verification";
                    $mail->Body    = "A new <b>{$selectedRole}</b> has registered.<br>" .
                                    "Name: <b>" . htmlspecialchars($_POST['full_name']) . "</b><br>" .
                                    "Email: <b>" . htmlspecialchars($_POST['email']) . "</b><br>" .
                                    "Please verify this user in the admin panel.";

                    $mail->send();
                } catch (Exception $e) {
                    echo "Mailer Error: " . $mail->ErrorInfo;
                }
            }
            echo "<script>
                alert('Registration successful for role: " . addslashes($selectedRole) . " Please Login');
                window.location.href = 'login.php';
            </script>";
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error during registration: " . $e->getMessage();
            $step = 'form';
        }
    } else {
        $step = 'form'; 
    }
}

function val($name) {
    return htmlspecialchars($_POST[$name] ?? '');
}

$selectedRole = $_POST['role'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .role-section { display: none; }
        .role-section.visible { display: block; }
    </style>
</head>
<body>
    <header>Domestic Violence Management System</header>

    <div class="register-form">
    <h2>User Registration</h2>

    <?php
    if (!empty($errors)) {
        echo "<div class='error-message'><ul>";
        foreach ($errors as $err) echo "<li>$err</li>";
        echo "</ul></div>";
    }
    ?>

    <form method="POST" enctype="multipart/form-data" id="regForm">
    <div class="form-group">
      <label>Select Role:<span style="color: red;">*</span></label>
      <div class="role-options">
        <label>
          <input type="radio" name="role" value="citizen" <?= $selectedRole=='citizen'?'checked':'' ?>> Citizen
        </label>
        <label>
          <input type="radio" name="role" value="police" <?= $selectedRole=='police'?'checked':'' ?>> Police
        </label>
        <label>
          <input type="radio" name="role" value="lawyer" <?= $selectedRole=='lawyer'?'checked':'' ?>> Lawyer
        </label>
      </div>
    </div>
    <hr>
        <h3>Common Information</h3>
        <label>Full Name:<span style="color: red;">*</span><input type="text" name="full_name" value="<?= val('full_name') ?>" required></label>
        <label>Date of Birth:<span style="color: red;">*</span><input type="date" name="dob" value="<?= val('dob') ?>" required></label>
        <label>Race:<span style="color: red;">*</span>
        <select name="race" required>
            <option value="">-- Please select --</option>
            <option value="Malay" <?= val('race') == 'Malay' ? 'selected' : '' ?>>Malay</option>
            <option value="Chinese" <?= val('race') == 'Chinese' ? 'selected' : '' ?>>Chinese</option>
            <option value="Indian" <?= val('race') == 'Indian' ? 'selected' : '' ?>>Indian</option>
            <option value="Sabah Bumiputera" <?= val('race') == 'Sabah Bumiputera' ? 'selected' : '' ?>>Sabah Bumiputera</option>
            <option value="Sarawak Bumiputera" <?= val('race') == 'Sarawak Bumiputera' ? 'selected' : '' ?>>Sarawak Bumiputera</option>
            <option value="Orang Asli" <?= val('race') == 'Orang Asli' ? 'selected' : '' ?>>Orang Asli</option>
            <option value="Others" <?= val('race') == 'Others' ? 'selected' : '' ?>>Others</option>
        </select>
        </label>   
        <label>Email:<span style="color: red;">*</span><input type="email" name="email" value="<?= val('email') ?>" required></label>
        <label>Phone Number:<span style="color: red;">*</span><input type="text" name="phone_num" value="<?= val('phone_num') ?>" ></label>
        <label>IC Number:<span style="color: red;">*</span><input type="text" name="ic" value="<?= val('ic') ?>"></label>
        <label>Passport Number:<input type="text" name="passport_num" value="<?= val('passport_num') ?>"></label>
        <label>Password:<span style="color: red;">*</span><input type="password" name="password" required></label>
        <label>Confirm Password:<span style="color: red;">*</span><input type="password" name="confirm_password" required></label>

        <!-- Citizen Section -->
        <div id="citizenSection" class="role-section">
            <h3>Citizen Address</h3>
            <label>Address Line 1:<span style="color: red;">*</span><input type="text" name="citizen_address_line1" value="<?= val('citizen_address_line1') ?>"></label>
            <label>Address Line 2:<input type="text" name="citizen_address_line2" value="<?= val('citizen_address_line2') ?>"></label>
            <label>City:<span style="color: red;">*</span><input type="text" name="citizen_city" value="<?= val('citizen_city') ?>"></label>
            <label>Postcode:<span style="color: red;">*</span><input type="text" name="citizen_postcode" value="<?= val('citizen_postcode') ?>"></label>
            <div class="form-group">
                <label for="state">State:<span style="color: red;">*</span></label>
                <select id="state" name="state">
                    <option value="">-- Select State --</option>
                    <?php
                    $states = [
                        'Johor', 'Kedah', 'Kelantan', 'Malacca', 'Negeri Sembilan', 
                        'Pahang', 'Penang', 'Perak', 'Perlis', 'Sabah', 'Sarawak', 
                        'Selangor', 'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya'
                    ];
                    foreach ($states as $state) {
                        $selected = ($_POST['state'] ?? '') === $state ? 'selected' : '';
                        echo "<option value=\"$state\" $selected>$state</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Police Section -->
        <div id="policeSection" class="role-section">
            <h3>Police Details</h3>
            <label>Badge ID:<span style="color: red;">*</span><input type="text" name="badge_id" value="<?= val('badge_id') ?>"></label>
            <div class="form-group">
                <label for="branch">Branch:<span style="color: red;">*</span></label>
                <select id="branch" name="branch">
                    <option value="">-- Select Branch --</option>
                    <?php
                    // Array: value => display text
                    $branches = [
                        'IPK Johor'            => 'Johor Police Contingent (IPK Johor)',
                        'IPK Kedah'            => 'Kedah Police Contingent (IPK Kedah)',
                        'IPK Kelantan'         => 'Kelantan Police Contingent (IPK Kelantan)',
                        'IPK Melaka'           => 'Malacca Police Contingent (IPK Melaka)',
                        'IPK Negeri Sembilan'  => 'Negeri Sembilan Police Contingent (IPK Negeri Sembilan)',
                        'IPK Pahang'           => 'Pahang Police Contingent (IPK Pahang)',
                        'IPK Pulau Pinang'     => 'Penang Police Contingent (IPK Pulau Pinang)',
                        'IPK Perak'            => 'Perak Police Contingent (IPK Perak)',
                        'IPK Perlis'           => 'Perlis Police Contingent (IPK Perlis)',
                        'IPK Sabah'            => 'Sabah Police Contingent (IPK Sabah)',
                        'IPK Sarawak'          => 'Sarawak Police Contingent (IPK Sarawak)',
                        'IPK Selangor'         => 'Selangor Police Contingent (IPK Selangor)',
                        'IPK Terengganu'       => 'Terengganu Police Contingent (IPK Terengganu)',
                        'IPK Kuala Lumpur'     => 'Kuala Lumpur Police Contingent (IPK KL)',
                        'IPK Labuan'           => 'Labuan Police Contingent (IPK Labuan)',
                        'IPK Putrajaya'        => 'Putrajaya Police Contingent (IPK Putrajaya)'
                    ];
                    foreach ($branches as $value => $display) {
                        $selected = ($_POST['branch'] ?? '') === $value ? 'selected' : '';
                        echo "<option value=\"$value\" $selected>$display</option>";
                    }
                    ?>
                </select>
            </div>
            <label>Verification Document:<span style="color: red;">*</span><input type="file" name="verification_doc"></label>
        </div>

        <!-- Lawyer Section -->
        <div id="lawyerSection" class="role-section">
            <h3>Lawyer Details</h3>
            <label>Bar Registration No:<span style="color: red;">*</span><input type="text" name="bar_registration_no" value="<?= val('bar_registration_no') ?>"></label>
            <label>Law Firm Name:<span style="color: red;">*</span><input type="text" name="law_firm_name" value="<?= val('law_firm_name') ?>"></label>
            <label>Verification Document:<span style="color: red;">*</span><input type="file" name="lawyer_verification_doc"></label>
        </div>

        <button type="submit" class="btn1" name="register_submit">Register</button>
        <p class="register-link">Already have an account? <a href="login.php">Login here</a></p>
    </form>

    <script>
    function showRoleSection(role) {
        const roles = ['citizen', 'police', 'lawyer'];
        roles.forEach(r => {
            const el = document.getElementById(r + 'Section');
            if (el) {
                if (r === role) {
                    el.classList.add('visible');
                } else {
                    el.classList.remove('visible');
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const selected = document.querySelector('input[name="role"]:checked');
        if (selected) {
            showRoleSection(selected.value);
        }
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', () => {
                showRoleSection(radio.value);
            });
        });
    });
    </script>
    </div>
    <footer>© 2025 DVMS. All rights reserved.</footer>
</body>
</html>