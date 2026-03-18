<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? null;
$user = null;

if($id){
    $stmt = $conn->prepare("SELECT * FROM emslss_users WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

$roles = $conn->query("SELECT * FROM emslss_roles");

if($_SERVER['REQUEST_METHOD']=='POST'){
    $username = $_POST['username'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $role_id = $_POST['role_id'];

    if($id){
        $stmt = $conn->prepare("UPDATE emslss_users SET username=?, full_name=?, phone=?, role_id=? WHERE id=?");
        $stmt->bind_param("sssii",$username,$full_name,$phone,$role_id,$id);
    } else {
        $password = password_hash("123456", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO emslss_users(username,password,full_name,phone,role_id,is_active) VALUES(?,?,?,?,?,1)");
        $stmt->bind_param("ssssi",$username,$password,$full_name,$phone,$role_id);
    }

    $stmt->execute();
    header("Location: admin_users.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__.'/../../templates/admin_topbar.php'; ?>

<div class="container mt-4">
<h3><?= $id ? 'Sửa User' : 'Thêm User' ?></h3>

<form method="POST">
    <input class="form-control mb-2" name="username" placeholder="Username" value="<?= $user['username'] ?? '' ?>" required>
    <input class="form-control mb-2" name="full_name" placeholder="Họ tên" value="<?= $user['full_name'] ?? '' ?>" required>
    <input class="form-control mb-2" name="phone" placeholder="Phone" value="<?= $user['phone'] ?? '' ?>">

    <select name="role_id" class="form-select mb-3">
        <?php while($r=$roles->fetch_assoc()): ?>
            <option value="<?= $r['id'] ?>" <?= (($user['role_id'] ?? '')==$r['id'])?'selected':'' ?>>
                <?= $r['role_name'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button class="btn btn-success">Lưu</button>
</form>
</div>

</body>
</html>