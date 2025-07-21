<?php
session_start();

// ตั้งค่าการแสดงผลข้อผิดพลาดสำหรับการพัฒนา (ควรปิดในการผลิตจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log'); // ตรวจสอบให้แน่ใจว่า path นี้สามารถเขียนได้

include 'conn.php'; // ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบและมีบทบาทเป็น Admin (role_id = 1) หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!'
    ];
    header('Location: index.php'); // เปลี่ยนเส้นทางไปยังหน้าเข้าสู่ระบบหรือหน้าหลัก
    exit();
}

// ดึงประเภทเมนูสำหรับ Dropdown
$type_menu_options = [];
$sql_types = "SELECT Type_menu_id, type_name_menu FROM type_menu ORDER BY type_name_menu ASC";
$result_types = $condb->query($sql_types);
if ($result_types) {
    while ($row = $result_types->fetch_assoc()) {
        $type_menu_options[] = $row;
    }
} else {
    error_log("Add Menu: Error fetching menu types: " . $condb->error);
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'ไม่สามารถโหลดประเภทอาหารได้: ' . htmlspecialchars($condb->error)
    ];
}

// จัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $type_menu_id = filter_input(INPUT_POST, 'type_menu_id', FILTER_VALIDATE_INT);
    $status = isset($_POST['status']) ? 1 : 0; // 1 ถ้าเช็คบ็อกซ์ถูกเลือก, 0 ถ้าไม่ถูกเลือก
    $image_url = ''; // ค่าเริ่มต้นว่างเปล่า จะถูกกำหนดถ้ามีการอัปโหลดรูปภาพ

    // ตรวจสอบความถูกต้องของข้อมูลเบื้องต้นจากฝั่งเซิร์ฟเวอร์
    if (empty($name) || empty($description) || $price === false || $price < 0 || $type_menu_id === false || $type_menu_id <= 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง (ชื่อ, รายละเอียด, ราคา, ประเภท).'
        ];
        // เปลี่ยนเส้นทางกลับไปที่ฟอร์มเพื่อแสดงข้อผิดพลาด
        header('Location: add_menu.php');
        exit();
    }

    // จัดการการอัปโหลดรูปภาพ
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/menu/"; // ไดเรกทอรีสำหรับเก็บรูปภาพ
        // สร้างไดเรกทอรีถ้ายังไม่มี
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // สร้างไดเรกทอรีพร้อมสิทธิ์และไดเรกทอรีย่อย
        }

        $image_name = basename($_FILES["image"]["name"]);
        // สร้างชื่อไฟล์ที่ไม่ซ้ำกันเพื่อป้องกันการเขียนทับ
        $unique_filename = uniqid() . "_" . preg_replace("/[^a-zA-Z0-9-.]/", "", $image_name);
        $target_file = $target_dir . $unique_filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // ตรวจสอบชนิดไฟล์และขนาดรูปภาพ
        $allowed_extensions = array("jpg", "jpeg", "png", "gif");
        $max_file_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($imageFileType, $allowed_extensions)) {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'อนุญาตให้อัปโหลดเฉพาะไฟล์ JPG, JPEG, PNG & GIF เท่านั้น.'
            ];
            header('Location: add_menu.php');
            exit();
        }
        if ($_FILES["image"]["size"] > $max_file_size) {
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB.'
            ];
            header('Location: add_menu.php');
            exit();
        }

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = $target_file; // เก็บพาธสัมพัทธ์ในฐานข้อมูล
        } else {
            error_log("Add Menu (Image Upload): Failed to move uploaded file: " . $_FILES["image"]["error"] . " temp: " . $_FILES["image"]["tmp_name"] . " target: " . $target_file);
            $_SESSION['message'] = [
                'type' => 'error',
                'text' => 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ.'
            ];
            header('Location: add_menu.php');
            exit();
        }
    }

    // แทรกข้อมูลลงในฐานข้อมูล
    // `menu_id` จะถูกสร้างโดยอัตโนมัติหากตั้งค่าเป็น AUTO_INCREMENT ในฐานข้อมูล
    $stmt = $condb->prepare("INSERT INTO menu (name, description, price, type_menu_id, status, Image_url) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Add Menu: Error preparing insert statement: " . $condb->error);
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'ระบบไม่สามารถเตรียมการเพิ่มข้อมูลได้. กรุณาลองใหม่ภายหลัง.'
        ];
        header('Location: add_menu.php');
        exit();
    }
    // "ssdiis" หมายถึง string, string, double (สำหรับ float price), integer, integer, string
    $stmt->bind_param("ssdiis", $name, $description, $price, $type_menu_id, $status, $image_url);

    if ($stmt->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'เพิ่มรายการอาหารใหม่สำเร็จ!'
        ];
        header('Location: manage_menu.php'); // เปลี่ยนเส้นทางไปยังหน้าจัดการรายการอาหาร
        exit();
    } else {
        error_log("Add Menu: Error executing insert statement: " . $stmt->error);
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'เกิดข้อผิดพลาดในการเพิ่มรายการอาหาร: ' . htmlspecialchars($stmt->error)
        ];
        header('Location: add_menu.php'); // อยู่ในหน้าเพิ่มเพื่อกรอกข้อมูลใหม่หรือแสดงข้อผิดพลาด
        exit();
    }
    $stmt->close();
}

// ส่วนของ HTML เพื่อแสดงฟอร์ม
include 'header.php';
include 'navbar.php';
include 'sidebar_menu.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายการอาหารใหม่ - แอดมิน</title>
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* สไตล์ Ant Design-like Message Container */
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
            display: block;
        }

        .ant-message-notice-wrapper { overflow: hidden; margin-bottom: 16px; }
        .ant-message-notice { padding: 8px 16px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, .15); display: block; pointer-events: auto; text-align: left; }
        .ant-message-custom-content { display: flex; align-items: center; gap: 8px; }
        .ant-message-success .anticon-msg { color: #52c41a; }
        .ant-message-error .anticon-msg { color: #ff4d4f; }
        .anticon-msg svg { width: 1em; height: 1em; vertical-align: -0.125em; }

        /* Content Container for Form */
        .content-container {
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 10px;
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
            font-family: 'Kanit', sans-serif !important; /* เพิ่ม font family */
        }

        .ant-input:focus,
        .ant-input:hover {
            border-color: #40a9ff;
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
            outline: 0;
        }

        textarea.ant-input {
            min-height: 60px;
            height: auto;
            resize: vertical;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
            font-family: 'Kanit', sans-serif !important; /* เพิ่ม font family */
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
            font-family: 'Kanit', sans-serif !important; /* เพิ่ม font family */
        }

        .btn-primary-modern { color: #fff; background-color: #1890ff; border-color: #1890ff; box-shadow: 0 2px 0 rgba(0, 0, 0, 0.045); }
        .btn-primary-modern:hover { color: #fff; background-color: #40a9ff; border-color: #40a9ff; }
        .btn-default-modern { color: rgba(0, 0, 0, 0.85); background-color: #fff; border-color: #d9d9d9; box-shadow: 0 2px 0 rgba(0, 0, 0, 0.015); }
        .btn-default-modern:hover { color: #40a9ff; background-color: #fff; border-color: #40a9ff; }

        /* File Input Styling */
        .form-control-file {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-family: 'Kanit', sans-serif !important; /* เพิ่ม font family */
        }
        .form-control-file:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        .form-control-file::-webkit-file-upload-button { border: 0; padding: 0.25rem 0.5rem; margin: -0.375rem -0.75rem -0.375rem; -webkit-margin-end: 0.75rem; margin-inline-end: 0.75rem; color: #fff; background-color: #007bff; background-image: none; border-color: #007bff; border-radius: 0.2rem; cursor: pointer; }
        .form-control-file::file-selector-button { border: 0; padding: 0.25rem 0.5rem; margin: -0.375rem -0.75rem -0.375rem; margin-inline-end: 0.75rem; color: #fff; background-color: #007bff; background-image: none; border-color: #007bff; border-radius: 0.2rem; cursor: pointer; }
        .form-check-input { margin-top: 0.3rem; margin-left: -1.25rem; }
        .form-check-label { margin-bottom: 0; font-family: 'Kanit', sans-serif !important; /* เพิ่ม font family */ }

        /* Global Font (ซ้ำกับข้างบนเพื่อความแน่ใจ) */
        body, h1, h2, h3, h4, h5, h6, .navbar-nav, .content-wrapper, .main-sidebar, .btn, table, .form-control, label {
            font-family: 'Kanit', sans-serif !important;
        }
        .content-wrapper {
            padding-top: 20px; padding-left: 20px; padding-right: 20px; padding-bottom: 20px;
            min-height: calc(100vh - (var(--main-header-height, 60px) + var(--main-footer-height, 57px)));
            display: flex; flex-direction: column;
        }
        .content { flex-grow: 1; }
        html, body { height: 100%; }
        .wrapper { min-height: 100%; }
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
                            <h1 class="m-0">เพิ่มรายการอาหารใหม่</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="admin/main.php">หน้าหลัก</a></li>
                                <li class="breadcrumb-item"><a href="manage_menu.php">จัดการรายการอาหาร</a></li>
                                <li class="breadcrumb-item active">เพิ่มรายการอาหารใหม่</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <main class="content">
                <div class="content-container">
                    <form action="add_menu.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">ชื่อรายการอาหาร:</label>
                            <input type="text" id="name" name="name" class="ant-input" required>
                        </div>
                        <div class="form-group">
                            <label for="description">รายละเอียด:</label>
                            <textarea id="description" name="description" class="ant-input" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="price">ราคา:</label>
                            <input type="number" id="price" name="price" step="0.01" class="ant-input" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="type_menu_id">ประเภทอาหาร:</label>
                            <select id="type_menu_id" name="type_menu_id" class="ant-input" required>
                                <option value="">เลือกประเภท</option>
                                <?php foreach ($type_menu_options as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['Type_menu_id']); ?>">
                                        <?php echo htmlspecialchars($type['type_name_menu']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="image">รูปภาพ:</label>
                            <input type="file" id="image" name="image" class="form-control-file" accept="image/jpeg, image/png, image/gif">
                            <small class="form-text text-muted">รองรับ JPG, JPEG, PNG, GIF (สูงสุด 2MB)</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="status" name="status" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="status">ใช้งาน</label>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn-modern btn-primary-modern mr-2">
                                <i class="fas fa-save"></i> บันทึก
                            </button>
                            <a href="manage_menu.php" class="btn-modern btn-default-modern">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
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
        // ฟังก์ชันสำหรับแสดงข้อความแจ้งเตือน (Ant Design-like)
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
                }, 3000); // ซ่อนหลังจาก 3 วินาที
            }
        }

        // ตรวจสอบข้อความจาก PHP session และแสดงผล
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['message'])): ?>
                const messageType = "<?php echo $_SESSION['message']['type']; ?>";
                const messageText = "<?php echo $_SESSION['message']['text']; ?>";
                showAntMessage(messageType, messageText);
                <?php unset($_SESSION['message']); // ล้างข้อความหลังจากแสดงผล ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
$condb->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>