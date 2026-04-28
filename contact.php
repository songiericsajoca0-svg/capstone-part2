<?php
// File: capstone-part2/contact.php
require_once 'includes/config.php';
// No auth-check.php - accessible kahit hindi naka-login

$page_title = "Contact Us - GoTrike";
$success_message = "";
$error_message = "";

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Save to database
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        
        if ($stmt->execute()) {
            $success_message = "Thank you for contacting us! We will get back to you within 24-48 hours.";
        } else {
            $error_message = "Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        @font-face {
            font-family: 'NaruMonoDemo';
            src: url('assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
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
            --card-bg: #ffffff;
            --text-primary: #1A1A1A;
            --text-secondary: #555555;
            --text-muted: #6b7280;
            --border-light: #e9ecef;
            --hover-bg: #f8fafc;
            --header-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
            --footer-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
            --scrollbar-track: #f0f0f0;
            --scrollbar-thumb: #2b51e8;
            --scrollbar-thumb-hover: #0d82e2;
            --info-icon-bg: #eff6ff;
            --bg-gradient: linear-gradient(to bottom right, #eff6ff, #f3f4f6);
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
            --header-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
            --footer-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
            --scrollbar-track: #2d3e4e;
            --scrollbar-thumb: #4d7cff;
            --scrollbar-thumb-hover: #6b8eff;
            --info-icon-bg: #2a3a48;
            --bg-gradient: linear-gradient(to bottom right, #0f1a24, #1a2a35);
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

        /* Apply NaruMonoDemo only to text elements */
        body, h1, h2, h3, h4, p, span, div, a, button, input, textarea, select, label, strong, b, small, .nav-menu a, .btn-primary, .footer-links a {
            font-family: 'NaruMonoDemo', monospace !important;
        }

        /* Icons remain with Font Awesome */
        i, .fas, .far, .fab, .fa, .social-link i, .info-icon i, .emergency-contact i, .contact-header i, .contact-info i, .contact-form i {
            font-family: "Font Awesome 6 Free" !important;
        }

        .fab, .fab i, .social-link .fab {
            font-family: "Font Awesome 6 Brands" !important;
        }

        body {
            background: var(--bg-gradient);
            transition: background 0.3s ease;
            min-height: 100vh;
        }

        /* Custom hover effects */
        .social-link {
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            transform: translateY(-5px);
        }
        
        .facebook:hover { background-color: #1877f2 !important; color: white !important; }
        .twitter:hover { background-color: #1da1f2 !important; color: white !important; }
        .instagram:hover { background: linear-gradient(45deg, #f09433, #d62976, #962fbf) !important; color: white !important; }
        .messenger:hover { background-color: #0084ff !important; color: white !important; }
        .viber:hover { background-color: #7360f2 !important; color: white !important; }
        
        .info-card {
            transition: transform 0.3s ease;
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
        
        .info-card:hover {
            transform: translateX(5px);
        }
        
        .btn-submit {
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(43, 81, 232, 0.3);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            background: var(--card-bg);
            border: 2px solid var(--primary);
            border-radius: 40px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'NaruMonoDemo', monospace;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .theme-toggle i {
            font-size: 1rem;
        }

        .theme-toggle:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Dark mode overrides for Tailwind */
        body.dark-mode .bg-white {
            background-color: var(--card-bg) !important;
        }
        
        body.dark-mode .text-gray-800,
        body.dark-mode .text-gray-700 {
            color: var(--text-primary) !important;
        }
        
        body.dark-mode .text-gray-500,
        body.dark-mode .text-gray-600 {
            color: var(--text-secondary) !important;
        }
        
        body.dark-mode .border-gray-100,
        body.dark-mode .border-gray-200 {
            border-color: var(--border-light) !important;
        }
        
        body.dark-mode .bg-gray-50,
        body.dark-mode .bg-gray-100 {
            background-color: var(--info-icon-bg) !important;
        }
        
        body.dark-mode input,
        body.dark-mode textarea,
        body.dark-mode select {
            background-color: var(--white) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-light) !important;
        }
        
        body.dark-mode header {
            background: var(--header-bg) !important;
            border-color: var(--border-light) !important;
        }
        
        body.dark-mode footer {
            background: var(--footer-bg) !important;
            border-color: var(--border-light) !important;
        }
        
        body.dark-mode .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2) !important;
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
        
        .footer-links {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            .header-flex {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<!-- Header with Navigation and Dark Mode Toggle -->
<header class="sticky top-0 z-50 shadow-sm" style="background: var(--header-bg); border-bottom: 2px solid var(--border-light);">
    <div class="container mx-auto px-4 sm:px-6 py-2">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="logo-section">
                <img src="assets/images/logo2.png" alt="Go Trike Logo" class="h-16 w-auto">
            </div>
            <div class="flex items-center gap-6">
                
                <button class="theme-toggle" id="darkModeToggle">
                    <i class="fas fa-moon"></i> Dark Mode
                </button>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-3xl p-8 sm:p-12 text-center mb-8 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-[url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"%3E%3Ccircle fill="white" cx="50" cy="50" r="40"/%3E%3C/svg%3E')] bg-repeat"></div>
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3 relative z-10">📞 Contact Us</h1>
        <p class="text-blue-100 text-lg relative z-10">We're here to help! Reach out to us anytime.</p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Contact Information -->
        <div class="bg-white rounded-3xl p-6 shadow-lg" style="background: var(--card-bg);">
            <h2 class="text-2xl font-bold text-blue-600 mb-5 flex items-center gap-2 border-b-2 pb-3" style="border-color: var(--border-light);">
                <i class="fas fa-address-card"></i> Get in Touch
            </h2>
            
            <div class="info-card flex gap-4 py-4 border-b" style="border-color: var(--border-light);">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-blue-600 text-xl flex-shrink-0" style="background: var(--info-icon-bg);">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-content">
                    <h3 class="font-bold mb-1" style="color: var(--text-primary);">Our Office Address</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">GoTrike Transport Services<br>Caloocan South, Metro Manila<br>Philippines 1400</p>
                </div>
            </div>
            
            <div class="info-card flex gap-4 py-4 border-b" style="border-color: var(--border-light);">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-blue-600 text-xl flex-shrink-0" style="background: var(--info-icon-bg);">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <div class="info-content">
                    <h3 class="font-bold mb-1" style="color: var(--text-primary);">Phone Numbers</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <strong>Hotline:</strong> <a href="tel:09928697153" class="text-blue-600 hover:underline">0992 869 7153</a><br>
                        <strong>Landline:</strong> <a href="tel:0212345678" class="text-blue-600 hover:underline">(02) 1234 5678</a><br>
                        <strong>Emergency:</strong> <a href="tel:09928697153" class="text-blue-600 hover:underline">0992 869 7153</a> (24/7)
                    </p>
                </div>
            </div>
            
            <div class="info-card flex gap-4 py-4 border-b" style="border-color: var(--border-light);">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-blue-600 text-xl flex-shrink-0" style="background: var(--info-icon-bg);">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-content">
                    <h3 class="font-bold mb-1" style="color: var(--text-primary);">Email Addresses</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <strong>Support:</strong> <a href="mailto:support@gotrike.com" class="text-blue-600 hover:underline">support@gotrike.com</a><br>
                        <strong>Complaints:</strong> <a href="mailto:complaints@gotrike.com" class="text-blue-600 hover:underline">complaints@gotrike.com</a><br>
                        <strong>Partnerships:</strong> <a href="mailto:partners@gotrike.com" class="text-blue-600 hover:underline">partners@gotrike.com</a>
                    </p>
                </div>
            </div>
            
            <div class="rounded-2xl p-5 mt-4" style="background: var(--info-icon-bg);">
                <h3 class="text-blue-600 font-bold mb-3 flex items-center gap-2"><i class="far fa-clock"></i> Business Hours</h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center border-b border-dashed pb-2" style="border-color: var(--border-light);">
                        <span class="font-semibold" style="color: var(--text-primary);">Monday - Friday</span>
                        <span style="color: var(--text-secondary);">6:00 AM - 10:00 PM</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-dashed pb-2" style="border-color: var(--border-light);">
                        <span class="font-semibold" style="color: var(--text-primary);">Saturday - Sunday</span>
                        <span style="color: var(--text-secondary);">7:00 AM - 9:00 PM</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-semibold" style="color: var(--text-primary);">Holidays</span>
                        <span style="color: var(--text-secondary);">8:00 AM - 8:00 PM</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-2xl p-4 text-center mt-4 hover:scale-[1.02] transition">
                <i class="fas fa-phone-alt text-white text-xl mr-2"></i>
                <strong class="text-white">24/7 Emergency Hotline:</strong>
                <a href="tel:09928697153" class="text-white font-bold text-lg block mt-1 hover:underline">0992 869 7153</a>
            </div>
            
            <div class="flex justify-center gap-3 mt-6">
                <a href="https://www.facebook.com/gotrike" target="_blank" class="social-link facebook w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background: var(--info-icon-bg); color: var(--primary);">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/gotrike" target="_blank" class="social-link twitter w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background: var(--info-icon-bg); color: var(--primary);">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://www.instagram.com/gotrike" target="_blank" class="social-link instagram w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background: var(--info-icon-bg); color: var(--primary);">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://m.me/gotrike" target="_blank" class="social-link messenger w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background: var(--info-icon-bg); color: var(--primary);">
                    <i class="fab fa-facebook-messenger"></i>
                </a>
                <a href="viber://chat?number=09928697153" class="social-link viber w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background: var(--info-icon-bg); color: var(--primary);">
                    <i class="fab fa-viber"></i>
                </a>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div class="bg-white rounded-3xl p-6 shadow-lg" style="background: var(--card-bg);">
            <h2 class="text-2xl font-bold text-blue-600 mb-5 flex items-center gap-2 border-b-2 pb-3" style="border-color: var(--border-light);">
                <i class="fas fa-paper-plane"></i> Send Us a Message
            </h2>
            
            <?php if ($success_message): ?>
                <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-5 border-l-4 border-green-500">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-xl mb-5 border-l-4 border-red-500">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="contactForm">
                <div class="mb-4">
                    <label class="block font-semibold mb-2 text-sm" style="color: var(--text-primary);">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="w-full px-4 py-3 border-2 rounded-xl focus:border-blue-500 focus:outline-none transition" style="border-color: var(--border-light); background: var(--white); color: var(--text-primary);" placeholder="Enter your full name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label class="block font-semibold mb-2 text-sm" style="color: var(--text-primary);">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="w-full px-4 py-3 border-2 rounded-xl focus:border-blue-500 focus:outline-none transition" style="border-color: var(--border-light); background: var(--white); color: var(--text-primary);" placeholder="Enter your email address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label class="block font-semibold mb-2 text-sm" style="color: var(--text-primary);">Subject <span class="text-red-500">*</span></label>
                    <select name="subject" class="w-full px-4 py-3 border-2 rounded-xl focus:border-blue-500 focus:outline-none transition cursor-pointer appearance-none" style="border-color: var(--border-light); background: var(--white); color: var(--text-primary);" required>
                        <option value="">Select a subject</option>
                        <option value="Booking Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Booking Inquiry') ? 'selected' : ''; ?>>Booking Inquiry</option>
                        <option value="Payment Issue" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Payment Issue') ? 'selected' : ''; ?>>Payment Issue</option>
                        <option value="Driver Complaint" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Driver Complaint') ? 'selected' : ''; ?>>Driver Complaint</option>
                        <option value="Technical Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                        <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Partnership') ? 'selected' : ''; ?>>Partnership / Business</option>
                        <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                    </select>
                </div>
                
                <div class="mb-5">
                    <label class="block font-semibold mb-2 text-sm" style="color: var(--text-primary);">Your Message <span class="text-red-500">*</span></label>
                    <textarea name="message" class="w-full px-4 py-3 border-2 rounded-xl focus:border-blue-500 focus:outline-none transition resize-y min-h-[120px]" style="border-color: var(--border-light); background: var(--white); color: var(--text-primary);" placeholder="Type your message here..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>
                
                <button type="submit" name="submit_contact" class="btn-submit w-full bg-gradient-to-r from-blue-600 to-blue-400 text-white py-4 rounded-xl font-bold text-lg hover:shadow-lg transition">
                    <i class="fas fa-paper-plane mr-2"></i> Send Message
                </button>
            </form>
            
            <p class="text-center text-xs mt-4" style="color: var(--text-muted);">
                <i class="fas fa-lock"></i> Your information is safe with us. We'll respond within 24-48 hours.
            </p>
        </div>
    </div>
    
    <!-- Map Section -->
    <div class="rounded-3xl shadow-lg overflow-hidden" style="background: var(--card-bg);">
        <h2 class="text-2xl font-bold text-blue-600 p-6 pb-0 flex items-center gap-2">
            <i class="fas fa-map-marked-alt"></i> Find Us Here
        </h2>
        <div class="mt-4">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15440.374495537333!2d120.97025185!3d14.65063065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b5d89f78393d%3A0x6e26710b10629b38!2sSouth+Caloocan%2C+Caloocan%2C+Metro+Manila!5e0!3m2!1sen!2sph!4v1709724000000!5m2!1sen!2sph" 
                width="100%" 
                height="400" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy">
            </iframe>
        </div>
    </div>
    <a href="index.php" class="back-link">
        ← Back to Home
    </a>
</div>

<!-- Footer -->
<footer style="background: var(--footer-bg); color: var(--text-primary); text-align: center; padding: 3rem 1rem; margin-top: 3rem; border-top: 2px solid var(--border-light);">
    <div class="container mx-auto px-4">
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

document.getElementById('contactForm').addEventListener('submit', function(e) {
    const name = document.querySelector('[name="name"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const subject = document.querySelector('[name="subject"]').value;
    const message = document.querySelector('[name="message"]').value.trim();
    
    if (name === '' || email === '' || subject === '' || message === '') {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (!email.includes('@') || !email.includes('.')) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return false;
    }
});
</script>

</body>
</html>