<?php
session_start();
include '../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// 2. Handle Delete Action (if clicked)
if (isset($_GET['delete_id'])) {
    $delId = $_GET['delete_id'];
    $sqlDel = "DELETE FROM PRODUCT WHERE prodId = :pid";
    $stmtDel = oci_parse($conn, $sqlDel);
    oci_bind_by_name($stmtDel, ":pid", $delId);
    
    if (oci_execute($stmtDel)) {
        echo "<script>alert('Product deleted successfully!'); window.location.href='inventory_list.php';</script>";
    } else {
        $e = oci_error($stmtDel);
        echo "<script>alert('Error deleting: " . $e['message'] . "');</script>";
    }
}

// 3. Fetch All Products
// SORT LOGIC:
// 1. Expired Items (Critical)
// 2. Low Stock Items (Warning)
// 3. Healthy Items (Normal)
$sql = "SELECT * FROM PRODUCT 
        ORDER BY 
        CASE 
            WHEN PRODEXPDATE < TRUNC(SYSDATE) AND PRODQUANTITY > 0 THEN 1 
            WHEN PRODQUANTITY < PRODMINIMUM THEN 2 
            ELSE 3 
        END ASC, 
        PRODEXPDATE ASC";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4 bg-light">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Inventory Management</h2>
                <div>
                    <!-- Added Restock Button here -->
                    <a href="restock.php" class="btn btn-outline-primary me-2"><i class="fas fa-truck-loading"></i> Restock</a>
                    <a href="inventory_add.php" class="btn btn-bakery"><i class="fas fa-plus"></i> Add New Product</a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (RM)</th>
                                <th>Stock</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while (($row = oci_fetch_assoc($stmt)) != false): ?>
                                <?php 
                                    // Calculate Low Stock status
                                    $isLow = $row['PRODQUANTITY'] < $row['PRODMINIMUM'];
                                    
                                    // Calculate Expiry Status
                                    // Note: PRODEXPDATE comes as string "DD-MON-YY" from Oracle usually
                                    $expTimestamp = strtotime($row['PRODEXPDATE']);
                                    $currentTimestamp = time();
                                    $isExpired = ($expTimestamp < $currentTimestamp && $row['PRODQUANTITY'] > 0);
                                    
                                    // Determine Row Styles
                                    $stockClass = $isLow ? 'text-danger fw-bold' : 'text-success';
                                    $dateClass = $isExpired ? 'text-danger fw-bold' : 'text-dark';
                                    
                                    // Determine Badge
                                    if ($isExpired) {
                                        $statusBadge = '<span class="badge bg-danger">EXPIRED</span>';
                                        $rowClass = "table-danger"; // Optional: Highlight entire row
                                    } elseif ($isLow) {
                                        $statusBadge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                                        $rowClass = "table-warning"; // Optional: Highlight entire row
                                    } else {
                                        $statusBadge = '<span class="badge bg-success">In Stock</span>';
                                        $rowClass = "";
                                    }
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><?php echo $row['PRODID']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['PRODNAME']; ?></div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo $row['PRODCATEGORY']; ?></span></td>
                                    <td><?php echo number_format($row['PRODPRICE'], 2); ?></td>
                                    <td class="<?php echo $stockClass; ?>"><?php echo $row['PRODQUANTITY']; ?></td>
                                    <td class="<?php echo $dateClass; ?>">
                                        <?php echo $row['PRODEXPDATE']; ?>
                                        <?php if($isExpired): ?>
                                            <i class="fas fa-exclamation-circle text-danger ms-1" title="Expired!"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $statusBadge; ?></td>
                                    <td>
                                        <a href="inventory_edit.php?id=<?php echo $row['PRODID']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php if($isExpired): ?>
                                            <!-- Show Dispose Link if Expired -->
                                            <a href="dispose_stock.php?id=<?php echo $row['PRODID']; ?>" class="btn btn-sm btn-outline-danger" title="Dispose Waste"><i class="fas fa-trash-alt"></i></a>
                                        <?php else: ?>
                                            <a href="inventory_list.php?delete_id=<?php echo $row['PRODID']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this item?');" title="Delete"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>