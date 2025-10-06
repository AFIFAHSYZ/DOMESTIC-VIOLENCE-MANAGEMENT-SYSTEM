<?php
require 'conn.php';
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request.");
}
$evidence_id = intval($_GET['id']);

// Fetch evidence and check access (join with dv_case for owner check)
$stmt = $pdo->prepare("SELECT e.*, c.reported_by FROM evidence e 
    JOIN dv_case c ON e.case_id = c.case_id WHERE e.evidence_id = ?");
$stmt->execute([$evidence_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die("File not found.");
}

// Optional: Only allow case owner to download
if ($_SESSION['user_id'] != $file['reported_by']) {
    die("Permission denied.");
}

$filename = $file['file_name'] ?? ('evidence_' . $evidence_id);
$mime = $file['mime_type'] ?? 'application/octet-stream';
$data = $file['attachment'];

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($data));
echo $data;
exit;
?>