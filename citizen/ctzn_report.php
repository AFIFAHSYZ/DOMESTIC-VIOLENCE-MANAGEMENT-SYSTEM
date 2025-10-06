<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unread message count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report New Case | DV Assistance System</title>
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
                <li ><a href="citizen_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li ><a href="ctzn_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li class="active"><a href="ctzn_report.php"><i class="fas fa-plus-circle"></i> Report New Case</a></li>   
                <li><a href="ctzn_messages.php"><i class="fas fa-envelope"></i> Messages
                <span class="notification-badge"><?= $unread ?></span></a></li>
                <li><a href="ctzn_resources.php"><i class="fas fa-book"></i> Resources</a></li>
                <li><a href="ctzn_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a><li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Report New Case</h1>
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
        <section class="content-section">
            <!-- Popup Modal for False Report Consequences -->
            <div id="falseReportModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);">
                <div style="background:#fff;max-width:400px;margin:10vh auto;padding:2em 1.5em;border-radius:10px;position:relative;box-shadow:0 2px 16px rgba(0,0,0,0.2);">
                    <h2 style="margin-top:0;">Consequences of Submitting a False Report</h2>
                    <ul style="margin-left:1em;">
                        <li>Making a false police report is a criminal offense under Section 182 of the Penal Code.</li>
                        <li>If convicted, the offender may be punished with imprisonment for up to 6 months or a fine up to RM2,000 or both.</li>
                        <li>Your account may be suspended or permanently banned from this system.</li>
                        <li>Authorities may take further action, including investigation and legal proceedings.</li>
                    </ul>
                    <p style="color:#b00;font-weight:bold;">Please ensure all information you provide is true and accurate to the best of your knowledge.</p>
                    <div style="text-align: right;">
                        <button type="button" onclick="closeFalseReportModal()" class="btn1">Close</button>
                    </div>
                </div>            
            </div>

            <!-- Progress Bar -->
            <div class="progress">
              <div class="progress-bar" id="progressBar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Step 1 of 4</div>
            </div>

            <form action="ctzn_submit_case.php" method="POST" enctype="multipart/form-data" class="form-profile" id="wizardForm">
                <!-- Step 1: Case Details -->
                <div class="form-step active" id="step-1">
                    <h3>Case Details</h3>
                    <div class="form-group">
                        <label for="incident_type">Incident Type <span style="color: red;">*</span></label>
                        <select class="form-select" id="incident_type" name="incident_type" required>
                            <option value="">Select</option>
                            <option>Physical Abuse</option>
                            <option>Emotional/Psychological Abuse</option>
                            <option>Verbal Abuse</option>
                            <option>Sexual Abuse</option>
                            <option>Financial/Economic Abuse</option>
                            <option>Technological/Digital Abuse</option>
                            <option>Cultural/Spiritual Abuse</option>
                            <option>Neglect (caregiving relationships)</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="incident_date">Date of Incident <span style="color: red;">*</span></label>
                        <input type="datetime-local" class="form-control" id="incident_date" name="incident_date" required>
                    </div>
                    <div class="form-group">
                        <label for="address_line_1">Street Address 1 <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="address_line_1" name="address_line_1" required>
                    </div>
                    <div class="form-group">
                        <label for="address_line_2">Street Address 2</label>
                        <input type="text" class="form-control" id="address_line_2" name="address_line_2">
                    </div>
                    <div class="form-group">
                        <label for="city">City <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="state">State <span style="color: red;">*</span></label>
                        <select class="form-control" id="state" name="state" required>
                            <option value="">-- Select State --</option>
                            <option value="Johor">Johor</option>
                            <option value="Kedah">Kedah</option>
                            <option value="Kelantan">Kelantan</option>
                            <option value="Melaka">Melaka</option>
                            <option value="Negeri Sembilan">Negeri Sembilan</option>
                            <option value="Pahang">Pahang</option>
                            <option value="Penang">Penang</option>
                            <option value="Perak">Perak</option>
                            <option value="Perlis">Perlis</option>
                            <option value="Sabah">Sabah</option>
                            <option value="Sarawak">Sarawak</option>
                            <option value="Selangor">Selangor</option>
                            <option value="Terengganu">Terengganu</option>
                            <option value="Kuala Lumpur">Kuala Lumpur</option>
                            <option value="Labuan">Labuan</option>
                            <option value="Putrajaya">Putrajaya</option>
                        </select>
                    </div>    
                    <div class="form-group">
                        <label for="postal_code">Postal Code <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description of Incident</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachment">Evidence Attachments (you may select multiple files) <span style="color: red;">*</span></label>
                        <input type="file" class="form-control" id="attachment" name="attachment[]" accept="image/*,application/pdf" multiple required>
                        <small>You can upload images or PDF files as evidence.</small>
                    </div>
                    <div class="step-buttons">
                        <span></span>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                </div>

                <!-- Step 2: Victim Details -->
                <div class="form-step" id="step-2">
                    <h3>Victim Details</h3>
                    <div class="form-group">
                        <label for="victim_name">Name of the Victim </label>
                        <input type="text" class="form-control" id="victim_name" name="victim_name" >
                    </div>
                    <div class="form-group">
                        <label for="victim_dob">Date of Birth</label>
                        <input type="date" class="form-control" id="victim_dob" name="victim_dob">
                    <div class="form-group">
                        <label for="victim_age">Age </label>
                        <input type="number" class="form-control" id="victim_age" name="victim_age">
                    </div>
                    </div>
                    <div class="form-group">
                        <label for="victim_gender">Gender </label>
                        <select class="form-select" id="victim_gender" name="victim_gender" >
                            <option value="">Select</option>
                            <option>Female</option>
                            <option>Male</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="victim_phone">Phone Number </label>
                        <input type="text" class="form-control" id="victim_phone" name="victim_phone">
                    </div>
                    <div class="form-check-row">
                        <label style="font-weight: normal; margin-bottom:0;">
                            <input type="checkbox" id="victim_same_address" style="margin-right: 0.5em;" />
                            Same as incident address
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="victim_address_line_1">Street Address 1 </label>
                        <input type="text" class="form-control" id="victim_address_line_1" name="victim_address_line_1" >
                    </div>
                    <div class="form-group">
                        <label for="victim_address_line_2">Street Address 2 </label>
                        <input type="text" class="form-control" id="victim_address_line_2" name="victim_address_line_2" >
                    </div>
                    <div class="form-group">
                        <label for="victim_city">City </label>
                        <input type="text" class="form-control" id="victim_city" name="victim_city">
                    </div>
                    <div class="form-group">
                        <label for="victim_state">State </label>
                        <input type="text" class="form-control" id="victim_state" name="victim_state">
                    </div>
                    <div class="form-group">
                        <label for="victim_postal_code">Postal Code </label>
                        <input type="text" class="form-control" id="victim_postal_code" name="victim_postal_code">
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                </div>

                <!-- Step 3: Offender Details -->
                <div class="form-step" id="step-3">
                    <h3>Offender Details</h3>
                    <div class="form-group">
                        <label for="offender_name">Name of the Offender <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="offender_name" name="offender_name"  >
                    </div>
                    <div class="form-group">
                        <label for="offender_dob">Date of Birth </label>
                        <input type="date" class="form-control" id="offender_dob" name="offender_dob">
                    </div>
                    <div class="form-group">
                        <label for="offender_age">Age </label>
                        <input type="number" class="form-control" id="offender_age" name="offender_age">
                    </div>

                    <div class="form-group">
                        <label for="offender_gender">Gender </label>
                        <select class="form-select" id="offender_gender" name="offender_gender" >
                            <option value="">Select</option>
                            <option>Female</option>
                            <option>Male</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="offender_phone">Phone Number </label>
                        <input type="text" class="form-control" id="offender_phone" name="offender_phone">
                    </div>
                    <div class="form-check-row">
                        <label style="font-weight: normal; margin-bottom:0;">
                            <input type="checkbox" id="offender_same_address" style="margin-right: 0.5em;" />
                            Same as incident address
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="offender_address_line_1">Street Address 1 </label>
                        <input type="text" class="form-control" id="offender_address_line_1" name="offender_address_line_1" >
                    </div>
                    <div class="form-group">
                        <label for="offender_address_line_2">Street Address 2</label>
                        <input type="text" class="form-control" id="offender_address_line_2" name="offender_address_line_2">
                    </div>
                    <div class="form-group">
                        <label for="offender_city">City </label>
                        <input type="text" class="form-control" id="offender_city" name="offender_city">
                    </div>
                    <div class="form-group">
                        <label for="offender_state">State </label>
                        <input type="text" class="form-control" id="offender_state" name="offender_state">
                    </div>
                    <div class="form-group">
                        <label for="offender_postal_code">Postal Code </label>
                        <input type="text" class="form-control" id="offender_postal_code" name="offender_postal_code">
                    </div>
                    <div class="form-group">
                        <label for="occupation">Occupation </label>
                        <input type="text" class="form-control" id="occupation" name="occupation">
                    </div>
                    <div class="form-group">
                        <label for="relationship_to_victim">Relationship to Victim </label>
                        <input type="text" class="form-control" id="relationship_to_victim" name="relationship_to_victim">
                    </div>
                    <div class="form-group">
                        <label for="physical_desc">Physical Description</label>
                        <input type="text" class="form-control" id="physical_desc" name="physical_desc">
                    </div>
                    <div class="form-group">
                        <label for="known_violence_history">Known Violence History </label>
                        <select class="form-select" id="known_violence_history" name="known_violence_history" >
                            <option value="">Select</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                            <option value="unknown">Unknown</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                </div>

                <!-- Step 4: Review & Submit -->
                <div class="form-step" id="step-4">
                    <h3>Review & Submit</h3>
                    <p>Please review your information before submitting.</p>
                    <label style="font-weight: normal; margin-bottom:0;">
                            <input type="checkbox" id="ack_false_report" style="margin-right: 0.5em;" required />
                            I acknowledge that submitting a false report is a serious offense and may lead to legal consequences. <span style="color: red;">*</span>
                            <a href="#" id="falseReportInfo" style="color:#007bff;text-decoration:underline;">(See details)</a>

                    </label>


                    <div class="step-buttons">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="submit" class="btn btn-primary">Submit Case Report</button>
                    </div>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
// Wizard logic
const steps = document.querySelectorAll('.form-step');
let currentStep = 0;
const progressBar = document.getElementById('progressBar');

// Show a given step, and toggle required attributes
function showStep(index) {
    steps.forEach((step, i) => {
        const isActive = i === index;
        step.classList.toggle('active', isActive);

        // Disable required on hidden steps
        step.querySelectorAll('[required]').forEach(input => {
            if (!isActive) {
                input.dataset.wasRequired = "true";
                input.removeAttribute('required');
            } else if (input.dataset.wasRequired) {
                input.setAttribute('required', 'true');
            }
        });
    });

    // Update progress bar
    const percent = Math.round(((index + 1) / steps.length) * 100);
    progressBar.style.width = percent + '%';
    progressBar.setAttribute('aria-valuenow', percent);
    progressBar.textContent = `Step ${index + 1} of ${steps.length}`;
}

function nextStep() {
    if (currentStep < steps.length - 1) {
        currentStep++;
        showStep(currentStep);
    }
}
function prevStep() {
    if (currentStep > 0) {
        currentStep--;
        showStep(currentStep);
    }
}

// Prevent form submit on Enter key (except inside textarea)
document.getElementById('wizardForm').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
    }
});

// Modal logic
function showFalseReportModal(callback) {
    document.getElementById('falseReportModal').style.display = 'block';
    window.falseReportModalCallback = callback;
}
function closeFalseReportModal() {
    document.getElementById('falseReportModal').style.display = 'none';
    if (typeof window.falseReportModalCallback === 'function') {
        window.falseReportModalCallback();
        window.falseReportModalCallback = null;
    }
}
document.getElementById('falseReportInfo').addEventListener('click', function(e) {
    e.preventDefault();
    showFalseReportModal();
});

// Safety: prevent submit if acknowledgment not checked
document.getElementById('wizardForm').addEventListener('submit', function(e) {
    var ack = document.getElementById('ack_false_report');
    if (!ack.checked) {
        alert('You must acknowledge the warning about false reports before submitting.');
        e.preventDefault();
    } else {
        console.log("Form is being submitted to server...");
    }
});

// Initialize first step
showStep(currentStep);
</script>


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