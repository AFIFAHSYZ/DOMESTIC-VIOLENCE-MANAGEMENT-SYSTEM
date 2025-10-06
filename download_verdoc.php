<?php
require 'conn.php';

if (!isset($_GET['id'])) {
    die("Document ID not specified.");
}

$userId = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT verification_doc FROM LAWENFORCEMENT_DETAIL WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$row = $stmt->fetch();

if ($row && $row['verification_doc']) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="verification_document.pdf"');
    echo $row['verification_doc'];
} else {
    echo "Document not found.";
}
