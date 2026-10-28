<?php
session_start();
include '../config/db_connect.php';

$order = null;
$error = "";

if (isset($_POST['btn_track'])) {
    $oid = $_POST['order_id'];

    $sql = "SELECT o.orderId, o.orderDate, o.orderStatus, c.custName, 
            (SELECT SUM(total) FROM ORDER_DETAIL WHERE orderId = o.orderId) as TOTAL_AMT
            FROM CUSTOMER_ORDER o 
            JOIN CUSTOMER c ON o.custId = c.custId 
            WHERE o.orderId = :oid";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":oid", $oid);
    oci_execute($stmt);
    $order = oci_fetch_assoc($stmt);

    if (!$order) {
        $error = "We couldn't find Order #$oid. Please check the receipt.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Order - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #fdf2e9; height: 100vh; display: flex; flex-direction: column; }
        .track-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            background: white;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: auto;
        }
        .status-icon { font-size: 4rem; margin-bottom: 20px; }
        .form-control { border-radius: 50px; padding: 12px 20px; text-align: center; font-size: 1.1rem; border: 2px solid #eee; }
        .form-control:focus { border-color: var(--bakery-primary); box-shadow: none; }
        
        /* Pulse Animation for Ready Status */
        .pulse-blue { animation: pulse 2s infinite; color: #0d6efd; }
        @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.8; } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark p-3">
        <div class="container justify-content-center">
            <a class="navbar-brand fs-4" href="../index.php"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</a>
        </div>
    </nav>

    <div class="container flex-grow-1 d-flex align-items-center">
        <div class="track-card text-center">
            <h2 class="fw-bold mb-4" style="font-family: 'Playfair Display', serif;">Track Your Order</h2>
            
            <form method="POST" class="mb-4">
                <div class="input-group mb-3">
                    <input type="number" name="order_id" class="form-control" placeholder="Enter Order ID (e.g. 6005)" required value="<?php echo isset($_POST['order_id']) ? $_POST['order_id'] : ''; ?>">
                    <button class="btn btn-warning rounded-pill px-4 ms-2 fw-bold" type="submit" name="btn_track">Track</button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger rounded-pill"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="card bg-light border-0 rounded-4 p-4 mt-4">
                    
                    <?php if ($order['ORDERSTATUS'] == 'Completed'): ?>
                        <!-- COMPLETED STATE -->
                        <div class="text-success status-icon"><i class="fas fa-check-circle"></i></div>
                        <h3 class="fw-bold text-success">Order Completed</h3>
                        <p class="text-muted">Thank you for shopping with us!</p>
                    
                    <?php elseif ($order['ORDERSTATUS'] == 'Ready'): ?>
                        <!-- READY STATE (NEW) -->
                        <div class="status-icon pulse-blue"><i class="fas fa-bell"></i></div>
                        <h3 class="fw-bold text-primary">Ready for Pickup!</h3>
                        <p class="text-dark">Your items are packed and waiting at the counter. Please show this screen to the staff.</p>
                        <div class="alert alert-primary border-0 small">
                            <i class="fas fa-info-circle me-1"></i> Please prepare payment.
                        </div>

                    <?php else: ?>
                        <!-- PENDING STATE -->
                        <div class="text-warning status-icon"><i class="fas fa-hourglass-half"></i></div>
                        <h3 class="fw-bold text-dark">Preparing...</h3>
                        <p class="text-muted">We have received your order and are packing it now.</p>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="row text-start small">
                        <div class="col-6 text-muted">Customer</div>
                        <div class="col-6 text-end fw-bold text-dark"><?php echo $order['CUSTNAME']; ?></div>
                        
                        <div class="col-6 text-muted">Date</div>
                        <div class="col-6 text-end fw-bold text-dark"><?php echo $order['ORDERDATE']; ?></div>
                        
                        <div class="col-6 text-muted mt-2">Total Amount</div>
                        <div class="col-6 text-end fw-bold text-primary fs-5 mt-2">RM <?php echo number_format($order['TOTAL_AMT'], 2); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="menu.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i> Back to Menu</a>
            </div>
        </div>
    </div>

</body>
</html>