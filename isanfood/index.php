<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "isanfood"; // Your database name, ensure this matches your setup

$condb = new mysqli($servername, $username, $password, $dbname);
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}

/**
 * Saves login history to the 'logs' table.
 *
 * @param mysqli $condb The database connection object.
 * @param int|null $userId The ID of the user who performed the action, or null if not applicable.
 * @param string $actionType The type of action performed (e.g., 'Login', 'Login Attempt Failed').
 * @param string $message Additional details about the action.
 */
function saveLog($condb, $userId, $actionType, $message = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    // Changed 'action' to 'action_type' and 'details' to 'message' to match recommended table structure
    $sql = "INSERT INTO logs (user_id, action_type, message, ip_address) VALUES (?, ?, ?, ?)";
    $stmt = $condb->prepare($sql);
    if ($stmt) {
        if ($userId === null) {
            // 's' for string if user_id can be NULL and you are binding it as a string representation of NULL
            // For INT columns, it's better to bind NULL directly if supported or use an empty string for 0
            // but for simplicity, we'll keep 'ssss' if nullUserId is effectively a string 'null' or empty.
            // If user_id is INT NULLABLE, 'isss' should work if $userId is truly null.
            $stmt->bind_param("ssss", $nullUserId, $actionType, $message, $ipAddress);
            $nullUserId = null; // Ensuring null is passed for user_id if column is nullable INT
        } else {
            $stmt->bind_param("isss", $userId, $actionType, $message, $ipAddress);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        // Log the actual error from the database connection
        error_log("Failed to prepare log insertion statement: " . $condb->error);
    }
}

$loginSuccess = false;
$redirectUrl = '';
$errorMsg = '';
$successMsg = '';
$displayAntMessage = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $personal_username = $_POST["personal_username"];
    $personal_password = $_POST["personal_password"];

    // Fetch user data from the 'User' table, adjusted to match your data dictionary
$sql_check_auth = "SELECT
                            u.User_id,
                            u.Username,
                            u.Password,
                            u.type_user_id,
                            u.status,
                            u.fname,
                            u.Sname,
                            t.type_name
                        FROM `User` AS u
                        LEFT JOIN type_user AS t ON u.type_user_id = t.type_user_id
                        WHERE u.Username = ? OR u.phone = ?";

    $stmt = $condb->prepare($sql_check_auth);
    if ($stmt === false) {
        die("SQL Error: " . $condb->error);
    }

    $stmt->bind_param("ss", $personal_username, $personal_username);
    $stmt->execute();
    $result_check_auth = $stmt->get_result();

    if ($result_check_auth->num_rows === 1) {
        $userData = $result_check_auth->fetch_assoc();

        // **WARNING: Using direct password comparison (highly insecure).**
        // For production, use `password_hash()` for storing and `password_verify()` for checking.
        if ($personal_password === $userData["Password"]) { // Changed from userData["password"]
            // Check user status based on your ENUM column 'status'
            // Assuming 'active' means enabled, anything else means disabled/inactive
            if ($userData['status'] !== 'active') { // Changed from !userData['is_active']
                $loginSuccess = false;
                $errorMsg = "บัญชีผู้ใช้งานถูกระงับ กรุณาติดต่อผู้ดูแลระบบ";
                $displayAntMessage = true;
                // Changed parameter names to match saveLog function signature
                saveLog($condb, $userData['User_id'], 'Login Attempt Failed', 'Inactive account: ' . $personal_username); // Changed from user_id
            } else {
                $_SESSION["user_id"] = $userData["User_id"];         // Changed from user_id
                $_SESSION["username"] = $userData["Username"];       // Changed from username
                $_SESSION["role_id"] = $userData["type_user_id"];   // Changed from role_id
                $_SESSION["role_name"] = $userData["type_name"];    // Changed from role_name

                // Set full_name for the session using fetched fname and Sname
                // If fname or Sname are NULL, default to username
                if (!empty($userData["fname"]) && !empty($userData["Sname"])) { // Changed from first_name, last_name
                    $_SESSION["full_name"] = $userData["fname"] . " " . $userData["Sname"]; // Changed from first_name, last_name
                } else {
                    $_SESSION["full_name"] = $userData["Username"]; // Changed from username
                }

                // Changed parameter names to match saveLog function signature
                saveLog($condb, $userData["User_id"], 'Login Successful', 'User logged in: ' . $personal_username); // Changed from user_id

                // Adjust roles to match your type_name values from type_user table
                switch ($_SESSION["role_name"]) {
                    case 'เจ้าของร้าน (Owner)': // Match type_name exactly
                        $redirectUrl = "admin/main.php"; // Assuming Owner is Admin
                        break;
                    case 'แคชเชียร์ (Cashier)': // Match type_name exactly
                        $redirectUrl = "employee/cashier_main.php"; // Example path for cashier
                        break;
                    case 'ครัว (Chef)': // Match type_name exactly
                        $redirectUrl = "employee/chef_main.php"; // Example path for chef
                        break;
                    case 'พนักงานเสิร์ฟ (Waiter)': // Match type_name exactly
                        $redirectUrl = "employee/waiter_main.php"; // Example path for waiter
                        break;
                    case 'ลูกค้า (Customer)': // Match type_name exactly
                        $redirectUrl = "customer/main.php";
                        break;
                    default:
                        $redirectUrl = '';
                }

                if ($redirectUrl !== '') {
                    $loginSuccess = true;
                    $successMsg = "เข้าสู่ระบบสำเร็จ";
                    $displayAntMessage = true;
                } else {
                    $loginSuccess = false;
                    $errorMsg = "สิทธิ์การใช้งานไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
                    $displayAntMessage = true;
                    // Changed parameter names to match saveLog function signature
                    saveLog($condb, $userData['User_id'], 'Login Attempt Failed', 'Invalid role: ' . $_SESSION["role_name"] . ' for user ' . $personal_username); // Changed from user_id
                }
            }
        } else {
            $loginSuccess = false;
            $errorMsg = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            $displayAntMessage = true;
            // Changed parameter names to match saveLog function signature
            saveLog($condb, $userData['User_id'], 'Login Attempt Failed', 'Incorrect password for: ' . $personal_username); // Changed from user_id
        }
    } else {
        $loginSuccess = false;
        $errorMsg = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        $displayAntMessage = true;
        // Changed parameter names to match saveLog function signature
        saveLog($condb, null, 'Login Attempt Failed', 'Username not found: ' . $personal_username);
    }
    $stmt->close();
}

$condb->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>เข้าสู่ระบบ - ร้านอาหารอีสานกันเอง</title>

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

    <style>
    body,
    html {
        height: 100%;
        font-family: 'Kanit', sans-serif;
        font-style: normal;
        font-weight: 400;
        background-image: url('');
        /* Update this path! */
        background-size: cover;
        background-repeat: no-repeat;
        background-attachment: fixed;
        background-position: center center;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0;
        padding: 0;
        color: #333;
    }

    .login-wrapper {
        background: #fff;
        padding: 24px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        width: 360px;
        text-align: center;
    }

    .login-header {
        margin-bottom: 24px;
    }

    .login-header img {
        height: 40px;
        margin-bottom: 8px;
    }

    .login-header h2 {
        font-size: 18px;
        color: rgba(0, 0, 0, 0.88);
        margin-top: 8px;
        font-weight: 600;
    }

    .login-header p {
        font-size: 14px;
        margin: 0;
        color: rgba(0, 0, 0, 0.65);
    }

    .login-form .form-group {
        margin-bottom: 16px;
        position: relative;
    }

    .login-form .form-control {
        width: 100%;
        padding: 10px 15px 10px 40px;
        border: 1px solid #d9d9d9;
        border-radius: 6px;
        font-size: 14px;
        line-height: 1.5714;
        color: rgba(0, 0, 0, 0.88);
        box-sizing: border-box;
        transition: all 0.2s;
    }

    .login-form .form-control:focus {
        border-color: #4096ff;
        box-shadow: 0 0 0 2px rgba(5, 145, 255, 0.1);
        outline: none;
    }

    .login-form .form-control::placeholder {
        font-family: 'Kanit', sans-serif;
        font-weight: 400;
    }

    .login-form .form-control::-webkit-input-placeholder {
        font-family: 'Kanit', sans-serif;
        font-weight: 400;
    }

    .login-form .form-control::-moz-placeholder {
        font-family: 'Kanit', sans-serif;
        font-weight: 400;
    }

    .login-form .form-control:-ms-input-placeholder {
        font-family: 'Kanit', sans-serif;
        font-weight: 400;
    }

    .form-group .anticon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(0, 0, 0, 0.45);
    }

    .login-form .anticon-eye-invisible,
    .login-form .anticon-eye {
        cursor: pointer;
        left: auto;
        right: 12px;
        z-index: 2;
    }

    .login-form .form-check {
        text-align: left;
        margin-bottom: 16px;
        font-size: 14px;
        color: rgba(0, 0, 0, 0.88);
    }

    .login-form .form-check-input {
        margin-right: 8px;
        border-radius: 2px;
        border: 1px solid #d9d9d9;
    }

    .login-form .login-button {
        width: 100%;
        padding: 8px 15px;
        height: 40px;
        background-color: rgba(17, 0, 255, 1);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-family: 'Kanit', sans-serif;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .login-form .login-button:hover {
        background-color: rgba(83, 71, 252, 1);
    }

    .login-footer {
        margin-top: 16px;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
    }

    .login-footer a {
        color: rgba(47, 0, 255, 1);
        text-decoration: none;
    }

    .login-footer a:hover {
        color: rgb(150, 20, 50);
        text-decoration: underline;
    }

    .ant-message {
        position: fixed;
        top: 8px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10000;
        pointer-events: none;
        display: flex;
        justify-content: center;
        box-sizing: border-box;
    }

    .ant-message-notice-wrapper {
        margin-bottom: 16px;
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        display: flex;
        justify-content: center;
    }

    .ant-message-notice-wrapper.show-message {
        opacity: 1;
        transform: translateY(0);
    }

    .ant-message-notice {
        padding: 9px 16px;
        border-radius: 8px;
        box-shadow: 0 6px 16px 0 rgba(0, 0, 0, 0.08), 0 3px 6px -4px rgba(0, 0, 0, 0.12), 0 9px 28px 8px rgba(0, 0, 0, 0.05);
        background: #fff;
        position: relative;
        pointer-events: all;
        display: inline-flex;
        /* Key for content-fitting width */
        align-items: center;
        max-width: fit-content;
        /* Key for content-fitting width */
        min-width: 150px;
        /* Ensures a minimum readable width */
        text-align: left;
    }

    .ant-message-custom-content {
        display: flex;
        align-items: center;
    }

    .anticon-msg {
        font-size: 16px;
        margin-right: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .anticon-msg svg {
        width: 1em;
        height: 1em;
        vertical-align: -0.125em;
    }

    .ant-message-error .anticon-msg {
        color: #ff4d4f;
    }

    .ant-message-success .anticon-msg {
        color: #1900ffff;
    }

    .ant-message-error span,
    .ant-message-success span {
        color: rgba(0, 0, 0, 0.88);
        font-size: 14px;
        line-height: 1.5714;
    }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="login-header">
            <img src="https://img.freepik.com/premium-vector/soup-icon-vector-image-can-be-used-autumn_120816-137433.jpg" alt="SUT Logo"
                style="height: 60px;">
            <h2 style="font-size: 20px; font-weight: 700; margin-top: 5px;"> ระบบการจัดการ
                <br>ร้านอีสานกันเอง
            </h2>
        </div>

        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <span class="anticon anticon-user"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" name="personal_username"
                    placeholder="ชื่อผู้ใช้งาน หรือ เบอร์โทรศัพท์" required autofocus />
            </div>
            <div class="form-group">
                <span class="anticon anticon-lock"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="personal_password" name="personal_password"
                    placeholder="รหัสผ่าน" required />
                <span class="anticon anticon-eye-invisible" id="togglePassword">
                    <i class="fas fa-eye-slash"></i>
                </span>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe">
                <label class="form-check-label" for="rememberMe">จดจำฉันไว้</label>
            </div>
            <button type="submit" class="login-button">เข้าสู่ระบบ</button>
        </form>

        <div class="login-footer">
            <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
            <a href="register.php">ยังไม่มีบัญชี? สมัครสมาชิก</a>
        </div>

        <div id="ant-error-message-container" class="ant-message ant-message-top css-qnu6hi" style="display: none;">
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

        <div id="ant-success-message-container" class="ant-message ant-message-top css-qnu6hi" style="display: none;">
            <div class="ant-message-notice-wrapper">
                <div class="ant-message-notice ant-message-notice-success">
                    <div class="ant-message-notice-content">
                        <div class="ant-message-custom-content ant-message-success">
                            <span role="img" aria-label="check-circle" class="anticon-msg anticon-check-circle">
                                <svg fill-rule="evenodd" viewBox="64 64 896 896" focusable="false"
                                    data-icon="check-circle" width="1em" height="1em" fill="currentColor"
                                    aria-hidden="true">
                                    <path
                                        d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm193.5 301.2l-1.4 1.6-141.2 141.6L363.6 653.2l-1.4 1.6a.2.2 0 00-.08.12c-.01.02-.02.04-.03.05-.03.04-.04.06-.07.1-.01.02-.02.03-.03.05a.15.15 0 00-.03.06L309.8 680a.2.2 0 00.08.2c.01.02.03.03.05.05.02.01.04.03.06.04.03.02.05.04.08.05a.15.15 0 00.06.03L310 680.5l45.9 45.9a.2.2 0 00.09.05c.03 0 .05-.01.09-.05l150-150 206.6-206.6a.2.2 0 00.05-.09c0-.03-.01-.05-.05-.09l-45.9-45.9a.2.2 0 00-.09-.05c-.03 0-.05.01-.09.05l-150 150z">
                                    </path>
                                </svg>
                            </span>
                            <span id="ant-success-message-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    $(document).ready(function() {
        $('#togglePassword').on('click', function() {
            const passwordField = $('#personal_password');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });

        <?php if ($loginSuccess && $displayAntMessage) : ?>
        var successMessage = "<?= htmlspecialchars($successMsg) ?>";
        var $antSuccessMessageContainer = $('#ant-success-message-container');
        var $antSuccessMessageText = $('#ant-success-message-text');
        var $antSuccessMessageNoticeWrapper = $antSuccessMessageContainer.find('.ant-message-notice-wrapper');

        console.log('Login Success Message Triggered');
        console.log('Message:', successMessage);

        $antSuccessMessageText.text(successMessage);
        $antSuccessMessageContainer.show();

        setTimeout(function() {
            $antSuccessMessageNoticeWrapper.addClass('show-message');
        }, 50);

        setTimeout(function() {
            $antSuccessMessageNoticeWrapper.removeClass('show-message');
            $antSuccessMessageNoticeWrapper.one('transitionend', function() {
                $antSuccessMessageContainer.hide();
                console.log('Redirecting to:', "<?= htmlspecialchars($redirectUrl) ?>");
                window.location.href = "<?= htmlspecialchars($redirectUrl) ?>";
            });
        }, 5000);
        <?php elseif (!$loginSuccess && $displayAntMessage && !empty($errorMsg)) : ?>
        var errorMessage = "<?= htmlspecialchars($errorMsg) ?>";
        var $antErrorMessageContainer = $('#ant-error-message-container');
        var $antErrorMessageText = $('#ant-error-message-text');
        var $antErrorMessageNoticeWrapper = $antErrorMessageContainer.find('.ant-message-notice-wrapper');

        console.log('Login Error Message Triggered');
        console.log('Error:', errorMessage);

        $antErrorMessageText.text(errorMessage);
        $antErrorMessageContainer.show();

        setTimeout(function() {
            $antErrorMessageNoticeWrapper.addClass('show-message');
        }, 50);

        setTimeout(function() {
            $antErrorMessageNoticeWrapper.removeClass('show-message');
            $antErrorMessageNoticeWrapper.one('transitionend', function() {
                $antErrorMessageContainer.hide();
            });
        }, 3000);
        <?php endif; ?>
    });
    </script>
</body>

</html>