<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// 1. Handle Add Customer
if (isset($_POST['add_cust_btn'])) {
    $cName = $_POST['cName'];
    $cPhone = $_POST['cPhone'];
    $cEmail = $_POST['cEmail'];
    // Removed Address

    // Generate ID
    $sqlID = "SELECT NVL(MAX(custId), 4000) + 1 AS NEXT_ID FROM CUSTOMER";
    $stID = oci_parse($conn, $sqlID);
    oci_execute($stID);
    $rID = oci_fetch_assoc($stID);
    $newId = $rID['NEXT_ID'];

    // Insert without Address
    $sqlIns = "INSERT INTO CUSTOMER (custId, custName, custPhone, custEmail) 
               VALUES (:id, :nm, :ph, :em)";
    $stIns = oci_parse($conn, $sqlIns);
    oci_bind_by_name($stIns, ":id", $newId);
    oci_bind_by_name($stIns, ":nm", $cName);
    oci_bind_by_name($stIns, ":ph", $cPhone);
    oci_bind_by_name($stIns, ":em", $cEmail);

    if (@oci_execute($stIns, OCI_COMMIT_ON_SUCCESS)) {
        $msg = "<div class='alert alert-success'>Customer added successfully!</div>";
    } else {
        $e = oci_error($stIns);
        $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
    }
}

// 2. Handle Delete Customer
if (isset($_GET['del_id'])) {
    $delId = $_GET['del_id'];
    $sqlDel = "DELETE FROM CUSTOMER WHERE custId = :id";
    $stDel = oci_parse($conn, $sqlDel);
    oci_bind_by_name($stDel, ":id", $delId);
    
    // Note: This will fail if customer has orders (Foreign Key constraint).
    // We catch the error to inform the user.
    if (@oci_execute($stDel, OCI_COMMIT_ON_SUCCESS)) {
        echo "<script>alert('Customer deleted successfully!'); window.location.href='customer_manage.php';</script>";
    } else {
        $e = oci_error($stDel);
        if ($e['code'] == 2292) { // Integrity constraint violated
            $msg = "<div class='alert alert-danger'>Cannot delete customer: They have existing orders in the system.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
        }
    }
}

// 3. Fetch Customers
$sqlC = "SELECT * FROM CUSTOMER ORDER BY custId DESC";
$stC = oci_parse($conn, $sqlC);
oci_execute($stC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Management - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar { height: 100vh !important; position: sticky; top: 0; }
    </style>
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <h2 class="fw-bold mb-4">Customer Management</h2>
        <?php echo $msg; ?>

        <div class="row">
            <!-- Left: Customer List -->
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Customer Database</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = oci_fetch_assoc($stC)): ?>
                                <tr>
                                    <td><?php echo $row['CUSTID']; ?></td>
                                    <td class="fw-bold"><?php echo $row['CUSTNAME']; ?></td>
                                    <td>
                                        <div class="small"><i class="fas fa-phone me-1 text-muted"></i><?php echo $row['CUSTPHONE']; ?></div>
                                        <div class="small"><i class="fas fa-envelope me-1 text-muted"></i><?php echo $row['CUSTEMAIL']; ?></div>
                                    </td>
                                    <td>
                                        <a href="customer_edit.php?id=<?php echo $row['CUSTID']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        <a href="customer_manage.php?del_id=<?php echo $row['CUSTID']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer? Warning: This creates issues if they have orders.')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Add Form -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-user-plus me-2"></i>Add Customer</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="cName" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Phone</label>
                                <input type="text" name="cPhone" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="cEmail" class="form-control" required>
                            </div>
                            <!-- Address Field Removed -->
                            <div class="d-grid">
                                <button type="submit" name="add_cust_btn" class="btn btn-primary">Save Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>