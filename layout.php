<?php
session_start();
$role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domestic Violence Management System</title>
  <link rel="stylesheet" href="style.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f4f6f9;
      color: #333;
      scroll-behavior: smooth;
    }

 
header {
    
    background-color: #4682B4 	;
    color: white;
    padding: 1.5rem;
    text-align: center;
    font-size: 2rem;
    font-weight: bold;
}
    .section {
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      padding: 60px 10%;
      min-height: 100vh;
  opacity: 1 !important;
  transform: none !important;
        transition: opacity 0.8s ease, transform 0.8s ease;
    }

    .section.reveal {
      opacity: 1;
      transform: translateY(0);
    }

    .section:nth-child(even) {
      flex-direction: row-reverse;
      background: #eef4ff;
    }

    .section img {
      width: 40%;
      max-width: 400px;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0,0,0,0.1);
    }

    .section .text {
      width: 55%;
    }

    .section h3 {
      font-size: 1.8rem;
      color: #1a237e;
      margin-bottom: 12px;
    }

    .section p {
      font-size: 1.05rem;
      line-height: 1.6;
      color: #333;
    }

    .highlight {
      background: #d3e7ff;
      padding: 4px 8px;
      border-radius: 4px;
    }

    .button {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 20px;
      background: #4285F4;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
    }

footer {
    text-align: center;
    padding: 10px;
    background-color: #f1f1f1;
    font-size: 14px;
    color: #555;
}

    @media (max-width: 768px) {
      .section {
        flex-direction: column !important;
        text-align: center;
      }

      .section img, .section .text {
        width: 100%;
      }

      .section img {
        margin-bottom: 20px;
      }
    }
  </style>
</head>
<body>

<header>Domestic Violence Management System</header>


<div class="section">
  <div class="text">
    <?php if($role === 'guest'): ?>
      <h3>Welcome to DVMS</h3>
      <p><strong>We're glad you're here.</strong> The Domestic Violence Management System is a safe and supportive space designed to help survivors, support workers, and authorities manage and respond to domestic violence cases effectively.</p>
      <p><strong>Register</strong> today to begin your journey toward safety, empowerment, and justice. If you already have an account, <strong>log in</strong> to access your dashboard and resources.</p>
      <a href="register.php" class="button">Register</a>
      <a href="login.php" class="button">Login</a>
    <?php endif; ?>
  </div>
  <img src="images/1.jpg" alt="About DVMS">
</div>


<div class="section">
  <div class="text">
    <h3>About the Domestic Violence Management System</h3>
    <p><span class="highlight">DVMS</span> is a secure, confidential platform that modernizes the way domestic violence cases are reported, tracked, and resolved. It empowers survivors by providing a direct channel to report incidents and seek help, while equipping social workmanagement. This system is built on the principles of privacy, accessibility, and support.</p>
  </div>
  <img src="images/2.jpg" alt="About DVMS">
</div>

<div class="section">
  <div class="text">
    <h3>For Survivors</h3>
    <p>Survivors can safely and confidentially report incidents through DVMS, gaining access to support resources around the clock. The platform is designed to prioritize their safety and well-being. Survivors can track the progress of their cases, view updates, and communicate directly with counselors and case workers. Additional educational materials and emergency contact information are also made available to support them in difficult times.</p>
  </div>
  <img src="images/3.jpg" alt="Support for Survivors">
</div>

<div class="section">
  <div class="text">
    <h3>For Authorities & Admins</h3>
    <p>Authorities and administrators benefit from role-based secure access to the platform. DVMS provides real-time monitoring of reported cases and emerging trends, allowing decision-makers to allocate resources effectively. The system sends automated alerts for urgent or high-risk situations and helps streamline interdepartmental collaboration. Administrators can generate analytical reports to guide strategic planning and intervention efforts.</p>
  </div>
  <img src="images/4.jpg" alt="Authorities Features">
</div>

<div class="section">
  <div class="text">
    <h3>Why Choose DVMS?</h3>
    <p>DVMS ensures confidentiality through advanced data encryption and strict privacy protocols. It empowers individuals by providing direct access to help when it’s most needed and improves case handling efficiency through integrated collaboration tools. The platform was developed in consultation with domestic violence experts, making it both survivor-focused and operationally robust. With user-friendly navigation and multilingual support, DVMS ensures inclusivity for all users.</p>
  </div>
  <img src="images/5.jpg" alt="Why Choose DVMS">
</div>

<div class="section">
  <div class="text">
    <h3>How It Works</h3>
    <p>Users register as either survivors or support agents. Once registered, they can report and manage cases in a secure environment tailored to their roles. DVMS facilitates collaborative tracking, analysis, and resolution of each case, ensuring accountability and timely action. The platform also includes training modules and knowledge bases to guide users on best practices and platform usage.</p>
  </div>
  <img src="images/6.jpg" alt="How It Works">
</div>

<div class="section">
  <div class="text">
    <h3>Get Help, Give Help</h3>
    <p>Whether you are seeking support or offering it, DVMS acts as a bridge to safety, healing, and justice. It connects survivors, counselors, legal professionals, and volunteers in a trusted digital space. Through DVMS, communities are empowered to work together, building resilience and promoting awareness. Join us in fostering a network where help is always within reach and every voice is heard.</p>
  </div>
  <img src="images/7.jpg" alt="Help and Support">
</div>

<footer>
  &copy; 2025 Domestic Violence Management System. All rights reserved.
</footer>

<script>
  // Scroll reveal
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('reveal');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.15
  });

  document.querySelectorAll('.section').forEach(section => {
    observer.observe(section);
  });
</script>

</body>
</html>
