<?php
session_start();
require '../conn.php';
require '../session_timeout.php';


// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'police') {
    header("Location: unauthorized.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, profilepic, user_id FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();


if (!$user) {
    die("Police user not found.");
}

// Get unread messages
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

// Filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'status';

// For dropdowns
$statusList = $pdo->query("SELECT status_id, status_name FROM case_status ORDER BY status_id")->fetchAll();
$caseTypeList = $pdo->query("SELECT DISTINCT abuse_type FROM dv_case WHERE abuse_type IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

// Build where clause (assigned_to = $user_id and date filters)
$where = "assigned_to = ?";
$params = [$user_id];

if ($date_from) {
    $where .= " AND report_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND report_date <= ?";
    $params[] = $date_to;
}

// Chart data
$data = [];
switch ($report_type) {
    case "status":
        $query = $pdo->prepare("SELECT cs.status_id, cs.status_name, COUNT(*) as count
            FROM dv_case dc
            JOIN case_status cs ON dc.status_id = cs.status_id
            WHERE $where
            GROUP BY cs.status_id, cs.status_name
            ORDER BY cs.status_id");
        $query->execute($params);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "type":
        $query = $pdo->prepare("SELECT abuse_type, COUNT(*) as count FROM dv_case WHERE abuse_type IS NOT NULL AND $where GROUP BY abuse_type");
        $query->execute($params);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "cases_month":
        $query = $pdo->prepare("SELECT TO_CHAR(report_date, 'Mon YYYY') as month, COUNT(*) as count FROM dv_case WHERE $where GROUP BY TO_CHAR(report_date, 'Mon YYYY') ORDER BY MIN(report_date)");
        $query->execute($params);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        break;
    default:
        $query = $pdo->prepare("SELECT cs.status_id, cs.status_name, COUNT(*) as count
            FROM dv_case dc
            JOIN case_status cs ON dc.status_id = cs.status_id
            WHERE $where
            GROUP BY cs.status_id, cs.status_name
            ORDER BY cs.status_id");
        $query->execute($params);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        break;
}

function escape($v) { return htmlspecialchars($v, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Police Reports & Analytics</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable, .printable * { visibility: visible; }
            .printable { position: absolute; left: 0; top: 0; width: 100%; background: white;}
            .no-print { display: none !important; }
        }
        .chart-container { width: 100%; max-width: 520px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .filter-form { display: flex; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
        .filter-form label { font-weight: bold; }
        .btn1 { padding: 5px 15px; background: #4682B4; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .export-btns { margin: 20px 0; text-align: right; }
        .export-btns button { margin-left: 10px; }
        #reportChart { max-width: 420px; max-height: 320px; margin: 0 auto 10px auto; display: block;}
        #chart-description { margin-top:16px; font-style:italic; color:#444; font-size:1.07em; text-align:left;}
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
                <li><a href="police_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="police_cases.php"><i class="fas fa-briefcase"></i> My Cases</a></li>
                <li >
                <a href="police_messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unread > 0): ?>
                        <span class="notification-badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                </li>
                <li  class="active"><a href="police_reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li><a href="police_profile.php"><i class="fas fa-user-cog"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header printable">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        </header>
        <form class="filter-form no-print" method="get" action="">
            <label for="report_type">Report:</label>
            <select name="report_type" id="report_type" onchange="this.form.submit()">
                <option value="status" <?= $report_type=='status'?'selected':''?>>Cases by Status</option>
                <option value="type" <?= $report_type=='type'?'selected':''?>>Cases by Type of Abuse</option>
                <option value="cases_month" <?= $report_type=='cases_month'?'selected':''?>>Cases Reported per Month</option>
            </select>
            <label for="date_from">From:</label>
            <input type="date" name="date_from" value="<?= escape($date_from) ?>" max="<?= date('Y-m-d') ?>">
            <label for="date_to">To:</label>
            <input type="date" name="date_to" value="<?= escape($date_to) ?>" max="<?= date('Y-m-d') ?>">
            <button type="submit" class="btn1">Generate Report</button>
        </form>

        <div class="export-btns no-print">
            <button onclick="window.print()" class="btn1"><i class="fas fa-print"></i> Print</button>
            <button onclick="exportPDF()" class="btn1"><i class="fas fa-file-pdf"></i> Export PDF</button>
        </div>

        <div class="chart-container printable" id="print-area">
            <h2>
                <?php
                $titles = [
                    "status"=>"Cases by Status",
                    "type"=>"Cases by Type of Abuse",
                    "cases_month"=>"Cases Reported per Month"
                ];
                echo $titles[$report_type] ?? "";
                ?>
            </h2>
            <p>Date range: <?= escape($date_from) ?> to <?= escape($date_to) ?></p>
            <?php if ($report_type === 'status' || $report_type === 'type'): ?>
                <div style="text-align:center;font-weight:bold;">
                    Total: <?= array_sum(array_column($data, 'count')) ?>
                </div>
            <?php endif; ?>
            <canvas id="reportChart"></canvas>
            <div id="chart-description">
                <?php
                $chartDescriptions = [
                    'status' =>
                        'This chart visualizes the distribution of cases across different status categories, providing an at-a-glance overview of your handled case progress and closure rates.',
                    'type' =>
                        'This chart displays the number of cases reported by type of abuse, helping you identify trends and focus areas in your assignments.',
                    'cases_month' =>
                        'This line graph shows the number of your assigned cases reported each month, revealing seasonal or temporal patterns in reporting.'
                ];
                echo $chartDescriptions[$report_type] ?? "";
                ?>
            </div>
        </div>
    </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const data = <?= json_encode($data) ?>;
const type = "<?= $report_type ?>";
let chartType = 'bar';
let labels = [];
let dataset = [];
let datasets = [];

if(type==='status') {
    chartType = 'pie';
    labels = data.map(d=>d.status_name);
    dataset = data.map(d=>d.count);
    datasets = [{data: dataset, backgroundColor: ['#4682B4','#5F9EA0','#6A5ACD','#B0C4DE','#DC143C','#FFA500','#20B2AA','#9370DB','#F08080','#FFD700']}];
} else if(type==='type') {
    chartType = 'pie';
    labels = data.map(d=>d.abuse_type);
    dataset = data.map(d=>d.count);
    datasets = [{data: dataset, backgroundColor: ['#DC143C', '#FFA500', '#20B2AA', '#9370DB', '#4682B4', '#FFD700', '#B0C4DE']}];
} else if(type==='cases_month') {
    chartType = 'line';
    labels = data.map(d=>d.month);
    dataset = data.map(d=>d.count);
    datasets = [{label:'Cases', data: dataset, borderColor:'#4682B4', fill:false }];
}
const total = dataset.reduce((a, b) => a + b, 0);

const chart = new Chart(document.getElementById('reportChart'), {
    type: chartType,
    data: { labels: labels, datasets: datasets },
    options: {
        plugins: {
            datalabels: {
                color: '#222',
                anchor: 'center',
                align: 'center',
                font: { weight: 'bold', size: 15 },
                formatter: function(value, context) {
                    if (type === 'status' || type === 'type') {
                        return value + ' (' + (total > 0 ? Math.round(100 * value / total) : 0) + '%)';
                    } else {
                        return value;
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});

// PDF export using html2canvas + jsPDF
function exportPDF() {
    const printArea = document.getElementById('print-area');
    html2canvas(printArea, {scale:2}).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new window.jspdf.jsPDF({
            orientation: 'landscape',
            unit: 'px',
            format: [canvas.width, canvas.height]
        });
        pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save('police-report-<?=date("Ymd_His")?>.pdf');
    });
}
</script>
</body>
</html>