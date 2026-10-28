<?php
session_start();
include 'config/db_connect.php';

$error = '';

if (isset($_POST['login_btn'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Secure Oracle Query
    $sql = "SELECT * FROM STAFF WHERE staffEmail = :email AND password = :pass";
    $stid = oci_parse($conn, $sql);

    // Bind parameters to prevent SQL Injection
    oci_bind_by_name($stid, ":email", $email);
    oci_bind_by_name($stid, ":pass", $password);
    
    oci_execute($stid);
    
    $row = oci_fetch_assoc($stid);

    if ($row) {
        // Login Success
        $_SESSION['staffId'] = $row['STAFFID'];
        $_SESSION['staffName'] = $row['STAFFNAME'];
        $_SESSION['role'] = $row['ROLE'];

        if ($row['ROLE'] == 'Manager') {
            header("Location: admin/manager_dashboard.php");
        } else {
            header("Location: staff/staff_pos.php");
        }
        exit();
    } else {
        $error = "Invalid Email or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Login - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: var(--bakery-dark); display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; background: white; padding: 40px; border-radius: 10px; }
    </style>
</head>
<body>

<div class="login-card shadow">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-uppercase" style="color: var(--bakery-primary);">City Bakery</h2>
        <p class="text-muted">Staff Access Portal</p>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="sophia@citybakery.com" required>
        </div>
        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Default: 12345" required>
        </div>
        <button type="submit" name="login_btn" class="btn btn-bakery w-100">Login</button>
    </form>
    
    <div class="mt-3 text-center">
        <a href="index.php" class="text-decoration-none">Back to Customer Menu</a>
    </div>
</div>

</body>
</html>