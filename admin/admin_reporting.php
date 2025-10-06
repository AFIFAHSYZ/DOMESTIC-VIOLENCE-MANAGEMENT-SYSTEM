<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    die("Admin user not found.");
}

// Handle filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'roles';

$date_params = [];
$date_where = "";
if ($date_from) {
    $date_where .= " AND report_date >= ?";
    $date_params[] = $date_from;
}
if ($date_to) {
    $date_where .= " AND report_date <= ?";
    $date_params[] = $date_to;
}

// Queries for each report type
$data = [];
switch ($report_type) {
    case "roles":
        $query = $pdo->query("
            SELECT 'Admin' AS role, COUNT(*) AS count
            FROM SYS_USER
            WHERE u_is_adm = TRUE
            UNION ALL
            SELECT 'Citizen' AS role, COUNT(*) AS count
            FROM SYS_USER
            WHERE u_is_ctzn = TRUE
            UNION ALL
            SELECT 'Law Enforcement' AS role, COUNT(*) AS count
            FROM SYS_USER
            WHERE u_is_lenf = TRUE
            UNION ALL
            SELECT 'Legal Representatives' AS role, COUNT(*) AS count
            FROM SYS_USER
            WHERE u_is_lrep = TRUE
        ");
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "age":
        $ageQuery = $pdo->prepare("SELECT dob FROM SYS_USER");
        $ageQuery->execute();
        $users = $ageQuery->fetchAll(PDO::FETCH_COLUMN);
        $ageGroups = ['Under 18'=>0, '18-25'=>0, '26-40'=>0, '41+'=>0];
        foreach ($users as $dob) {
            if (!$dob) continue;
            $age = (int)date_diff(date_create($dob), date_create('today'))->y;
            if ($age < 18) $ageGroups['Under 18']++;
            elseif ($age <= 25) $ageGroups['18-25']++;
            elseif ($age <= 40) $ageGroups['26-40']++;
            else $ageGroups['41+']++;
        }
        foreach($ageGroups as $group=>$count) $data[] = ['age_group'=>$group, 'count'=>$count];
        break;
    case "abuse":
        $caseTypeQuery = $pdo->prepare("SELECT abuse_type, COUNT(*) as count FROM dv_case WHERE 1=1 $date_where GROUP BY abuse_type");
        $caseTypeQuery->execute($date_params);
        $data = $caseTypeQuery->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "cases_month":
        $caseMonthQuery = $pdo->prepare("SELECT TO_CHAR(report_date, 'Mon YYYY') as month, COUNT(*) as count FROM dv_case WHERE 1=1 $date_where GROUP BY TO_CHAR(report_date, 'Mon YYYY') ORDER BY MIN(report_date)");
        $caseMonthQuery->execute($date_params);
        $data = $caseMonthQuery->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "logs":
        $logsQuery = $pdo->prepare("SELECT DATE(event_time) as date, COUNT(*) as count FROM SYSTEM_LOGS WHERE DATE(event_time) >= ? AND DATE(event_time) <= ? GROUP BY DATE(event_time)");
        $logsQuery->execute([$date_from, $date_to]);
        $data = $logsQuery->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "login_logout":
        $loginLogoutQuery = $pdo->prepare("SELECT DATE(event_time) as date,
           COUNT(*) FILTER (WHERE event_type = 'login') AS login_count,
           COUNT(*) FILTER (WHERE event_type = 'logout') AS logout_count
        FROM SYSTEM_LOGS WHERE DATE(event_time) >= ? AND DATE(event_time) <= ?
        GROUP BY DATE(event_time)
        ORDER BY DATE(event_time)");
        $loginLogoutQuery->execute([$date_from, $date_to]);
        $data = $loginLogoutQuery->fetchAll(PDO::FETCH_ASSOC);
        break;
    case "actions":
        $actionTypeQuery = $pdo->prepare("SELECT event_type, COUNT(*) as count FROM SYSTEM_LOGS WHERE DATE(event_time) >= ? AND DATE(event_time) <= ? GROUP BY event_type");
        $actionTypeQuery->execute([$date_from, $date_to]);
        $data = $actionTypeQuery->fetchAll(PDO::FETCH_ASSOC);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        @media print {
            .no-print { display: none !important; }
            .printable, .printable * { visibility: visible !important; }
            canvas { display: block !important; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar no-print">
        <div class="sidebar-header">
            <div class="user-profile">
                <?php if ($user['profilepic']): ?>
                    <img src="get_profilepic.php?id=<?= $user['user_id'] ?>" alt="Profile" class="user-avatar">
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
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li ><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li class="active"><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    <main class="main-content">
        <header class="content-header printable">
            <h1><i class="fas fa-bar-chart"></i> Admin Report</h1>
        </header>
        <form class="filter-form no-print" method="get" action="">
            <label for="report_type">Report:</label>
            <select name="report_type" id="report_type" onchange="this.form.submit()">
                <option value="roles" <?= $report_type=='roles'?'selected':''?>>Users by Role</option>
                <option value="age" <?= $report_type=='age'?'selected':''?>>User Age Groups</option>
                <option value="abuse" <?= $report_type=='abuse'?'selected':''?>>Cases by Type of Abuse</option>
                <option value="cases_month" <?= $report_type=='cases_month'?'selected':''?>>Cases Reported per Month</option>
                <option value="logs" <?= $report_type=='logs'?'selected':''?>>System Log Activity</option>
            </select>
            <label for="date_from">From:</label>
            <input type="date" name="date_from" value="<?=htmlspecialchars($date_from)?>" max="<?=date('Y-m-d')?>">
            <label for="date_to">To:</label>
            <input type="date" name="date_to" value="<?=htmlspecialchars($date_to)?>" max="<?=date('Y-m-d')?>">
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
                    "roles"=>"Users by Role",
                    "age"=>"User Age Groups",
                    "abuse"=>"Cases by Type of Abuse",
                    "cases_month"=>"Cases Reported per Month",
                    "logs"=>"System Log Activity",
                    "login_logout"=>"Login vs Logout Activity",
                    "actions"=>"System Actions by Type"
                ];
                echo $titles[$report_type] ?? "";
                ?>
            </h2>
            <p>Date range: <?=htmlspecialchars($date_from)?> to <?=htmlspecialchars($date_to)?></p>
            <canvas id="reportChart"></canvas>
            <div id="chart-description">
                <?php
                // Deeper & calculated chart descriptions
                $chartDescriptions = [
                    'roles' =>
                        ' Role 1(Admin) 2(Citizen) 3(Law Enforcement) 4(Legal Representatives) : This analysis reveals the structural balance of user roles within the system. By examining the proportionality of roles, we can infer the administrative, operational, and end-user capacities at play, thus identifying potential areas of over- or under-representation and their implications for governance.',
                    'age' =>
                        'The age group visualization provides a nuanced perspective on the demographic spectrum of the user base. Interpreting these distributions allows us to anticipate user needs, detect generational trends, and calibrate outreach or support strategies in a data-driven fashion.',
                    'abuse' =>
                        'This chart deciphers the prevalence of each abuse type reported in domestic violence cases. By quantifying each category, we gain actionable intelligence on the dominant forms of abuse, enabling the design of more targeted interventions and resource allocation.',
                    'cases_month' =>
                        'Monthly case reporting trends are plotted to expose subtle shifts and recurring patterns in incident frequency. Through this temporal lens, administrators can correlate spikes to external factors and optimize the timing of awareness campaigns or response efforts.',
                    'logs' =>
                        'System log activity over time is an indirect yet powerful indicator of both user engagement and system stability. Anomalies or surges in this metric, when interpreted carefully, can signal operational events, security breaches, or organic growth.',
                    'login_logout' =>
                        'By juxtaposing login and logout activities, this analysis surfaces behavioral rhythms within the platform. Discrepancies or synchrony in these flows can reveal adoption cycles, session patterns, or even atypical use that merits further exploration.',
                    'actions' =>
                        'The spectrum of system actions, as portrayed here, provides granular insight into the operational fabric of the platform. Dominant or rare action types may reflect either healthy workflow execution or inefficiencies and can guide process refinement.'
                ];
                echo $chartDescriptions[$report_type] ?? "";
                ?>
            </div>
        </div>
    </main>
</div>
<!-- jsPDF and html2canvas from CDN for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const data = <?= json_encode($data) ?>;
const type = "<?= $report_type ?>";
let chartType = 'bar';
let labels = [];
let dataset = [];
let datasets = [];

if(type==='roles') {
    chartType = 'pie';
    labels = data.map(d=>d.role);
    dataset = data.map(d=>d.count);
    datasets = [{data: dataset, backgroundColor: ['#4682B4','#5F9EA0','#6A5ACD','#B0C4DE']}];
} else if(type==='age') {
    labels = data.map(d=>d.age_group);
    dataset = data.map(d=>d.count);
    datasets = [{label:'Users',data:dataset,backgroundColor:'#5F9EA0'}];
} else if(type==='abuse') {
    chartType = 'pie';
    labels = data.map(d=>d.abuse_type);
    dataset = data.map(d=>d.count);
    datasets = [{data: dataset, backgroundColor: ['#DC143C', '#FFA500', '#20B2AA', '#9370DB']}];
} else if(type==='cases_month') {
    chartType = 'line';
    labels = data.map(d=>d.month);
    dataset = data.map(d=>d.count);
    datasets = [{label:'Cases', data: dataset, borderColor:'#4682B4', fill:false }];
} else if(type==='logs') {
    chartType = 'line';
    labels = data.map(d=>d.date);
    dataset = data.map(d=>d.count);
    datasets = [{label:'Log Entries', data: dataset, borderColor:'#B22222', fill:false}];
}
const chart = new Chart(document.getElementById('reportChart'), {
    type: chartType,
    data: { labels: labels, datasets: datasets }
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
        pdf.save('admin-report-<?=date("Ymd_His")?>.pdf');
    });
}
</script>
</body>
</html>