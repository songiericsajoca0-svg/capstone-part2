<?php
// index.php - Caloocan South Tricycle Booking Landing Page with Gemini-like Chatbot
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Go Trike | Caloocan South Tricycle Booking</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
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
    }

    /* ── PRELOADER STYLES ── */
    #loader-wrapper {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--white);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.5s ease, visibility 0.5s;
    }

    .loader-content {
      text-align: center;
      max-width: 300px;
    }

    .loader-video {
      width: 180px; 
      height: auto;
      margin-bottom: 15px;
      border-radius: 50%;
    }

    .loader-brand {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: -1px;
      animation: pulse 1.5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.05); opacity: 0.7; }
    }

    .loader-hidden {
      opacity: 0;
      visibility: hidden;
    }

    /* ── MAIN STYLES ── */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--light);
      color: var(--dark);
      line-height: 1.6;
      overflow-x: hidden;
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
      background: linear-gradient(to bottom, #87CEEB, #FFFFFF);
      border-bottom: 2px solid #f0f0f0;
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
      color: #333;
      font-weight: 600;
      transition: 0.3s;
    }

    .nav-menu a:hover {
      color: var(--primary);
    }

    .hero {
      text-align: center;
      padding: 5rem 1.5rem 4rem;
    }

    .hero h2 {
      font-size: 2.8rem;
      margin-bottom: 1.2rem;
      color: var(--dark);
    }

    .hero p {
      font-size: 1.25rem;
      color: var(--gray);
      max-width: 720px;
      margin: 0 auto 2.5rem;
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
      background: white;
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
      background: var(--white);
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
      background: white;
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
    }

    /* ── NEWSLETTER SECTION ── */
    .newsletter-section {
      background: linear-gradient(135deg, #003366 0%, #001a33 100%);
      color: white;
      border-radius: 30px;
      padding: 4rem 2rem;
      margin: 2rem auto;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .newsletter-section h2 {
      font-size: 2rem;
      margin-bottom: 1rem;
    }

    .newsletter-section p {
      margin-bottom: 2rem;
      opacity: 0.9;
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
    }

    .btn-subscribe {
      background: white;
      color: Black;
      padding: 0 2rem;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
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
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
      background: linear-gradient(to bottom, #87CEEB, #FFFFFF);
      color: #333;
      text-align: center;
      padding: 3rem 1rem;
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
    }

    .chatbot-info p {
      font-size: 12px;
      margin: 5px 0 0;
      opacity: 0.8;
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

    .chatbot-close:hover {
      opacity: 1;
    }

    .chat-messages {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
      background: #f8f9fa;
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
    }

    .user .message-content {
      background: var(--primary);
      color: white;
      border-bottom-right-radius: 5px;
    }

    .bot .message-content {
      background: white;
      color: var(--dark);
      border-bottom-left-radius: 5px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .message-time {
      font-size: 11px;
      color: #999;
      margin-top: 5px;
      margin-left: 10px;
      margin-right: 10px;
    }

    .chat-input-area {
      padding: 20px;
      background: white;
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
      transition: border-color 0.3s;
    }

    .chat-input-area input:focus {
      border-color: var(--primary);
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

    .chat-input-area button:hover {
      background: var(--primary-dark);
      transform: scale(1.05);
    }

    .typing-indicator {
      display: flex;
      gap: 5px;
      padding: 12px 16px;
      background: white;
      border-radius: 18px;
      border-bottom-left-radius: 5px;
      width: fit-content;
    }

    .typing-indicator span {
      width: 8px;
      height: 8px;
      background: #999;
      border-radius: 50%;
      animation: typing 1s infinite ease-in-out;
    }

    .typing-indicator span:nth-child(2) {
      animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes typing {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-10px); }
    }

    /* Quick replies */
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
    }

    .quick-reply-btn:hover {
      background: var(--primary);
      color: white;
    }

    @media (max-width: 768px) {
      .hero h2 { font-size: 2rem; }
      .subscribe-form { flex-direction: column; }
      .btn-subscribe { padding: 1rem; }
      .nav-menu { display: none; }
      
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

<div id="loader-wrapper">
  <div class="loader-content">
    <video class="loader-video" autoplay loop muted playsinline>
      <source src="assets/images/logo1.mp4" type="video/mp4">
    </video>
    <p style="color: var(--gray); margin-top: 10px; font-weight: 600;">Welcome! Please wait...</p>
  </div>
</div>

<header class="main-header">
  <div class="container header-flex">
    <div class="logo-section">
      <img src="assets/images/logo2.png" alt="Go Trike Logo" class="header-logo">
    </div>
    
    <nav class="nav-menu">
      <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">Services</a></li>
        <li><a href="#">About Us</a></li>
      </ul>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="container">
    <h2>The Easiest Way to Book a Trike</h2>
    <p>Monumento, MCU, Grace Park, Sangandaan, Dagat-Dagatan, Bagong Barrio, 10th Avenue, and many more areas — all at your fingertips.</p>
    
    <div class="hero-buttons">
      <a href="register.php" class="btn btn-primary">Create Account</a>
      <a href="login.php" class="btn btn-outline">Login to Account</a>
    </div>
  </div>
</section>

<section class="features container">
  <h2 style="text-align:center;">Why Choose Us?</h2>
  <div class="features-grid">
    <div class="feature-card">
      <i class="fas fa-map-marker-alt"></i>
      <h3>Real-time Location</h3>
      <p>Live updates on pick-up points — no more guessing where the driver is.</p>
    </div>
    <div class="feature-card">
      <i class="fas fa-shield-alt"></i>
      <h3>Verified Drivers</h3>
      <p>All drivers are background-checked for your safety and peace of mind.</p>
    </div>
    <div class="feature-card">
      <i class="fas fa-bolt"></i>
      <h3>Quick Booking</h3>
      <p>Book in under 30 seconds. Pay with cash or GCash effortlessly.</p>
    </div>
  </div>
</section>

<section class="how-it-works container">
  <h2 style="text-align:center;">How It Works</h2>
  <div class="steps">
    <div class="step">
      <div class="step-number">1</div>
      <h3>Set Destination</h3>
      <p>Pin your exact pick-up and drop-off spots on the map.</p>
    </div>
    <div class="step">
      <div class="step-number">2</div>
      <h3>Book Ride</h3>
      <p>Instantly match with the nearest available tricycle driver.</p>
    </div>
    <div class="step">
      <div class="step-number">3</div>
      <h3>Enjoy Trip</h3>
      <p>Track your ride in real-time and arrive safely at your destination.</p>
    </div>
  </div>
</section>

<section class="container">
  <div class="newsletter-section">
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
    <div class="map-wrapper">
      <div class="map-container">
        <iframe 
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15440.374495537333!2d120.97025185!3d14.65063065!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b5d89f78393d%3A0x6e26710b10629b38!2sSouth+Caloocan%2C+Caloocan%2C+Metro+Manila!5e0!3m2!1sen!2sph!4v1709724000000!5m2!1sen!2sph" 
          width="100%" 
          height="450" 
          style="border:0;" 
          allowfullscreen="" 
          loading="lazy" 
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <p>© <?php echo date("Y"); ?> Go Trike - Caloocan South. All rights reserved.</p>
    <p style="margin-top: 10px;">
        <a href="#" style="color:#555; text-decoration:none;">Privacy Policy</a> • 
        <a href="#" style="color:#555; text-decoration:none;">Terms of Service</a>
    </p>
  </div>
</footer>

<!-- CHATBOT TOGGLE BUTTON -->
<button class="chatbot-toggle" id="chatbotToggle">
  <i class="fas fa-comment"></i>
</button>

<!-- CHATBOT CONTAINER -->
<div class="chatbot-container" id="chatbotContainer">
  <div class="chatbot-header" id="chatbotHeader">
    <div class="chatbot-header-left">
      <div class="chatbot-avatar">
        <i class="fas fa-robot"></i>
      </div>
      <div class="chatbot-info">
        <h3>Go Trike Assistant</h3>
        <p>Online • Powered by AI</p>
      </div>
    </div>
    <button class="chatbot-close" id="chatbotClose">
      <i class="fas fa-times"></i>
    </button>
  </div>
  
  <div class="chat-messages" id="chatMessages">
    <!-- Welcome message -->
    <div class="message bot">
      <div class="message-content">
        👋 Hi! I'm your Go Trike virtual assistant. How can I help you today?
      </div>
      <div class="message-time">Just now</div>
    </div>
    
    <!-- Quick replies will be added here dynamically -->
  </div>
  
  <div class="chat-input-area">
    <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off">
    <button id="sendMessage">
      <i class="fas fa-paper-plane"></i>
    </button>
  </div>
</div>

<script>
  window.addEventListener('load', function() {
    const loader = document.getElementById('loader-wrapper');
    const body = document.body;

    // Loader logic: 2.5 seconds delay
    setTimeout(() => {
      loader.classList.add('loader-hidden');
      body.classList.remove('loading');
      
      setTimeout(() => {
        loader.style.display = 'none';
      }, 500);
    }, 1000); 
  });

  // ========== CHATBOT FUNCTIONALITY ==========
  document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotContainer = document.getElementById('chatbotContainer');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotHeader = document.getElementById('chatbotHeader');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendButton = document.getElementById('sendMessage');
    
    let isOpen = false;
    
    // Toggle chatbot
    chatbotToggle.addEventListener('click', function() {
      chatbotContainer.classList.add('active');
      isOpen = true;
      
      // Add quick replies after opening
      setTimeout(() => {
        addQuickReplies();
      }, 500);
    });
    
    // Close chatbot
    chatbotClose.addEventListener('click', function() {
      chatbotContainer.classList.remove('active');
      isOpen = false;
    });
    
    // Close when clicking header (optional)
    // chatbotHeader.addEventListener('click', function() {
    //   chatbotContainer.classList.remove('active');
    //   isOpen = false;
    // });
    
    // Send message on button click
    sendButton.addEventListener('click', sendUserMessage);
    
    // Send message on Enter key
    chatInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        sendUserMessage();
      }
    });
    
    function sendUserMessage() {
      const message = chatInput.value.trim();
      if (message === '') return;
      
      // Add user message to chat
      addMessage(message, 'user');
      chatInput.value = '';
      
      // Show typing indicator
      showTypingIndicator();
      
      // Simulate bot response after delay
      setTimeout(() => {
        removeTypingIndicator();
        const botResponse = generateBotResponse(message);
        addMessage(botResponse, 'bot');
        
        // Add quick replies after bot response
        addQuickReplies();
      }, 1500);
    }
    
    function addMessage(text, sender) {
      const messageDiv = document.createElement('div');
      messageDiv.className = `message ${sender}`;
      
      const contentDiv = document.createElement('div');
      contentDiv.className = 'message-content';
      contentDiv.textContent = text;
      
      const timeDiv = document.createElement('div');
      timeDiv.className = 'message-time';
      timeDiv.textContent = getCurrentTime();
      
      messageDiv.appendChild(contentDiv);
      messageDiv.appendChild(timeDiv);
      
      chatMessages.appendChild(messageDiv);
      
      // Scroll to bottom
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function showTypingIndicator() {
      const typingDiv = document.createElement('div');
      typingDiv.className = 'message bot';
      typingDiv.id = 'typingIndicator';
      
      const indicatorDiv = document.createElement('div');
      indicatorDiv.className = 'typing-indicator';
      indicatorDiv.innerHTML = '<span></span><span></span><span></span>';
      
      typingDiv.appendChild(indicatorDiv);
      chatMessages.appendChild(typingDiv);
      
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function removeTypingIndicator() {
      const typingIndicator = document.getElementById('typingIndicator');
      if (typingIndicator) {
        typingIndicator.remove();
      }
    }
    
    function addQuickReplies() {
      // Remove existing quick replies if any
      const existingQuickReplies = document.querySelector('.quick-replies');
      if (existingQuickReplies) {
        existingQuickReplies.remove();
      }
      
      // Create quick replies container
      const quickRepliesDiv = document.createElement('div');
      quickRepliesDiv.className = 'message bot';
      
      const repliesContainer = document.createElement('div');
      repliesContainer.className = 'quick-replies';
      
      const questions = [
        'How to book a ride?',
        'What are your service areas?',
        'Payment methods?',
        'Driver requirements?',
        'Contact support'
      ];
      
      questions.forEach(question => {
        const btn = document.createElement('button');
        btn.className = 'quick-reply-btn';
        btn.textContent = question;
        btn.onclick = function() {
          handleQuickReply(question);
        };
        repliesContainer.appendChild(btn);
      });
      
      quickRepliesDiv.appendChild(repliesContainer);
      chatMessages.appendChild(quickRepliesDiv);
      
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function handleQuickReply(question) {
      // Add user's quick reply
      addMessage(question, 'user');
      
      // Show typing indicator
      showTypingIndicator();
      
      // Generate response based on quick reply
      setTimeout(() => {
        removeTypingIndicator();
        const response = generateBotResponse(question);
        addMessage(response, 'bot');
        
        // Add quick replies again
        addQuickReplies();
      }, 1000);
    }
    
    function generateBotResponse(userInput) {
      const input = userInput.toLowerCase();
      
      // Knowledge base for Go Trike
      if (input.includes('book') || input.includes('ride')) {
        return "To book a ride:\n\n1. Create an account or login\n2. Set your pickup and drop-off locations\n3. Choose your preferred tricycle\n4. Confirm booking\n\nYou can track your ride in real-time!";
      }
      
      if (input.includes('area') || input.includes('coverage') || input.includes('location')) {
        return "We currently serve these areas in Caloocan South:\n\n• Monumento\n• MCU\n• Grace Park\n• Sangandaan\n• Dagat-Dagatan\n• Bagong Barrio\n• 10th Avenue\n• And more nearby areas!";
      }
      
      if (input.includes('payment') || input.includes('pay') || input.includes('cash') || input.includes('gcash')) {
        return "We accept multiple payment methods:\n\n💵 Cash\n📱 GCash\n All payments are secure and verified.";
      }
      
      if (input.includes('driver') || input.includes('requirements')) {
return "Driver Information:\n\n🔒 Only the admin is authorized to create driver accounts.\n🔒 All driver details are strictly confidential.\n\n📌 For any inquiries regarding driver requirements or application, please contact the admin directly.\n\nThank you for understanding.";      }
      
      if (input.includes('contact') || input.includes('support') || input.includes('help')) {
return "You can reach our support team:\n\n📞 Hotline: 09928697153\n📧 Email: songiericsajoca0@gmail.com\n💬 or  Live chat: 24/7 available!\n\n";      }
      
      if (input.includes('hello') || input.includes('hi') || input.includes('hey')) {
        return "Hello! 👋 How can I assist you with your tricycle booking today?";
      }
      
      if (input.includes('thank')) {
        return "You're welcome! 😊 Is there anything else I can help you with?";
      }
      
      // Default response
      return "I understand you're asking about '" + userInput + "'. For more specific information, please contact our support team or choose from the quick replies below. 😊";
    }
    
    function getCurrentTime() {
      const now = new Date();
      let hours = now.getHours();
      let minutes = now.getMinutes();
      const ampm = hours >= 12 ? 'PM' : 'AM';
      
      hours = hours % 12;
      hours = hours ? hours : 12; // 0 should be 12
      minutes = minutes < 10 ? '0' + minutes : minutes;
      
      return hours + ':' + minutes + ' ' + ampm;
    }
    
    // Add initial quick replies after page load
    setTimeout(() => {
      if (isOpen) {
        addQuickReplies();
      }
    }, 2000);
  });
</script>

</body>
</html>