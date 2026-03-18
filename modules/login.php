<?php
session_start();
include '../config/db.php';

$error='';

if($_SERVER['REQUEST_METHOD']=='POST')
{
    $u = trim($_POST['username']);
    $p = md5(trim($_POST['password']));

    $stmt = $conn->prepare("SELECT * FROM emslss_users WHERE username=? AND password=? LIMIT 1");
    $stmt->bind_param("ss",$u,$p);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0)
	{
        $user = $result->fetch_assoc();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
		if($user['role']=='shipper')
		{
			header("Location: pickup_dashboard.php");
			exit;
		}
		else
		{
			header("Location: admin/dashboard.php");
			exit;
		}

    } 
	else 
	{
        $error = "Sai tài khoản hoặc mật khẩu";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>EMS-LSS Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5" style="max-width:400px;">

<h3>EMS-LSS Login</h3>

<?php if($error!=''){ ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php } ?>

<form method="POST">

<div class="mb-3">
<input name="username" class="form-control" placeholder="Username" required>
</div>

<div class="mb-3">
<input name="password" type="password" class="form-control" placeholder="Password" required>
</div>

<button type="submit" class="btn btn-primary w-100">Login</button>

</form>

</div>

</body>
</html>