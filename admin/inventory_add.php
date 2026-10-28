<?php
session_start();
include '../config/db_connect.php';

// Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// Fetch Suppliers for the dropdown
$sqlSup = "SELECT supId, supName FROM SUPPLIER ORDER BY supName ASC";
$stmtSup = oci_parse($conn, $sqlSup);
oci_execute($stmtSup);

if (isset($_POST['save_btn'])) {
    $pName = $_POST['pName'];
    $pCat = $_POST['pCat'];
    $pPrice = $_POST['pPrice']; 
    $pCost = $_POST['pCost'];   
    $pQty = $_POST['pQty'];
    $pMin = $_POST['pMin'];
    $pExp = $_POST['pExp']; 
    $supId = $_POST['supId'];
    $pDesc = $_POST['pDesc']; 

    // 1. Handle Image Upload
    $pImage = ""; 
    if (isset($_FILES['pImg']) && $_FILES['pImg']['error'] == 0) {
        $targetDir = "../assets/img/products/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true); // Ensure dir exists
        
        $ext = strtolower(pathinfo($_FILES["pImg"]["name"], PATHINFO_EXTENSION));
        $newFilename = "prod_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES["pImg"]["tmp_name"], $targetDir . $newFilename)) {
            $pImage = $newFilename;
        }
    }

    // 2. IDs Generation (USING SEQUENCES)
    $sqlPID = "SELECT SEQ_PROD_ID.NEXTVAL AS NEXT_ID FROM DUAL";
    $stID = oci_parse($conn, $sqlPID); oci_execute($stID);
    $rID = oci_fetch_assoc($stID); $newProdId = $rID['NEXT_ID'];

    $sqlIID = "SELECT SEQ_INV_ID.NEXTVAL AS NEXT_ID FROM DUAL";
    $stIID = oci_parse($conn, $sqlIID); oci_execute($stIID);
    $rIID = oci_fetch_assoc($stIID); $newInvId = $rIID['NEXT_ID'];

    // 3. START TRANSACTION
    // A. Insert Product
    $sqlProd = "INSERT INTO PRODUCT (prodId, prodName, prodCategory, prodPrice, prodQuantity, prodMinimum, prodExpDate, prodImage, prodDesc) 
                VALUES (:pid, :pname, :pcat, :pprice, :pqty, :pmin, TO_DATE(:pexp, 'YYYY-MM-DD'), :pimg, :pdesc)";
    $stProd = oci_parse($conn, $sqlProd);
    oci_bind_by_name($stProd, ":pid", $newProdId);
    oci_bind_by_name($stProd, ":pname", $pName);
    oci_bind_by_name($stProd, ":pcat", $pCat);
    oci_bind_by_name($stProd, ":pprice", $pPrice);
    oci_bind_by_name($stProd, ":pqty", $pQty);
    oci_bind_by_name($stProd, ":pmin", $pMin);
    oci_bind_by_name($stProd, ":pexp", $pExp);
    oci_bind_by_name($stProd, ":pimg", $pImage);
    oci_bind_by_name($stProd, ":pdesc", $pDesc);
    $r1 = oci_execute($stProd, OCI_NO_AUTO_COMMIT);

    // B. Insert Invoice
    $invTotal = $pQty * $pCost;
    $sqlInv = "INSERT INTO INVOICE (invId, supplyDate, invTotal, supId) 
               VALUES (:iid, SYSDATE, :total, :sid)";
    $stInv = oci_parse($conn, $sqlInv);
    oci_bind_by_name($stInv, ":iid", $newInvId);
    oci_bind_by_name($stInv, ":total", $invTotal);
    oci_bind_by_name($stInv, ":sid", $supId);
    $r2 = oci_execute($stInv, OCI_NO_AUTO_COMMIT);

    // C. Insert Invoice Detail
    $sqlDet = "INSERT INTO INVOICE_DETAIL (invId, prodId, lineQuantity, unitCost, lineTotal) 
               VALUES (:iid, :pid, :qty, :ucost, :ltotal)";
    $stDet = oci_parse($conn, $sqlDet);
    oci_bind_by_name($stDet, ":iid", $newInvId);
    oci_bind_by_name($stDet, ":pid", $newProdId);
    oci_bind_by_name($stDet, ":qty", $pQty);
    oci_bind_by_name($stDet, ":ucost", $pCost);
    oci_bind_by_name($stDet, ":ltotal", $invTotal);
    $r3 = oci_execute($stDet, OCI_NO_AUTO_COMMIT);

    if ($r1 && $r2 && $r3) {
        oci_commit($conn);
        echo "<script>alert('Product Created Successfully!'); window.location.href='inventory_list.php';</script>";
    } else {
        oci_rollback($conn);
        $e = oci_error($stProd);
        $msg = "<div class='alert alert-danger shadow-sm border-0'><i class='fas fa-exclamation-triangle me-2'></i>Error: " . $e['message'] . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - City Bakery</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        h1, h2, h3, h4, h5 { font-family: 'Playfair Display', serif; }
        
        .form-label { font-weight: 500; font-size: 0.9rem; color: #555; }
        .form-control, .form-select { 
            border-radius: 8px; 
            padding: 10px 15px; 
            border: 1px solid #dee2e6; 
            transition: 0.2s;
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--bakery-primary); 
            box-shadow: 0 0 0 0.25rem rgba(211, 84, 0, 0.1); 
        }
        .input-group-text { background-color: #f8f9fa; border-color: #dee2e6; }
        
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .section-title { position: relative; padding-bottom: 10px; margin-bottom: 20px; }
        .section-title::after { 
            content: ''; position: absolute; bottom: 0; left: 0; width: 50px; height: 3px; background: var(--bakery-primary); border-radius: 2px;
        }
    </style>
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="container-fluid" style="max-width: 1000px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold m-0 text-dark">Add Inventory</h2>
                <a href="inventory_list.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Back</a>
            </div>
            
            <?php echo $msg; ?>

            <div class="card p-4 p-lg-5">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-5">
                        <!-- Column 1: Product Info -->
                        <div class="col-md-7">
                            <h5 class="section-title text-primary">Product Details</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="pName" class="form-control" placeholder="e.g. Signature Sourdough" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="pCat" class="form-select">
                                        <option value="Cakes">Cakes</option>
                                        <option value="Bread">Bread</option>
                                        <option value="Pastries">Pastries</option>
                                        <option value="Beverage">Beverage</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Selling Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">RM</span>
                                        <input type="number" step="0.01" name="pPrice" class="form-control" placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="pDesc" class="form-control" rows="4" placeholder="Describe the taste, ingredients, and allergens..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Product Image</label>
                                <input type="file" name="pImg" class="form-control" accept="image/*">
                                <div class="form-text">Supported formats: JPG, PNG, WEBP.</div>
                            </div>
                        </div>

                        <!-- Column 2: Initial Supply Record -->
                        <div class="col-md-5">
                            <div class="bg-light p-4 rounded-3 h-100">
                                <h5 class="section-title text-success">Stock & Procurement</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Supplier</label>
                                    <select name="supId" class="form-select" required>
                                        <option value="">-- Select Supplier --</option>
                                        <?php while($s = oci_fetch_assoc($stmtSup)) {
                                            echo "<option value='".$s['SUPID']."'>".$s['SUPNAME']."</option>";
                                        } ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Initial Quantity</label>
                                        <input type="number" name="pQty" class="form-control" required>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Unit Cost</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" step="0.01" name="pCost" class="form-control" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="pExp" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-danger">Low Stock Alert Level</label>
                                    <input type="number" name="pMin" class="form-control" value="10" required>
                                    <div class="form-text">System will notify when stock falls below this.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                        <a href="inventory_list.php" class="btn btn-light px-4 border rounded-pill">Cancel</a>
                        <button type="submit" name="save_btn" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>