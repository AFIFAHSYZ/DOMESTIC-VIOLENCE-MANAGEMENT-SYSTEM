<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Resources | DV Assistance System</title>
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
                        <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
                    alt="Profile" class="user-avatar">
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
                    <li >
                        <a href="citizen_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_cases.php">
                            <i class="fas fa-folder-open"></i> My Cases
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_report.php">
                            <i class="fas fa-plus-circle"></i> Report New Case
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_messages.php">
                            <i class="fas fa-envelope"></i> Messages
                        <span class="notification-badge"><?= $unread ?></span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="ctzn_resources.php">
                            <i class="fas fa-book"></i> Resources
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_profile.php">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                    </li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a><li>
                </ul>
            </nav>
        </aside>


        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1>Support Resources</h1>
            </header>
<?php
echo '
<div style="
    background: #fff3cd;
    border-left: 6px solid #ffc107;
    padding: 12px 18px;
    margin: 15px 0;
    border-radius: 6px;
    color: #856404;
    display: flex;
    align-items: center;
    font-size: 14px;
">
    <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
    For your safety: press  <strong> Esc </strong>  button on your keyboard 3 times quickly to exit this system.
</div>
';
?>
            <!-- Laws Section -->
            <section class="content-section">
                <h2><i class="fas fa-gavel"></i> Malaysian Laws</h2>
                <ul class="resource-list">
                    <li>
                        <strong>Domestic Violence Act 1994 (Act 521)</strong><br>
                        Provides protection and legal recourse for victims of domestic violence.
                        <br><a href="https://www.commonlii.org/my/legis/consol_act/dva1994178/" target="_blank">Read on AGC Malaysia</a>
                    </li>
                    <li>
                        <strong>Penal Code (Sections 323, 324, 506, etc.)</strong><br>
                                                Covers assault, criminal intimidation, and bodily harm.

                        <br><a href="https://www.rcrc-resilience-southeastasia.org/wp-content/uploads/2017/12/Penal-Code-Act-574.pdf" target="_blank">Read More</a>
                    </li>
                    <li>
                        <strong>Child Act 2001</strong><br>
                                                Protects children from abuse and neglect.

                        <br><a href="https://antislaverylaw.ac.uk/wp-content/uploads/2019/08/Malaysia-Child-Act-1.pdf" target="_blank">Read More</a>
                    </li>
                </ul>
            </section>

            <!-- News Section -->
            <section class="content-section">
                <h2><i class="fas fa-newspaper"></i> Recent News & Articles</h2>
                <ul class="resource-list">
                    <li>
                        <a href="https://www.freemalaysiatoday.com/category/tag/domestic-violence" target="_blank">
                            More articles on domestic violence (Free Malaysia Today)
                        </a>
                    </li>
                    <li>
                        <a href="https://wao.org.my/our-advocacy/" target="_blank">
                            Our Advocacy to End Violence Against Women in Malaysia (WAO)
                        </a>
                    </li>
                    <li>
                        <a href="https://findahelpline.com/countries/my/topics/abuse-domestic-violence" target="_blank">
                            Helplines in Malaysia for abuse & domestic violence.
                        </a>
                    </li>
                </ul>
            </section>

            <!-- External Websites Section -->
            <section class="content-section">
                <h2><i class="fas fa-globe"></i> Helpful Websites</h2>
                <div class="resources-grid">
                    <a href="https://www.kpwkm.gov.my/portal-main/women" target="_blank" class="resource-card">
                        <i class="fas fa-female"></i>
                        <h3>Ministry of Women, Family & Community Dev</h3>
                    </a>
                    <a href="https://wao.org.my/" target="_blank" class="resource-card">
                        <i class="fas fa-hands-helping"></i>
                        <h3>Women's Aid Organisation (WAO)</h3>
                    </a>
                    <a href="https://findahelpline.com/countries/my/topics/abuse-domestic-violence" target="_blank" class="resource-card">
                        <i class="fas fa-phone-alt"></i>
                        <h3>Helpline List</h3>
                    </a>
                    <a href="https://sistersinislam.org/violence-against-women/" target="_blank" class="resource-card">
                        <i class="fas fa-users"></i>
                        <h3>Sisters in Islam</h3>
                    </a>
                </div>
            </section>
        </main>
    </div>

    <script src="js/script.js"></script>

    


<!-- Escape Key Exit Script -->
<script src="js/script.js"></script>


<!-- Escape Key Exit Script -->
<script>
let escPressCount = 0;
let escTimer = null;

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        escPressCount++;

        clearTimeout(escTimer);
        escTimer = setTimeout(() => escPressCount = 0, 3000);

        if (escPressCount >= 3) {
            window.location.href = 'about:blank';
        }
    }
});
</script>

</body>
</html>
