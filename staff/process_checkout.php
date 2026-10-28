<?php
session_start();
include '../config/db_connect.php';

if (!isset($_POST['checkout_btn']) || empty($_SESSION['cart'])) {
    header("Location: staff_pos.php");
    exit();
}

$staffId = $_SESSION['staffId'];
$custId = $_POST['cust_id'];
// Get discount from session (default to 0 if not set)
$discount = isset($_SESSION['discount']) ? $_SESSION['discount'] : 0;

// 1. Generate New Order ID (USING SEQUENCE)
$sqlID = "SELECT SEQ_ORDER_ID.NEXTVAL AS NEXT_ID FROM DUAL";
$stmtID = oci_parse($conn, $sqlID);
oci_execute($stmtID);
$rowID = oci_fetch_assoc($stmtID);
$newOrderId = $rowID['NEXT_ID'];

// 2. Insert into CUSTOMER_ORDER (Added ORDER_DISCOUNT)
$sqlOrder = "INSERT INTO CUSTOMER_ORDER (orderId, orderDate, orderTime, custId, staffId, orderStatus, order_discount) 
             VALUES (:oid, TRUNC(SYSDATE), TO_CHAR(SYSDATE, 'HH24:MI'), :cid, :sid, 'Completed', :disc)";
$stmtOrder = oci_parse($conn, $sqlOrder);

oci_bind_by_name($stmtOrder, ":oid", $newOrderId);
oci_bind_by_name($stmtOrder, ":cid", $custId);
oci_bind_by_name($stmtOrder, ":sid", $staffId);
oci_bind_by_name($stmtOrder, ":disc", $discount); // Bind Discount

$r1 = oci_execute($stmtOrder, OCI_NO_AUTO_COMMIT); 

// 3. Loop Cart Items
$success = true;

foreach ($_SESSION['cart'] as $item) {
    $prodId = $item['id'];
    $qty = $item['qty'];
    $price = $item['price'];
    $total = $qty * $price;

    // A. Insert Detail
    $sqlDetail = "INSERT INTO ORDER_DETAIL (orderId, prodId, quantity, unitPrice, total) 
                  VALUES (:oid, :pid, :qty, :price, :total)";
    $stmtDetail = oci_parse($conn, $sqlDetail);
    
    oci_bind_by_name($stmtDetail, ":oid", $newOrderId);
    oci_bind_by_name($stmtDetail, ":pid", $prodId);
    oci_bind_by_name($stmtDetail, ":qty", $qty);
    oci_bind_by_name($stmtDetail, ":price", $price);
    oci_bind_by_name($stmtDetail, ":total", $total);
    
    $r2 = oci_execute($stmtDetail, OCI_NO_AUTO_COMMIT);

    // B. Deduct Stock handled by TRIGGER TRG_DEDUCT_STOCK
    $r3 = true; 

    if (!$r2) {
        $success = false;
        break;
    }
}

// 4. Commit
if ($success && $r1) {
    oci_commit($conn);
    unset($_SESSION['cart']);
    unset($_SESSION['discount']); // Clear discount after success
    header("Location: receipt.php?oid=$newOrderId");
    exit();
} else {
    oci_rollback($conn);
    $e = oci_error($stmtOrder);
    echo "Error processing order: " . $e['message'];
}
?>