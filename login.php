<?php
require_once 'includes/config.php';

// Siguraduhin na ang session ay nagsisimula (kung wala pa sa config.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['name']    = $row['name'];
            $_SESSION['role']    = $row['role'];

            // Redirect base sa role
            if ($row['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } elseif ($row['role'] === 'driver') {
                header("Location: driver/dashboard.php");
            } else {
                header("Location: passenger/dashboard.php");
            }
            exit;
        }
    }
    $error = "Invalid email or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Secure Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <style>
    :root {
      --primary: #1e40af; 
      --secondary: #3b82f6; 
      --bg-gradient: linear-gradient(135deg, #0ea5e9 0%, #ffffff 50%, #1e3a8a 100%);
      --text-main: #1e293b;
      --text-muted: #64748b;
      --error: #ef4444;
      --border: #e2e8f0;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg-gradient);
      background-attachment: fixed;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 3rem 2.5rem;
      border-radius: 28px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      width: 100%;
      max-width: 440px;
      text-align: center;
      animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .back-home {
      display: flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      color: var(--text-muted);
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      transition: color 0.2s;
      width: fit-content;
    }
    .back-home:hover { color: var(--primary); }

    .logo-container { margin-bottom: 1.5rem; }
    .logo { height: 100px; object-fit: contain; }

    h2 {
      color: var(--text-main);
      font-weight: 800;
      font-size: 2rem;
      margin: 0 0 0.5rem 0;
      letter-spacing: -0.05em;
    }

    .subtitle {
      color: var(--text-muted);
      font-size: 0.95rem;
      margin-bottom: 2.5rem;
    }

    .alert {
      padding: 0.8rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      font-size: 0.85rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      justify-content: center;
      width: 100%;
    }
    .alert-error { background: #fef2f2; color: var(--error); border: 1px solid #fee2e2; }

    .form-group {
      text-align: left;
      margin-bottom: 1.5rem;
      width: 100%;
    }

    label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      margin-bottom: 0.6rem;
      color: var(--text-main);
      padding-left: 4px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-wrapper i.main-icon {
      position: absolute;
      left: 14px;
      color: var(--text-muted);
      width: 18px;
    }

    input {
      width: 100%;
      padding: 14px 16px 14px 44px;
      border: 1.5px solid var(--border);
      border-radius: 14px;
      font-size: 1rem;
      font-family: inherit;
      transition: all 0.3s;
      background: #f8fafc;
    }

    input:focus {
      outline: none;
      border-color: var(--secondary);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }

    .eye-toggle {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      display: flex;
    }

    .btn-login {
      width: 100%;
      padding: 16px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 14px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 1rem;
    }

    .btn-login:hover {
      background: #111827;
      transform: translateY(-2px);
      box-shadow: 0 10px 20px -5px rgba(30, 64, 175, 0.4);
    }

    .footer-text {
      margin-top: 2rem;
      font-size: 0.9rem;
      color: var(--text-muted);
    }

    .footer-text a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 700;
    }

    .footer-text a:hover { text-decoration: underline; }

    /* Custom Style for Forgot Password link to match UI */
    .forgot-link {
      font-size: 0.8rem; 
      color: var(--secondary); 
      text-decoration: none; 
      margin-bottom: 0.6rem; 
      font-weight: 600;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: var(--primary); }

  </style>
</head>
<body>

<div class="login-card">
    <a href="index.php" class="back-home">
        <i data-lucide="chevron-left" size="18"></i>
        Back to Home
    </a>

    <div class="logo-container">
        <img src="assets/images/logo2.png" alt="Portal Logo" class="logo">
    </div>
    
    <h2>Welcome Back</h2>
    <p class="subtitle">Please enter your details to sign in.</p>

    <?php if(isset($error)): ?>
        <div class="alert alert-error">
            <i data-lucide="alert-circle" size="18"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrapper">
                <i data-lucide="mail" class="main-icon"></i>
                <input type="email" name="email" placeholder="name@company.com" required>
            </div>
        </div>
        
        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <label>Password</label>
                <a href="forgotpass.php" class="forgot-link">Forgot Password?</a>
            </div>
            <div class="input-wrapper">
                <i data-lucide="lock" class="main-icon"></i>
                <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
                <button type="button" class="eye-toggle" id="togglePassword">
                    <i data-lucide="eye" id="eyeIcon" size="18"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <span>Sign In to Account</span>
            <i data-lucide="arrow-right" size="18"></i>
        </button>
    </form>

    <p class="footer-text">
        New here? <a href="register.php">Create an account</a>
    </p>
</div>

<script>
    lucide.createIcons();

    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#passwordInput');
    const eyeIcon = document.querySelector('#eyeIcon');

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        if (type === 'text') {
            eyeIcon.setAttribute('data-lucide', 'eye-off');
        } else {
            eyeIcon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    });

    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Simple loading state
        const btn = document.querySelector('.btn-login');
        const span = btn.querySelector('span');
        span.innerHTML = 'Authenticating...';
        btn.style.opacity = '0.8';
        btn.style.pointerEvents = 'none';
    });
</script>

</body>
</html>