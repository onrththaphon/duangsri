<?php
session_start(); // Start the session to access user data and messages

// Set PHP error reporting for development.
// In a production environment, these should be turned off or logged to a file without displaying.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log.log'); // Ensure this path is writable by the web server

include 'conn.php'; // Include your database connection file (e.g., $condb variable should be available)

// --- Access Control: Check if the user is logged in and is an Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    // If not logged in or not an admin, set an error message and redirect
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้!' // You do not have permission to access this page!
    ];
    header('Location: index.php'); // Redirect to login page or home page
    exit();
}

$menu_id = null;
$menu_data = []; // This will hold the data of the menu item being edited
$type_menu_options = []; // This will hold options for the menu type dropdown

// --- Initial Data Fetch: Get menu_id from GET request and retrieve menu item data ---
if (isset($_GET['id'])) {
    $menu_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($menu_id === false || $menu_id <= 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'รหัสเมนูไม่ถูกต้อง!'
        ];
        header('Location: manage_menu.php');
        exit();
    }

    // แก้ไขชื่อตารางและคอลัมน์ให้ตรงกับ isanfood.sql: tb_menu -> menu, tmt.type_name -> tmt.type_name_menu
    $stmt = $condb->prepare("SELECT tm.*, tmt.type_name_menu FROM menu tm JOIN type_menu tmt ON tm.type_menu_id = tmt.Type_menu_id WHERE menu_id = ?");
    $stmt->bind_param("i", $menu_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $menu_data = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'ไม่พบข้อมูลเมนูที่ต้องการแก้ไข!'
        ];
        header('Location: manage_menu.php');
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'ไม่พบรหัสเมนูที่ระบุ!'
    ];
    header('Location: manage_menu.php');
    exit();
}

// Fetch menu types for the dropdown
// แก้ไขชื่อตารางและคอลัมน์ให้ตรงกับ isanfood.sql: tb_menu_type -> type_menu, type_id -> Type_menu_id, type_name -> type_name_menu
$type_query = $condb->query("SELECT Type_menu_id, type_name_menu FROM type_menu ORDER BY type_name_menu");
if ($type_query) {
    while ($row = $type_query->fetch_assoc()) {
        $type_menu_options[] = $row;
    }
} else {
    // Handle error if type_menu cannot be fetched
    error_log("Error fetching menu types: " . $condb->error);
}

// --- Handle Form Submission for Updating Menu Item ---
if (isset($_POST['update_menu'])) {
    $menu_id = filter_input(INPUT_POST, 'menu_id', FILTER_VALIDATE_INT);
    $menu_name = filter_input(INPUT_POST, 'menu_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // This will map to 'name' in DB
    $menu_detail = filter_input(INPUT_POST, 'menu_detail', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // This will map to 'description' in DB
    $menu_price = filter_input(INPUT_POST, 'menu_price', FILTER_VALIDATE_FLOAT);
    $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT); // This will map to 'type_menu_id' in DB
    $old_image = filter_input(INPUT_POST, 'old_menu_image', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // รับชื่อรูปภาพเดิม (map to Image_url in DB)

    // Validate inputs
    if ($menu_id === false || $menu_id <= 0 || !$menu_name || !$menu_detail || $menu_price === false || $menu_price < 0 || $type_id === false || $type_id <= 0) {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'ข้อมูลที่กรอกไม่ถูกต้องหรือไม่ครบถ้วน!'
        ];
        header('Location: edit_menu.php?id=' . $menu_id);
        exit();
    }

    $menu_image_final_name = $old_image; // Default to old image name

    // Handle image upload
    if (!empty($_FILES['menu_image']['name'])) {
        $target_dir = "uploads/"; // ตรวจสอบให้แน่ใจว่าโฟลเดอร์ uploads มีอยู่และมีสิทธิ์ในการเขียน
        $original_filename = basename($_FILES["menu_image"]["name"]);
        $imageFileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $new_filename = uniqid('menu_') . '.' . $imageFileType; // Generate unique filename
        $target_file = $target_dir . $new_filename;
        $uploadOk = 1;

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["menu_image"]["tmp_name"]);
        if ($check !== false) {
            $uploadOk = 1;
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'ไฟล์ไม่ใช่รูปภาพ!'];
            $uploadOk = 0;
        }

        // Check file size (e.g., 5MB limit)
        if ($_FILES["menu_image"]["size"] > 5000000) { // 5MB
            $_SESSION['message'] = ['type' => 'error', 'text' => 'ขออภัย, ไฟล์รูปภาพใหญ่เกินไป! (สูงสุด 5MB)'];
            $uploadOk = 0;
        }

        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'ขออภัย, อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG & GIF เท่านั้น!'];
            $uploadOk = 0;
        }

        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            header('Location: edit_menu.php?id=' . $menu_id);
            exit();
        } else {
            // If everything is ok, try to upload file
            if (move_uploaded_file($_FILES["menu_image"]["tmp_name"], $target_file)) {
                $menu_image_final_name = $new_filename; // Set new image name for database

                // Optional: Delete old image if it exists and is not default_menu.png
                // ตรวจสอบให้แน่ใจว่า 'uploads/' ตรงกับ $target_dir
                if (!empty($old_image) && file_exists($target_dir . $old_image) && $old_image !== 'default_menu.png') {
                    unlink($target_dir . $old_image);
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'ขออภัย, เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ.'];
                header('Location: edit_menu.php?id=' . $menu_id);
                exit();
            }
        }
    }

    // Update menu data in the database
    // แก้ไขคำสั่ง SQL UPDATE ให้ใช้ชื่อคอลัมน์จากฐานข้อมูล isanfood.sql:
    // name, description, price, type_menu_id, Image_url
    $sql_update = "UPDATE menu SET name = ?, description = ?, price = ?, type_menu_id = ?, Image_url = ? WHERE menu_id = ?";
    $stmt_update = $condb->prepare($sql_update);
    // ตรวจสอบให้แน่ใจว่าลำดับของ bind_param ตรงกับลำดับของ ? ใน SQL
    // 'ssdiss' -> s: menu_name, s: menu_detail, d: menu_price, i: type_id, s: menu_image_final_name, i: menu_id
    // ใน SQL dump, menu_id เป็น int(11) -> ควรใช้ 'i'
    $stmt_update->bind_param("ssdiss", $menu_name, $menu_detail, $menu_price, $type_id, $menu_image_final_name, $menu_id);


    if ($stmt_update->execute()) {
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => 'แก้ไขข้อมูลเมนูสำเร็จแล้ว!'
        ];
        // Refresh menu_data to show updated info immediately (optional, as it redirects)
        // แก้ไขชื่อตารางและคอลัมน์ในส่วน refresh ให้ตรงกับ isanfood.sql: tb_menu -> menu, tmt.type_name -> tmt.type_name_menu
        $stmt_refresh = $condb->prepare("SELECT tm.*, tmt.type_name_menu FROM menu tm JOIN type_menu tmt ON tm.type_menu_id = tmt.Type_menu_id WHERE menu_id = ?");
        $stmt_refresh->bind_param("i", $menu_id);
        $stmt_refresh->execute();
        $result_refresh = $stmt_refresh->get_result();
        if ($result_refresh->num_rows === 1) {
            $menu_data = $result_refresh->fetch_assoc();
        }
        $stmt_refresh->close();

        header('Location: manage_menu.php'); // Redirect to manage page
        exit();
    } else {
        $_SESSION['message'] = [
            'type' => 'error',
            'text' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลเมนู: ' . $stmt_update->error
        ];
    }
    $stmt_update->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลเมนู - ระบบจัดการร้านอาหาร</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #acacacff;
        }
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background-color: #ffffffff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: bold;
        }
        .btn-custom {
            background-color: #ffc65dff;
            color: #343a40;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #e99648ff;
            color: #343a40;
        }
        .btn-danger-custom {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-danger-custom:hover {
            background-color: #c82333;
            color: white;
        }
        .img-thumbnail {
            max-width: 200px;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 5px;
        }
        /* Ant Design-like message styles */
        .ant-message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            display: none; /* Hidden by default */
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .ant-message-container.show {
            display: block;
            opacity: 1;
        }
        .ant-message {
            padding: 10px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
        }
        .ant-message-success {
            background-color: #f6ffed;
            border: 1px solid #b7eb8f;
            color: #52c41a;
        }
        .ant-message-error {
            background-color: #fff1f0;
            border: 1px solid #ffa39e;
            color: #f5222d;
        }
        .ant-message-icon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="manage_menu.php">
                <i class="fas fa-utensils"></i> ระบบจัดการร้านอาหาร
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="manage_menu.php"><i class="fas fa-book-open"></i> จัดการเมนู</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-chart-line"></i> รายงาน</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container form-container">
        <h2 class="mb-4 text-center">แก้ไขข้อมูลเมนูอาหาร</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="menu_id" value="<?php echo htmlspecialchars($menu_data['menu_id']); ?>">
            <input type="hidden" name="old_menu_image" value="<?php echo htmlspecialchars($menu_data['Image_url']); ?>">

            <div class="mb-3">
                <label for="menu_name" class="form-label">ชื่อเมนู</label>
                <input type="text" class="form-control" id="menu_name" name="menu_name" value="<?php echo htmlspecialchars($menu_data['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="menu_detail" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="menu_detail" name="menu_detail" rows="3"><?php echo htmlspecialchars($menu_data['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="menu_price" class="form-label">ราคา</label>
                <input type="number" class="form-control" id="menu_price" name="menu_price" step="0.01" value="<?php echo htmlspecialchars($menu_data['price']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="type_id" class="form-label">ประเภทเมนู</label>
                <select class="form-select" id="type_id" name="type_id" required>
                    <option value="">เลือกประเภทเมนู</option>
                    <?php foreach ($type_menu_options as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['Type_menu_id']); ?>" <?php echo ($type['Type_menu_id'] == $menu_data['type_menu_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['type_name_menu']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="menu_image" class="form-label">รูปภาพเมนู</label>
                <input class="form-control" type="file" id="menu_image" name="menu_image" accept="image/*">
                <?php if (!empty($menu_data['Image_url'])): // ใช้ Image_url ?>
                    <img src="uploads/<?php echo htmlspecialchars($menu_data['Image_url']); ?>" alt="รูปภาพเมนู" class="img-thumbnail">
                    <p class="mt-2">ไฟล์ปัจจุบัน: <?php echo htmlspecialchars($menu_data['Image_url']); ?></p>
                <?php else: ?>
                    <p class="mt-2">ไม่มีรูปภาพปัจจุบัน</p>
                <?php endif; ?>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="update_menu" class="btn btn-custom">บันทึกการแก้ไข</button>
                <a href="manage_menu.php" class="btn btn-danger-custom">ยกเลิก</a>
            </div>
        </form>
    </div>

    <div id="ant-success-message-container" class="ant-message-container">
        <div class="ant-message ant-message-success">
            <span class="ant-message-icon"><i class="fas fa-check-circle"></i></span>
            <span id="ant-success-message-text"></span>
        </div>
    </div>
    <div id="ant-error-message-container" class="ant-message-container">
        <div class="ant-message ant-message-error">
            <span class="ant-message-icon"><i class="fas fa-times-circle"></i></span>
            <span id="ant-error-message-text"></span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // JavaScript function to display Ant Design-like messages
        function showAntMessage(type, text) {
            const containerId = type === 'success' ? 'ant-success-message-container' : 'ant-error-message-container';
            const textId = type === 'success' ? 'ant-success-message-text' : 'ant-error-message-text';
            const container = document.getElementById(containerId);
            const textElement = document.getElementById(textId);

            if (container && textElement) {
                textElement.textContent = text;
                container.classList.add('show'); // Add 'show' class to make it visible
                setTimeout(() => {
                    container.classList.remove('show'); // Hide after 3 seconds
                }, 3000);
            }
        }

        // Check for PHP session messages on page load and display them
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['message'])): ?>
                const messageType = "<?php echo $_SESSION['message']['type']; ?>";
                const messageText = "<?php echo $_SESSION['message']['text']; ?>";
                showAntMessage(messageType, messageText);
                <?php unset($_SESSION['message']); // Clear the message after displaying it ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
$condb->close(); // Close the database connection
?>