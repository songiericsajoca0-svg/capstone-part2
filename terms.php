<?php
// File: capstone-part2/terms.php
require_once 'includes/config.php';
// No auth-check.php - accessible kahit hindi naka-login

$page_title = "Terms of Service - GoTrike";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * {
            font-family: 'NaruMonoDemo', monospace !important;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Light Mode Variables (Default) */
        :root {
            --primary: #2b51e8;
            --primary-dark: #0d82e2;
            --primary-light: #E3F2FD;
            --dark: #1A1A1A;
            --gray: #555555;
            --light: #F8FBFF;
            --white: #FFFFFF;
            --accent: #FFD700;
            --card-bg: #ffffff;
            --text-primary: #1A1A1A;
            --text-secondary: #555555;
            --text-muted: #6b7280;
            --border-light: #e9ecef;
            --hover-bg: #fafbff;
            --highlight-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --contact-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --header-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
            --footer-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
            --scrollbar-track: #f0f0f0;
            --scrollbar-thumb: #2b51e8;
            --scrollbar-thumb-hover: #0d82e2;
        }

        /* Dark Mode Variables */
        body.dark-mode {
            --primary: #4d7cff;
            --primary-dark: #3a6be8;
            --primary-light: #1e2a5e;
            --dark: #f0f0f0;
            --gray: #cccccc;
            --light: #121826;
            --white: #1e2a3a;
            --card-bg: #1e2a3a;
            --text-primary: #f0f0f0;
            --text-secondary: #cccccc;
            --text-muted: #a0a0a0;
            --border-light: #2d3e4e;
            --hover-bg: #2a3a48;
            --highlight-bg: linear-gradient(135deg, #1e2a3a 0%, #2a3a48 100%);
            --contact-bg: linear-gradient(135deg, #1e2a3a 0%, #2a3a48 100%);
            --header-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
            --footer-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
            --scrollbar-track: #2d3e4e;
            --scrollbar-thumb: #4d7cff;
            --scrollbar-thumb-hover: #6b8eff;
        }

        /* ===== CUSTOM SCROLLBAR - BLUE THEME ===== */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 10px;
            transition: background 0.2s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }

        body {
            background: var(--light);
            color: var(--text-primary);
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.2s ease;
        }

        /* Main Header */
        .main-header {
            background: var(--header-bg);
            border-bottom: 2px solid var(--border-light);
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            height: 80px;
            width: auto;
        }

        .nav-menu ul {
            list-style: none;
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-menu a:hover {
            color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Dark Mode Toggle Button */
        .theme-toggle {
            background: var(--white);
            border: 2px solid var(--primary);
            border-radius: 40px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'NaruMonoDemo', monospace;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            margin-left: 20px;
        }

        .theme-toggle i {
            font-size: 1rem;
        }

        .theme-toggle:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Terms Container */
        .terms-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .terms-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 28px;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .terms-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
            background-size: 50px 50px;
            animation: shimmer 60s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .terms-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .terms-header p {
            margin: 1rem 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .last-updated {
            background: var(--card-bg);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            display: inline-block;
            width: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .terms-content {
            background: var(--card-bg);
            border-radius: 28px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .terms-section {
            padding: 2rem;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.3s ease;
        }

        .terms-section:hover {
            background: var(--hover-bg);
        }

        .terms-section:last-child {
            border-bottom: none;
        }

        .terms-section h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .terms-section h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin: 1.5rem 0 0.75rem;
            font-weight: bold;
        }

        .terms-section p {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 1rem;
        }

        .terms-section ul, .terms-section ol {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        .terms-section li {
            margin-bottom: 0.5rem;
        }

        .terms-section li strong {
            color: var(--primary);
        }

        .highlight-box {
            background: var(--highlight-bg);
            padding: 1.5rem;
            border-radius: 20px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--primary);
        }

        .highlight-box h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .highlight-box p {
            color: var(--text-secondary);
        }

        .contact-card {
            background: var(--contact-bg);
            padding: 1.5rem;
            border-radius: 20px;
            margin-top: 1rem;
            border: 1px solid var(--border-light);
        }

        .contact-card p {
            margin: 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
        }

        .contact-card strong {
            color: var(--primary);
            min-width: 140px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: var(--card-bg);
        }

        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
            background: var(--hover-bg);
        }

        /* Footer */
        footer {
            background: var(--footer-bg);
            color: var(--text-primary);
            text-align: center;
            padding: 3rem 1rem;
            margin-top: 3rem;
        }

        .footer-links {
            margin-top: 1rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .terms-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }

            .terms-header {
                padding: 2rem 1rem;
            }

            .terms-header h1 {
                font-size: 1.8rem;
            }

            .terms-section {
                padding: 1.5rem;
            }

            .nav-menu {
                display: none;
            }

            .theme-toggle {
                margin-left: 0;
                padding: 6px 12px;
                font-size: 0.8rem;
            }

            .contact-card p {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .contact-card strong {
                min-width: auto;
            }
        }

        @media print {
            .main-header,
            footer,
            .back-link,
            .theme-toggle {
                display: none;
            }

            .terms-container {
                margin: 0;
                padding: 0;
            }

            .terms-section {
                page-break-inside: avoid;
            }

            .terms-header {
                background: var(--primary);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Header with Dark Mode Toggle -->
<header class="main-header">
    <div class="container header-flex">
        <div class="logo-section">
            <img src="assets/images/logo2.png" alt="Go Trike Logo" class="header-logo">
        </div>
        <div style="display: flex; align-items: center;">
           
            <button class="theme-toggle" id="darkModeToggle">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </div>
    </div>
</header>

<div class="terms-container">
    <div class="terms-header">
        <h1>📜 Terms of Service</h1>
        <p>Please read these terms carefully before using our platform</p>
    </div>
    
    <div style="text-align: center;">
        <div class="last-updated">
            📅 Last Updated: January 15, 2024 | Version 2.0 | Effective Date: January 1, 2024
        </div>
    </div>
    
    <div class="terms-content">
        <div class="terms-section">
            <h2>1. 📋 Acceptance of Terms</h2>
            <p>By accessing or using the GoTrike platform ("Platform"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, please do not use our Platform. These Terms constitute a legally binding agreement between you and GoTrike Transport Services.</p>
            <p>We reserve the right to modify these Terms at any time. Your continued use of the Platform after any changes constitutes acceptance of the new Terms. It is your responsibility to review these Terms periodically.</p>
        </div>
        
        <div class="terms-section">
            <h2>2. 👤 User Accounts</h2>
            <h3>2.1 Account Registration</h3>
            <p>To use our booking services, you must create an account. You agree to provide accurate, current, and complete information during registration and to update such information to keep it accurate, current, and complete.</p>
            
            <h3>2.2 Account Security</h3>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You agree to notify us immediately of any unauthorized use of your account. GoTrike is not liable for any loss or damage arising from your failure to protect your account.</p>
            
            <h3>2.3 Account Eligibility</h3>
            <p>You must be at least 18 years old to create an account. By creating an account, you represent that you are at least 18 years of age. Users under 18 must have parental consent to use our services.</p>
        </div>
        
        <div class="terms-section">
            <h2>3. 🚗 Booking Services</h2>
            <h3>3.1 Booking Process</h3>
            <p>Our Platform allows users to book tricycle services within Caloocan South and surrounding areas. When you make a booking, you agree to:</p>
            <ul>
                <li>Provide accurate pickup and drop-off locations</li>
                <li>Specify the correct number of passengers</li>
                <li>Be present at the designated pickup location at the scheduled time</li>
                <li>Pay the fare amount as calculated by the system</li>
            </ul>
            
            <h3>3.2 Booking Confirmation</h3>
            <p>Once a booking is confirmed, you will receive a booking code and QR code for verification. Please present this to your assigned driver upon pickup. The QR code serves as proof of valid booking.</p>
            
            <h3>3.3 Cancellations and Refunds</h3>
            <ul>
                <li><strong>Cancellation by User:</strong> You may cancel a booking up to 1 hour before the scheduled pickup time without penalty. Cancellations made less than 1 hour before pickup may incur a cancellation fee.</li>
                <li><strong>Cancellation by Driver:</strong> If a driver cancels, you will receive a full refund or we will help you find an alternative driver.</li>
                <li><strong>Refund Policy:</strong> Refunds for paid bookings will be processed within 3-5 business days to your original payment method.</li>
            </ul>
        </div>
        
        <div class="terms-section">
            <h2>4. 💰 Payments and Fees</h2>
            <h3>4.1 Fare Calculation</h3>
            <p>Fares are calculated based on distance, number of passengers, and number of tricycle units required. The fare displayed at booking is the final amount you will pay, subject to any applicable discounts or promotions.</p>
            
            <h3>4.2 Payment Methods</h3>
            <p>We accept the following payment methods:</p>
            <ul>
                <li><strong>Cash:</strong> Pay directly to the driver upon trip completion</li>
                <li><strong>GCash:</strong> Pay via GCash through our integrated payment system</li>
            </ul>
            
            <h3>4.3 Payment Verification</h3>
            <p>For GCash payments, you must provide a valid reference number. We reserve the right to verify transactions before confirming payment status. Fraudulent transactions will be reported to authorities.</p>
            
            <div class="highlight-box">
                <h4>⚠️ Important Note on Payments</h4>
                <p>All payments are processed securely. We do not store your full payment details. For cash payments, please ensure you have the exact amount or prepare change for the driver.</p>
            </div>
        </div>
        
        <div class="terms-section">
            <h2>5. 🔒 User Responsibilities and Conduct</h2>
            <p>You agree to use the Platform only for lawful purposes and in accordance with these Terms. You agree not to:</p>
            <ul>
                <li>Use the Platform in any way that violates applicable laws or regulations</li>
                <li>Impersonate any person or entity or falsely state your affiliation with any person or entity</li>
                <li>Interfere with or disrupt the Platform or servers connected to the Platform</li>
                <li>Attempt to gain unauthorized access to any portion of the Platform</li>
                <li>Use the Platform to harass, abuse, or harm another person</li>
                <li>Share or publish offensive, obscene, or inappropriate content</li>
                <li>Make false bookings or misuse the booking system</li>
            </ul>
        </div>
        
        <div class="terms-section">
            <h2>6. 🛡️ Driver Services and Safety</h2>
            <h3>6.1 Driver Verification</h3>
            <p>All drivers on our Platform undergo background verification. However, we cannot guarantee that every driver will meet your expectations. We strive to maintain high standards but encourage users to report any concerns.</p>
            
            <h3>6.2 Safety Guidelines</h3>
            <ul>
                <li>Always verify the driver's identity using the QR code system</li>
                <li>Wear helmets if provided by the driver</li>
                <li>Follow traffic rules and safety regulations</li>
                <li>Report any safety concerns immediately to our support team</li>
                <li>Do not overload the tricycle beyond its capacity</li>
            </ul>
            
            <h3>6.3 Passenger Conduct</h3>
            <p>Passengers are expected to treat drivers with respect. Abusive behavior, non-payment, or any form of harassment may result in account suspension or permanent ban from the Platform.</p>
        </div>
        
        <div class="terms-section">
            <h2>7. 📱 QR Code and Booking Verification</h2>
            <p>Upon successful payment, we generate a unique QR code for your booking. This QR code:</p>
            <ul>
                <li>Contains your booking details for driver verification</li>
                <li>Must be presented to the driver before the trip begins</li>
                <li>Is valid only for the specific booking and date</li>
                <li>Cannot be transferred or shared with others</li>
            </ul>
            <p>Drivers have the right to refuse service if the QR code is not presented or appears tampered with.</p>
        </div>
        
        <div class="terms-section">
            <h2>8. 🔐 Privacy and Data Protection</h2>
            <p>Your privacy is important to us. Our collection and use of personal information is governed by our <a href="/policy.php" style="color: var(--primary);">Privacy Policy</a>. By using our Platform, you consent to the collection and use of your information as described in the Privacy Policy.</p>
            <p>We comply with the Philippine Data Privacy Act of 2012 (Republic Act No. 10173). Your data is handled with strict confidentiality and used only for service delivery and improvement.</p>
        </div>
        
        <div class="terms-section">
            <h2>9. ⚠️ Limitation of Liability</h2>
            <p>To the maximum extent permitted by law, GoTrike and its affiliates, officers, employees, and agents shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of the Platform. This includes, without limitation:</p>
            <ul>
                <li>Delays or cancellations of bookings</li>
                <li>Accidents or incidents during trips</li>
                <li>Loss or damage to personal property</li>
                <li>Issues with third-party payment processors</li>
                <li>Technical issues or downtime of the Platform</li>
            </ul>
            <p>In no event shall our total liability exceed the amount paid by you for the specific booking in question.</p>
        </div>
        
        <div class="terms-section">
            <h2>10. 📝 Disclaimer of Warranties</h2>
            <p>The Platform is provided on an "AS IS" and "AS AVAILABLE" basis. GoTrike makes no representations or warranties of any kind, express or implied, regarding the operation of the Platform or the information, content, and materials included. We do not warrant that:</p>
            <ul>
                <li>The Platform will be uninterrupted, secure, or error-free</li>
                <li>Any errors or defects will be corrected</li>
                <li>The results obtained from using the Platform will be accurate or reliable</li>
                <li>Drivers will always be available in your area</li>
            </ul>
        </div>
        
        <div class="terms-section">
            <h2>11. 🚫 Termination</h2>
            <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach these Terms. Upon termination:</p>
            <ul>
                <li>Your right to use the Platform will immediately cease</li>
                <li>Any pending bookings may be cancelled</li>
                <li>Refunds for unused bookings will be processed according to our refund policy</li>
            </ul>
            <p>You may terminate your account at any time by contacting our support team.</p>
        </div>
        
        <div class="terms-section">
            <h2>12. ⚖️ Governing Law</h2>
            <p>These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines, without regard to its conflict of law provisions. Any disputes arising under these Terms shall be subject to the exclusive jurisdiction of the courts of Caloocan City, Metro Manila, Philippines.</p>
        </div>
        
        <div class="terms-section">
            <h2>13. 🔧 Dispute Resolution</h2>
            <p>In the event of any dispute arising from these Terms or your use of the Platform, we encourage you to first contact our customer support team to seek a resolution. If the dispute cannot be resolved informally, both parties agree to submit to binding arbitration in accordance with the Philippine Arbitration Law.</p>
        </div>
        
        <div class="terms-section">
            <h2>14. 📞 Contact Information</h2>
            <div class="contact-card">
                <p><strong>📧 Email:</strong> support@gotrike.com</p>
                <p><strong>📞 Hotline:</strong> 09928697153</p>
                <p><strong>📍 Address:</strong> GoTrike Transport Services, Caloocan South, Metro Manila, Philippines</p>
                <p><strong>🕒 Support Hours:</strong> Monday to Sunday, 6:00 AM - 10:00 PM</p>
                <p><strong>📱 Emergency Contact:</strong> 09928697153 (24/7)</p>
            </div>
        </div>
        
        <div class="terms-section">
            <h2>15. 🔄 Severability</h2>
            <p>If any provision of these Terms is found to be unenforceable or invalid under any applicable law, such unenforceability or invalidity shall not render these Terms unenforceable or invalid as a whole. Such provisions shall be deleted without affecting the remaining provisions herein.</p>
        </div>
        
        <div class="terms-section">
            <h2>16. 📢 Entire Agreement</h2>
            <p>These Terms, together with the Privacy Policy, constitute the entire agreement between you and GoTrike regarding your use of the Platform and supersede all prior agreements and understandings, whether written or oral.</p>
        </div>
        
        <div class="terms-section" style="text-align: center; background: var(--hover-bg);">
            <p style="font-size: 0.9rem; color: var(--text-secondary);">By using GoTrike, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.</p>
            <p style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">Last reviewed: January 15, 2024</p>
        </div>
    </div>
    
    <a href="/index.php" class="back-link">
        ← Back to Home
    </a>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <p>© <?php echo date("Y"); ?> Go Trike - Caloocan South. All rights reserved.</p>
       
    </div>
</footer>

<script>
// ========== DARK MODE TOGGLE ==========
(function() {
    const toggleBtn = document.getElementById('darkModeToggle');
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        toggleBtn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
    }
    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const nowDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', nowDark);
        toggleBtn.innerHTML = nowDark ? '<i class="fas fa-sun"></i> Light Mode' : '<i class="fas fa-moon"></i> Dark Mode';
    });
})();
</script>

</body>
</html>