<?php
session_start();
include '../config/db_connect.php';

if (!isset($_GET['oid'])) {
    header("Location: menu.php");
    exit();
}

$oid = $_GET['oid'];

// Fetch Order Details
$sql = "SELECT o.orderId, o.orderDate, o.orderTime, o.orderStatus, c.custName,
        (SELECT SUM(d.total) FROM ORDER_DETAIL d WHERE d.orderId = o.orderId) as GRAND_TOTAL
        FROM CUSTOMER_ORDER o
        JOIN CUSTOMER c ON o.custId = c.custId
        WHERE o.orderId = :oid";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":oid", $oid);
oci_execute($stmt);
$order = oci_fetch_assoc($stmt);

if (!$order) { die("Order not found."); }

// Fetch Items
$sqlItems = "SELECT p.prodName, d.quantity, d.total 
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
    <title>Order Confirmed - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #2c3e50; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .ticket-wrapper {
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
            animation: slideUp 0.8s ease-out;
        }
        
        .ticket {
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        /* Jagged Edge Effect */
        .ticket::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 20px;
            background: radial-gradient(circle, transparent, transparent 50%, white 50%, white 100%) -7px -8px / 16px 16px repeat-x;
            transform: rotate(180deg);
        }

        .ticket-header { background: var(--bakery-primary); padding: 30px; text-align: center; color: white; }
        .ticket-body { padding: 30px; }
        .ticket-id { font-family: 'Playfair Display', serif; letter-spacing: 2px; }
        .dashed-line { border-top: 2px dashed #eee; margin: 20px 0; }
        
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        @media print {
            body { background: white; }
            .ticket-wrapper { filter: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="ticket-wrapper">
        <div class="ticket">
            <div class="ticket-header">
                <i class="fas fa-check-circle fa-3x mb-3 text-white-50"></i>
                <h4 class="fw-bold m-0">Order Received!</h4>
                <p class="small opacity-75 mb-0">City Bakery System</p>
            </div>
            
            <div class="ticket-body">
                <div class="text-center mb-4">
                    <small class="text-muted text-uppercase fw-bold">Order Number</small>
                    <h1 class="display-4 fw-bold text-dark ticket-id">#<?php echo $order['ORDERID']; ?></h1>
                    <span class="badge bg-warning text-dark px-3 rounded-pill">Status: <?php echo $order['ORDERSTATUS']; ?></span>
                </div>

                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span><?php echo $order['ORDERDATE']; ?></span>
                    <span><?php echo $order['ORDERTIME']; ?></span>
                </div>
                <div class="small text-muted mb-3 text-center">Customer: <strong><?php echo $order['CUSTNAME']; ?></strong></div>

                <div class="dashed-line"></div>

                <ul class="list-unstyled mb-0 small">
                    <?php while ($item = oci_fetch_assoc($stmtItems)): ?>
                    <li class="d-flex justify-content-between mb-2">
                        <span><span class="fw-bold"><?php echo $item['QUANTITY']; ?>x</span> <?php echo $item['PRODNAME']; ?></span>
                        <span class="fw-bold">RM <?php echo number_format($item['TOTAL'], 2); ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>

                <div class="dashed-line"></div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="fs-5 fw-bold text-dark">Total</span>
                    <span class="fs-4 fw-bold text-success">RM <?php echo number_format($order['GRAND_TOTAL'], 2); ?></span>
                </div>
                
                <div class="mt-4 pt-3 text-center no-print d-grid gap-2">
                    <p class="small text-muted mb-2">Please show this screen at the counter.</p>
                    <button onclick="window.print()" class="btn btn-outline-dark btn-sm rounded-pill">Save Receipt</button>
                    <a href="menu.php" class="btn btn-primary rounded-pill fw-bold">Back to Menu</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>