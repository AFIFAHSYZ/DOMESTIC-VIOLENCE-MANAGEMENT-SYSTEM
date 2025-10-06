<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../conn.php';

// Validate input
if (!isset($_GET['table']) || !isset($_GET['user_id'])) {
    http_response_code(400);
    exit("Missing parameters.");
}

$table = $_GET['table'];
$user_id = (int)$_GET['user_id'];

// Whitelist table names to prevent SQL injection
$allowedTables = ['legalrep_detail', 'lawenforcement_detail'];
if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    exit("Invalid table parameter.");
}

// Build and execute query
$sql = "SELECT verification_doc FROM $table WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['verification_doc'])) {
    http_response_code(404);
    exit("Verification document not found.");
}

$data = $row['verification_doc'];

// If data is a stream (resource), convert it to string
if (is_resource($data)) {
    $data = stream_get_contents($data);
}

// Clear output buffer
if (ob_get_length()) {
    ob_end_clean();
}

// Send file as PDF inline
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="verification_document.pdf"');
header('Content-Length: ' . strlen($data));
echo $data;
exit;
?>
