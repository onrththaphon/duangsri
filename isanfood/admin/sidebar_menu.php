<?php
// header.php (or config.php / init.php)
// It's good practice to keep the session_status() check here
// to prevent the "session already active" notice if it's included multiple times.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage = basename($_SERVER['PHP_SELF']);

// The $loggedInUserTypeName variable is no longer strictly needed for menu display,
// but you might still use it for other parts of your page (e.g., displaying user's name).
$loggedInUserTypeName = $_SESSION['type_name'] ?? null;
?>

<style>
/* Sidebar Background Color */
.main-sidebar.sidebar-light-primary {
    background: rgba(128, 72, 0, 1) !important;
    /* Deep maroon */
    color: white;
    /* Default text color for contrast */
}

/* Brand Link / Logo Section Styling */
.brand-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    /* Align content to the left */
    padding: 20px 15px;
    /* Adjust padding as needed for spacing */
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    /* Thin white line below logo */
    color: white !important;
    /* Ensure text is white */
    line-height: 1.2;
    /* Adjust line height for the text */
}

.brand-link .brand-image-sut {
    max-height: 45px;
    /* Adjust logo height */
    width: auto;
    margin-right: 10px;
    /* Space between logo and text */
    object-fit: contain;
    /* Ensure the image scales correctly */
}

.brand-text-sut {
    font-size: 1.1rem;
    /* Adjust font size for main text */
    font-weight: 500;
    /* Medium weight */
    color: white;
    /* Ensure text is white */
}

.brand-text-sut small {
    display: block;
    /* Make the small text go to a new line */
    font-size: 0.8rem;
    /* Adjust font size for "INSTITUTE OF ENGINEERING" */
    font-weight: 300;
    /* Lighter weight */
    color: rgba(255, 255, 255, 0.7);
    /* Slightly lighter for secondary text */
}

/* Remove User Panel Space */
.sidebar .user-panel {
    display: none !important;
    /* Hide the entire user panel */
}

/* General Nav Link Styling */
.main-sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    /* Lighter white for default links */
    font-size: 1rem;
    /* Standard font size */
    padding: 12px 15px;
    /* Adjust padding for menu items */
    transition: background-color 0.2s ease, color 0.2s ease;
}

.main-sidebar .nav-icon {
    color: rgba(255, 255, 255, 1) !important;
    /* Icon color matches link color */
    font-size: 1rem;
    /* Match icon size to text size for consistency */
    margin-right: 10px;
    /* Space between icon and text */
    width: 24px;
    /* Fix width for icons to align text */
    text-align: center;
}

/* Hover State */
.main-sidebar .nav-link:hover {
    background-color: rgba(0, 0, 0, 0.1);
    /* Slightly darker shade on hover */
    color: white !important;
    /* Keep text white on hover */
}

.main-sidebar .nav-link:hover .nav-icon {
    color: white !important;
}

/* Active State (Solid dark background like in image) */
.nav-link.active {
    background: rgba(54, 32, 2, 1) !important;
    /* A darker maroon for the active state */
    color: white !important;
}

.nav-link.active .nav-icon {
    color: white !important;
}

/* Remove any unwanted borders/lines from previous configs */
/* This covers the general removal for elements that might create lines */
.user-panel,
.main-sidebar .nav-pills.nav-sidebar,
.sidebar-toggler-item,
hr.sidebar-divider {
    /* Add hr.sidebar-divider if it exists in your template */
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
}

.sidebar>nav.mt-2 {
    margin-top: 0 !important;
    /* Remove top margin from the nav container */
}

/* Ensure treeview icons are not affected by color changes for main items */
.nav-sidebar .nav-item>.nav-link i.right {
    color: inherit;
    /* Inherit color from parent link or other specific rule */
}

.nav-sidebar .nav-item>.nav-link.active i.right {
    color: white !important;
    /* Ensure angle-left is visible when active */
}


/* Specific CSS for the thin line below the logo/header */
.brand-link::after {
    content: "";
    display: block;
    width: calc(100% - 30px);
    /* Adjust width to match image (full width - padding) */
    height: 1px;
    background-color: rgba(255, 255, 255, 0.2);
    /* Thin white line */
    position: absolute;
    bottom: 0;
    left: 15px;
    /* Match left padding */
}

/* Ensure the brand-link is positioned relative for the ::after pseudo-element */
.brand-link {
    position: relative;
}
</style>

<aside class="main-sidebar sidebar-light-primary elevation-4">

   <a href="main.php" class="brand-link">
    <img src="dist/img/logoback3.jpg" alt="Esankaneng logo" class="brand-image-sut img-circle">
    <span class="brand-text-sut font-weight-light">ร้านอีสานกันเอง<small>ระบบการจัดการหลังบ้าน</small>
</span>
</a>

    <br>
    <div class="sidebar">

        <div class="user-panel mt-3 pb-3 mb-3 d-flex justify-content-center">
            <div class="user-show-pc text-center">
            </div>
        </div>

        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="main.php" class="nav-link <?= $activePage == 'main.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>หน้าหลัก</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_users.php" class="nav-link <?= $activePage == 'manage_users.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users-cog"></i> <p>จัดการผู้ใช้งาน</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_menu.php" class="nav-link <?= $activePage == 'manage_menu.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-utensils"></i> <p>จัดการเมนูอาหาร</p>
                    </a>
                </li>
                  <li class="nav-item">
                    <a href="report.php" class="nav-link <?= $activePage == 'report.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-utensils"></i> <p>ออกรายงาน</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>