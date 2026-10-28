<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
// Check if ID is set to avoid errors
if (!isset($_GET['id'])) {
    header("Location: inventory_list.php");
    exit();
}
$prodId = $_GET['id'];

// Fetch Existing Data
if (isset($prodId)) {
    $sqlFetch = "SELECT * FROM PRODUCT WHERE prodId = :pid";
    $stmtFetch = oci_parse($conn, $sqlFetch);
    oci_bind_by_name($stmtFetch, ":pid", $prodId);
    oci_execute($stmtFetch);
    $row = oci_fetch_assoc($stmtFetch);
    if (!$row) die("Product not found!");
}

// Handle Update
if (isset($_POST['update_btn'])) {
    $pName = $_POST['pName'];
    $pDesc = $_POST['pDesc'];
    $pCat = $_POST['pCat'];
    $pPrice = $_POST['pPrice'];
    $pQty = $_POST['pQty'];
    $pMin = $_POST['pMin'];
    $pExp = $_POST['pExp']; // NEW: Get Date from form

    // --- Image Upload Logic ---
    $imageUpdated = false;
    $newFilename = "";

    // Check if a file was uploaded and there is no error
    if (isset($_FILES['pImg']) && $_FILES['pImg']['error'] == 0) {
        $targetDir = "../assets/img/products/";
        
        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileExt = strtolower(pathinfo($_FILES["pImg"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExt, $allowed)) {
            // Use timestamp to prevent name conflicts
            $newFilename = "prod_" . time() . "." . $fileExt;
            
            if (move_uploaded_file($_FILES["pImg"]["tmp_name"], $targetDir . $newFilename)) {
                $imageUpdated = true;
            } else {
                $msg = "<div class='alert alert-danger'>Failed to upload image. Check folder permissions.</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>Invalid file type. Only JPG, PNG, GIF allowed.</div>";
        }
    }

    if (empty($msg)) {
        // Construct SQL based on whether image is updated
        // Added prodExpDate = TO_DATE(...) to both queries
        if ($imageUpdated) {
            $sqlUpdate = "UPDATE PRODUCT SET 
                          prodName = :pname, 
                          prodDesc = :pdesc,
                          prodCategory = :pcat, 
                          prodPrice = :pprice, 
                          prodQuantity = :pqty, 
                          prodMinimum = :pmin,
                          prodExpDate = TO_DATE(:pexp, 'YYYY-MM-DD'),
                          prodImage = :pimg 
                          WHERE prodId = :pid";
        } else {
            // Keep existing image
            $sqlUpdate = "UPDATE PRODUCT SET 
                          prodName = :pname, 
                          prodDesc = :pdesc,
                          prodCategory = :pcat, 
                          prodPrice = :pprice, 
                          prodQuantity = :pqty, 
                          prodMinimum = :pmin,
                          prodExpDate = TO_DATE(:pexp, 'YYYY-MM-DD')
                          WHERE prodId = :pid";
        }

        $stmtUpdate = oci_parse($conn, $sqlUpdate);
        
        // Bind common variables
        oci_bind_by_name($stmtUpdate, ":pname", $pName);
        oci_bind_by_name($stmtUpdate, ":pdesc", $pDesc);
        oci_bind_by_name($stmtUpdate, ":pcat", $pCat);
        oci_bind_by_name($stmtUpdate, ":pprice", $pPrice);
        oci_bind_by_name($stmtUpdate, ":pqty", $pQty);
        oci_bind_by_name($stmtUpdate, ":pmin", $pMin);
        oci_bind_by_name($stmtUpdate, ":pexp", $pExp); // Bind Date
        oci_bind_by_name($stmtUpdate, ":pid", $prodId);

        // Bind image only if updated
        if ($imageUpdated) {
            oci_bind_by_name($stmtUpdate, ":pimg", $newFilename);
        }

        if (oci_execute($stmtUpdate, OCI_COMMIT_ON_SUCCESS)) {
            echo "<script>alert('Product Updated Successfully!'); window.location.href='inventory_list.php';</script>";
        } else {
            $e = oci_error($stmtUpdate);
            $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <div class="d-flex" style="min-height: 100vh;">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4 bg-light">
            <h2 class="fw-bold mb-4">Edit Product (ID: <?php echo $prodId; ?>)</h2>
            <?php echo $msg; ?>

            <div class="card shadow-sm border-0 p-4" style="max-width: 800px;">
                <!-- Added enctype="multipart/form-data" for file upload support -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Left Column: Details -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="pName" class="form-control" required value="<?php echo htmlspecialchars($row['PRODNAME']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="pDesc" class="form-control" rows="3"><?php echo htmlspecialchars($row['PRODDESC']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="pCat" class="form-select">
                                        <option value="Cakes" <?php if($row['PRODCATEGORY'] == 'Cakes') echo 'selected'; ?>>Cakes</option>
                                        <option value="Bread" <?php if($row['PRODCATEGORY'] == 'Bread') echo 'selected'; ?>>Bread</option>
                                        <option value="Pastries" <?php if($row['PRODCATEGORY'] == 'Pastries') echo 'selected'; ?>>Pastries</option>
                                        <option value="Beverage" <?php if($row['PRODCATEGORY'] == 'Beverage') echo 'selected'; ?>>Beverage</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Price (RM)</label>
                                    <input type="number" step="0.01" name="pPrice" class="form-control" required value="<?php echo $row['PRODPRICE']; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Current Quantity</label>
                                    <input type="number" name="pQty" class="form-control" required value="<?php echo $row['PRODQUANTITY']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Minimum Stock</label>
                                    <input type="number" name="pMin" class="form-control" required value="<?php echo $row['PRODMINIMUM']; ?>">
                                </div>
                                <!-- NEW: Expiry Date Field -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label text-danger">Expiry Date</label>
                                    <?php 
                                        // Convert Oracle Date to HTML Date Format (YYYY-MM-DD)
                                        $htmlDate = '';
                                        if (!empty($row['PRODEXPDATE'])) {
                                            $htmlDate = date('Y-m-d', strtotime($row['PRODEXPDATE']));
                                        }
                                    ?>
                                    <input type="date" name="pExp" class="form-control border-danger" required value="<?php echo $htmlDate; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Image -->
                        <div class="col-md-5 ps-md-4 border-start">
                            <label class="form-label fw-bold">Product Image</label>
                            
                            <!-- Current Image Display -->
                            <div class="card mb-3 bg-light border-0">
                                <div class="card-body text-center">
                                    <?php if (!empty($row['PRODIMAGE'])): ?>
                                        <img src="../assets/img/products/<?php echo $row['PRODIMAGE']; ?>" class="img-fluid rounded shadow-sm mb-2" style="max-height: 200px;" alt="Product Image">
                                        <p class="small text-muted mb-0">Current Image</p>
                                    <?php else: ?>
                                        <div class="py-5 text-muted">
                                            <p class="mb-0">No image uploaded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Upload New Image -->
                            <div class="mb-3">
                                <label class="form-label small">Change Image</label>
                                <input type="file" name="pImg" class="form-control" accept="image/*">
                                <div class="form-text small">Leave blank to keep current image.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-2">
                        <a href="inventory_list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_btn" class="btn btn-primary px-4">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>