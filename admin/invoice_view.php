<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: invoice_list.php");
    exit();
}

$invId = $_GET['id'];

// 1. Fetch Invoice Header (Supplier Details + Invoice Summary)
$sqlHeader = "SELECT i.invId, i.supplyDate, i.invTotal, s.supName, s.supEmail, s.supPhone
              FROM INVOICE i
              JOIN SUPPLIER s ON i.supId = s.supId
              WHERE i.invId = :iid";

$stHeader = oci_parse($conn, $sqlHeader);
oci_bind_by_name($stHeader, ":iid", $invId);
oci_execute($stHeader);
$header = oci_fetch_assoc($stHeader);

if (!$header) {
    die("<div class='alert alert-danger m-5'>Invoice not found in the database.</div>");
}

// 2. Fetch Invoice Items (Products linked to this Invoice)
$sqlItems = "SELECT id.lineQuantity, id.unitCost, id.lineTotal, p.prodName
              FROM INVOICE_DETAIL id
              JOIN PRODUCT p ON id.prodId = p.prodId
              WHERE id.invId = :iid";

$stItems = oci_parse($conn, $sqlItems);
oci_bind_by_name($stItems, ":iid", $invId);
oci_execute($stItems);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Details #<?php echo $invId; ?> - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="mb-4 d-flex justify-content-between align-items-end">
            <div>
                <a href="invoice_list.php" class="btn btn-outline-secondary btn-sm mb-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to History
                </a>
                <h2 class="fw-bold m-0">Supply Invoice #<?php echo $invId; ?></h2>
            </div>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-dark">
                    <i class="fas fa-print me-2"></i>Print Invoice
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Supplier & Summary -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white fw-bold">Supplier Information</div>
                    <div class="card-body">
                        <h5 class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($header['SUPNAME']); ?></h5>
                        <p class="mb-1 text-muted small"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($header['SUPEMAIL']); ?></p>
                        <p class="mb-0 text-muted small"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($header['SUPPHONE']); ?></p>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold border-bottom">Batch Summary</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Supply Date:</span>
                            <span class="fw-bold"><?php echo date('d M Y', strtotime($header['SUPPLYDATE'])); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Total Cost:</span>
                            <span class="fw-bold text-success fs-5">RM <?php echo number_format($header['INVTOTAL'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Line Items -->
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Items in this Batch</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = oci_fetch_assoc($stItems)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['PRODNAME']); ?></td>
                                    <td class="text-center"><?php echo $item['LINEQUANTITY']; ?></td>
                                    <td class="text-end">RM <?php echo number_format($item['UNITCOST'], 2); ?></td>
                                    <td class="text-end fw-bold">RM <?php echo number_format($item['LINETOTAL'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="3" class="text-end fw-bold">GRAND TOTAL</td>
                                    <td class="text-end fw-bold">RM <?php echo number_format($header['INVTOTAL'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>