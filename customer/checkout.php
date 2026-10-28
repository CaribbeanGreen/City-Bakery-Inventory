<?php
session_start();
include '../config/db_connect.php';

// Redirect if empty
if (empty($_SESSION['cust_cart'])) {
    header("Location: menu.php");
    exit();
}

// Calculate Total for UX Display
$cartTotal = 0;
foreach ($_SESSION['cust_cart'] as $item) {
    $cartTotal += $item['price'] * $item['qty'];
}

$msg = "";

// Initialize variables (Priority: POST -> Cookie -> Empty)
$cName = isset($_POST['cName']) ? $_POST['cName'] : (isset($_COOKIE['bakery_name']) ? $_COOKIE['bakery_name'] : '');
$cPhone = isset($_POST['cPhone']) ? $_POST['cPhone'] : (isset($_COOKIE['bakery_phone']) ? $_COOKIE['bakery_phone'] : '');
$cEmail = isset($_POST['cEmail']) ? $_POST['cEmail'] : (isset($_COOKIE['bakery_email']) ? $_COOKIE['bakery_email'] : '');

if (isset($_POST['place_order'])) {
    $cName = $_POST['cName'];
    $cPhone = $_POST['cPhone'];
    $cEmail = $_POST['cEmail'];
    
    // Cookie Logic
    if (isset($_POST['remember_me'])) {
        setcookie('bakery_name', $cName, time() + (86400 * 30), "/");
        setcookie('bakery_phone', $cPhone, time() + (86400 * 30), "/");
        setcookie('bakery_email', $cEmail, time() + (86400 * 30), "/");
    } else {
        if (isset($_COOKIE['bakery_name'])) {
            setcookie('bakery_name', '', time() - 3600, "/");
            setcookie('bakery_phone', '', time() - 3600, "/");
            setcookie('bakery_email', '', time() - 3600, "/");
        }
    }
    
    $onlineStaffId = 101; 
    $targetCustId = 0;
    $orderStatus = 'Pending'; 

    // --- TRANSACTION LOGIC (UNCHANGED - USES SEQUENCES) ---
    // 1. Check Customer
    $sqlCheck = "SELECT CUSTID FROM CUSTOMER WHERE CUSTEMAIL = :em";
    $stmtCheck = oci_parse($conn, $sqlCheck);
    oci_bind_by_name($stmtCheck, ":em", $cEmail);
    oci_execute($stmtCheck);
    $existingRow = oci_fetch_assoc($stmtCheck);

    if ($existingRow) {
        $targetCustId = $existingRow['CUSTID'];
    } else {
        // SEQUENCE FOR CUSTOMER
        $sqlCID = "SELECT SEQ_CUST_ID.NEXTVAL AS NEXT_ID FROM DUAL";
        $stmtCID = oci_parse($conn, $sqlCID);
        oci_execute($stmtCID);
        $rowCID = oci_fetch_assoc($stmtCID);
        $targetCustId = $rowCID['NEXT_ID'];

        $sqlCust = "INSERT INTO CUSTOMER (custId, custName, custPhone, custEmail) 
                    VALUES (:id, :nm, :ph, :em)";
        $stmtCust = oci_parse($conn, $sqlCust);
        oci_bind_by_name($stmtCust, ":id", $targetCustId);
        oci_bind_by_name($stmtCust, ":nm", $cName);
        oci_bind_by_name($stmtCust, ":ph", $cPhone);
        oci_bind_by_name($stmtCust, ":em", $cEmail);
        
        if (!oci_execute($stmtCust, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmtCust);
            $msg = "<div class='alert alert-danger shadow-sm border-0 mb-4'><i class='fas fa-exclamation-circle me-2'></i>Error creating profile: " . $e['message'] . "</div>";
            $targetCustId = 0; 
        }
    }
    
    // 2. Create Order
    if ($targetCustId > 0) {
        // SEQUENCE FOR ORDER
        $sqlOID = "SELECT SEQ_ORDER_ID.NEXTVAL AS NEXT_ID FROM DUAL";
        $stmtOID = oci_parse($conn, $sqlOID);
        oci_execute($stmtOID);
        $rowOID = oci_fetch_assoc($stmtOID);
        $newOrderId = $rowOID['NEXT_ID'];

        $sqlOrder = "INSERT INTO CUSTOMER_ORDER (orderId, orderDate, orderTime, custId, staffId, orderStatus) 
                     VALUES (:oid, TRUNC(SYSDATE), TO_CHAR(SYSDATE, 'HH24:MI'), :cid, :sid, :stat)";
        $stmtOrder = oci_parse($conn, $sqlOrder);
        oci_bind_by_name($stmtOrder, ":oid", $newOrderId);
        oci_bind_by_name($stmtOrder, ":cid", $targetCustId);
        oci_bind_by_name($stmtOrder, ":sid", $onlineStaffId);
        oci_bind_by_name($stmtOrder, ":stat", $orderStatus);
        
        $r1 = oci_execute($stmtOrder, OCI_NO_AUTO_COMMIT);

        // 3. Insert Items
        $r2 = true;
        foreach ($_SESSION['cust_cart'] as $item) {
            $pid = $item['id'];
            $qty = $item['qty'];
            $price = $item['price'];
            $total = $qty * $price;

            $sqlDet = "INSERT INTO ORDER_DETAIL (orderId, prodId, quantity, unitPrice, total) 
                       VALUES (:oid, :pid, :qty, :price, :total)";
            $stmtDet = oci_parse($conn, $sqlDet);
            oci_bind_by_name($stmtDet, ":oid", $newOrderId);
            oci_bind_by_name($stmtDet, ":pid", $pid);
            oci_bind_by_name($stmtDet, ":qty", $qty);
            oci_bind_by_name($stmtDet, ":price", $price);
            oci_bind_by_name($stmtDet, ":total", $total);
            $execDet = oci_execute($stmtDet, OCI_NO_AUTO_COMMIT);
            
            // Note: Stock update is handled by SQL Trigger TRG_DEDUCT_STOCK
            
            if (!$execDet) {
                $r2 = false;
                break;
            }
        }

        if ($r1 && $r2) {
            oci_commit($conn);
            unset($_SESSION['cust_cart']);
            header("Location: order_success.php?oid=$newOrderId");
            exit();
        } else {
            oci_rollback($conn);
            $msg = "<div class='alert alert-danger shadow-sm border-0 mb-4'>Transaction Failed. Please try again.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - City Bakery</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #fdf2e9; min-height: 100vh; display: flex; flex-direction: column; }
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Playfair Display', serif; }
        
        .checkout-container { max-width: 600px; margin: auto; padding: 40px 20px; }
        
        .checkout-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            background: white;
            overflow: hidden;
        }
        
        .form-floating > .form-control { border-radius: 10px; border: 1px solid #eee; }
        .form-floating > .form-control:focus { border-color: var(--bakery-primary); box-shadow: 0 0 0 0.25rem rgba(211, 84, 0, 0.1); }
        
        .pickup-alert {
            background-color: #fff8e1;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark p-3 shadow-sm">
        <div class="container justify-content-center">
            <span class="navbar-brand fs-3 m-0"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</span>
        </div>
    </nav>

    <div class="checkout-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Final Step</h2>
            <p class="text-muted">You are just one tap away from deliciousness.</p>
        </div>
        
        <?php echo $msg; ?>

        <div class="card checkout-card p-4 p-md-5">
            <form method="POST">
                <h5 class="mb-4 text-dark pb-2 border-bottom">Contact Information</h5>
                
                <div class="form-floating mb-3">
                    <input type="text" name="cName" class="form-control" id="fName" placeholder="Name" value="<?php echo htmlspecialchars($cName); ?>" required>
                    <label for="fName">Full Name</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" name="cPhone" class="form-control" id="fPhone" placeholder="Phone" value="<?php echo htmlspecialchars($cPhone); ?>" required>
                    <label for="fPhone">Phone Number</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="email" name="cEmail" class="form-control" id="fEmail" placeholder="Email" value="<?php echo htmlspecialchars($cEmail); ?>" required>
                    <label for="fEmail">Email Address</label>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" checked>
                    <label class="form-check-label text-muted small" for="rememberMe">
                        Securely save my details for next time
                    </label>
                </div>

                <div class="alert pickup-alert d-flex align-items-center mb-4 p-3" role="alert">
                    <div class="fs-1 text-warning me-3"><i class="fas fa-store"></i></div>
                    <div>
                        <strong class="text-dark d-block">Self Pickup Only</strong>
                        <span class="small text-muted">Please pay at the counter (Cash/QR) when you collect your order.</span>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    <button type="submit" name="place_order" class="btn btn-dark btn-lg py-3 rounded-pill shadow-sm fw-bold">
                        Confirm Order &bull; RM <?php echo number_format($cartTotal, 2); ?>
                    </button>
                    <a href="cart.php" class="btn btn-outline-secondary rounded-pill border-0">Back to Tray</a>
                </div>
            </form>
        </div>
        
        <div class="text-center mt-4 text-muted small opacity-75">
            <i class="fas fa-lock me-1"></i> Your information is processed securely.
        </div>
    </div>

</body>
</html>