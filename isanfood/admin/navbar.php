<?php
// It's recommended that session_start() is called in header.php or an initial config file.
// If it's not, and you need $_SESSION here, uncomment:
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
?>
<style>
/* Applying styles directly to the main header navigation */
.main-header.navbar { /* Target the main nav element with these classes */
    background: white; /* Set background to white */
    padding-left: 20px; /* Add left padding */
    padding-right: 20px; /* Add right padding */
    height: 60px; /* Set fixed height for the navbar */
    /* Ensure no unwanted shadows or borders if AdminLTE adds them by default */
    box-shadow: none !important; /* Remove default shadow */
    border-bottom: none !important; /* Remove default bottom border */
}


/* CSS สำหรับวงกลมโปรไฟล์ผู้ใช้ใน Navbar */
.user-avatar-circle {
    width: 32px; /* ปรับขนาดตามต้องการ */
    height: 32px; /* ต้องตรงกับความกว้างเพื่อให้เป็นวงกลมสมบูรณ์ */
    border-radius: 50%; /* ทำให้เป็นวงกลม */
    background-color: rgba(58, 61, 255, 1); /* สีน้ำตาลแดงเข้ม (Maroon) */
    display: flex;
    align-items: center;
    justify-content: center;
    color: white; /* สีไอคอนสีขาว */
    font-size: 16px; /* ปรับขนาดไอคอน */
}

.user-name-text {
    font-weight: 500; /* น้ำหนักฟอนต์ปานกลางสำหรับชื่อผู้ใช้ */
    color: #000000ff; /* สีข้อความดำเริ่มต้นของ AdminLTE */
}

/* ตรวจสอบให้แน่ใจว่ารายการใน Navbar จัดแนวตั้งตรงกัน */
.main-header .navbar-nav .nav-item .nav-link {
    height: 100%; /* ทำให้ลิงก์เติมเต็มพื้นที่แนวตั้ง */
    display: flex;
    align-items: center; /* จัดเนื้อหาตรงกลางในแนวตั้ง */
    padding-top: 0.5rem; /* การเว้นระยะขอบบนเริ่มต้นของ AdminLTE */
    padding-bottom: 0.5rem; /* การเว้นระยะขอบล่างเริ่มต้นของ AdminLTE */
}

/* ปรับตำแหน่งลูกศร dropdown หากจำเป็น */
.user-panel-mini .fa-caret-down {
    margin-left: 5px; /* ปรับระยะห่างระหว่างชื่อและลูกศร */
    font-size: 0.8rem; /* ทำให้ลูกศรเล็กลงเล็กน้อย */
}

/* หากพื้นหลัง Navbar ไม่ใช่สีขาว คุณอาจต้องปรับสีของ user-name-text */
.main-header.navbar-white {
    background-color: #ffffff; /* กำหนดให้พื้นหลังเป็นสีขาวอย่างชัดเจน หากยังไม่ได้เป็น */
}

/* CSS to show dropdown on hover */
/* Targeting the parent .nav-item which has the dropdown class */
.navbar-nav .nav-item.dropdown:hover > .dropdown-menu {
    display: block;
    margin-top: 0; /* Remove any default top margin that might cause a gap */
}

/* Optional: To ensure the dropdown toggle doesn't interfere */
/* This might be needed if the default Bootstrap JS is still active */
.navbar-nav .nav-item.dropdown:hover > .nav-link[data-toggle="dropdown"] {
    /* Prevent default click behavior, though jQuery will override it anyway */
    pointer-events: auto; /* Ensure it's clickable if needed, but hover takes precedence */
    cursor: pointer;
}


</style>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <div class="user-panel-mini d-inline-flex align-items-center">
                    <div class="user-avatar-circle">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="d-none d-sm-inline ml-2 user-name-text">
                        <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Guest'; ?>
                    </span>
                    <i class="fas fa-caret-down ml-1"></i>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <a href="#" class="dropdown-item">
                    <i class="fas fa-user mr-2"></i> โปรไฟล์ของฉัน
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt mr-2"></i> ออกจากระบบ
                </a>
            </div>
        </li>
    </ul>
</nav>

<script>
$(document).ready(function() {
    // Select the specific dropdown for the user profile
    $('.navbar-nav .nav-item.dropdown').hover(function() {
        // On mouse enter, add the 'show' class to the dropdown-menu
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeIn(200);
        $(this).addClass('show'); // Also add 'show' to the parent nav-item
        $(this).find('[data-toggle="dropdown"]').attr('aria-expanded', 'true'); // Update ARIA attribute
    }, function() {
        // On mouse leave, remove the 'show' class from the dropdown-menu
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeOut(200);
        $(this).removeClass('show'); // Remove 'show' from the parent nav-item
        $(this).find('[data-toggle="dropdown"]').attr('aria-expanded', 'false'); // Update ARIA attribute
    });

    // Handle click event: if a user clicks, still allow the dropdown to function normally
    // This is useful for touch devices or if a user prefers clicking
    $('.navbar-nav .nav-item.dropdown .nav-link[data-toggle="dropdown"]').click(function(e) {
        // Prevent the default Bootstrap click behavior that might conflict
        // with our hover logic by immediately hiding the menu.
        // But still allow it to toggle if not already visible by hover.
        if (!$(this).parent().hasClass('show')) {
            e.preventDefault(); // Only prevent if it's not already "shown" by hover
            $(this).parent().toggleClass('show');
            $(this).parent().find('.dropdown-menu').stop(true, true).fadeToggle(200);
            $(this).attr('aria-expanded', $(this).parent().hasClass('show') ? 'true' : 'false');
        } else {
            // If already shown by hover, allow the click to hide it
            $(this).parent().removeClass('show');
            $(this).parent().find('.dropdown-menu').stop(true, true).fadeOut(200);
            $(this).attr('aria-expanded', 'false');
        }
    });

    // Ensure dropdown closes if another part of the document is clicked
    $(document).on('click', function (e) {
        if (!$('.navbar-nav .nav-item.dropdown').is(e.target) && $('.navbar-nav .nav-item.dropdown').has(e.target).length === 0 && $('.navbar-nav .nav-item.dropdown.show').length > 0) {
            $('.navbar-nav .nav-item.dropdown.show .dropdown-menu').stop(true, true).fadeOut(200);
            $('.navbar-nav .nav-item.dropdown.show').removeClass('show');
            $('.navbar-nav .nav-item.dropdown.show [data-toggle="dropdown"]').attr('aria-expanded', 'false');
        }
    });
});
</script>