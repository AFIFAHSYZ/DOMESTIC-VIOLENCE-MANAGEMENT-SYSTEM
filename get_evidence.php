<?php
session_start();
require 'conn.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

// Check if evidence ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Missing evidence ID.');
}

$evidence_id = (int) $_GET['id'];

// Fetch file info from the DB
$stmt = $pdo->prepare("
    SELECT file_name, mime_type, attachment
    FROM evidence
    WHERE evidence_id = ?
");
$stmt->execute([$evidence_id]);
$evidence = $stmt->fetch(PDO::FETCH_ASSOC);

// If no evidence found
if (!$evidence) {
    http_response_code(404);
    exit('Evidence not found.');
}

// If DB returns a stream resource, convert it
$data = $evidence['attachment'];
if (is_resource($data)) {
    $data = stream_get_contents($data);
}

// Check if download mode is requested
$isDownload = isset($_GET['download']) && $_GET['download'] == 1;

// Send appropriate headers based on DB values
header('Content-Type: ' . $evidence['mime_type']);
if ($isDownload) {
    header('Content-Disposition: attachment; filename="' . basename($evidence['file_name']) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($evidence['file_name']) . '"');
}

// Output the file
echo $data;
exit;
