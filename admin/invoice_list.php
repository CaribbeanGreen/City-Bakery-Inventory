<?php
session_start();
include '../config/db_connect.php';

// Security Check: Only Managers/Inventory Staff
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// Fetch all invoices joined with Supplier table
$sql = "SELECT i.invId, i.supplyDate, i.invTotal, s.supName 
        FROM INVOICE i 
        JOIN SUPPLIER s ON i.supId = s.supId 
        ORDER BY i.invId DESC";

$stid = oci_parse($conn, $sql);
oci_execute($stid);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Restock History - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Restock History (Invoices)</h2>
            <a href="restock.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Record New Supply
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Inv ID</th>
                            <th>Supply Date</th>
                            <th>Supplier Name</th>
                            <th class="text-end">Invoice Total</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasRecords = false;
                        while ($row = oci_fetch_assoc($stid)): 
                            $hasRecords = true;
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?php echo $row['INVID']; ?></span></td>
                            <td><?php echo date('d-M-Y', strtotime($row['SUPPLYDATE'])); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['SUPNAME']); ?></td>
                            <td class="text-end fw-bold text-success">RM <?php echo number_format($row['INVTOTAL'], 2); ?></td>
                            <td class="text-center">
                                <a href="invoice_view.php?id=<?php echo $row['INVID']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> Details
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if (!$hasRecords): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-info-circle me-2"></i>No restock invoices found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>