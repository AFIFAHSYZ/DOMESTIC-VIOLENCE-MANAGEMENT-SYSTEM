<?php
session_start();
require 'conn.php';
require_once 'fpdf/fpdf.php'; // Adjust path if needed

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid case ID.");
}
$case_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT 
        c.case_id, c.report_date, c.abuse_type, c.abuse_desc, c.address_line1, c.address_line2,
        c.city, c.postal_code, s.status_name, v.full_name AS victim_name, o.full_name AS offender_name,
        r.full_name AS reporter_name, p.full_name AS officer_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    LEFT JOIN offender o ON c.offender_id = o.offender_id
    LEFT JOIN sys_user r ON c.assigned_lawyer = r.user_id
    LEFT JOIN sys_user p ON c.assigned_to = p.user_id
    WHERE c.case_id = ? AND c.reported_by = ?
    LIMIT 1
");
$stmt->execute([$case_id, $user_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    die("Case not found or you are not authorized to download this case.");
}

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Case Details', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);

foreach([
    'Case ID' => $case['case_id'],
    'Reported By' => $case['reporter_name'] ?? '-',
    'Report Date' => date('M d, Y', strtotime($case['report_date'])),
    'Victim Name' => $case['victim_name'] ?? '-',
    'Offender Name' => $case['offender_name'] ?? '-',
    'Abuse Type' => $case['abuse_type'],
    'Abuse Description' => $case['abuse_desc'],
    'Address Line 1' => $case['address_line1'],
    'Address Line 2' => $case['address_line2'],
    'City' => $case['city'],
    'Postal Code' => $case['postal_code'],
    'Status' => $case['status_name'],
    'Officer Name' => $case['officer_name'] ?? '-'
] as $label => $value) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 10, $label . ':', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 10, $value ?: '-', 0, 1);
}

$pdf->Output('D', "case_{$case['case_id']}.pdf");
exit;