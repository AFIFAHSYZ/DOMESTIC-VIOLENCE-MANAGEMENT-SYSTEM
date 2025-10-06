<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ctzn_report.php");
    exit();
}

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get attachment as binary (bytea) for single file (adjust for multiple if needed)
$attachmentData = null;
function nullIfEmpty($value) {
    return ($value === '' || $value === null) ? null : $value;
}

try {
    $pdo->beginTransaction();

    // 1. Insert Victim
    $victimSql = "INSERT INTO victim (
        full_name, date_of_birth, gender, phone_number, email, notes,
        address_line1, address_line2, city, state, postal_code, age
    ) VALUES (
        :full_name, :date_of_birth, :gender, :phone_number, :email, :notes,
        :address_line1, :address_line2, :city, :state, :postal_code,  :age
    ) RETURNING victim_id";
    $stmtVictim = $pdo->prepare($victimSql);
$stmtVictim->execute([
    ':full_name' => $_POST['victim_name'],
    ':date_of_birth' => nullIfEmpty($_POST['victim_dob']),
    ':gender' => $_POST['victim_gender'],
    ':phone_number' => nullIfEmpty($_POST['victim_phone']),
    ':email' => $_POST['victim_email'] ?? null,
    ':notes' => null,
    ':address_line1' => $_POST['victim_address_line_1'],
    ':address_line2' => $_POST['victim_address_line_2'],
    ':city' => $_POST['victim_city'],
    ':state' => $_POST['victim_state'],
    ':postal_code' => nullIfEmpty($_POST['victim_postal_code']),
        ':age' => nullIfEmpty($_POST['victim_age']),

]);
    $victim_id = $stmtVictim->fetchColumn();



    $violenceHistory = null;
if (isset($_POST['known_violence_history'])) {
    switch ($_POST['known_violence_history']) {
        case 'yes':
            $violenceHistory = true;
            break;
        case 'no':
            $violenceHistory = false;
            break;
        case 'unknown':
            $violenceHistory = null; // store NULL if unknown
            break;
    }
}

    // 2. Insert Offender
    $offenderSql = "INSERT INTO offender (
        full_name, date_of_birth, gender, phone_number,
        address_line1, address_line2, city, state, postal_code,
        occupation, relationship_to_victim, physical_description,
        known_violence_history, notes, age
    ) VALUES (
        :full_name, :date_of_birth, :gender, :phone_number,
        :address_line1, :address_line2, :city, :state, :postal_code,
        :occupation, :relationship_to_victim, :physical_description,
        :known_violence_history, :notes,  :age
    ) RETURNING offender_id";
    $stmtOffender = $pdo->prepare($offenderSql);
    $stmtOffender->execute([
        ':full_name' => $_POST['offender_name'],
        ':date_of_birth' => $_POST['offender_dob'] ?: null,
        ':gender' => $_POST['offender_gender'],
':phone_number' => nullIfEmpty($_POST['offender_phone']),
        ':address_line1' => $_POST['offender_address_line_1'],
        ':address_line2' => $_POST['offender_address_line_2'],
        ':city' => $_POST['offender_city'],
        ':state' => $_POST['offender_state'],
':postal_code' => nullIfEmpty($_POST['offender_postal_code']),
        ':occupation' => $_POST['occupation'],
        ':relationship_to_victim' => $_POST['relationship_to_victim'],
        ':physical_description' => $_POST['physical_desc'],
    ':known_violence_history' => $violenceHistory,
':notes' => $_POST['notes'] ?? null,
':age' => nullIfEmpty($_POST['offender_age'])
    ]);
    $offender_id = $stmtOffender->fetchColumn();

    // 3. Insert Case
    $caseSql = "INSERT INTO dv_case (
        reported_by, abuse_type, abuse_desc, report_date, status_id,
        address_line1, address_line2, city, state, postal_code,
        victim_id, offender_id
    ) VALUES (
        :reported_by, :abuse_type, :abuse_desc, CURRENT_TIMESTAMP, :status_id,
        :address_line1, :address_line2, :city, :state, :postal_code,
        :victim_id, :offender_id
    ) RETURNING case_id";
    $stmtCase = $pdo->prepare($caseSql);
    $stmtCase->execute([
        ':reported_by' => $user_id,
        ':abuse_type' => $_POST['incident_type'],
        ':abuse_desc' => $_POST['description'],
        ':status_id' => 1, 
        ':address_line1' => $_POST['address_line_1'],
        ':address_line2' => $_POST['address_line_2'],
        ':city' => $_POST['city'],
        ':state' => $_POST['state'],
':postal_code' => nullIfEmpty($_POST['postal_code']),
        ':victim_id' => $victim_id,
        ':offender_id' => $offender_id,
    ]);
    $case_id = $stmtCase->fetchColumn();

    // 4. Insert Evidence (if any)
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


    $pdo->commit();

    $smtpUser   = $_ENV['SMTP_USER'] ?? null;
    $smtpPass   = $_ENV['SMTP_PASS'] ?? null;
    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? null;

    if ($smtpUser && $smtpPass && $adminEmail) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($smtpUser, 'DVMS Case Report');
            $mail->addAddress($adminEmail);

            $mail->isHTML(true);
            $mail->Subject = "New DV Case Reported: Action Required";
            $mail->Body    =
                "A new domestic violence case has been reported.<br>" .
                "Reported by User ID: <b>" . htmlspecialchars($user_id) . "</b><br>" .
                "Incident Type: <b>" . htmlspecialchars($_POST['incident_type']) . "</b><br>" .
                "Date/Time: <b>" . htmlspecialchars($_POST['incident_date'] ?? '') . "</b><br>" .
                "<a href='$caseDetailsUrl'>Review and assign a lawyer or police officer</a>.";

            $mail->send();
        } catch (Exception $e) {
            error_log("Admin alert email failed: " . $mail->ErrorInfo);
        }
    } else {
        error_log("SMTP or Admin email not set. Admin alert email not sent.");
    }

header("Location: ctzn_cases.php?success=1");
exit();


} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
}
?>