<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Caloocan South Tricycle Booking</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/style.css">
  
  <style>
    /* Load the Custom Font */
    @font-face {
      font-family: 'NaruMonoDemo';
      src: url('../assets/fonts/NaruMonoDemo-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }

    /* Apply Font to Header and all its children */
    header, 
    header * {
      font-family: 'NaruMonoDemo', monospace !important;
    }

    /* Header gradient */
    header {
      background: linear-gradient(to bottom, #87CEEB, #FFFFFF) !important;
    }

    /* Logo size and center alignment */
    .logo-container img {
      height: 50px; /* Binabaan nang konti para magkasya ang sub-text */
      width: auto;
      object-fit: contain;
    }

    /* Navigation Container */
    nav {
      background: transparent !important;
      display: flex;
      align-items: center;
    }

    /* Individual nav links - Added uppercase */
    .nav-link {
      position: relative;
      padding: 0.5rem 1rem;
      font-weight: 700; /* Mas makapal para sa caps */
      font-size: 0.75rem; /* Slightly smaller for better fit with all caps */
      color: #000000 !important;
      text-decoration: none;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      height: 100%;
      text-transform: uppercase; /* Gagawa sa lahat na Capital Letters */
      letter-spacing: 0.05em; /* Konting spacing para readable ang caps */
    }

    /* Hover: light blue background */
    .nav-link:hover {
      background: #dbeafe;
      color: #1e40af !important;
      border-radius: 0.375rem;
    }

    /* Active link style */
    .nav-link.active,
    .nav-link.is-active {
      color: #1d4ed8 !important;
    }

    /* Blue underline for active/hover */
    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 15%;
      width: 70%;
      height: 3px;
      background-color: #2563eb;
      transform: scaleX(0);
      transition: transform 0.2s ease-in-out;
      border-radius: 2px;
    }

    .nav-link:hover::after,
    .nav-link.active::after,
    .nav-link.is-active::after {
      transform: scaleX(1);
    }

    /* Role Text below Logo */
    .role-badge {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      color: #1e40af;
      line-height: 1;
      margin-top: 2px;
      letter-spacing: 1px;
    }

    /* --- LOGOUT / USER PILL CSS --- */
    .user-pill {
      display: flex;
      align-items: center;
      padding: 0.5rem 1.25rem;
      margin-left: 1.5rem;
      border: 2px solid #ef4444;
      color: #dc2626 !important;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.75rem;
      text-decoration: none;
      transition: all 0.3s ease;
      background: transparent;
      text-transform: uppercase;
    }

    .user-pill:hover {
      background-color: #dc2626;
      color: #ffffff !important;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transform: translateY(-1px);
    }

    /* Register Button */
    .register-btn {
      padding: 0.6rem 1.5rem;
      background-color: #2563eb;
      color: white !important;
      border-radius: 0.5rem;
      font-weight: 700;
      transition: all 0.2s;
      margin-left: 1rem;
      text-transform: uppercase;
      font-size: 0.75rem;
    }

    .register-btn:hover {
      background-color: #1d4ed8;
    }

    /* Mobile menu styles */
    .mobile-menu {
      display: none;
      position: fixed;
      top: 80px;
      left: 0;
      right: 0;
      background: white;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      z-index: 100;
      max-height: calc(100vh - 80px);
      overflow-y: auto;
    }

    .mobile-menu.open {
      display: block;
    }

    .mobile-nav-link {
      display: block;
      padding: 1rem 1.5rem;
      font-weight: 700;
      font-size: 0.875rem;
      color: #000000;
      text-decoration: none;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid #e5e7eb;
    }

    .mobile-nav-link:hover {
      background: #dbeafe;
      color: #1e40af;
    }

    .mobile-user-pill {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 1rem 1.5rem;
      padding: 0.75rem 1rem;
      border: 2px solid #ef4444;
      color: #dc2626;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.75rem;
      text-decoration: none;
      text-transform: uppercase;
    }

    .mobile-register-btn {
      display: block;
      margin: 1rem 1.5rem;
      padding: 0.75rem;
      background-color: #2563eb;
      color: white;
      text-align: center;
      border-radius: 0.5rem;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.75rem;
      text-decoration: none;
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="border-b border-gray-200 sticky top-0 z-50 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-20"> 
      
      <div class="flex items-center logo-container">
        <a href="<?php 
            if (isset($_SESSION['user_id'])) {
                if ($_SESSION['role'] === 'admin') echo '../admin/dashboard.php';
                elseif ($_SESSION['role'] === 'driver') echo '../driver/driver_dashboard.php';
                else echo '../passenger/dashboard.php';
            } else {
                echo '../index.php';
            }
         ?>" class="flex flex-col items-center"> <img src="../assets/images/logo2.png" alt="Logo">
          
          <?php if (isset($_SESSION['role'])): ?>
            <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
          <?php endif; ?>
        </a>
      </div>

      <nav class="hidden md:flex items-center">
        <?php 
          $current_page = basename($_SERVER['PHP_SELF']);
          function isActive($page) {
              global $current_page;
              return $current_page === $page ? 'is-active' : '';
          }
        ?>

        <?php if (isset($_SESSION['user_id'])): ?>
          
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="../admin/dashboard.php" class="nav-link <?php echo isActive('dashboard.php'); ?>">Dashboard</a>
            <a href="../admin/location.php" class="nav-link <?php echo isActive('location.php'); ?>">Available Locations</a>
            <a href="../admin/reports.php" class="nav-link <?php echo isActive('reports.php'); ?>">Reports</a>
            <a href="../admin/driver_accounts.php" class="nav-link <?php echo isActive('driver_accounts.php'); ?>">Drivers</a>
            <a href="../admin/passenger_accounts.php" class="nav-link <?php echo isActive('passenger_accounts.php'); ?>"></a>
          <?php endif; ?>

          <?php if ($_SESSION['role'] === 'driver'): ?>
            <a href="../driver/driver_dashboard.php" class="nav-link <?php echo isActive('driver_dashboard.php'); ?>">Driver Booking Dashboard</a>
            <a href="../driver/history.php" class="nav-link <?php echo isActive('history.php'); ?>">History</a>
            <a href="../driver/profile.php" class="nav-link <?php echo isActive('profile.php'); ?>">Profile</a>
          <?php endif; ?>

          <?php if ($_SESSION['role'] === 'passenger'): ?>
            <a href="../passenger/dashboard.php" class="nav-link <?php echo isActive('dashboard.php'); ?>">Dashboard</a>
            <a href="../passenger/my-bookings.php" class="nav-link <?php echo isActive('my-bookings.php'); ?>">My Bookings</a>
            <a href="../passenger/profile.php" class="nav-link <?php echo isActive('profile.php'); ?>">Profile</a>
          <?php endif; ?>

          <a href="../logout.php" class="user-pill">
            <span>Logout</span>
            <span class="ml-2 opacity-70 font-normal text-[10px] uppercase tracking-wider hidden lg:inline border-l border-red-300 pl-2">
              <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
            </span>
          </a>

        <?php else: ?>
          <a href="../index.php" class="nav-link <?php echo isActive('index.php'); ?>">Home</a>
          <a href="../login.php" class="nav-link <?php echo isActive('login.php'); ?>">Login</a>
          <a href="../register.php" class="register-btn">Register</a>
        <?php endif; ?>
      </nav>

      <div class="md:hidden flex items-center">
        <button type="button" id="mobileMenuButton" class="p-2 text-gray-700 hover:text-blue-700 focus:outline-none">
          <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

    </div>
  </div>
</header>

<!-- Mobile Menu -->
<div id="mobileMenu" class="mobile-menu md:hidden">
  <div class="bg-white">
    <?php if (isset($_SESSION['user_id'])): ?>
      
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="../admin/dashboard.php" class="mobile-nav-link">Dashboard</a>
        <a href="../admin/location.php" class="mobile-nav-link">Available Locations</a>
        <a href="../admin/reports.php" class="mobile-nav-link">Reports</a>
        <a href="../admin/driver_accounts.php" class="mobile-nav-link">Drivers</a>
        <a href="../admin/passenger_accounts.php" class="mobile-nav-link">Passengers</a>
      <?php endif; ?>

      <?php if ($_SESSION['role'] === 'driver'): ?>
        <a href="../driver/driver_dashboard.php" class="mobile-nav-link">Driver Booking Dashboard</a>
        <a href="../driver/history.php" class="mobile-nav-link">History</a>
        <a href="../driver/profile.php" class="mobile-nav-link">Profile</a>
      <?php endif; ?>

      <?php if ($_SESSION['role'] === 'passenger'): ?>
        <a href="../passenger/dashboard.php" class="mobile-nav-link">Dashboard</a>
        <a href="../passenger/my-bookings.php" class="mobile-nav-link">My Bookings</a>
        <a href="../passenger/profile.php" class="mobile-nav-link">Profile</a>
      <?php endif; ?>

      <a href="../logout.php" class="mobile-user-pill">
        <span>Logout</span>
        <span class="opacity-70 font-normal text-[10px] uppercase">
          <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
        </span>
      </a>

    <?php else: ?>
      <a href="../index.php" class="mobile-nav-link">Home</a>
      <a href="../login.php" class="mobile-nav-link">Login</a>
      <a href="../register.php" class="mobile-register-btn">Register</a>
    <?php endif; ?>
  </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    </main>

<script>
  // Mobile menu toggle functionality
  const mobileMenuButton = document.getElementById('mobileMenuButton');
  const mobileMenu = document.getElementById('mobileMenu');

  if (mobileMenuButton && mobileMenu) {
    mobileMenuButton.addEventListener('click', function() {
      mobileMenu.classList.toggle('open');
      
      // Optional: Change icon when menu is open
      const svg = mobileMenuButton.querySelector('svg');
      if (mobileMenu.classList.contains('open')) {
        svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
      } else {
        svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />';
      }
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
      if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
        mobileMenu.classList.remove('open');
        // Reset icon
        const svg = mobileMenuButton.querySelector('svg');
        svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />';
      }
    });
  }
</script>

</body>
</html>