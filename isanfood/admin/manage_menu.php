<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log');

include 'conn.php'; // Your database connection file

// Check if user is logged in and has admin role (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'
    ];
    header('Location: index.php'); // Redirect to login or home page
    exit();
}

// --- Handle Menu Item Deletion ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $menuIdToDelete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($menuIdToDelete === false || $menuIdToDelete <= 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'รหัสเมนูที่ต้องการลบไม่ถูกต้อง!'
        ];
        header('Location: manage_menu.php');
        exit();
    }

    $stmt_delete = $condb->prepare("DELETE FROM menu WHERE menu_id = ?");

    if ($stmt_delete === false) {
        error_log("Manage Menu (Delete): Error preparing delete statement: " . $condb->error);
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'ระบบไม่สามารถเตรียมการลบข้อมูลได้ กรุณาลองใหม่ภายหลัง.'
        ];
        header('Location: manage_menu.php');
        exit();
    }

    $stmt_delete->bind_param("i", $menuIdToDelete);

    if ($stmt_delete->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'ลบรายการอาหารสำเร็จ!'
        ];
    } else {
        error_log("Manage Menu (Delete): Error executing delete statement for MenuID " . $menuIdToDelete . ": " . $stmt_delete->error);
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'เกิดข้อผิดพลาดในการลบรายการอาหาร: ' . htmlspecialchars($stmt_delete->error)
        ];
    }
    $stmt_delete->close();
    header('Location: manage_menu.php');
    exit();
}

// --- Handle Menu Item Status Toggle (using AJAX for a smoother experience) ---
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status' && isset($_POST['menu_id'])) {
    header('Content-Type: application/json'); // Respond with JSON
    $menuId = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);
    $currentStatus = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);
    $newStatus = $currentStatus == 1 ? 0 : 1; // Toggle status

    if ($menuId === false || $menuId <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสเมนูไม่ถูกต้อง.']);
        exit();
    }

    $stmt_update = $condb->prepare("UPDATE menu SET status = ? WHERE menu_id = ?");
    if ($stmt_update === false) {
        error_log("Manage Menu (Toggle Status): Error preparing update statement: " . $condb->error);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดภายในระบบ.']);
        exit();
    }
    $stmt_update->bind_param("ii", $newStatus, $menuId);

    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => 'อัปเดตสถานะรายการอาหารสำเร็จ!']);
    } else {
        error_log("Manage Menu (Toggle Status): Error executing update statement for MenuID " . $menuId . ": " . $stmt_update->error);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะ.']);
    }
    $stmt_update->close();
    exit(); // Important to exit after AJAX response
}


// Fetch Menu Data
$sql = "SELECT m.menu_id, m.name, m.description, m.price, m.status, m.Image_url, tm.type_name_menu
        FROM menu m
        LEFT JOIN type_menu tm ON m.type_menu_id = tm.Type_menu_id
        ORDER BY m.menu_id ASC";

$result = $condb->query($sql);

$menuItems = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $menuItems[] = $row;
        }
    }
} else {
    error_log("Manage Menu (Fetch): Error fetching menu data: " . $condb->error);
    // Optionally set a session error message here as well
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'ไม่สามารถโหลดข้อมูลรายการอาหารได้: ' . htmlspecialchars($condb->error)
    ];
}

include 'header.php';
include 'navbar.php';
include 'sidebar_menu.php';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายการอาหาร - แอดมิน</title>
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* Global Font */
        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .navbar-nav,
        .content-wrapper,
        .main-sidebar,
        .btn,
        table {
            font-family: 'Kanit', sans-serif !important;
        }

        /* AdminLTE Layout Overrides */
        .content-wrapper {
            padding-top: 20px;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 20px;
            min-height: calc(100vh - (var(--main-header-height, 60px) + var(--main-footer-height, 57px)));
            display: flex;
            flex-direction: column;
        }

        .content {
            flex-grow: 1;
        }

        html,
        body {
            height: 100%;
        }

        .wrapper {
            min-height: 100%;
        }

        /* Ant Design-like Message Container (Top-Right Fixed) */
        .ant-message-top.css-qnu6hi {
            position: fixed;
            top: 65px;
            right: 24px;
            left: auto;
            transform: none;
            width: auto;
            max-width: 400px;
            min-width: 250px;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            display: none;
        }

        .ant-message-top.css-qnu6hi.show {
            opacity: 1;
            pointer-events: auto;
            display: block; /* Ensure it's block when shown */
        }

        /* Ant Design Message Styles */
        .ant-message-notice-wrapper {
            overflow: hidden;
            margin-bottom: 16px;
        }

        .ant-message-notice {
            padding: 8px 16px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
            display: block;
            pointer-events: auto;
            text-align: left;
        }

        .ant-message-custom-content {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ant-message-success .anticon-msg {
            color: #52c41a;
        }

        .ant-message-error .anticon-msg {
            color: #ff4d4f;
        }

        .anticon-msg svg {
            width: 1em;
            height: 1em;
            vertical-align: -0.125em;
        }

        /* Content Container for Table and Controls */
        .content-container {
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 10px;
        }

        /* Ant Design-like Row/Column Styling */
        .ant-row.css-ee1yud {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 20px;
        }

        .ant-col.ant-col-12.css-ee1yud {
            display: flex;
            align-items: center;
        }

        /* Ant Design Divider */
        .ant-divider.css-ee1yud.ant-divider-horizontal {
            border-color: rgb(204, 204, 204);
            margin-top: 0px;
            margin-bottom: 0px;
            border-top-width: 1px;
            border-top-style: solid;
        }

        /* Table Styling */
        .ant-table-wrapper.css-ee1yud {
            margin-top: 20px;
        }

        .ant-table.css-ee1yud {
            font-size: 14px;
            border-collapse: collapse;
            width: 100%;
        }

        .ant-table-thead>tr>th {
            background-color: #fafafa;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 16px;
            text-align: center;
            font-weight: 500;
            color: rgba(0, 0, 0, 0.85);
            white-space: nowrap;
        }

        .ant-table-tbody>tr>td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: rgba(0, 0, 0, 0.85);
            text-align: center;
            vertical-align: middle; /* Center content vertically */
        }

        .ant-table-tbody>tr:last-child>td {
            border-bottom: none;
        }

        .ant-table-placeholder {
            text-align: center;
            color: rgba(0, 0, 0, 0.25);
            padding: 16px;
        }

        /* Button Styles (Modern/Ant Design-like) */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
            text-decoration: none;
        }

        .btn-primary-modern {
            color: #fff;
            background-color: #1890ff;
            border-color: #1890ff;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.045);
        }

        .btn-primary-modern:hover {
            color: #fff;
            background-color: #40a9ff;
            border-color: #40a9ff;
        }

        .btn-success-modern {
            color: #fff;
            background-color: #52c41a;
            border-color: #52c41a;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.045);
        }

        .btn-success-modern:hover {
            color: #fff;
            background-color: #73d13d;
            border-color: #73d13d;
        }

        .btn-default-modern {
            color: rgba(0, 0, 0, 0.85);
            background-color: #fff;
            border-color: #d9d9d9;
            box-shadow: 0 2px 0 rgba(0, 0, 0, 0.015);
        }

        .btn-default-modern:hover {
            color: #40a9ff;
            background-color: #fff;
            border-color: #40a9ff;
        }

        /* Ant Design Input Style */
        .ant-input {
            box-sizing: border-box;
            margin: 0;
            font-variant: tabular-nums;
            list-style: none;
            font-feature-settings: "tnum";
            position: relative;
            display: inline-block;
            width: 100%;
            min-width: 0;
            padding: 4px 11px;
            color: rgba(0, 0, 0, 0.85);
            font-size: 14px;
            line-height: 1.5715;
            background-color: #fff;
            background-image: none;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            transition: all 0.3s;
            height: 32px;
        }

        .ant-input:focus,
        .ant-input:hover {
            border-color: #40a9ff;
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
            outline: 0;
        }

        /* Ant Design Toggle Switch Styles */
        .ant-switch {
            position: relative;
            display: inline-block;
            min-width: 44px;
            height: 22px;
            line-height: 22px;
            vertical-align: middle;
            border-radius: 100px;
            background-color: rgba(0, 0, 0, 0.25);
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .ant-switch-checked {
            background-color: #1890ff;
        }

        .ant-switch-handle {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: #fff;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1);
        }

        .ant-switch-checked .ant-switch-handle {
            left: calc(100% - 2px - 18px);
        }

        .ant-switch-inner {
            color: #fff;
            font-size: 12px;
            display: block;
            margin: 0 7px;
        }

        .ant-switch-inner-checked {
            display: none;
        }

        .ant-switch-checked .ant-switch-inner-unchecked {
            display: none;
        }

        .ant-switch-inner-unchecked {
            display: none;
        }

        .ant-switch-checked .ant-switch-inner-checked {
            display: block;
            float: left;
            margin-right: 0px;
            margin-left: 7px;
        }

        .ant-switch:not(.ant-switch-checked) .ant-switch-inner-unchecked {
            display: block;
            float: right;
            margin-left: 0px;
            margin-right: 7px;
        }

        /* Category Badge Styling (similar to role-badge) */
        .category-badge {
            display: inline-block;
            padding: 0.2em 0.6em;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            background-color: #e6f7ff; /* Light blue */
            color: #1890ff; /* Blue text */
            border: 1px solid #91d5ff;
        }

        /* Action Buttons (Circular) */
        .action-circle-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
            font-size: 14px;
            margin: 0 4px;
            box-shadow: none;
        }

        .edit-btn {
            background-color: #fff;
            border: 1px solid #d9d9d9;
            color: rgba(0, 0, 0, 0.65);
        }

        .edit-btn:hover {
            border-color: #1890ff;
            color: #1890ff;
        }

        .delete-btn {
            background-color: #fff;
            border: 1px solid #ffa39e;
            color: #ff4d4f;
        }

        .delete-btn:hover {
            border-color: #ff7875;
            color: #ff7875;
        }
        
        .menu-image {
            width: 80px; /* Adjust as needed */
            height: 80px; /* Adjust as needed */
            object-fit: cover; /* Ensures image covers the area without distortion */
            border-radius: 4px;
            vertical-align: middle;
        }

    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        <div id="ant-error-message-container" class="ant-message ant-message-top css-qnu6hi">
            <div class="ant-message-notice-wrapper">
                <div class="ant-message-notice ant-message-notice-error">
                    <div class="ant-message-notice-content">
                        <div class="ant-message-custom-content ant-message-error">
                            <span role="img" aria-label="close-circle" class="anticon-msg anticon-close-circle">
                                <svg fill-rule="evenodd" viewBox="64 64 896 896" focusable="false"
                                    data-icon="close-circle" width="1em" height="1em" fill="currentColor"
                                    aria-hidden="true">
                                    <path
                                        d="M512 64c247.4 0 448 200.6 448 448S759.4 960 512 960 64 759.4 64 512 264.6 64 512 64zm127.98 274.82h-.04l-.08.06L512 466.75 384.14 338.88c-.04-.05-.06-.06-.08-.06a.12.12 0 00-.07 0c-.03 0-.05.01-.09.05l-45.02 45.02a.2.2 0 00-.05.09.12.12 0 000 .07v.02a.27.27 0 00.06.06L466.75 512 338.88 639.86c-.05.04-.06.06-.06.08a.12.12 0 000 .07c0 .03.01.05.05.09l45.02 45.02a.2.2 0 00.09.05.12.12 0 00.07 0c.02 0 .04-.01.08-.05L512 557.25l127.86 127.87c.04.04.06.05.08.05a.12.12 0 00.07 0c.03 0 .05-.01.09-.05l45.02-45.02a.2.2 0 00.05-.09.12.12 0 000-.07v-.02a.27.27 0 00-.05-.06L557.25 512l127.87-127.86c.04-.04.05-.06.05-.08a.12.12 0 000-.07c0-.03-.01-.05-.05-.09l-45.02-45.02a.2.2 0 00-.09-.05.12.12 0 00-.07 0z">
                                    </path>
                                </svg>
                            </span>
                            <span id="ant-error-message-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="ant-success-message-container" class="ant-message ant-message-top css-qnu6hi">
            <div class="ant-message-notice-wrapper">
                <div class="ant-message-notice ant-message-notice-success">
                    <div class="ant-message-notice-content">
                        <div class="ant-message-custom-content ant-message-success">
                            <span role="img" aria-label="check-circle" class="anticon-msg anticon-check-circle">
                                <svg fill-rule="evenodd" viewBox="64 64 896 896" focusable="false"
                                    data-icon="check-circle" width="1em" height="1em" fill="currentColor"
                                    aria-hidden="true">
                                    <path
                                        d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm193.5 301.2l-1.4 1.6-141.2 141.6L363.6 653.2l-1.4 1.6a.2.2 0 00-.08.12c-.01.02-.02.04-.03.05-.03.04-.04.06-.07.1-.01.02-.02.03-.03.05a.15.15 0 00-.03.06L309.8 680a.2.2 0 00.08.2c.01.02.03.03.05.05.02.01.04.03.06.04.03.02.05.04.08.05a.15.15 0 00.06.03L310 680.5l45.9 45.9a.2.2 0 00.09.05c.03 0 .05-.01.09-.05l150-150 206.6-206.6a.2.2 0 00.05-.09c0-.03-.01-.05-.05-.09l-45.9-45.9a.2.2 0 00-.09-.05c-.03 0-.05.01-.05-.09l-150 150z">
                                    </path>
                                </svg>
                            </span>
                            <span id="ant-success-message-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">จัดการรายการอาหาร</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="admin/main.php">หน้าหลัก</a></li>
                                <li class="breadcrumb-item active">จัดการรายการอาหาร</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <main class="content">
                <div class="content-container">
                    <div class="ant-row css-ee1yud">
                        <div class="ant-col ant-col-12 css-ee1yud" style="justify-content: flex-start;">
                            <h4>รายการอาหารในระบบ</h4>
                        </div>
                        <div class="ant-col ant-col-12 css-ee1yud"
                            style="justify-content: flex-end; align-items: center; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <input type="text" id="menuSearchInput" class="ant-input"
                                    placeholder="ค้นหารายการอาหาร..." style="width: 200px;">
                                <button id="clearSearch" class="btn-modern btn-default-modern"
                                    style="padding: 6px 10px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <a href="add_menu.php" class="btn-modern btn-success-modern">
                                <i class="fas fa-plus"></i> เพิ่มรายการอาหาร
                            </a>
                        </div>
                    </div>
                    <div class="ant-divider css-ee1yud ant-divider-horizontal" role="separator"
                        style="border-color: rgb(204, 204, 204); margin-top: 0px; margin-bottom: 0px;"></div>

                    <div style="margin-top: 20px;">
                        <div class="ant-table-wrapper css-ee1yud">
                            <div class="ant-spin-nested-loading css-ee1yud">
                                <div class="ant-spin-container">
                                    <div class="ant-table css-ee1yud">
                                        <div class="ant-table-container">
                                            <div class="ant-table-content">
                                                <table style="table-layout: fixed; width: 100%;">
                                                    <colgroup></colgroup>
                                                    <thead class="ant-table-thead">
                                                        <tr>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 5%;">ID</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 10%;">รูปภาพ</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 15%;">ชื่ออาหาร</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 20%;">รายละเอียด</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 10%;">ราคา</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 10%;">ประเภท</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 10%;">สถานะ</th>
                                                            <th class="ant-table-cell" scope="col"
                                                                style="text-align: center; width: 20%;">การจัดการ</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="ant-table-tbody" id="menuTableBody">
                                                        <?php if (!empty($menuItems)): ?>
                                                        <?php foreach ($menuItems as $item): ?>
                                                        <tr data-menu-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                            data-menu-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                            data-menu-type="<?php echo htmlspecialchars($item['type_name_menu']); ?>">
                                                            <td class="ant-table-cell">
                                                                <?php echo htmlspecialchars($item['menu_id']); ?></td>
                                                            <td class="ant-table-cell">
                                                                <?php if (!empty($item['Image_url'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($item['Image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="menu-image">
                                                                <?php else: ?>
                                                                    <i class="far fa-image fa-2x text-muted"></i>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="ant-table-cell">
                                                                <?php echo htmlspecialchars($item['name']); ?></td>
                                                            <td class="ant-table-cell">
                                                                <?php echo htmlspecialchars($item['description']); ?>
                                                            </td>
                                                            <td class="ant-table-cell">
                                                                <?php echo number_format($item['price'], 2); ?> บาท
                                                            </td>
                                                            <td class="ant-table-cell">
                                                                <span class="category-badge">
                                                                    <?php echo htmlspecialchars($item['type_name_menu']); ?>
                                                                </span>
                                                            </td>
                                                            <td class="ant-table-cell">
                                                                <div class="ant-switch <?php echo $item['status'] == 1 ? 'ant-switch-checked' : ''; ?>"
                                                                    data-menu-id="<?php echo htmlspecialchars($item['menu_id']); ?>"
                                                                    data-is-active="<?php echo htmlspecialchars($item['status']); ?>">
                                                                    <span class="ant-switch-handle"></span>
                                                                    <span class="ant-switch-inner ant-switch-inner-checked">เปิด</span>
                                                                    <span class="ant-switch-inner ant-switch-inner-unchecked">ปิด</span>
                                                                </div>
                                                            </td>
                                                            <td class="ant-table-cell">
                                                                <button type="button" class="action-circle-btn edit-btn"
                                                                    onclick="location.href='edit_menu.php?id=<?php echo htmlspecialchars($item['menu_id']); ?>'"
                                                                    title="แก้ไขรายการอาหาร">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="action-circle-btn delete-btn"
                                                                    onclick="confirmDelete(<?php echo htmlspecialchars($item['menu_id']); ?>)"
                                                                    title="ลบรายการอาหาร">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <?php else: ?>
                                                        <tr class="ant-table-placeholder" style="display: table-row;">
                                                            <td class="ant-table-cell" colspan="8">
                                                                <div class="css-ee1yud ant-empty ant-empty-normal">
                                                                    <div class="ant-empty-image">
                                                                        <svg width="64" height="41" viewBox="0 0 64 41"
                                                                            xmlns="http://www.w3.org/2000/svg">
                                                                            <title>Simple Empty</title>
                                                                            <g transform="translate(0 1)" fill="none"
                                                                                fill-rule="evenodd">
                                                                                <ellipse fill="#F5F5F5" cx="32" cy="33"
                                                                                    rx="32" ry="7"></ellipse>
                                                                                <g fill-rule="nonzero" stroke="#D9D9D9">
                                                                                    <path
                                                                                        d="M55 12.76L44.854 1.258C44.367.474 43.656 0 42.907 0H21.093c-.749 0-1.46.474-1.947 1.257L9 12.761V22h46v-9.24z">
                                                                                    </path>
                                                                                    <path
                                                                                        d="M41.613 15.931c0-1.605.994-2.93 2.227-2.931H55v18.137C55 33.26 53.046 35 50.5 35h-37C13.954 35 12 33.259 12 30.068V13h11.16c1.233 0 2.227 1.323 2.227 2.928v.0220z"
                                                                                        fill="#FAFFA7"></path>
                                                                                </g>
                                                                            </g>
                                                                        </svg>
                                                                    </div>
                                                                    <p class="ant-empty-description">ไม่มีข้อมูลรายการอาหาร</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <?php include 'footer.php'; ?>
    </div>
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Function to display Ant Design-like message
        function showAntMessage(type, text) {
            const containerId = type === 'success' ? 'ant-success-message-container' : 'ant-error-message-container';
            const textId = type === 'success' ? 'ant-success-message-text' : 'ant-error-message-text';
            const container = document.getElementById(containerId);
            const textElement = document.getElementById(textId);

            if (container && textElement) {
                textElement.textContent = text;
                container.classList.add('show');
                setTimeout(() => {
                    container.classList.remove('show');
                }, 3000); // Hide after 3 seconds
            }
        }

        // Check for PHP session messages and display them
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['message'])): ?>
                const messageType = "<?php echo $_SESSION['message']['type']; ?>";
                const messageText = "<?php echo $_SESSION['message']['text']; ?>";
                showAntMessage(messageType, messageText);
                <?php unset($_SESSION['message']); // Clear the message after displaying ?>
            <?php endif; ?>

            // Handle status toggle via AJAX
            document.querySelectorAll('.ant-switch').forEach(function (switchElement) {
                switchElement.addEventListener('click', function () {
                    const menuId = this.dataset.menuId;
                    const currentStatus = parseInt(this.dataset.isActive);
                    const newStatus = currentStatus === 1 ? 0 : 1;
                    const switchDiv = this;

                    fetch('manage_menu.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=toggle_status&menu_id=' + menuId + '&current_status=' + currentStatus
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                switchDiv.dataset.isActive = data.new_status;
                                if (data.new_status === 1) {
                                    switchDiv.classList.add('ant-switch-checked');
                                } else {
                                    switchDiv.classList.remove('ant-switch-checked');
                                }
                                showAntMessage('success', data.message);
                            } else {
                                showAntMessage('error', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAntMessage('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์.');
                        });
                });
            });

            // Search functionality
            const menuSearchInput = document.getElementById('menuSearchInput');
            const clearSearchButton = document.getElementById('clearSearch');
            const menuTableBody = document.getElementById('menuTableBody');
            const placeholderRow = menuTableBody.querySelector('.ant-table-placeholder');

            function filterTable() {
                const searchValue = menuSearchInput.value.toLowerCase().trim();
                let hasVisibleRows = false;

                Array.from(menuTableBody.children).forEach(row => {
                    // Skip the placeholder row from filtering directly
                    if (row.classList.contains('ant-table-placeholder')) {
                        return;
                    }

                    const menuName = row.dataset.menuName.toLowerCase();
                    const menuDescription = row.dataset.menuDescription.toLowerCase();
                    const menuType = row.dataset.menuType.toLowerCase();

                    if (menuName.includes(searchValue) || menuDescription.includes(searchValue) || menuType.includes(searchValue)) {
                        row.style.display = '';
                        hasVisibleRows = true;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (placeholderRow) {
                    if (hasVisibleRows) {
                        placeholderRow.style.display = 'none';
                    } else {
                        placeholderRow.style.display = 'table-row';
                    }
                }
            }

            menuSearchInput.addEventListener('keyup', filterTable);
            clearSearchButton.addEventListener('click', function() {
                menuSearchInput.value = '';
                filterTable(); // Re-filter to show all rows
            });
        });

        // SweetAlert2 for Delete Confirmation
        function confirmDelete(menuId) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "คุณต้องการลบรายการอาหารนี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'manage_menu.php?action=delete&id=' + menuId;
                }
            });
        }
    </script>
</body>

</html>

<?php
$condb->close(); // Close database connection
?>