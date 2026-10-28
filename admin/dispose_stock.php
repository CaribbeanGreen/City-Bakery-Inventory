<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$prodId = isset($_GET['id']) ? $_GET['id'] : 0;

// 1. Fetch Current Product Data
$sql = "SELECT prodName, prodQuantity, prodExpDate FROM PRODUCT WHERE prodId = :pid";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":pid", $prodId);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if (!$row) {
    header("Location: manager_dashboard.php");
    exit();
}

// 2. Handle Disposal Submission
if (isset($_POST['confirm_dispose'])) {
    $disposeQty = (int)$_POST['dispose_qty'];
    
    // Validation: Sanity check to prevent negative stock
    if ($disposeQty > $row['PRODQUANTITY']) {
        $msg = "<div class='alert alert-danger'>Error: You cannot dispose more than the current stock level (" . $row['PRODQUANTITY'] . ").</div>";
    } elseif ($disposeQty <= 0) {
        $msg = "<div class='alert alert-danger'>Error: Please enter a valid quantity greater than 0.</div>";
    } else {
        // SQL: Simply subtract Qty. 
        // With Single Batch Policy, we don't need to manage multiple dates. We just reduce the current pile.
        $sqlUpdate = "UPDATE PRODUCT SET 
                      prodQuantity = prodQuantity - :dqty
                      WHERE prodId = :pid";
        
        $stmtUpd = oci_parse($conn, $sqlUpdate);
        oci_bind_by_name($stmtUpd, ":dqty", $disposeQty);
        oci_bind_by_name($stmtUpd, ":pid", $prodId);
        
        if (oci_execute($stmtUpd, OCI_COMMIT_ON_SUCCESS)) {
            echo "<script>
                    alert('Waste recorded! Removed $disposeQty items from inventory.');
                    window.location.href='manager_dashboard.php';
                  </script>";
            exit();
        } else {
            $e = oci_error($stmtUpd);
            $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispose Stock - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="container" style="max-width: 600px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-danger m-0">Dispose Stock</h2>
                <a href="manager_dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">Back to Dashboard</a>
            </div>
            
            <?php echo $msg; ?>

            <div class="card shadow-sm border-0 border-top border-danger border-5">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-1"><?php echo $row['PRODNAME']; ?></h4>
                    <div class="d-flex gap-3 mb-4 text-muted small">
                        <span>Current Stock: <strong class="text-dark"><?php echo $row['PRODQUANTITY']; ?></strong></span>
                        <span>&bull;</span>
                        <span>Expiry Date: <strong class="text-danger"><?php echo $row['PRODEXPDATE']; ?></strong></span>
                    </div>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Quantity to Dispose</label>
                            <!-- UX: Default value is set to FULL STOCK for quick "Expire All" action -->
                            <input type="number" name="dispose_qty" class="form-control form-control-lg" max="<?php echo $row['PRODQUANTITY']; ?>" min="1" value="<?php echo $row['PRODQUANTITY']; ?>" required>
                            <div class="form-text text-muted">
                                Defaulted to full stock. Adjust if you are only throwing away a few damaged items.
                            </div>
                        </div>

                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div class="small">
                                <strong>Warning:</strong> This action cannot be undone. The selected items will be removed from inventory immediately.
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="confirm_dispose" class="btn btn-danger btn-lg shadow-sm">
                                <i class="fas fa-trash-alt me-2"></i> Confirm Disposal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>