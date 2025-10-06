<?php 
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Fetch unread messages count for notification badge (optional for lawyers)
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

// Example resources categorized for better display
$laws = [
    [
        'title' => 'Domestic Violence Act 1994',
        'description' => 'Key provisions under the Domestic Violence Act 1994 and its latest amendments.',
        'link' => 'https://www.wccpenang.org/domestic-violence-laws-in-malaysia/'
    ],
    [
        'title' => 'Protection Order Procedures',
        'description' => 'How to apply for an Emergency Protection Order (EPO).',
        'link' => 'https://wao.org.my/laws-on-domestic-violence/'
    ],
    [
        'title' => 'BUKU GARIS PANDUAN PENGENDALIAN KES KEGANASAN RUMAH TANGGA EDISI KEDUA.pdf',
        'description' => 'Garis Panduan Pengendalian Kes Keganasan Rumah Tangga Tahun 2023 Edisi Kedua',
        'link' => 'https://www.kehakiman.gov.my/sites/default/files/2023-06/BUKU%20GARIS%20PANDUAN%20PENGENDALIAN%20KES%20KEGANASAN%20RUMAH%20TANGGA%20EDISI%20KEDUA.pdf'
    ]
];

$statistics = [
    [
        'title' => 'Domestic Violence Statistics',
        'description' => 'A breakdown of Malaysian domestic violence case statistics.',
        'link' => 'https://wao.org.my/domestic-violence-statistics/'
    ]
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Legal Resources | DVMS</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
<div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <?php if ($user['profilepic']): ?>
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" alt="Profile" class="user-avatar">
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
                <li><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Case</a></li>
                <li><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unread > 0): ?>
                        <span class="notification-badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li class="active"><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-book"></i> Legal Resources</h1>
        </header>
<div class="section-dropdown">
    <label for="sectionSelect">Jump to Section:</label>
    <select id="sectionSelect" onchange="jumpToSection()">
        <option value="">-- Select Section --</option>
        <option value="laws">Laws & Statutes</option>
        <option value="guidelines">Court Guidelines and Forms</option>
        <option value="training">Training and Continuing Education</option>
        <option value="case-law">Case Law Summaries & Precedents</option>
        <option value="support">Support Organizations & Contact Info</option>
    </select>
</div>

        <section  id="laws" class="content-section">
    <h2><i class="fas fa-gavel"></i> Laws & Statutes</h2>
    <ul class="resource-list">
        <li>
            <strong>Domestic Violence Act 1994 (Act 521)</strong><br>
            Detailed guide on protections under this act.<br>
            <a href="https://www.commonlii.org/my/legis/consol_act/dva1994178/" target="_blank">Read full act</a>
        </li>
        <li>
            <strong>Penal Code (Sections on violence, intimidation, threats)</strong><br>
            Relevant provisions covering assault, intimidation, and bodily harm.<br>
            <a href="https://www.rcrc-resilience-southeastasia.org/wp-content/uploads/2017/12/Penal-Code-Act-574.pdf" target="_blank">Read More</a>
        </li>
        <li>
            <strong>Child Protection Laws</strong><br>
            Laws protecting children from abuse and neglect.<br>
            <a href="https://antislaverylaw.ac.uk/wp-content/uploads/2019/08/Malaysia-Child-Act-1.pdf" target="_blank">Read More</a>
        </li>
        <li>
            <strong>Protection Order Procedures</strong><br>
            Guide on applying for Emergency Protection Orders and related procedures.<br>
            <a href="https://wao.org.my/laws-on-domestic-violence/" target="_blank">Learn more</a>
        </li>
    </ul>
</section>

<section id="guidelines" class="content-section">
    <h2><i class="fas fa-file-alt"></i> Court Guidelines and Forms</h2>
    <ul class="resource-list">
        <li>
            <strong>BUKU GARIS PANDUAN PENGENDALIAN KES KEGANASAN RUMAH TANGGA EDISI KEDUA.pdf</strong><br>
            Official guidelines for handling domestic violence cases.<br>
            <a href="https://www.kehakiman.gov.my/sites/default/files/2023-06/BUKU%20GARIS%20PANDUAN%20PENGENDALIAN%20KES%20KEGANASAN%20RUMAH%20TANGGA%20EDISI%20KEDUA.pdf" target="_blank">View PDF</a>
        </li>
        <li>
            <strong>Procedures In Civil Cases</strong><br>
            View procedures on cases (How To Claim, Trial & Post Trial Procedure)<br>
            <a href="https://www.kehakiman.gov.my/en/procedures-civil-cases" target="_blank">View Page</a>
        </li>
        <li>
            <strong>Understanding Divorce Laws</strong><br>
            Understanding Divorce Laws in Malaysia: A Guide to Legal Procedures<br>
            <a href="https://www.cywongngpartners.com.my/updates/understanding-divorce-laws-in-malaysia-a-guide-to-legal-procedures/" target="_blank">Download Guide</a>
        </li>
    </ul>
</section>

<section id="training" class="content-section">
    <h2><i class="fas fa-chalkboard-teacher"></i> Training and Continuing Education</h2>
    <ul class="resource-list">
        <li>
            <a href="https://vawnet.org/sc/training-and-education-0" target="_blank">VamNet.org Training and Education on Domestic & Sexual Violence</a><br>
            An Online Resource Library on Domestic & Sexual Violence Search Terms.
        </li>
        <li>
            <a href="https://klbar.org.my/events/" target="_blank">The Kuala Lumpur Bar Events</a><br>
            Upcoming legal education events and workshops.
        </li>
        <li>
            <a href="https://wao.org.my/volunteer/" target="_blank">Women's Aid Organisation Volunteering</a><br>
            Volunteer with WAO.
        </li>
    </ul>
</section>

<section  id="case-law" class="content-section">
    <h2><i class="fas fa-gavel"></i> Case Law Summaries & Precedents</h2>
    <ul class="resource-list">
        <li>
            <strong>Domestic Violence Act 1994</strong><br>
            An Overview of Domestic Violence Act 1994<br>
            <a href="https://mahwengkwai.com/domestic-violence-act-1994-an-overview/" target="_blank">Open Page</a>
        </li>
        <li>
            <strong>Malaysian Legal Database (CommonLII)</strong><br>
                        Search for case precedents and rulings.<br>
            <a href="https://www.commonlii.org/my/" target="_blank">Open Page</a>

        </li>
    </ul>
</section>

<section id="support" class="content-section">
    <h2><i class="fas fa-hands-helping"></i> Support Organizations & Contact Info</h2>
    <ul class="resource-list">
        <li>
            <strong>Women's Aid Organisation (WAO)</strong><br>
            Support and referrals for victims.<br>
            <a href="https://wao.org.my/contact-us/" target="_blank">Contact WAO</a>
        </li>
        <li>
            <strong>Ministry of Women, Family & Community Development</strong><br>
            Government resources and legal support.<br>
            <a href=https://www.kpwkm.gov.my/portal-main/article?id=alamat-dan-peta-lokasi target="_blank">Contact Ministry</a>
        </li>
        <li>
            <strong>Graces List</strong><br>
            Phone numbers and emails for local shelters.<br>
            <a href="/resources/crisis_centers.pdf" target="_blank">View List</a>
        </li>
    </ul>
</section>

    </main>
</div>

<script>
function jumpToSection() {
    const select = document.getElementById('sectionSelect');
    const selected = select.value;

    if (!selected) return;

    // Option 1: Scroll to section
    document.getElementById(selected).scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>
