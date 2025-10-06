<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include 'conn.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM sys_user WHERE email = :email");
        $stmt->execute(['email' => $email]);

        if ($stmt->rowCount() === 0) {
            $message = "Email not found.";
        } else {
            $new_password = bin2hex(random_bytes(4));
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE sys_user SET password = :password WHERE email = :email");
            $update->execute(['password' => $hashed_password, 'email' => $email]);
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Use the SMTP_USER as the sender address
    $mail->setFrom($_ENV['SMTP_USER'], 'DVMS'); // Or replace 'Your System' with your preferred sender name
    $mail->addAddress($email);

    $mail->Subject = 'New Password';
    $mail->Body    = "Hello,\n\nYour new password is: $new_password\nPlease log in using the new password.";

    $mail->send();
    $message = "A new password has been sent to your email.";
} catch (Exception $e) {
    $message = "Failed to send email. Error: {$mail->ErrorInfo}";
}

        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background-color: #f9f9f9; }
        .container { max-width: 400px; margin: 0 auto; background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        h2 { text-align: center; }
        label { display: block; margin-top: 10px; }
        input[type="email"], input[type="submit"] { width: 100%; padding: 8px; margin-top: 5px; }
        input[type="submit"] { background: #007bff; color: white; border: none; cursor: pointer; margin-top: 20px; }
        input[type="submit"]:hover { background: #0056b3; }
        .message { margin-top: 15px; padding: 10px; background: #f1f1f1; border-left: 5px solid #ccc; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Reset Your Password</h2>
        <form method="post" action="">
            <label for="email">Enter your registered email:</label>
            <input type="email" name="email" id="email" required>
            <input type="submit" value="Reset Password">
        </form>

        <?php if (!empty($message)) { ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>
                    <p class="register-link">Log in to your account <a href="login.php">Click here</a></p>

    </div>

</body>
</html>
