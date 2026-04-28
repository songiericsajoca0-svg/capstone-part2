<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Go Trike | Caloocan South Tricycle Booking</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* ── CUSTOM FONT: NaruMonoDemo (exact path and family as requested) ── */
    @font-face {
      font-family: 'NaruMonoDemo';
      src: url('assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
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
      --chat-bg: #ffffff;
      --chat-user: #e3f2fd;
      --chat-bot: #f5f5f5;
      --chat-border: #e0e0e0;
      --header-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
      --footer-bg: linear-gradient(to bottom, #87CEEB, #FFFFFF);
      --card-bg: #ffffff;
      --step-bg: #ffffff;
      --text-primary: #1A1A1A;
      --text-secondary: #555555;
      --border-light: #f0f0f0;
      --map-shadow: rgba(0,0,0,0.1);
      --newsletter-bg: linear-gradient(135deg, #003366 0%, #001a33 100%);
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
      --chat-bg: #1e2a3a;
      --chat-user: #2b3b4e;
      --chat-bot: #2c3e4e;
      --chat-border: #3a4c62;
      --header-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
      --footer-bg: linear-gradient(to bottom, #0a2a3a, #0f1a24);
      --card-bg: #1e2a3a;
      --step-bg: #1e2a3a;
      --text-primary: #f0f0f0;
      --text-secondary: #cccccc;
      --border-light: #2d3e4e;
      --map-shadow: rgba(0,0,0,0.3);
      --newsletter-bg: linear-gradient(135deg, #001a2a 0%, #000d1a 100%);
      --scrollbar-track: #2d3e4e;
      --scrollbar-thumb: #4d7cff;
      --scrollbar-thumb-hover: #6b8eff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      transition: background-color 0.3s ease, border-color 0.2s ease, color 0.2s ease;
    }

    body {
      font-family: 'NaruMonoDemo', 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--light);
      color: var(--text-primary);
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* ===== CUSTOM SCROLLBAR - BLUE THEME ===== */
    /* For WebKit browsers (Chrome, Safari, Edge, etc.) */
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

    /* For Firefox */
    * {
      scrollbar-width: thin;
      scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
    }

    body.loading {
      overflow: hidden;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }

    /* Updated Header */
    .main-header {
      background: var(--header-bg);
      border-bottom: 2px solid var(--border-light);
      padding: 10px 0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
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
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
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

    .hero {
      text-align: center;
      padding: 5rem 1.5rem 4rem;
    }

    .hero h2 {
      font-size: 2.8rem;
      margin-bottom: 1.2rem;
      color: var(--text-primary);
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .hero p {
      font-size: 1.25rem;
      color: var(--text-secondary);
      max-width: 720px;
      margin: 0 auto 2.5rem;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .hero-buttons {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 1.2rem;
      margin: 3rem 0;
    }

    .btn {
      display: inline-block;
      padding: 1rem 2.2rem;
      font-size: 1.1rem;
      font-weight: 600;
      text-decoration: none;
      border-radius: 50px;
      transition: all 0.3s ease;
      cursor: pointer;
      border: none;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
      box-shadow: 0 6px 20px rgba(43, 81, 232, 0.25);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-3px);
    }

    .btn-outline {
      background: transparent;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-outline:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-3px);
    }

    section {
      padding: 5rem 0;
    }

    /* Features */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .feature-card {
      text-align: center;
      padding: 2.5rem 1.8rem;
      border-radius: 16px;
      background: var(--card-bg);
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      transition: 0.35s;
    }

    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 40px rgba(43, 81, 232, 0.15);
    }

    .feature-card i {
      font-size: 3rem;
      color: var(--primary);
      margin-bottom: 1.4rem;
    }

    .feature-card h3, .feature-card p {
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
      color: var(--text-primary);
    }

    .feature-card p {
      color: var(--text-secondary);
    }

    /* Steps */
    .steps {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2rem;
      margin-top: 3rem;
    }

    .step {
      flex: 1;
      min-width: 280px;
      max-width: 350px;
      padding: 2rem;
      background: var(--step-bg);
      border-radius: 16px;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    .step-number {
      width: 50px;
      height: 50px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      font-weight: bold;
      margin: 0 auto 1.5rem;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .step h3, .step p {
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
      color: var(--text-primary);
    }

    .step p {
      color: var(--text-secondary);
    }

    /* NEWSLETTER SECTION WITH PARTICLE CANVAS */
    .newsletter-section {
      background: var(--newsletter-bg);
      color: white;
      border-radius: 30px;
      padding: 4rem 2rem;
      margin: 2rem auto;
      text-align: center;
      position: relative;
      overflow: hidden;
      isolation: isolate;
    }

    .particle-canvas {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
      border-radius: 30px;
    }

    .newsletter-section h2,
    .newsletter-section p,
    .subscribe-form {
      position: relative;
      z-index: 2;
    }

    .newsletter-section h2 {
      font-size: 2rem;
      margin-bottom: 1rem;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
      color: white;
    }

    .newsletter-section p {
      margin-bottom: 2rem;
      opacity: 0.9;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
      color: rgba(255,255,255,0.9);
    }

    .subscribe-form {
      display: flex;
      max-width: 500px;
      margin: 0 auto;
      gap: 10px;
    }

    .subscribe-form input[type="email"] {
      flex: 1;
      padding: 1rem 1.5rem;
      border-radius: 50px;
      border: none;
      outline: none;
      font-size: 1rem;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .btn-subscribe {
      background: white;
      color: black;
      padding: 0 2rem;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .btn-subscribe:hover {
      background: #ffffff;
      transform: scale(1.05);
    }

    /* Map */
    .map-wrapper {
      max-width: 1000px;
      margin: 3rem auto 0;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 30px var(--map-shadow);
    }

    .map-container {
      position: relative;
      padding-bottom: 450px;
      height: 0;
    }

    .map-container iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: 0;
    }

    footer {
      background: var(--footer-bg);
      color: var(--text-primary);
      text-align: center;
      padding: 3rem 1rem;
    }

    footer p {
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .footer-links {
      margin-top: 1rem;
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.75rem;
    }

    .footer-links a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      margin: 0 8px;
      padding: 0.4rem 0.8rem;
      border-radius: 40px;
      transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(2px);
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .footer-links a::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0%;
      height: 2px;
      background: linear-gradient(90deg, var(--primary), var(--primary-dark));
      transition: all 0.35s ease-out;
      transform: translateX(-50%);
      border-radius: 2px;
    }

    .footer-links a:hover::after {
      width: 70%;
    }

    .footer-links a:hover {
      color: var(--primary-dark);
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-5px) scale(1.05);
      box-shadow: 0 8px 18px rgba(43, 81, 232, 0.2);
    }

    /* ========== CHATBOT STYLES ========== */
    .chatbot-toggle {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      transition: all 0.3s ease;
      z-index: 1000;
    }

    .chatbot-toggle:hover {
      transform: scale(1.1);
      background: var(--primary-dark);
    }

    .chatbot-container {
      position: fixed;
      bottom: 100px;
      right: 30px;
      width: 380px;
      height: 600px;
      background: var(--chat-bg);
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      display: none;
      flex-direction: column;
      overflow: hidden;
      z-index: 1001;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .chatbot-container.active {
      display: flex;
    }

    .chatbot-header {
      background: var(--primary);
      color: white;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
    }

    .chatbot-header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .chatbot-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      font-weight: bold;
      font-size: 20px;
    }

    .chatbot-info h3 {
      font-size: 16px;
      margin: 0;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .chatbot-info p {
      font-size: 12px;
      margin: 5px 0 0;
      opacity: 0.8;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .chatbot-close {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
      opacity: 0.8;
      transition: opacity 0.3s;
    }

    .chat-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      background: var(--light);
    }

    .message {
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
    }

    .message.user {
      align-items: flex-end;
    }

    .message.bot {
      align-items: flex-start;
    }

    .message-content {
      max-width: 80%;
      padding: 12px 16px;
      border-radius: 18px;
      font-size: 14px;
      line-height: 1.5;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .user .message-content {
      background: var(--primary);
      color: white;
      border-bottom-right-radius: 5px;
    }

    .bot .message-content {
      background: var(--chat-bot);
      color: var(--text-primary);
      border-bottom-left-radius: 5px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .message-time {
      font-size: 11px;
      color: var(--gray);
      margin-top: 5px;
      margin-left: 10px;
      margin-right: 10px;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .chat-input-area {
      padding: 20px;
      background: var(--white);
      border-top: 1px solid var(--chat-border);
      display: flex;
      gap: 10px;
    }

    .chat-input-area input {
      flex: 1;
      padding: 12px 16px;
      border: 1px solid var(--chat-border);
      border-radius: 25px;
      outline: none;
      font-size: 14px;
      background: var(--light);
      color: var(--text-primary);
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .chat-input-area button {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      border: none;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .quick-replies {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }

    .quick-reply-btn {
      padding: 8px 16px;
      background: var(--primary-light);
      color: var(--primary);
      border: 1px solid var(--primary);
      border-radius: 20px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.3s;
      font-family: 'NaruMonoDemo', 'Segoe UI', sans-serif;
    }

    .quick-reply-btn:hover {
      background: var(--primary);
      color: white;
    }

    .typing-indicator {
      display: flex;
      gap: 5px;
      padding: 12px 16px;
      background: var(--chat-bot);
      border-radius: 18px;
      border-bottom-left-radius: 5px;
      width: fit-content;
    }

    .typing-indicator span {
      width: 8px;
      height: 8px;
      background: var(--gray);
      border-radius: 50%;
      animation: typing 1s infinite ease-in-out;
    }

    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typing {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-10px); }
    }

    @media (max-width: 768px) {
      .hero h2 { font-size: 2rem; }
      .subscribe-form { flex-direction: column; }
      .btn-subscribe { padding: 1rem; }
      .nav-menu { display: none; }
      .theme-toggle { margin-left: 0; padding: 6px 12px; font-size: 0.8rem; }
      .chatbot-container {
        width: 100%;
        height: 100%;
        bottom: 0;
        right: 0;
        border-radius: 0;
      }
      .chatbot-toggle {
        bottom: 20px;
        right: 20px;
      }
    }
  </style>
</head>
<body class="loading">

<div id="loader-wrapper" style="position: fixed; top:0; left:0; width:100%; height:100%; background: var(--white); display: flex; justify-content: center; align-items: center; z-index: 9999; transition: opacity 0.5s;">
  <div class="loader-content" style="text-align:center;">
    <video class="loader-video" autoplay loop muted playsinline style="width:180px; border-radius:50%; margin-bottom:15px;">
      <source src="assets/images/logo1.mp4" type="video/mp4">
    </video>
    <p style="color: var(--gray); font-family: 'NaruMonoDemo', sans-serif;">Welcome! Please wait...</p>
  </div>
</div>

<header class="main-header">
  <div class="container header-flex">
    <div class="logo-section">
      <img src="assets/images/logo2.png" alt="Go Trike Logo" class="header-logo" onerror="this.src='https://via.placeholder.com/80?text=GO+Trike'">
    </div>
    <div style="display: flex; align-items: center;">
      <nav class="nav-menu">
        <ul>
          <li><a href="#">Home</a></li>
         
        </ul>
      </nav>
      <button class="theme-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i> Dark Mode
      </button>
    </div>
  </div>
</header>

<section class="hero">
  <div class="container">
    <h2>"Book Your Trike, Skip the Wait."</h2>
    <p>Go Trike provides professional, on-demand transportation solutions using accredited local TODAs. Only the nearest TODA gets your booking - ensuring faster pickups, verified drivers, and a safe, reliable trip from your home to your destination.</p>
    <div class="hero-buttons">
      <a href="/register.php" class="btn btn-primary">Create Account</a>
      <a href="/login.php" class="btn btn-outline">Login to Account</a>
    </div>
  </div>
</section>

<section class="features container">
  <h2 style="text-align:center;">Why Choose Us?</h2>
  <div class="features-grid">
    <div class="feature-card"><i class="fas fa-map-marker-alt"></i><h3>Real-time Location</h3><p>Live updates on pick-up points no more guessing where the driver is.</p></div>
    <div class="feature-card"><i class="fas fa-shield-alt"></i><h3>Verified Drivers</h3><p>All drivers are background-checked for your safety and peace of mind.</p></div>
    <div class="feature-card"><i class="fas fa-bolt"></i><h3>Quick Booking</h3><p>Book in under 30 seconds. Pay with cash  effortlessly.</p></div>
  </div>
</section>

<section class="how-it-works container">
  <h2 style="text-align:center;">How It Works</h2>
  <div class="steps">
    <div class="step"><div class="step-number">1</div><h3>Set Destination</h3><p>Pin your exact pick-up and drop-off spots on the map.</p></div>
    <div class="step"><div class="step-number">2</div><h3>Book Ride</h3><p>Instantly match with the nearest available tricycle driver.</p></div>
    <div class="step"><div class="step-number">3</div><h3>Enjoy Trip</h3><p>Track your ride in real-time and arrive safely at your destination.</p></div>
  </div>
</section>

<section class="container">
  <div class="newsletter-section" id="newsletterWithParticles">
    <canvas id="particleCanvas" class="particle-canvas"></canvas>
    <h2>Stay Updated!</h2>
    <p>"Be the first to know about our latest features and exclusive promos in Caloocan South."</p>
    <form class="subscribe-form" action="#" method="POST" onsubmit="alert('Salamat sa pag-subscribe!'); return false;">
      <input type="email" placeholder="Enter your email address" required>
      <button type="submit" class="btn-subscribe">Subscribe</button>
    </form>
  </div>
</section>

<section class="coverage">
  <div class="container">
    <h2 style="text-align:center;">Our Coverage Area</h2>
    <div class="map-wrapper"><div class="map-container"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15440.374495537333!2d120.97025185!3d14.65063065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b5d89f78393d%3A0x6e26710b10629b38!2sSouth+Caloocan%2C+Caloocan%2C+Metro+Manila!5e0!3m2!1sen!2sph!4v1709724000000!5m2!1sen!2sph" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe></div></div>
  </div>
</section>

<footer>
  <div class="container">
    <p>© <?php echo date("Y"); ?> Go Trike - Caloocan South. All rights reserved.</p>
    <div class="footer-links">
        <a href="/policy.php"><i class="fas fa-lock"></i> Privacy Policy</a>
        <a href="/terms.php"><i class="fas fa-file-alt"></i> Terms of Service</a>
        <a href="/contact.php"><i class="fas fa-phone-alt"></i> Contact Us</a>
    </div>
  </div>
</footer>

<button class="chatbot-toggle" id="chatbotToggle"><i class="fas fa-comment"></i></button>
<div class="chatbot-container" id="chatbotContainer"><div class="chatbot-header" id="chatbotHeader"><div class="chatbot-header-left"><div class="chatbot-avatar"><i class="fas fa-robot"></i></div><div class="chatbot-info"><h3>Go Trike Assistant</h3><p>Online • Powered by AI</p></div></div><button class="chatbot-close" id="chatbotClose"><i class="fas fa-times"></i></button></div><div class="chat-messages" id="chatMessages"><div class="message bot"><div class="message-content">👋 Hi! I'm your Go Trike virtual assistant. How can I help you today?</div><div class="message-time">Just now</div></div></div><div class="chat-input-area"><input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off"><button id="sendMessage"><i class="fas fa-paper-plane"></i></button></div></div>

<script>
  // --- PRELOADER ---
  window.addEventListener('load', function() {
    const loader = document.getElementById('loader-wrapper');
    const body = document.body;
    setTimeout(() => {
      loader.style.opacity = '0';
      loader.style.visibility = 'hidden';
      body.classList.remove('loading');
      setTimeout(() => { if(loader) loader.style.display = 'none'; }, 500);
    }, 1000);
  });

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

  // ========== PARTICLE SYSTEM ==========
  (function() {
    const canvas = document.getElementById('particleCanvas');
    if (!canvas) return;
    let ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    let mouseX = 0, mouseY = 0;
    let hasMouseMoved = false;
    const PARTICLE_COUNT = 55;
    const MAX_DIST = 150;
    const MOUSE_INFLUENCE_RADIUS = 200;
    const MOUSE_FORCE = 0.08;

    class Particle {
      constructor(x, y, vx, vy, radius) {
        this.x = x; this.y = y; this.vx = vx; this.vy = vy;
        this.radius = radius || Math.random() * 3.5 + 1.8;
        this.originalX = x; this.originalY = y;
      }
      update(mouseX, mouseY, hasMoved) {
        if (hasMoved) {
          let dx = this.x - mouseX, dy = this.y - mouseY;
          let dist = Math.hypot(dx, dy);
          if (dist < MOUSE_INFLUENCE_RADIUS && dist > 0.5) {
            let force = (MOUSE_INFLUENCE_RADIUS - dist) / MOUSE_INFLUENCE_RADIUS;
            let angle = Math.atan2(dy, dx);
            this.vx += Math.cos(angle) * force * MOUSE_FORCE;
            this.vy += Math.sin(angle) * force * MOUSE_FORCE;
          }
        }
        let dxToOrigin = this.originalX - this.x, dyToOrigin = this.originalY - this.y;
        this.vx += dxToOrigin * 0.008; this.vy += dyToOrigin * 0.008;
        this.vx *= 0.97; this.vy *= 0.97;
        this.x += this.vx; this.y += this.vy;
        if (this.x < 5) { this.x = 5; this.vx *= -0.2; }
        if (this.x > width - 5) { this.x = width - 5; this.vx *= -0.2; }
        if (this.y < 5) { this.y = 5; this.vy *= -0.2; }
        if (this.y > height - 5) { this.y = height - 5; this.vy *= -0.2; }
      }
      draw(ctx) {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.fill();
        ctx.shadowBlur = 6;
        ctx.shadowColor = 'rgba(255,255,200,0.6)';
        ctx.fill();
        ctx.shadowBlur = 0;
      }
    }
    function initParticles(w, h) {
      particles = [];
      for (let i = 0; i < PARTICLE_COUNT; i++) {
        let x = Math.random() * w, y = Math.random() * h;
        let vx = (Math.random() - 0.5) * 0.4, vy = (Math.random() - 0.5) * 0.4;
        particles.push(new Particle(x, y, vx, vy, Math.random() * 3.2 + 1.5));
      }
    }
    function drawLines(ctx, particles) {
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          let dx = particles[i].x - particles[j].x, dy = particles[i].y - particles[j].y;
          let distance = Math.hypot(dx, dy);
          if (distance < MAX_DIST) {
            let opacity = 0.35 * (1 - distance / MAX_DIST);
            ctx.beginPath();
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            ctx.strokeStyle = `rgba(255, 255, 255, ${opacity})`;
            ctx.lineWidth = 1.2;
            ctx.stroke();
          }
        }
      }
    }
    function resizeCanvas() {
      const container = document.getElementById('newsletterWithParticles');
      if (!container) return;
      const rect = container.getBoundingClientRect();
      width = rect.width; height = rect.height;
      canvas.width = width; canvas.height = height;
      canvas.style.width = `${width}px`; canvas.style.height = `${height}px`;
      initParticles(width, height);
    }
    function animate() {
      if (!ctx) return;
      ctx.clearRect(0, 0, width, height);
      for (let p of particles) p.update(mouseX, mouseY, hasMouseMoved);
      drawLines(ctx, particles);
      for (let p of particles) p.draw(ctx);
      requestAnimationFrame(animate);
    }
    const containerEl = document.getElementById('newsletterWithParticles');
    if (containerEl) {
      const resizeObserver = new ResizeObserver(() => resizeCanvas());
      resizeObserver.observe(containerEl);
      window.addEventListener('resize', () => resizeCanvas());
      containerEl.addEventListener('mousemove', (e) => {
        const rect = containerEl.getBoundingClientRect();
        let offsetX = e.clientX - rect.left, offsetY = e.clientY - rect.top;
        if (offsetX >= 0 && offsetX <= width && offsetY >= 0 && offsetY <= height) {
          mouseX = offsetX; mouseY = offsetY; hasMouseMoved = true;
        } else hasMouseMoved = false;
      });
      containerEl.addEventListener('mouseleave', () => { hasMouseMoved = false; });
      resizeCanvas();
      animate();
    }
  })();

  // ========== CHATBOT ==========
  document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggle = document.getElementById('chatbotToggle'), chatbotContainer = document.getElementById('chatbotContainer'), chatbotClose = document.getElementById('chatbotClose');
    const chatMessages = document.getElementById('chatMessages'), chatInput = document.getElementById('chatInput'), sendButton = document.getElementById('sendMessage');
    chatbotToggle.addEventListener('click', () => { chatbotContainer.classList.add('active'); setTimeout(() => addQuickReplies(), 500); });
    chatbotClose.addEventListener('click', () => { chatbotContainer.classList.remove('active'); });
    sendButton.addEventListener('click', sendUserMessage);
    chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendUserMessage(); });
    function sendUserMessage() {
      const message = chatInput.value.trim();
      if (message === '') return;
      addMessage(message, 'user');
      chatInput.value = '';
      showTypingIndicator();
      setTimeout(() => {
        removeTypingIndicator();
        addMessage(generateBotResponse(message), 'bot');
        addQuickReplies();
      }, 1500);
    }
    function addMessage(text, sender) {
      const messageDiv = document.createElement('div'); messageDiv.className = `message ${sender}`;
      const contentDiv = document.createElement('div'); contentDiv.className = 'message-content'; contentDiv.textContent = text;
      const timeDiv = document.createElement('div'); timeDiv.className = 'message-time'; timeDiv.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
      messageDiv.appendChild(contentDiv); messageDiv.appendChild(timeDiv);
      chatMessages.appendChild(messageDiv); chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    function showTypingIndicator() {
      const typingDiv = document.createElement('div'); typingDiv.className = 'message bot'; typingDiv.id = 'typingIndicator';
      const indicatorDiv = document.createElement('div'); indicatorDiv.className = 'typing-indicator'; indicatorDiv.innerHTML = '<span></span><span></span><span></span>';
      typingDiv.appendChild(indicatorDiv); chatMessages.appendChild(typingDiv); chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    function removeTypingIndicator() { const el = document.getElementById('typingIndicator'); if(el) el.remove(); }
    function addQuickReplies() {
      const existing = document.querySelector('.quick-replies'); if(existing && existing.parentElement) existing.parentElement.remove();
      const quickDiv = document.createElement('div'); quickDiv.className = 'message bot';
      const container = document.createElement('div'); container.className = 'quick-replies';
      const questions = ['How to book a ride?', 'What are your service areas?', 'Payment methods?', 'Driver requirements?', 'Contact support'];
      questions.forEach(q => { const btn = document.createElement('button'); btn.className = 'quick-reply-btn'; btn.textContent = q; btn.onclick = () => handleQuickReply(q); container.appendChild(btn); });
      quickDiv.appendChild(container); chatMessages.appendChild(quickDiv); chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    function handleQuickReply(q) { addMessage(q, 'user'); showTypingIndicator(); setTimeout(() => { removeTypingIndicator(); addMessage(generateBotResponse(q), 'bot'); addQuickReplies(); }, 1000); }
    function generateBotResponse(input) {
      const i = input.toLowerCase();
      if(i.includes('book')||i.includes('ride')) return "To book a ride:\n1. Create account/login\n2. Set pickup & drop-off\n3. Confirm booking\nTrack your ride in real-time!";
      if(i.includes('area')||i.includes('coverage')) return "We serve Caloocan South: Monumento, MCU, Grace Park, Sangandaan, Dagat-Dagatan, Bagong Barrio, 10th Avenue & more!";
      if(i.includes('payment')||i.includes('pay')||i.includes('cash')) return "Payment methods: 💵 Cash, 📱 GCash. All secure!";
      if(i.includes('driver')||i.includes('requirements')) return "Driver accounts managed by admin. For inquiries, contact support directly.";
      if(i.includes('contact')||i.includes('support')) return "Support: 📞 09928697153 | 📧 songiericsajoca0@gmail.com | Live chat 24/7.";
      if(i.includes('hello')||i.includes('hi')) return "Hello! 👋 Need help with your trike booking?";
      return "I'm here to help! Pick a question above or type your inquiry. 😊";
    }
  });
</script>
</body>
</html>