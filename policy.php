<?php
// File: capstone-part2/policy.php
require_once 'includes/config.php';

$page_title = "Privacy Policy - GoTrike";
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
            --border-light: #e9ecef;
            --hover-bg: #fafbff;
            --table-bg: #f8fafc;
            --table-hover: #f1f5f9;
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
            --border-light: #2d3e4e;
            --hover-bg: #2a3a48;
            --table-bg: #1e2a3a;
            --table-hover: #2a3a48;
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
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.2s ease;
        }

        /* Main Header - Same as index.php */
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

        /* Main Container */
        .privacy-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        /* Header Card */
        .privacy-header {
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

        .privacy-header::before {
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
        
        .privacy-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        
        .privacy-header p {
            margin: 1rem 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        /* Last Updated */
        .last-updated {
            background: var(--card-bg);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: inline-block;
            width: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        /* Main Content Card */
        .privacy-content {
            background: var(--card-bg);
            border-radius: 28px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .privacy-section {
            padding: 2rem;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.3s ease;
        }
        
        .privacy-section:hover {
            background: var(--hover-bg);
        }
        
        .privacy-section:last-child {
            border-bottom: none;
        }
        
        .privacy-section h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .privacy-section h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin: 1.5rem 0 0.75rem;
            font-weight: bold;
        }
        
        .privacy-section p {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 1rem;
        }
        
        .privacy-section ul, .privacy-section ol {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 1rem;
            padding-left: 2rem;
        }
        
        .privacy-section li {
            margin-bottom: 0.5rem;
        }
        
        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: var(--table-bg);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .data-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover td {
            background: var(--table-hover);
        }
        
        /* Contact Card */
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
        
        /* Accept Button */
        .accept-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            box-shadow: 0 5px 15px rgba(43, 81, 232, 0.3);
        }
        
        .accept-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(43, 81, 232, 0.4);
        }
        
        /* Back Link */
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
            .privacy-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            
            .privacy-header {
                padding: 2rem 1rem;
            }
            
            .privacy-header h1 {
                font-size: 1.8rem;
            }
            
            .privacy-section {
                padding: 1.5rem;
            }
            
            .data-table {
                font-size: 0.8rem;
                display: block;
                overflow-x: auto;
            }
            
            .data-table th,
            .data-table td {
                padding: 10px 12px;
            }
            
            .contact-card p {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .contact-card strong {
                min-width: auto;
            }
            
            .nav-menu {
                display: none;
            }
            
            .theme-toggle {
                margin-left: 0;
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
        
        @media print {
            .main-header,
            footer,
            .back-link,
            .accept-btn,
            .theme-toggle {
                display: none;
            }
            
            .privacy-container {
                margin: 0;
                padding: 0;
            }
            
            .privacy-section {
                page-break-inside: avoid;
            }
            
            .privacy-header {
                background: #2b51e8;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Header - Same as index.php with Dark Mode Toggle -->
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

<div class="privacy-container">
    <!-- Header -->
    <div class="privacy-header">
        <h1>🔒 Privacy Policy</h1>
        <p>Your privacy is our priority. Learn how we protect your information.</p>
    </div>
    
    <div style="text-align: center;">
        <div class="last-updated">
            📅 Last Updated: January 15, 2024 | Version 2.0
        </div>
    </div>
    
    <div class="privacy-content">
        <div class="privacy-section">
            <h2>📋 1. Introduction</h2>
            <p>GoTrike ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our tricycle booking platform. Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the platform.</p>
            <p>We reserve the right to make changes to this Privacy Policy at any time and for any reason. We will alert you about any changes by updating the "Last Updated" date of this Privacy Policy. You are encouraged to periodically review this Privacy Policy to stay informed of updates.</p>
        </div>
        
        <div class="privacy-section">
            <h2>📊 2. Information We Collect</h2>
            <p>We collect information that you voluntarily provide to us when you register on the platform, make a booking, or contact us. The types of information we may collect include:</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Data Collected</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Personal Information</strong></td>
                        <td>Full name, email address, phone number</td>
                        <td>Account creation, booking confirmation, communication</td>
                    </tr>
                    <tr>
                        <td><strong>Booking Information</strong></td>
                        <td>Pickup/dropoff locations, number of passengers, trip details, fare amount</td>
                        <td>Service delivery, trip planning, fare calculation</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Information</strong></td>
                        <td>GCash reference number, payment status, transaction history</td>
                        <td>Payment processing, transaction verification</td>
                    </tr>
                    <tr>
                        <td><strong>Usage Data</strong></td>
                        <td>IP address, browser type, device information, access times</td>
                        <td>Platform optimization, security, analytics</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="privacy-section">
            <h2>⚙️ 3. How We Use Your Information</h2>
            <p>We use the information we collect for various purposes, including:</p>
            <ul>
                <li>✅ To create and manage your account</li>
                <li>✅ To process and confirm your bookings</li>
                <li>✅ To generate QR codes for trip verification</li>
                <li>✅ To process payments and verify transactions</li>
                <li>✅ To communicate with you about your bookings</li>
                <li>✅ To provide customer support and respond to inquiries</li>
                <li>✅ To improve our platform and user experience</li>
                <li>✅ To comply with legal obligations</li>
                <li>✅ To prevent fraudulent activities and ensure security</li>
            </ul>
        </div>
        
        <div class="privacy-section">
            <h2>🔐 4. Data Security</h2>
            <p>We implement appropriate technical and organizational security measures to protect your personal information. These measures include:</p>
            <ul>
                <li>🔒 SSL encryption for data transmission</li>
                <li>🔒 Secure database storage with access controls</li>
                <li>🔒 Regular security audits and updates</li>
                <li>🔒 Password hashing and secure authentication</li>
                <li>🔒 Limited employee access to sensitive data</li>
            </ul>
            <p>While we strive to protect your personal information, no method of transmission over the Internet or electronic storage is 100% secure. We cannot guarantee absolute security.</p>
        </div>
        
        <div class="privacy-section">
            <h2>🤝 5. Sharing Your Information</h2>
            <p>We do not sell, trade, or rent your personal information to third parties. We may share information in the following situations:</p>
            <ul>
                <li><strong>With Drivers:</strong> Your pickup/dropoff locations and contact details are shared with assigned drivers to facilitate your trip.</li>
                <li><strong>Service Providers:</strong> We may share information with third-party vendors who assist us in operating our platform (payment processors, hosting services).</li>
                <li><strong>Legal Requirements:</strong> We may disclose information if required by law or in response to valid legal requests.</li>
                <li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or asset sale, your information may be transferred.</li>
            </ul>
        </div>
        
        <div class="privacy-section">
            <h2>🍪 6. Cookies and Tracking Technologies</h2>
            <p>We use cookies and similar tracking technologies to enhance your experience on our platform. Cookies help us:</p>
            <ul>
                <li>Remember your login session</li>
                <li>Understand how you use our platform</li>
                <li>Improve loading times and performance</li>
                <li>Provide personalized content</li>
            </ul>
            <p>You can control cookie preferences through your browser settings. However, disabling cookies may affect platform functionality.</p>
        </div>
        
        <div class="privacy-section">
            <h2>👤 7. Your Rights and Choices</h2>
            <p>Depending on your location, you may have certain rights regarding your personal information:</p>
            <ul>
                <li><strong>Access:</strong> Request a copy of your personal data</li>
                <li><strong>Correction:</strong> Request corrections to inaccurate information</li>
                <li><strong>Deletion:</strong> Request deletion of your account and data</li>
                <li><strong>Opt-out:</strong> Unsubscribe from marketing communications</li>
                <li><strong>Data Portability:</strong> Receive your data in a structured format</li>
            </ul>
            <p>To exercise these rights, please contact us using the information provided below.</p>
        </div>
        
        <div class="privacy-section">
            <h2>⏱️ 8. Data Retention</h2>
            <p>We retain your personal information for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law. Specifically:</p>
            <ul>
                <li>Account information: Retained until account deletion</li>
                <li>Booking history: Retained for 3 years for transaction records</li>
                <li>Payment information: Retained for 5 years for financial audit purposes</li>
                <li>Usage data: Retained for 12 months for analytics</li>
            </ul>
        </div>
        
        <div class="privacy-section">
            <h2>👶 9. Children's Privacy</h2>
            <p>Our platform is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected personal information from a child under 13, we will take steps to delete that information.</p>
        </div>
        
        <div class="privacy-section">
            <h2>🌍 10. Third-Party Links</h2>
            <p>Our platform may contain links to third-party websites (e.g., GCash). We are not responsible for the privacy practices or content of these third-party sites. We encourage you to read the privacy policies of any external sites you visit.</p>
        </div>
        
        <div class="privacy-section">
            <h2>📜 11. Consent Withdrawal</h2>
            <p>You have the right to withdraw your consent to our processing of your personal information at any time. To withdraw consent, please contact our Data Protection Officer. Withdrawal of consent does not affect the lawfulness of processing based on consent before its withdrawal.</p>
        </div>
        
        <div class="privacy-section">
            <h2>📞 12. Contact Information</h2>
            <div class="contact-card">
                <p><strong>📧 Email:</strong> privacy@gotrike.com</p>
                <p><strong>📞 Phone:</strong> (02) 1234 5678</p>
                <p><strong>📱 Mobile:</strong> 0993 591 5712</p>
                <p><strong>📍 Address:</strong> GoTrike Transport Services, Unit 123, Digital Hub, Metro Manila, Philippines</p>
                <p><strong>👤 Data Protection Officer:</strong> Juan Dela Cruz</p>
                <p><strong>🕒 Response Time:</strong> We aim to respond to all inquiries within 2-3 business days</p>
            </div>
        </div>
        
        <div class="privacy-section">
            <h2>⚖️ 13. Governing Law</h2>
            <p>This Privacy Policy is governed by and construed in accordance with the laws of the Republic of the Philippines, including the Data Privacy Act of 2012 (Republic Act No. 10173). Any disputes arising from this policy shall be subject to the exclusive jurisdiction of the courts of Metro Manila, Philippines.</p>
        </div>
        
        <div class="privacy-section" style="text-align: center; background: var(--hover-bg);">
            <p style="font-size: 0.9rem; color: var(--text-secondary);">By using GoTrike, you acknowledge that you have read and understood this Privacy Policy and agree to its terms.</p>
            <button class="accept-btn" onclick="acceptPrivacyPolicy()">
                ✓ I Understand and Accept
            </button>
        </div>
    </div>
    
    <a href="javascript:history.back()" class="back-link">
        ← Back to Previous Page
    </a>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <p>© <?php echo date("Y"); ?> Go Trike - Caloocan South. All rights reserved.</p>
    
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

function acceptPrivacyPolicy() {
    // Store acceptance in localStorage
    localStorage.setItem('privacy_accepted', 'true');
    localStorage.setItem('privacy_accept_date', new Date().toISOString());
    
    // Show confirmation
    Swal.fire({
        icon: 'success',
        title: 'Privacy Policy Accepted',
        text: 'Thank you for reviewing our Privacy Policy. You can now continue using GoTrike.',
        confirmButtonColor: '#2b51e8',
        timer: 3000,
        showConfirmButton: true
    }).then(() => {
        // Redirect to dashboard or previous page
        if (document.referrer && document.referrer.includes('register')) {
            window.location.href = 'dashboard.php';
        } else if (document.referrer) {
            window.location.href = document.referrer;
        } else {
            window.location.href = 'dashboard.php';
        }
    });
}

// Check if user already accepted (optional)
document.addEventListener('DOMContentLoaded', function() {
    const accepted = localStorage.getItem('privacy_accepted');
    if (accepted) {
        console.log('Privacy policy already accepted on: ' + localStorage.getItem('privacy_accept_date'));
    }
});
</script>

</body>
</html>