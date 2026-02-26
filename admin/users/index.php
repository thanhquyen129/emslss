<?php
session_start(); // Đảm bảo session đã được bắt đầu

// Kiểm tra xem người dùng đã đăng nhập chưa và có vai trò 'admin' không
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Nếu không phải admin hoặc chưa đăng nhập, chuyển hướng về trang đăng nhập
    header("Location: ../../login.php");
    exit; // Dừng script ngay lập tức
}
// admin/users/index.php - Giao diện quản lý người dùng (danh sách)
// QUAN TRỌNG: Script này hiện chưa có hệ thống xác thực và phân quyền.
//            Bạn cần thêm chức năng đăng nhập và kiểm tra vai trò admin sau này.

require_once '../../includes/config.php'; // Điều chỉnh đường dẫn nếu file config.php không đúng vị trí này

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Lấy danh sách người dùng từ bảng vmlbooking_users
    // Sắp xếp theo ngày tạo mới nhất lên đầu
    $stmt = $pdo->query("SELECT id, username, company_name, role, email, phone, is_active, created_at FROM vmlbooking_users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi kết nối hoặc truy vấn database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Người dùng - Booking System Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px 0;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border: none;
            cursor: pointer;
        }
        .btn-add { background-color: #28a745; }
        .btn-edit { background-color: #ffc107; color: #333; }
        .btn-delete { background-color: #dc3545; }
        .action-links a { margin-right: 5px; }
        /* Thêm style cho nhóm nút để chúng nằm cạnh nhau */
        .button-group {
            margin-bottom: 20px;
            display: flex;
            gap: 10px; /* Khoảng cách giữa các nút */
            flex-wrap: wrap; /* Cho phép các nút xuống dòng trên màn hình nhỏ */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Quản lý Người dùng</h2>
        <div class="button-group">
            <a href="../../index.php" class="btn">Quay lại Dashboard</a>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <a href="add.php" class="btn btn-add">Thêm Người dùng Mới</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($users)): ?>
            <p>Chưa có người dùng nào được tạo trong hệ thống.</p>
            <p>Hãy thêm người dùng đầu tiên bằng cách nhấn vào nút "Thêm Người dùng Mới" ở trên.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Tên Công ty</th>
                    <th>Vai trò</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Hoạt động</th>
                    <th>Ngày tạo</th>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <th>Thao tác</th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td><?php echo $user['is_active'] ? 'Có' : 'Không'; ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <td class="action-links">
                                    <a href="edit.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-edit">Sửa</a>
                                    <a href="delete.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-delete" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng <?php echo htmlspecialchars($user['username']); ?> không?');">Xóa</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>