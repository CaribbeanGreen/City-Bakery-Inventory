<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// Check if ID exists in URL
if (!isset($_GET['id'])) {
    header("Location: customer_manage.php");
    exit();
}

$id = $_GET['id'];
$msg = "";

// 1. Fetch Data
$sql = "SELECT * FROM CUSTOMER WHERE custId = :id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":id", $id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if (!$row) die("Customer not found.");

// 2. Handle Update
if (isset($_POST['update_btn'])) {
    $nm = $_POST['cName'];
    $ph = $_POST['cPhone'];
    $em = $_POST['cEmail'];
    // Removed Address from variables

    // Updated SQL (Removed custAddress)
    $sqlUpd = "UPDATE CUSTOMER SET custName=:nm, custPhone=:ph, custEmail=:em WHERE custId=:id";
    $stUpd = oci_parse($conn, $sqlUpd);
    
    oci_bind_by_name($stUpd, ":nm", $nm);
    oci_bind_by_name($stUpd, ":ph", $ph);
    oci_bind_by_name($stUpd, ":em", $em);
    oci_bind_by_name($stUpd, ":id", $id);

    if (oci_execute($stUpd, OCI_COMMIT_ON_SUCCESS)) {
        echo "<script>alert('Customer updated!'); window.location.href='customer_manage.php';</script>";
    } else {
        $e = oci_error($stUpd);
        $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <h2 class="fw-bold mb-4">Edit Customer (ID: <?php echo $id; ?>)</h2>
        <?php echo $msg; ?>

        <div class="card shadow-sm border-0 p-4" style="max-width: 600px;">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <input type="text" name="cName" class="form-control" value="<?php echo htmlspecialchars($row['CUSTNAME']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone</label>
                    <input type="text" name="cPhone" class="form-control" value="<?php echo htmlspecialchars($row['CUSTPHONE']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="cEmail" class="form-control" value="<?php echo htmlspecialchars($row['CUSTEMAIL']); ?>" required>
                </div>
                
                <!-- Address Field Completely Removed -->

                <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-2">
                    <a href="customer_manage.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_btn" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>