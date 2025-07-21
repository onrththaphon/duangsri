<?php
session_start(); // Uncommented session_start()
include 'conn.php';       // เชื่อมต่อฐานข้อมูล
include 'header.php';      // ส่วนหัวของหน้า
include 'navbar.php';      // แถบเมนูด้านบน
include 'sidebar_menu.php'; // เมนูด้านข้าง
?>

<style>
/* CSS สำหรับคอนเทนเนอร์เนื้อหาหลัก */
.content-box {
    padding: 24px;
    /* height: 100%; <- Be careful with height: 100%; here. It often needs a defined parent height.
                       For typical content areas, removing it is often better unless you have a specific
                       reason for fixed height and manage parent heights.
                       If you want it to fill available space, ensure .content-wrapper and html, body have height: 100%;
                       For now, I'll comment it out, but you can uncomment if you manage parent heights. */
    background: white;
    border-radius: 20px;
    box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 10px;
    margin-bottom: 20px;
    /* Add some space below if it's a floating box */
}

/* Optional: Adjust .content-wrapper padding to make space for the floating content-box */
.content-wrapper {
    padding-top: 20px;
    /* Add top padding to create space below navbar */
    padding-left: 20px;
    /* Add left padding */
    padding-right: 20px;
    /* Add right padding */
    padding-bottom: 20px;
    /* Add bottom padding */
}

/* To make height: 100% work reliably, ensure html, body, and .content-wrapper have 100% height */
html,
body {
    height: 100%;
}

.wrapper {
    min-height: 100%;
}

.content-wrapper {
    min-height: calc(100vh - (var(--main-header-height, 60px) + var(--main-footer-height, 57px)));
    /* Adjust dynamically */
    display: flex;
    /* Use flexbox to manage inner content height */
    flex-direction: column;
}

.content {
    flex-grow: 1;
    /* Allow the content section to grow and take available space */
}

/* For your card and callout, if they are meant to be *inside* the content-box */
.content-box .callout,
.content-box .card {
    /* You might want to remove any margin-bottom from these if content-box has its own margin */
    margin-bottom: 15px;
    /* Example: slightly less margin if inside a contained box */
}
</style>

<div class="content-wrapper">
    <?php /*
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>หน้าแรก (ผู้ดูแลระบบ)</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="main.php">หน้าแรก</a></li>
                    <li class="breadcrumb-item active">ผู้ดูแลระบบ</li>
                </ol>
            </div>
        </div>
    </div>
</section>
*/ ?>

  <section class="content">
     <div class="container-fluid">
         <div class="content-box">

             <img src="dist/img/backG2.jpg" alt="คำอธิบายรูปภาพ"
                 style="max-width:100%; height:100%; border-radius: 10px;">
         </div>
     </div>
 </section>
</div>

<?php include 'footer.php'; ?>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar/main.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar/main.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>