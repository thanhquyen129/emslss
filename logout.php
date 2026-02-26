<?php
session_start(); // Bắt đầu session

// Hủy tất cả các biến session
$_SESSION = array();

// Nếu muốn hủy hoàn toàn session, cũng xóa cookie session.
// Lưu ý: Thao tác này sẽ làm mất session_id.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, hủy session
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit;
?>