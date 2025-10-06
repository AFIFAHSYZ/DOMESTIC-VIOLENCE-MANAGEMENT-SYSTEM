<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}


$user_id = $_SESSION['user_id'];

// ========== Fetch Logged-in User ==========
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) die("User not found.");

// ========== Unread Messages ==========
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

// ========== Filters ==========
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'status';

// ========== Chart Data Query ==========
$where = "assigned_lawyer = ?";
$params = [$user_id];
if ($date_from) {
    $where .= " AND report_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND report_date <= ?";
    $params[] = $date_to;
}

$data = [];
switch ($report_type) {
    case 'status':
        $query = $pdo->prepare("SELECT cs.status_name, COUNT(*) as count
            FROM dv_case dc
            JOIN case_status cs ON dc.status_id = cs.status_id
            WHERE $where
            GROUP BY cs.status_name
            ORDER BY cs.status_name");
        break;
    case 'type':
        $query = $pdo->prepare("SELECT abuse_type, COUNT(*) as count
            FROM dv_case
            WHERE abuse_type IS NOT NULL AND $where
            GROUP BY abuse_type
            ORDER BY abuse_type");
        break;
    case 'cases_month':
        $query = $pdo->prepare("SELECT TO_CHAR(report_date, 'Mon YYYY') as month, COUNT(*) as count
            FROM dv_case
            WHERE $where
            GROUP BY TO_CHAR(report_date, 'Mon YYYY')
            ORDER BY MIN(report_date)");
        break;
    default:
        $data = [];
        break;
}
if (isset($query)) {
    $query->execute($params);
    $data = $query->fetchAll(PDO::FETCH_ASSOC);
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lawyer Reports & Analytics</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable, .printable * { visibility: visible; }
            .no-print { display: none !important; }
        }
        .chart-container {
            width: 100%; max-width: 520px; margin: 30px auto;
            padding: 20px; background: #fff; border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0;
        }
        .filter-form label { font-weight: bold; }
        .btn1 {
            padding: 6px 15px; background: #4682B4; color: #fff;
            border: none; border-radius: 6px; cursor: pointer;
        }
        #chart-description {
            margin-top: 16px; font-style: italic;
            color: #444; font-size: 1.05em;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-profile">
                    <?php if ($user['profilepic']): ?>
                        <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>" alt="Profile" class="user-avatar">
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
                <li ><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Case</a></li>
                <li><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages <?= $unread > 0 ? "<span class='notification-badge'>$unread</span>" : "" ?></a></li>
                <li class="active"><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header printable">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        </header>

        <form class="filter-form no-print" method="get">
            <label>Report:</label>
            <select name="report_type" onchange="this.form.submit()">
                <option value="status" <?= $report_type=='status'?'selected':'' ?>>Cases by Status</option>
                <option value="type" <?= $report_type=='type'?'selected':'' ?>>Cases by Type of Abuse</option>
                <option value="cases_month" <?= $report_type=='cases_month'?'selected':'' ?>>Cases per Month</option>
            </select>
            <label>From:</label>
            <input type="date" name="date_from" value="<?= h($date_from) ?>" max="<?= date('Y-m-d') ?>">
            <label>To:</label>
            <input type="date" name="date_to" value="<?= h($date_to) ?>" max="<?= date('Y-m-d') ?>">
            <button type="submit" class="btn1">Generate</button>
        </form>

        <div class="no-print" style="text-align:right; margin-bottom:20px;">
            <button onclick="window.print()" class="btn1"><i class="fas fa-print"></i> Print</button>
            <button onclick="exportPDF()" class="btn1"><i class="fas fa-file-pdf"></i> Export PDF</button>
        </div>

        <div class="chart-container printable" id="print-area">
            <h2>
                <?= [
                    'status' => 'Cases by Status',
                    'type' => 'Cases by Type of Abuse',
                    'cases_month' => 'Cases per Month'
                ][$report_type] ?? '' ?>
            </h2>
            <p>Date range: <?= h($date_from) ?> to <?= h($date_to) ?></p>

            <?php if (count($data) > 0): ?>
                <canvas id="reportChart"></canvas>
                <div id="chart-description">
                    <?= [
                        'status' => 'This chart shows the number of cases based on their status.',
                        'type' => 'This chart shows case distribution by type of abuse.',
                        'cases_month' => 'This chart shows the number of cases reported per month.'
                    ][$report_type] ?? '' ?>
                </div>
            <?php else: ?>
                <p style="text-align:center; font-style:italic; color:#777; margin-top:20px;">
                    No data found for the selected range.
                </p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const data = <?= json_encode($data) ?>;
const type = "<?= $report_type ?>";

let labels = [], dataset = [], datasets = [], chartType = 'bar';
if (data.length > 0) {
    if (type === 'status') {
        chartType = 'pie';
        labels = data.map(d => d.status_name);
        dataset = data.map(d => d.count);
    } else if (type === 'type') {
        chartType = 'pie';
        labels = data.map(d => d.abuse_type);
        dataset = data.map(d => d.count);
    } else if (type === 'cases_month') {
        chartType = 'line';
        labels = data.map(d => d.month);
        dataset = data.map(d => d.count);
    }
    datasets = [{
        data: dataset,
        backgroundColor: ['#4682B4','#DC143C','#20B2AA','#9370DB','#FFA500','#FFD700','#6A5ACD'],
        borderColor: '#4682B4',
        label: 'Cases',
        fill: false
    }];
    const total = dataset.reduce((a, b) => a + b, 0);

    new Chart(document.getElementById('reportChart'), {
        type: chartType,
        data: { labels: labels, datasets: datasets },
        options: {
            plugins: {
                datalabels: {
                    color: '#222',
                    anchor: 'center',
                    align: 'center',
                    font: { weight: 'bold', size: 15 },
                    formatter: (value) => {
                        return (type === 'cases_month') ? value : value + ' (' + Math.round(value * 100 / total) + '%)';
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}

function exportPDF() {
    html2canvas(document.getElementById('print-area'), {scale:2}).then(canvas => {
        const pdf = new window.jspdf.jsPDF('landscape', 'px', [canvas.width, canvas.height]);
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save('lawyer-report-<?=date("Ymd_His")?>.pdf');
    });
}
</script>
</body>
</html>
