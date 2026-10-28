<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['oid'])) {
    header("Location: manager_dashboard.php");
    exit();
}

$oid = $_GET['oid'];

// 1. Fetch Order Header
$sqlHead = "SELECT o.orderId, o.orderDate, o.orderTime, o.orderStatus, c.custName, c.custPhone, c.custEmail, s.staffName
            FROM CUSTOMER_ORDER o
            JOIN CUSTOMER c ON o.custId = c.custId
            JOIN STAFF s ON o.staffId = s.staffId
            WHERE o.orderId = :oid";
$stmtHead = oci_parse($conn, $sqlHead);
oci_bind_by_name($stmtHead, ":oid", $oid);
oci_execute($stmtHead);
$head = oci_fetch_assoc($stmtHead);

if (!$head) die("Order not found.");

// 2. Fetch Order Items
$sqlItems = "SELECT p.prodName, d.quantity, d.unitPrice, d.total
             FROM ORDER_DETAIL d
             JOIN PRODUCT p ON d.prodId = p.prodId
             WHERE d.orderId = :oid";
$stmtItems = oci_parse($conn, $sqlItems);
oci_bind_by_name($stmtItems, ":oid", $oid);
oci_execute($stmtItems);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order #<?php echo $oid; ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="mb-4">
            <a href="manager_dashboard.php" class="btn btn-outline-secondary btn-sm mb-2">&larr; Back to Dashboard</a>
            <h2 class="fw-bold">Order Details #<?php echo $head['ORDERID']; ?></h2>
        </div>

        <div class="row">
            <!-- Customer Info -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">Customer Info</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Name:</strong> <?php echo $head['CUSTNAME']; ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo $head['CUSTPHONE']; ?></p>
                        <p class="mb-0"><strong>Email:</strong> <?php echo $head['CUSTEMAIL']; ?></p>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Order Info</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Date:</strong> <?php echo $head['ORDERDATE']; ?> <?php echo $head['ORDERTIME']; ?></p>
                        <p class="mb-1"><strong>Staff:</strong> <?php echo $head['STAFFNAME']; ?></p>
                        <p class="mb-0"><strong>Status:</strong> 
                            <?php if($head['ORDERSTATUS'] == 'Completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Items Purchased</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grandTotal = 0;
                                while ($item = oci_fetch_assoc($stmtItems)): 
                                    $grandTotal += $item['TOTAL'];
                                ?>
                                <tr>
                                    <td><?php echo $item['PRODNAME']; ?></td>
                                    <td class="text-center"><?php echo $item['QUANTITY']; ?></td>
                                    <td class="text-end">RM <?php echo number_format($item['UNITPRICE'], 2); ?></td>
                                    <td class="text-end fw-bold">RM <?php echo number_format($item['TOTAL'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="table-secondary">
                                    <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                    <td class="text-end fw-bold fs-5">RM <?php echo number_format($grandTotal, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="text-end mt-4">
                    <button onclick="window.print()" class="btn btn-outline-dark"><i class="fas fa-print"></i> Print Invoice</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>