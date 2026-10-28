<?php
session_start();
include '../config/db_connect.php';

// Security: Only Staff
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login.php");
    exit();
}

// 1. Handle "Mark Ready" Action (Kitchen -> Counter)
if (isset($_GET['ready_id'])) {
    $oid = $_GET['ready_id'];
    $sqlReady = "UPDATE CUSTOMER_ORDER SET orderStatus = 'Ready' WHERE orderId = :oid";
    $stmtReady = oci_parse($conn, $sqlReady);
    oci_bind_by_name($stmtReady, ":oid", $oid);
    if (oci_execute($stmtReady, OCI_COMMIT_ON_SUCCESS)) {
        header("Location: view_orders.php");
        exit();
    }
}

// 2. Handle "Complete Order" Action (Customer Paid)
if (isset($_GET['complete_id'])) {
    $oid = $_GET['complete_id'];
    $staffId = $_SESSION['staffId']; // The staff who collected payment
    
    // Update Status to Completed AND set the Staff ID
    $sqlUpd = "UPDATE CUSTOMER_ORDER SET orderStatus = 'Completed', staffId = :sid WHERE orderId = :oid";
    $stmtUpd = oci_parse($conn, $sqlUpd);
    oci_bind_by_name($stmtUpd, ":sid", $staffId);
    oci_bind_by_name($stmtUpd, ":oid", $oid);
    
    if (oci_execute($stmtUpd, OCI_COMMIT_ON_SUCCESS)) {
        // Redirect to the Receipt page so staff can print proof of payment
        header("Location: receipt.php?oid=$oid");
        exit();
    }
}

// 3. NEW: Handle "Cancel Order" Action (Restock Items)
if (isset($_GET['cancel_id'])) {
    $oid = $_GET['cancel_id'];
    
    // A. Fetch Items to Restore Stock
    $sqlItems = "SELECT prodId, quantity FROM ORDER_DETAIL WHERE orderId = :oid";
    $stmtItems = oci_parse($conn, $sqlItems);
    oci_bind_by_name($stmtItems, ":oid", $oid);
    oci_execute($stmtItems);
    
    $restoreSuccess = true;
    while ($item = oci_fetch_assoc($stmtItems)) {
        $pid = $item['PRODID'];
        $qty = $item['QUANTITY'];
        
        // Add quantity back to Product table
        $sqlRestock = "UPDATE PRODUCT SET prodQuantity = prodQuantity + :qty WHERE prodId = :pid";
        $stmtRestock = oci_parse($conn, $sqlRestock);
        oci_bind_by_name($stmtRestock, ":qty", $qty);
        oci_bind_by_name($stmtRestock, ":pid", $pid);
        
        if (!oci_execute($stmtRestock, OCI_NO_AUTO_COMMIT)) {
            $restoreSuccess = false;
            break;
        }
    }
    
    // B. Mark Order as Cancelled
    if ($restoreSuccess) {
        $sqlCancel = "UPDATE CUSTOMER_ORDER SET orderStatus = 'Cancelled' WHERE orderId = :oid";
        $stmtCancel = oci_parse($conn, $sqlCancel);
        oci_bind_by_name($stmtCancel, ":oid", $oid);
        
        if (oci_execute($stmtCancel, OCI_NO_AUTO_COMMIT)) {
            oci_commit($conn); // Commit both Stock Update & Status Update
            header("Location: view_orders.php");
            exit();
        } else {
            oci_rollback($conn);
        }
    } else {
        oci_rollback($conn);
    }
}

// Fetch Pending AND Ready Orders
$sqlPending = "SELECT o.orderId, o.orderDate, o.orderTime, o.orderStatus, c.custName, c.custPhone, 
               (SELECT SUM(d.total) FROM ORDER_DETAIL d WHERE d.orderId = o.orderId) AS GRAND_TOTAL,
               (SELECT LISTAGG(p.prodName || ' (x' || d.quantity || ')', ', ') WITHIN GROUP (ORDER BY p.prodName)
                FROM ORDER_DETAIL d 
                JOIN PRODUCT p ON d.prodId = p.prodId 
                WHERE d.orderId = o.orderId) AS ITEMS
               FROM CUSTOMER_ORDER o
               JOIN CUSTOMER c ON o.custId = c.custId
               WHERE o.orderStatus IN ('Pending', 'Ready')
               ORDER BY 
                CASE WHEN o.orderStatus = 'Ready' THEN 1 ELSE 2 END, 
                o.orderId ASC";

$stmtPending = oci_parse($conn, $sqlPending);
oci_execute($stmtPending);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Kitchen Queue - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <nav class="navbar navbar-custom px-3">
        <span class="navbar-brand mb-0 h1">City Bakery Staff</span>
        <div class="text-white">
            <a href="staff_pos.php" class="btn btn-sm btn-outline-light me-2">Back to POS</a>
            <a href="../logout.php" class="btn btn-sm btn-light text-dark">Logout</a>
        </div>
    </nav>

    <div class="container-fluid px-4 my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tasks text-warning me-2"></i>Live Order Queue</h2>
            <button class="btn btn-primary" onclick="window.location.reload()"><i class="fas fa-sync"></i> Refresh List</button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>Order ID</th>
                            <th>Time</th>
                            <th>Customer</th>
                            <th style="width: 35%;">Items to Prepare</th>
                            <th class="text-end">To Pay</th>
                            <th class="text-center" style="width: 200px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while ($row = oci_fetch_assoc($stmtPending)): 
                            $count++;
                            $isReady = ($row['ORDERSTATUS'] == 'Ready');
                            $rowClass = $isReady ? 'table-warning' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <?php if($isReady): ?>
                                    <span class="badge bg-primary fs-6">READY FOR PICKUP</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">PREPARING</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-bold">#<?php echo $row['ORDERID']; ?></span></td>
                            <td><?php echo $row['ORDERTIME']; ?></td>
                            <td>
                                <strong><?php echo $row['CUSTNAME']; ?></strong><br>
                                <small class="text-muted"><i class="fas fa-phone-alt"></i> <?php echo $row['CUSTPHONE']; ?></small>
                            </td>
                            <td>
                                <div class="small text-dark fw-bold">
                                    <?php echo $row['ITEMS']; ?>
                                </div>
                            </td>
                            <td class="fw-bold text-danger text-end">RM <?php echo number_format($row['GRAND_TOTAL'], 2); ?></td>
                            <td class="text-center">
                                <div class="d-grid gap-1">
                                    <?php if(!$isReady): ?>
                                        <!-- Step 1 Button: Mark Ready -->
                                        <a href="view_orders.php?ready_id=<?php echo $row['ORDERID']; ?>" 
                                           class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-bell me-1"></i> Alert Ready
                                        </a>
                                    <?php else: ?>
                                        <!-- Step 2 Button: Complete Payment -->
                                        <a href="view_orders.php?complete_id=<?php echo $row['ORDERID']; ?>" 
                                           class="btn btn-success btn-sm w-100"
                                           onclick="return confirm('Confirm payment received for Order #<?php echo $row['ORDERID']; ?>?')">
                                            <i class="fas fa-check-circle me-1"></i> Paid & Done
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Cancel Button -->
                                    <a href="view_orders.php?cancel_id=<?php echo $row['ORDERID']; ?>" 
                                       class="btn btn-link text-danger btn-sm text-decoration-none small"
                                       onclick="return confirm('WARNING: Are you sure you want to CANCEL Order #<?php echo $row['ORDERID']; ?>? \n\nThis will return items to stock.')">
                                        <i class="fas fa-times-circle"></i> Cancel Order
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if($count == 0): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">All caught up! No active orders.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>