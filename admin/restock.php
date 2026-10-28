<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// Handle Multi-Item Submission
if (isset($_POST['submit_restock'])) {
    $supId = $_POST['supId'];
    $supplyDate = $_POST['supplyDate'];
    $productIds = $_POST['prodId'];
    $quantities = $_POST['qty'];
    $unitCosts = $_POST['cost'];
    $expiryDates = $_POST['exp'];

    // --- STRICT POLICY CHECK ---
    // Rule 1: Cannot restock if item is EXPIRED but still has quantity (Must Dispose First).
    // Rule 2: Cannot restock if item is HEALTHY and has PLENTY of stock (Prevent Overstock).
    
    $expiredConflict = [];
    $overstockConflict = [];
    
    foreach ($productIds as $pid) {
        $sqlCheck = "SELECT prodName, prodQuantity, prodExpDate FROM PRODUCT WHERE prodId = :pid";
        $stmtCheck = oci_parse($conn, $sqlCheck);
        oci_bind_by_name($stmtCheck, ":pid", $pid);
        oci_execute($stmtCheck);
        $rowC = oci_fetch_assoc($stmtCheck);

        if ($rowC) {
            $expDate = strtotime($rowC['PRODEXPDATE']);
            $today = time();
            $isExpired = ($expDate < $today);
            $hasStock = ($rowC['PRODQUANTITY'] > 0);

            // Check Rule 1: Expired items must be disposed of (qty set to 0) before restocking
            if ($isExpired && $hasStock) {
                $expiredConflict[] = $rowC['PRODNAME'];
            }
            
            // Check Rule 2: Prevent restocking if we have healthy stock above minimum
            // This allows restocking non-expired items ONLY if they are running low.
             $sqlMin = "SELECT prodMinimum FROM PRODUCT WHERE prodId = :pid";
             $stmtMin = oci_parse($conn, $sqlMin);
             oci_bind_by_name($stmtMin, ":pid", $pid);
             oci_execute($stmtMin);
             $rowMin = oci_fetch_assoc($stmtMin);
             
             if (!$isExpired && $rowC['PRODQUANTITY'] > $rowMin['PRODMINIMUM']) {
                 $overstockConflict[] = $rowC['PRODNAME'];
             }
        }
    }

    if (!empty($expiredConflict)) {
        $names = implode(", ", $expiredConflict);
        echo "<script>
            alert('CRITICAL SAFETY ERROR: The following items are EXPIRED but still have stock:\\n$names\\n\\nYou MUST dispose of these items first before adding new stock. Go to Dashboard > Dispose.'); 
            window.location.href='manager_dashboard.php';
        </script>";
        exit();
    }

    if (!empty($overstockConflict)) {
        $names = implode(", ", $overstockConflict);
        echo "<script>
            alert('Policy Violation: The following items still have sufficient fresh stock:\\n$names\\n\\nYou can only restock items that are Low Stock.'); 
            window.location.href='restock.php';
        </script>";
        exit();
    }
    // ------------------------

    // 1. Generate Invoice ID (USING SEQUENCE)
    $sqlInvID = "SELECT SEQ_INV_ID.NEXTVAL AS NEXT_ID FROM DUAL";
    $stIID = oci_parse($conn, $sqlInvID);
    oci_execute($stIID);
    $rIID = oci_fetch_assoc($stIID);
    $newInvId = $rIID['NEXT_ID'];

    // 2. Calculate Total
    $grandTotal = 0;
    for ($i = 0; $i < count($productIds); $i++) {
        $grandTotal += ($quantities[$i] * $unitCosts[$i]);
    }

    // 3. START TRANSACTION
    // A. Create Invoice Header
    $sqlHead = "INSERT INTO INVOICE (invId, supplyDate, invTotal, supId) 
                VALUES (:iid, TO_DATE(:sdate, 'YYYY-MM-DD'), :total, :sid)";
    $stHead = oci_parse($conn, $sqlHead);
    oci_bind_by_name($stHead, ":iid", $newInvId);
    oci_bind_by_name($stHead, ":sdate", $supplyDate);
    oci_bind_by_name($stHead, ":total", $grandTotal);
    oci_bind_by_name($stHead, ":sid", $supId);
    $r1 = @oci_execute($stHead, OCI_NO_AUTO_COMMIT);

    // B. Create Items & Update Stock
    $allSuccess = true;
    if ($r1) {
        for ($i = 0; $i < count($productIds); $i++) {
            $pid = $productIds[$i];
            $qty = $quantities[$i];
            $ucost = $unitCosts[$i];
            $ltotal = $qty * $ucost;
            $newExp = $expiryDates[$i];

            // Detail Insert
            $sqlDet = "INSERT INTO INVOICE_DETAIL (invId, prodId, lineQuantity, unitCost, lineTotal) 
                       VALUES (:iid, :pid, :qty, :ucost, :ltotal)";
            $stDet = oci_parse($conn, $sqlDet);
            oci_bind_by_name($stDet, ":iid", $newInvId);
            oci_bind_by_name($stDet, ":pid", $pid);
            oci_bind_by_name($stDet, ":qty", $qty);
            oci_bind_by_name($stDet, ":ucost", $ucost);
            oci_bind_by_name($stDet, ":ltotal", $ltotal);
            $rd = oci_execute($stDet, OCI_NO_AUTO_COMMIT);

            // Product Update (Qty AND Expiry Date)
            // SAFETY LOGIC: If current stock > 0, we KEEP the older date to ensure we don't hide expiring items.
            // If shelf is empty, we update to the new batch date.
            $sqlUpd = "UPDATE PRODUCT SET 
                       prodQuantity = prodQuantity + :qty, 
                       prodExpDate = CASE 
                           WHEN prodQuantity <= 0 THEN TO_DATE(:exp, 'YYYY-MM-DD') 
                           WHEN TO_DATE(:exp, 'YYYY-MM-DD') < prodExpDate THEN TO_DATE(:exp, 'YYYY-MM-DD')
                           ELSE prodExpDate 
                       END
                       WHERE prodId = :pid";
            $stUpd = oci_parse($conn, $sqlUpd);
            oci_bind_by_name($stUpd, ":qty", $qty);
            oci_bind_by_name($stUpd, ":exp", $newExp);
            oci_bind_by_name($stUpd, ":pid", $pid);
            $ru = oci_execute($stUpd, OCI_NO_AUTO_COMMIT);

            if (!$rd || !$ru) {
                $allSuccess = false;
                break;
            }
        }
    } else {
        $allSuccess = false;
    }

    if ($r1 && $allSuccess) {
        oci_commit($conn);
        echo "<script>alert('Stock & Expiry Dates Updated! Invoice #$newInvId created.'); window.location.href='invoice_list.php';</script>";
    } else {
        oci_rollback($conn);
        $e = oci_error($stHead); // Check header error
        if (!$e) $e = oci_error($stDet); // Check detail error
        $msg = "<div class='alert alert-danger'>Failed to process restock transaction. Error: " . $e['message'] . "</div>";
    }
}

// Fetch Suppliers and Products
$sqlSup = "SELECT supId, supName FROM SUPPLIER ORDER BY supName ASC";
$stSup = oci_parse($conn, $sqlSup); oci_execute($stSup);

$sqlProd = "SELECT prodId, prodName FROM PRODUCT ORDER BY prodName ASC";
$stProd = oci_parse($conn, $sqlProd); oci_execute($stProd);
$allProducts = [];
while($p = oci_fetch_assoc($stProd)) { $allProducts[] = $p; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement & Restock - City Bakery</title>
    <!-- Bootstrap 5 & Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        h1, h2, h3, h4, h5 { font-family: 'Playfair Display', serif; }
        
        .table-form input, .table-form select { 
            border: 1px solid transparent; 
            background: transparent; 
            padding: 8px;
            border-radius: 5px;
            width: 100%;
        }
        .table-form input:focus, .table-form select:focus { 
            border: 1px solid var(--bakery-primary); 
            background: white; 
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.1);
            outline: none;
        }
        .table-form tr:hover input, .table-form tr:hover select {
            border: 1px solid #dee2e6;
            background: white;
        }
        
        .card { border-radius: 15px; }
        .input-group-text { background-color: #fff; border-right: none; }
        .form-select.border-start-0 { border-left: none; }
    </style>
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="container-fluid" style="max-width: 1200px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold m-0 text-dark">Record New Supply</h2>
                <a href="invoice_list.php" class="btn btn-outline-dark rounded-pill px-4"><i class="fas fa-history me-2"></i>History</a>
            </div>
            
            <?php echo $msg; ?>

            <form method="POST">
                <!-- Supplier & Date Card -->
                <div class="card shadow-sm border-0 mb-4 p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Supplier</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-truck text-muted"></i></span>
                                <select name="supId" class="form-select border-start-0" required>
                                    <option value="">-- Choose Supplier --</option>
                                    <?php while($s = oci_fetch_assoc($stSup)): ?>
                                        <option value="<?php echo $s['SUPID']; ?>"><?php echo $s['SUPNAME']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <a href="supplier_manage.php" class="btn btn-outline-secondary" title="Add Supplier"><i class="fas fa-plus"></i></a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Supply Date</label>
                            <input type="date" name="supplyDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Items Table Card -->
                <div class="card shadow-sm border-0 overflow-hidden">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">Invoice Items</h5>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" onclick="addRow()"><i class="fas fa-plus me-1"></i> Add Row</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 table-form align-middle" id="restockTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;" class="ps-4">Product</th>
                                    <th style="width: 15%;">New Batch Expiry</th>
                                    <th style="width: 15%;">Quantity</th>
                                    <th style="width: 15%;">Unit Cost (RM)</th>
                                    <th style="width: 15%;" class="text-end">Total</th>
                                    <th style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="item-row">
                                    <td class="ps-4">
                                        <select name="prodId[]" class="form-select" required>
                                            <option value="">-- Select Product --</option>
                                            <?php foreach($allProducts as $p): ?>
                                                <option value="<?php echo $p['PRODID']; ?>"><?php echo $p['PRODNAME']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="date" name="exp[]" class="form-control" required></td>
                                    <td><input type="number" name="qty[]" class="form-control qty-input" min="1" placeholder="0" required oninput="calcTotal(this)"></td>
                                    <td><input type="number" step="0.01" name="cost[]" class="form-control cost-input" placeholder="0.00" required oninput="calcTotal(this)"></td>
                                    <td class="text-end fw-bold line-total text-dark">RM 0.00</td>
                                    <td class="text-center"><button type="button" class="btn btn-link text-danger p-0" onclick="removeRow(this)"><i class="fas fa-trash-alt"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4" class="text-end text-uppercase text-muted small pt-3">Grand Total</td>
                                    <td class="text-end text-success fs-5 pt-2" id="grandTotalText">RM 0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="manager_dashboard.php" class="btn btn-light border rounded-pill px-4">Cancel</a>
                    <button type="submit" name="submit_restock" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">
                        <i class="fas fa-check-circle me-2"></i> Confirm Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addRow() {
    const table = document.getElementById('restockTable').getElementsByTagName('tbody')[0];
    const newRow = table.rows[0].cloneNode(true);
    
    // Clear all inputs
    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    
    // Reset Total Text
    newRow.querySelector('.line-total').innerText = 'RM 0.00';
    
    table.appendChild(newRow);
}

function removeRow(btn) {
    const table = document.getElementById('restockTable').getElementsByTagName('tbody')[0];
    if (table.rows.length > 1) {
        btn.closest('tr').remove();
        updateGrandTotal();
    }
}

function calcTotal(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    const total = qty * cost;
    row.querySelector('.line-total').innerText = 'RM ' + total.toFixed(2);
    updateGrandTotal();
}

function updateGrandTotal() {
    let grand = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        grand += (qty * cost);
    });
    document.getElementById('grandTotalText').innerText = 'RM ' + grand.toFixed(2);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>