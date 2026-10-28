<?php
session_start();
include '../config/db_connect.php';

// Security
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login.php");
    exit();
}

$oid = $_GET['oid'];

// 1. Fetch Order Header (ADDED: o.order_discount)
$sqlHead = "SELECT o.orderId, o.orderDate, o.orderTime, c.custName, s.staffName, o.order_discount
            FROM CUSTOMER_ORDER o
            JOIN CUSTOMER c ON o.custId = c.custId
            JOIN STAFF s ON o.staffId = s.staffId
            WHERE o.orderId = :oid";
$stmtHead = oci_parse($conn, $sqlHead);
oci_bind_by_name($stmtHead, ":oid", $oid);
oci_execute($stmtHead);
$head = oci_fetch_assoc($stmtHead);

if (!$head) die("Order not found.");

// 2. Fetch Order Items (Join with Product)
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
    <title>Receipt #<?php echo $oid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Courier New', Courier, monospace; }
        .receipt-box {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-top: 5px solid #d35400; /* Bakery Orange */
        }
        .receipt-header { text-align: center; margin-bottom: 20px; }
        .dashed-line { border-top: 2px dashed #000; margin: 15px 0; }
        @media print {
            body * { visibility: hidden; }
            .receipt-box, .receipt-box * { visibility: visible; }
            .receipt-box { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; padding: 0; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="receipt-box">
        <div class="receipt-header">
            <h3 class="fw-bold m-0">CITY BAKERY</h3>
            <small>Jalan Sultan Ismail, Kuala Terengganu</small><br>
            <small>Tel: 09-666 7777</small>
        </div>

        <div class="dashed-line"></div>

        <div class="d-flex justify-content-between small">
            <span>Order #: <strong><?php echo $head['ORDERID']; ?></strong></span>
            <span><?php echo $head['ORDERDATE']; ?></span>
        </div>
        <div class="d-flex justify-content-between small">
            <span>Staff: <?php echo $head['STAFFNAME']; ?></span>
            <span><?php echo $head['ORDERTIME']; ?></span>
        </div>
        <div class="small mt-1">Cust: <?php echo $head['CUSTNAME']; ?></div>

        <div class="dashed-line"></div>

        <table class="table table-borderless table-sm small m-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-center">Qty</th>
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
                    <td class="text-end"><?php echo number_format($item['TOTAL'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <!-- NEW: Discount Logic -->
        <?php
            // Get discount from DB (default to 0 if null)
            $discountPercent = isset($head['ORDER_DISCOUNT']) ? $head['ORDER_DISCOUNT'] : 0;
            $discountAmt = $grandTotal * ($discountPercent / 100);
            $finalTotal = $grandTotal - $discountAmt;
        ?>

        <div class="d-flex justify-content-between small mb-1">
            <span>Subtotal</span>
            <span>RM <?php echo number_format($grandTotal, 2); ?></span>
        </div>

        <?php if ($discountPercent > 0): ?>
        <div class="d-flex justify-content-between small mb-1 text-danger">
            <span>Discount (<?php echo $discountPercent; ?>%)</span>
            <span>- RM <?php echo number_format($discountAmt, 2); ?></span>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between fw-bold fs-5 mt-2 border-top pt-2">
            <span>TOTAL</span>
            <span>RM <?php echo number_format($finalTotal, 2); ?></span>
        </div>

        <div class="dashed-line"></div>

        <div class="text-center small text-muted">
            <p class="mb-1">Thank you for your visit!</p>
            <p>Please come again.</p>
        </div>

        <div class="mt-4 text-center no-print d-grid gap-2">
            <button onclick="window.print()" class="btn btn-dark">Print Receipt</button>
            <a href="staff_pos.php" class="btn btn-outline-secondary">New Order</a>
        </div>
    </div>

</body>
</html>