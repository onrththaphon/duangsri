<?php
session_start(); // Uncommented session_start()     // เชื่อมต่อฐานข้อมูล
include 'header.php';      // ส่วนหัวของหน้า
include 'navbar.php';      // แถบเมนูด้านบน
include 'sidebar_menu.php';
// manage_users.php

// --------------------------------------------------------
// ส่วน PHP สำหรับการจัดการข้อมูล (Database Operations)
// --------------------------------------------------------

// ตรวจสอบให้แน่ใจว่าคุณใส่ชื่อไฟล์เชื่อมต่อของคุณให้ถูกต้อง
// ในที่นี้คือ 'conn.php' ตามที่คุณแจ้งมา
include 'conn.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

// --- จัดการการ Submit ฟอร์ม เพิ่ม/แก้ไข พนักงาน (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ดึงข้อมูลจาก $_POST
    $id = isset($_POST['employeeId']) && $_POST['employeeId'] !== '' ? (int)$_POST['employeeId'] : null;
    $fullName = $condb->real_escape_string($_POST['employeeName']); // ใช้ $condb
    $phone = $condb->real_escape_string($_POST['employeePhone']);   // ใช้ $condb
    $status = $condb->real_escape_string(($_POST['employeeStatus'] === 'available') ? 'active' : 'inactive'); // แปลงสถานะ JS -> DB
    $selectedPositions = isset($_POST['positions']) ? (array)$_POST['positions'] : [];
    $positions_db_format = $condb->real_escape_string(implode(',', $selectedPositions)); // แปลง Array -> String, ใช้ $condb

    // แยกชื่อ-นามสกุล สำหรับคอลัมน์ fname และ Sname ใน DB
    $nameParts = explode(' ', $fullName, 2);
    $fname = $nameParts[0];
    $Sname = isset($nameParts[1]) ? $nameParts[1] : '';

    $success_message = "";
    $error_message = "";

    if ($id) {
        // อัปเดตข้อมูลพนักงานที่มีอยู่
        // (ไม่ได้อัปเดต Username, Password, type_user_id, email ผ่านหน้านี้)
        $sql = "ภาพรวม user SET fname=?, Sname=?, phone=?, status=?, position=? WHERE User_id=?";
        $stmt = $condb->prepare($sql); // ใช้ $condb
        $stmt->bind_param("sssssi", $fname, $Sname, $phone, $status, $positions_db_format, $id);
        if ($stmt->execute()) {
            $success_message = "แก้ไขข้อมูลพนักงานสำเร็จ!";
        } else {
            $error_message = "Error updating record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // เพิ่มพนักงานใหม่
        // กำหนดค่าเริ่มต้นสำหรับฟิลด์ที่ไม่ได้มาจาก Frontend ใน manage_users.php
        // (Username, Password, type_user_id)
        $default_type_user_id = 4; // ตัวอย่าง: กำหนดให้เป็น 'พนักงานเสิร์ฟ (Waiter)'
        $default_username = strtolower(str_replace(' ', '', $fname));
        $default_password = '0000'; // **คำเตือน: รหัสผ่านนี้ไม่ปลอดภัย ควรเข้ารหัสจริงในการใช้งานจริง**

        $sql = "INSERT INTO user (type_user_id, Username, Password, fname, Sname, phone, status, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $condb->prepare($sql); // ใช้ $condb
        $stmt->bind_param("isssssss", $default_type_user_id, $default_username, $default_password, $fname, $Sname, $phone, $status, $positions_db_format);

        if ($stmt->execute()) {
            $success_message = "เพิ่มพนักงานใหม่สำเร็จ!";
        } else {
            $error_message = "Error inserting record: " . $stmt->error;
        }
        $stmt->close();
    }

    // แสดงข้อความแจ้งเตือน (alert) ด้วย JavaScript ก่อน redirect
    if ($success_message) {
        echo "<script>alert('" . $success_message . "');</script>";
    } elseif ($error_message) {
        echo "<script>alert('" . $error_message . "');</script>";
    }

    // Redirect กลับไปที่หน้าเดิมเพื่อรีเฟรชข้อมูลและป้องกัน Form Resubmission
    echo "<script>window.location.href = 'manage_users.php';</script>";
    exit(); // หยุดการทำงานของ script หลังจาก redirect
}

// --- จัดการการลบพนักงาน (GET Request โดยใช้ URL parameters) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    $sql = "DELETE FROM user WHERE User_id=?";
    $stmt = $condb->prepare($sql); // ใช้ $condb
    $stmt->bind_param("i", $id_to_delete);

    $success_message = "";
    $error_message = "";

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success_message = "ลบพนักงานสำเร็จ!";
        } else {
            $error_message = "ไม่พบพนักงานที่ต้องการลบ (ID: " . $id_to_delete . ")";
        }
    } else {
        $error_message = "Error deleting record: " . $stmt->error;
    }
    $stmt->close();

    // แสดงข้อความแจ้งเตือน (alert) ด้วย JavaScript ก่อน redirect
    if ($success_message) {
        echo "<script>alert('" . $success_message . "');</script>";
    } elseif ($error_message) {
        echo "<script>alert('" . $error_message . "');</script>";
    }

    // Redirect กลับไปที่หน้าเดิม
    echo "<script>window.location.href = 'manage_users.php';</script>";
    exit(); // หยุดการทำงานของ script หลังจาก redirect
}

// --- ดึงข้อมูลพนักงานทั้งหมดสำหรับ JavaScript Initial Render ---
$employees_data = []; 
$sql_employees = "SELECT User_id, fname, Sname, phone, status, province, district, subdistrict, position FROM user";
$result_employees = $condb->query($sql_employees); // ใช้ $condb
if ($result_employees->num_rows > 0) {
    while($row = $result_employees->fetch_assoc()) {
        $employee = [
            'id' => (string)$row['User_id'], // แปลง ID เป็น string ให้ตรงกับ JS
            'name' => $row['fname'] . ' ' . $row['Sname'], // รวมชื่อ-นามสกุล
            'phone' => $row['phone'],
            'status' => ($row['status'] === 'active') ? 'available' : 'unavailable', // แปลงสถานะ DB -> JS
            'province'=> $row['province'],
            'district'=> $row['district'],
            'subdistrict'=> $row['subdistrict'],
            'positions' => $row['position'] ? explode(',', $row['position']) : [], // แปลง string ของตำแหน่งให้เป็น array
        ];
        $employees_data[] = $employee;
    }
}
$json_employees_data = json_encode($employees_data);

// --- ดึงข้อมูลตำแหน่ง (Positions) ทั้งหมดสำหรับ JavaScript Initial Render ---
$all_positions_data = [];
$sql_positions = "SELECT type_user_id, type_name FROM type_user"; // ดึงจากตาราง type_user
$result_positions = $condb->query($sql_positions); // ใช้ $condb
if ($result_positions->num_rows > 0) {
    while($row = $result_positions->fetch_assoc()) {
        // สร้าง 'value' ที่เป็น slug จาก 'type_name' เพื่อใช้ใน JS
        $value_slug = strtolower(str_replace([' ', '(', ')', '-'], ['_', '', '', '_'], $row['type_name']));
        $all_positions_data[] = [
            'value' => $value_slug,
            'label' => $row['type_name']
        ];
    }
}
$json_all_positions_data = json_encode($all_positions_data);

$condb->close(); // ปิดการเชื่อมต่อฐานข้อมูล โดยใช้ $condb
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>ระบบจัดการหลังบ้าน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="plugins/summernote/summernote-bs4.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;700&display=swap" rel="stylesheet">
    <link href="path/to/bootstrap.min.css" rel="stylesheet">
    <link href="path/to/your-custom-styles.css" rel="stylesheet">
</head>
<style>
    body {
        font-family: 'Kanit', sans-serif;
        font-style: normal;
        font-weight: 400;
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

    /* Custom styles for employee management system */
    .search-filter-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .search-input-container {
        position: relative;
        flex-grow: 1;
        margin-right: 15px;
    }

    .search-input-container i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
    }

    #searchInput {
        width: 100%;
        padding: 10px 10px 10px 35px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }

    .filter-select select {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-left: 10px;
        font-size: 1rem;
        background-color: #f9f9f9;
        cursor: pointer;
    }

    .add-employee-btn {
        background-color: #28a745;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
        display: flex;
        align-items: center;
        margin-left: 15px;
    }

    .add-employee-btn i {
        margin-right: 8px;
    }

    .add-employee-btn:hover {
        background-color: #218838;
    }

    .employee-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .employee-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        position: relative;
        transition: transform 0.2s ease-in-out;
        padding: 20px;
    }

    .employee-card:hover {
        transform: translateY(-5px);
    }

    .status-tag {
        position: absolute;
        top: 5px;
        right: 5px;
        padding: 5px 8px;
        border-radius: 5px;
        font-size: 0.8rem;
        font-weight: bold;
        color: white;
    }

    .status-tag.available {
        background-color: #28a745;
    }

    .status-tag.unavailable {
        background-color: #dc3545;
    }

    .employee-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .employee-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: bold;
        color: white;
        margin-right: 15px;
        flex-shrink: 0;
    }

    /* Avatar colors */
    .employee-avatar.orange {
        background-color: #fd7e14;
    }

    .employee-avatar.pink {
        background-color: #e83e8c;
    }

    .employee-avatar.green {
        background-color: #28a745;
    }

    .employee-avatar.purple {
        background-color: #6f42c1;
    }

    .employee-avatar.blue {
        background-color: #007bff;
    }


    .employee-info h3 {
        margin: 0 0 5px;
        font-size: 1.4rem;
        color: #333;
    }

    .employee-info p {
        margin: 0;
        color: #777;
        font-size: 0.9rem;
    }

    .employee-details {
        margin-bottom: 15px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .detail-item i {
        color: #555;
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    .permissions-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 5px;
    }

    .permission-tag {
        background-color: #e9ecef;
        color: #495057;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .employee-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid #eee;
        padding-top: 15px;
        margin-top: 15px;
    }

    .action-button {
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        transition: background-color 0.2s ease;
    }

    .action-button i {
        margin-right: 5px;
    }

    .action-button.edit {
        background-color: #b67008ff;
        color: white;
    }

    .action-button.edit:hover {
        background-color: #ff9148ff;
    }

    .action-button.delete {
        background-color: #dc3545;
        color: white;
    }

    .action-button.delete:hover {
        background-color: #c82333;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        /* Higher than AdminLTE's default */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 30px;
        border-radius: 10px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        position: relative;
    }

    .close-button {
        color: #aaa;
        position: absolute;
        top: 15px;
        right: 25px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-button:hover,
    .close-button:focus {
        color: #000;
        text-decoration: none;
    }

    .modal h2 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
        font-size: 1.8rem;
        text-align: center;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"],
    .form-group select {
        width: calc(100% - 20px);
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
        box-sizing: border-box;
        /* Include padding in width */
    }

    .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 5px;
        background-color: #f9f9f9;
    }

    .checkbox-group label {
        margin-bottom: 0;
        display: flex;
        align-items: center;
        cursor: pointer;
        font-weight: normal;
        color: #333;
    }

    .checkbox-group input[type="checkbox"] {
        margin-right: 5px;
        width: auto;
    }

    .modal-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px;
    }

    .modal-buttons .save-btn,
    .modal-buttons .cancel-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.2s ease;
    }

    .modal-buttons .save-btn {
        background-color: #007bff;
        color: white;
    }

    .modal-buttons .save-btn:hover {
        background-color: #0056b3;
    }

    .modal-buttons .cancel-btn {
        background-color: #6c757d;
        color: white;
    }

    .modal-buttons .cancel-btn:hover {
        background-color: #5a6268;
    }
</style>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
            <section class="content">
                <div class="container-fluid">
                    <div class="search-filter-section">
                        <div class="search-input-container">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="ค้นหาพนักงาน...">
                        </div>
                        <div class="filter-select">
                            <select id="positionFilter">
                                <option value="">ทุกตำแหน่ง</option>
                            </select>
                        </div>
                        <div class="filter-select">
                            <select id="statusFilter">
                                <option value="">ทุกสถานะ</option>
                                <option value="available">กำลังใช้งาน</option>
                                <option value="unavailable">ไม่ใช้งาน</option>
                            </select>
                        </div>
                        <button class="add-employee-btn" id="addEmployeeBtn">
                            <i class="fas fa-plus"></i>
                            พนักงาน
                        </button>
                    </div>

                    <div class="employee-cards-container" id="employeeCardsContainer">
                        </div>

                </div>
            </section>
        </div>

        <div id="employeeModal" class="modal">
            <div class="modal-content">
                <span class="close-button">&times;</span>
                <h2 id="modalTitle">เพิ่มพนักงานใหม่</h2>
                <form id="employeeForm" action="manage_users.php" method="POST">
                    <input type="hidden" id="employeeId" name="employeeId">
                    <div class="form-group">
                        <label for="employeeName">ชื่อ-นามสกุล:</label>
                        <input type="text" id="employeeName" name="employeeName" required>
                    </div>
                        <div class="form-group">
                        <label for="employeeName">ชื่อ-นามสกุล:</label>
                        <input type="text" id="employeeName" name="employeeName" required>
                    </div>
                    <div class="form-group">
                        <label for="employeePhone">เบอร์โทรศัพท์:</label>
                        <input type="tel" id="employeePhone" name="employeePhone">
                    </div>
                    <div class="form-group">
                        <label for="employeeStatus">สถานะ:</label>
                        <select id="employeeStatus" name="employeeStatus" required>
                            <option value="available">กำลังใช้งาน</option>
                            <option value="unavailable">ไม่ใช้งาน</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ตำแหน่ง:</label>
                        <div id="employeePositions" class="checkbox-group">
                            </div>
                    </div>
                    <div class="modal-buttons">
                        <button type="submit" class="save-btn">บันทึก</button>
                        <button type="button" class="cancel-btn" id="cancelModalBtn">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/adminlte.min.js"></script>
    <script>
        $(document).ready(function () {
            // Select the specific dropdown for the user profile
            $('.navbar-nav .nav-item.dropdown').hover(function () {
                $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeIn(200);
                $(this).addClass('show');
                $(this).find('[data-toggle="dropdown"]').attr('aria-expanded', 'true');
            }, function () {
                $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeOut(200);
                $(this).removeClass('show');
                $(this).find('[data-toggle="dropdown"]').attr('aria-expanded', 'false');
            });

            $('.navbar-nav .nav-item.dropdown .nav-link[data-toggle="dropdown"]').click(function (e) {
                if (!$(this).parent().hasClass('show')) {
                    e.preventDefault();
                    $(this).parent().toggleClass('show');
                    $(this).parent().find('.dropdown-menu').stop(true, true).fadeToggle(200);
                    $(this).attr('aria-expanded', $(this).parent().hasClass('show') ? 'true' : 'false');
                } else {
                    $(this).parent().removeClass('show');
                    $(this).parent().find('.dropdown-menu').stop(true, true).fadeOut(200);
                    $(this).attr('aria-expanded', 'false');
                }
            });

            $(document).on('click', function (e) {
                if (!$('.navbar-nav .nav-item.dropdown').is(e.target) && $('.navbar-nav .nav-item.dropdown').has(e.target).length === 0 && $('.navbar-nav .nav-item.dropdown.show').length > 0) {
                    $('.navbar-nav .nav-item.dropdown.show .dropdown-menu').stop(true, true).fadeOut(200);
                    $('.navbar-nav .nav-item.dropdown.show').removeClass('show');
                    $('.navbar-nav .nav-item.dropdown.show [data-toggle="dropdown"]').attr('aria-expanded', 'false');
                }
            });
        });

        // Initialize employees and allPositions from PHP
        // ข้อมูลพนักงานเริ่มต้นจะถูกดึงจากฐานข้อมูลผ่าน PHP และแปลงเป็น JSON ตรงนี้
        let employees = <?php echo $json_employees_data; ?>;
        // ข้อมูลตำแหน่งงานเริ่มต้นจะถูกดึงจากฐานข้อมูลผ่าน PHP และแปลงเป็น JSON ตรงนี้
        let allPositions = <?php echo $json_all_positions_data; ?>;

        // DOM Elements
        const employeeCardsContainer = document.getElementById('employeeCardsContainer');
        const addEmployeeBtn = document.getElementById('addEmployeeBtn');
        const employeeModal = document.getElementById('employeeModal');
        const closeButton = employeeModal.querySelector('.close-button');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const employeeForm = document.getElementById('employeeForm');
        const modalTitle = document.getElementById('modalTitle');
        const employeeIdInput = document.getElementById('employeeId');
        const employeeNameInput = document.getElementById('employeeName');
        const employeePhoneInput = document.getElementById('employeePhone');
        const employeeStatusInput = document.getElementById('employeeStatus');
        const employeePositionsContainer = document.getElementById('employeePositions');
        const searchInput = document.getElementById('searchInput');
        const positionFilter = document.getElementById('positionFilter');
        const statusFilter = document.getElementById('statusFilter');

        // Function to get a consistent avatar color based on the first letter of the name
        function getAvatarColor(name) {
            const firstChar = name.charAt(0).toLowerCase();
            const colors = ['orange', 'pink', 'green', 'purple', 'blue'];
            let hash = 0;
            for (let i = 0; i < firstChar.length; i++) {
                hash = firstChar.charCodeAt(i) + ((hash << 5) - hash);
            }
            const colorIndex = Math.abs(hash) % colors.length;
            return colors[colorIndex];
        }

        // Function to render a single employee card
        function renderEmployeeCard(employee) {
            const card = document.createElement('div');
            card.className = `employee-card ${employee.status}`;
            card.dataset.employeeId = employee.id;

            const initials = employee.name.charAt(0);
            const statusLabel = employee.status === 'available' ? 'กำลังใช้งาน' : 'ไม่ใช้งาน';
            const statusClass = employee.status === 'available' ? 'available' : 'unavailable';

            const positionTagsHtml = Array.isArray(employee.positions) ? employee.positions.map(posValue => {
                const position = allPositions.find(p => p.value === posValue);
                return position ? `<span class="permission-tag">${position.label}</span>` : '';
            }).join('') : '';

            card.innerHTML = `
                <span class="status-tag ${statusClass}">${statusLabel}</span>
                <div class="employee-header">
                    <div class="employee-avatar ${getAvatarColor(employee.name)}">${initials}</div>
                    <div class="employee-info">
                        <h3>${employee.name}</h3>
                        <h7>จังหวัด. ${employee.province}</h7>
                        <h7>อำเภอ. ${employee.district}</h7>
                        <h7>ตำบล. ${employee.subdistrict}</h7>
                        <p>${employee.phone || 'N/A'}</p>
                    </div>
                </div>
                <div class="employee-details">
                    <div class="detail-item">
                        <i class="fas fa-user"></i>
                        <span>ตำแหน่ง</span>
                        <div class="permissions-tags">
                            ${positionTagsHtml}
                        </div>
                    </div>
                </div>
                <div class="employee-actions">
                    <button class="action-button edit" onclick="editEmployee('${employee.id}')">
                        <i class="fas fa-edit"></i>
                        แก้ไข
                    </button>
                    <button class="action-button delete" onclick="deleteEmployee('${employee.id}')">
                        <i class="fas fa-trash"></i>
                        ลบ
                    </button>
                </div>
            `;
            return card;
        }

        // Function to render all employees based on current filters
        function renderFilteredEmployees() {
            employeeCardsContainer.innerHTML = ''; // Clear existing cards
            const searchTerm = searchInput.value.toLowerCase();
            const selectedPosition = positionFilter.value;
            const selectedStatus = statusFilter.value;

            const filteredEmployees = employees.filter(employee => {
                const matchesSearch = employee.name.toLowerCase().includes(searchTerm) ||
                    (employee.email && employee.email.toLowerCase().includes(searchTerm)) ||
                    (employee.phone && employee.phone.toLowerCase().includes(searchTerm));
                const matchesPosition = selectedPosition === '' || (Array.isArray(employee.positions) && employee.positions.includes(selectedPosition));
                const matchesStatus = selectedStatus === '' || employee.status === selectedStatus;
                return matchesSearch && matchesPosition && matchesStatus;
            });

            filteredEmployees.forEach(employee => {
                employeeCardsContainer.appendChild(renderEmployeeCard(employee));
            });
        }

        // Function to populate position filter options (ใช้ allPositions ที่ได้จาก PHP)
        function populatePositionFilter() {
            positionFilter.innerHTML = '<option value="">ทุกตำแหน่ง</option>';
            allPositions.forEach(pos => {
                const option = document.createElement('option');
                option.value = pos.value;
                option.textContent = pos.label;
                positionFilter.appendChild(option);
            });
        }

        // Function to populate position checkboxes in the modal (ใช้ allPositions ที่ได้จาก PHP)
        function populatePositionCheckboxes(selectedPositions = []) {
            employeePositionsContainer.innerHTML = '';
            allPositions.forEach(pos => {
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'positions[]'; // สำคัญ: ใช้ [] เพื่อให้ PHP รับเป็น Array
                checkbox.value = pos.value;
                if (selectedPositions.includes(pos.value)) {
                    checkbox.checked = true;
                }
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(pos.label));
                employeePositionsContainer.appendChild(label);
            });
        }

        // --- Modal Control Functions ---
        function openModal(isEdit = false, employee = null) {
            employeeModal.style.display = 'flex';
            if (isEdit && employee) {
                modalTitle.textContent = 'แก้ไขข้อมูลพนักงาน';
                employeeIdInput.value = employee.id;
                employeeNameInput.value = employee.name;
                employeeEmailInput.value = employee.email || '';
                employeePhoneInput.value = employee.phone || '';
                employeeStatusInput.value = employee.status;
                populatePositionCheckboxes(employee.positions);
            } else {
                modalTitle.textContent = 'เพิ่มพนักงานใหม่';
                employeeIdInput.value = '';
                employeeForm.reset(); // Clear all form fields
                populatePositionCheckboxes([]); // No positions selected initially
            }
        }

        function closeModal() {
            employeeModal.style.display = 'none';
        }

        // --- Event Listeners ---
        addEmployeeBtn.addEventListener('click', () => openModal(false));
        closeButton.addEventListener('click', closeModal);
        cancelModalBtn.addEventListener('click', closeModal);
        window.addEventListener('click', (event) => {
            if (event.target == employeeModal) {
                closeModal();
            }
        });

        // ฟอร์มจะถูก Submit โดยตรงไปยัง PHP แล้ว PHP จะทำการ Redirect
        employeeForm.addEventListener('submit', (event) => {
            const name = employeeNameInput.value.trim();
            if (!name) {
                alert('กรุณากรอกชื่อ-นามสกุลพนักงาน');
                event.preventDefault(); // ป้องกันการ Submit ถ้า validation ไม่ผ่าน
                return false;
            }
            // หากผ่าน validation, ฟอร์มจะถูก Submit ตามปกติไปยัง manage_users.php
        });


        // Global functions for card actions (modified to trigger PHP delete via URL)
        window.editEmployee = (id) => {
            const employee = employees.find(emp => emp.id == id);
            if (employee) {
                openModal(true, employee);
            }
        };

        window.deleteEmployee = (id) => {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบพนักงานคนนี้?')) {
                // เปลี่ยนเส้นทาง URL เพื่อส่งคำสั่งลบไปยัง PHP โดยตรง
                window.location.href = `manage_users.php?action=delete&id=${id}`;
            }
        };

        // Search and Filter event listeners
        searchInput.addEventListener('input', renderFilteredEmployees);
        positionFilter.addEventListener('change', renderFilteredEmployees);
        statusFilter.addEventListener('change', renderFilteredEmployees);

        // Initial render when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            populatePositionFilter();
            renderFilteredEmployees(); // แสดงผลข้อมูลที่โหลดมาตั้งแต่แรก
        });
    </script>
</body>

</html>