<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// 1. Handle Add Supplier
if (isset($_POST['add_sup_btn'])) {
    $name = $_POST['supName'];
    $email = $_POST['supEmail'];
    $phone = $_POST['supPhone'];

    // Generate ID
    $sqlID = "SELECT NVL(MAX(supId), 3000) + 1 AS NEXT_ID FROM SUPPLIER";
    $stID = oci_parse($conn, $sqlID);
    oci_execute($stID);
    $rID = oci_fetch_assoc($stID);
    $newId = $rID['NEXT_ID'];

    $sqlIns = "INSERT INTO SUPPLIER (supId, supName, supEmail, supPhone) VALUES (:id, :nm, :em, :ph)";
    $stIns = oci_parse($conn, $sqlIns);
    oci_bind_by_name($stIns, ":id", $newId);
    oci_bind_by_name($stIns, ":nm", $name);
    oci_bind_by_name($stIns, ":em", $email);
    oci_bind_by_name($stIns, ":ph", $phone);

    if (oci_execute($stIns, OCI_COMMIT_ON_SUCCESS)) {
        $msg = "<div class='alert alert-success'>Supplier '$name' added successfully!</div>";
    } else {
        $e = oci_error($stIns);
        $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
    }
}

// 2. Fetch Suppliers
$sqlFetch = "SELECT * FROM SUPPLIER ORDER BY supName ASC";
$stFetch = oci_parse($conn, $sqlFetch);
oci_execute($stFetch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Management - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Supplier Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupModal">
                <i class="fas fa-plus me-2"></i>Add New Supplier
            </button>
        </div>

        <?php echo $msg; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Supplier Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = oci_fetch_assoc($stFetch)): ?>
                        <tr>
                            <td><?php echo $row['SUPID']; ?></td>
                            <td class="fw-bold"><?php echo $row['SUPNAME']; ?></td>
                            <td><?php echo $row['SUPEMAIL']; ?></td>
                            <td><?php echo $row['SUPPHONE']; ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Supplier Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="supName" class="form-control" placeholder="e.g., Global Flour Supplies" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="supEmail" class="form-control" placeholder="supplier@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="supPhone" class="form-control" placeholder="012-3456789" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#addSupModal">Cancel</button>
                    <button type="submit" name="add_sup_btn" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>