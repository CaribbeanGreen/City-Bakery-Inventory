<?php
session_start();
include '../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['staffId'])) {
    header("Location: ../login.php");
    exit();
}

// 2. Check for Pending Online Orders
$sqlPending = "SELECT COUNT(*) AS CNT FROM CUSTOMER_ORDER WHERE orderStatus = 'Pending'";
$stmtP = oci_parse($conn, $sqlPending);
oci_execute($stmtP);
$rowP = oci_fetch_assoc($stmtP);
$pendingCount = $rowP['CNT'];

// 3. Handle "Quick Add Customer"
if (isset($_POST['save_cust_btn'])) {
    $cName = $_POST['cName'];
    $cPhone = $_POST['cPhone'];
    $cEmail = !empty($_POST['cEmail']) ? $_POST['cEmail'] : str_replace(' ', '', strtolower($cName)) . rand(100,999) . '@customer.com';

    // Sequence for Customer ID
    $sqlID = "SELECT SEQ_CUST_ID.NEXTVAL AS NEXT_ID FROM DUAL";
    $stmtID = oci_parse($conn, $sqlID);
    oci_execute($stmtID);
    $rowID = oci_fetch_assoc($stmtID);
    $newCId = $rowID['NEXT_ID'];

    $sqlIns = "INSERT INTO CUSTOMER (custId, custName, custPhone, custEmail) VALUES (:id, :nm, :ph, :em)";
    $stmtIns = oci_parse($conn, $sqlIns);
    oci_bind_by_name($stmtIns, ":id", $newCId);
    oci_bind_by_name($stmtIns, ":nm", $cName);
    oci_bind_by_name($stmtIns, ":ph", $cPhone);
    oci_bind_by_name($stmtIns, ":em", $cEmail);

    if (oci_execute($stmtIns)) {
        echo "<script>alert('Customer Added! ID: $newCId');</script>";
    } else {
        $e = oci_error($stmtIns);
        echo "<script>alert('Error: " . $e['message'] . "');</script>";
    }
}

// 4. Fetch Products
$sql = "SELECT * FROM PRODUCT ORDER BY prodName ASC";
$stid = oci_parse($conn, $sql);
oci_execute($stid);

// 5. Handle Cart Actions
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}
// Initialize Discount Session
if (!isset($_SESSION['discount'])) {
    $_SESSION['discount'] = 0; 
}

// A. Add to Cart
if (isset($_POST['add_to_cart'])) {
    $pId = $_POST['prod_id'];
    $pName = $_POST['prod_name'];
    $pPrice = $_POST['prod_price'];
    $pStock = $_POST['prod_stock']; 
    
    $found = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['id'] == $pId) {
            if ($item['qty'] + 1 <= $pStock) {
                $_SESSION['cart'][$key]['qty'] += 1;
            } else {
                echo "<script>alert('Cannot add more. Stock limit reached!');</script>";
            }
            $found = true;
            break;
        }
    }

    if (!$found) {
        if ($pStock > 0) {
            $_SESSION['cart'][] = array('id' => $pId, 'name' => $pName, 'price' => $pPrice, 'qty' => 1);
        } else {
            echo "<script>alert('Item is Out of Stock!');</script>";
        }
    }
}

// B. Update Quantity
if (isset($_POST['update_qty'])) {
    $pId = $_POST['prod_id'];
    $newQty = (int)$_POST['qty'];
    
    $sqlCheck = "SELECT prodQuantity FROM PRODUCT WHERE prodId = :pid";
    $stCheck = oci_parse($conn, $sqlCheck);
    oci_bind_by_name($stCheck, ":pid", $pId);
    oci_execute($stCheck);
    $rowCheck = oci_fetch_assoc($stCheck);
    $maxStock = $rowCheck['PRODQUANTITY'];

    if ($newQty < 1) $newQty = 1;
    
    if ($newQty <= $maxStock) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $pId) {
                $_SESSION['cart'][$key]['qty'] = $newQty;
                break;
            }
        }
    } else {
        echo "<script>alert('Requested quantity exceeds available stock ($maxStock).');</script>";
    }
}

// C. Remove Item
if (isset($_POST['remove_item'])) {
    $pId = $_POST['prod_id'];
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['id'] == $pId) {
            unset($_SESSION['cart'][$key]);
            break;
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']); 
}

// D. Clear Cart
if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    $_SESSION['discount'] = 0; // Reset discount
}

// E. Handle Discount Logic
if (isset($_POST['apply_discount'])) {
    $disc = floatval($_POST['discount_val']);
    if ($disc < 0) $disc = 0;
    if ($disc > 100) $disc = 100; // Cap at 100%
    $_SESSION['discount'] = $disc;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff POS - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { overflow: hidden; height: 100vh; font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .navbar-brand { font-family: 'Playfair Display', serif; }
        .product-grid { height: calc(100vh - 70px); overflow-y: auto; padding: 20px; }
        .order-panel { height: calc(100vh - 70px); background: white; border-left: 1px solid #ddd; display: flex; flex-direction: column; }
        .order-list { flex-grow: 1; overflow-y: auto; }
        
        .product-card-pos { cursor: pointer; transition: 0.2s; height: 100%; border: 1px solid #eee; border-radius: 12px; background: white; }
        .product-card-pos:hover { border-color: var(--bakery-primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .card-disabled { background-color: #e9ecef; cursor: not-allowed; opacity: 0.7; }
        .card-disabled:hover { border: 1px solid #ddd; transform: none; }
        
        /* Search Bar Style */
        .pos-search {
            border-radius: 20px;
            border: 2px solid #eee;
            padding-left: 15px;
        }
        .pos-search:focus {
            border-color: var(--bakery-primary);
            box-shadow: none;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom px-3 shadow-sm">
        <span class="navbar-brand mb-0 h1 text-white"><i class="fas fa-cash-register me-2"></i>City Bakery POS</span>
        <div class="text-white d-flex align-items-center">
            <a href="view_orders.php" class="btn btn-sm btn-warning me-4 position-relative fw-bold text-dark rounded-pill px-3">
                <i class="fas fa-bell me-1"></i> Online Orders
                <?php if($pendingCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white">
                    <?php echo $pendingCount; ?>
                </span>
                <?php endif; ?>
            </a>
            <span class="me-3 small text-white-50"><i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['staffName']; ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Logout</a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            
            <!-- LEFT: Product Grid -->
            <div class="col-md-8 product-grid bg-light">
                
                <!-- NEW: Search Bar -->
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0 rounded-start-pill ps-3"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="posSearch" class="form-control border-0 rounded-end-pill py-2 shadow-sm" placeholder="Search Product Name or ID (e.g., 'Croissant' or '2001')">
                    </div>
                </div>

                <div class="row g-3" id="productContainer">
                    <?php while (($row = oci_fetch_assoc($stid)) != false): ?>
                    <?php
                        $desc = !empty($row['PRODDESC']) ? htmlspecialchars($row['PRODDESC']) : "No description available.";
                        $expDate = strtotime($row['PRODEXPDATE']); 
                        $today = time();
                        $isExpired = ($expDate < $today); 
                        $isSoldOut = ($row['PRODQUANTITY'] <= 0);
                        $isDisabled = $isExpired || $isSoldOut;
                    ?>
                    <!-- Added data attributes for JS search -->
                    <div class="col-md-3 col-6 product-item" 
                         data-name="<?php echo strtolower($row['PRODNAME']); ?>" 
                         data-id="<?php echo $row['PRODID']; ?>">
                         
                        <form method="POST" style="height: 100%;">
                            <input type="hidden" name="prod_id" value="<?php echo $row['PRODID']; ?>">
                            <input type="hidden" name="prod_name" value="<?php echo $row['PRODNAME']; ?>">
                            <input type="hidden" name="prod_price" value="<?php echo $row['PRODPRICE']; ?>">
                            <input type="hidden" name="prod_stock" value="<?php echo $row['PRODQUANTITY']; ?>">
                            
                            <button type="submit" name="add_to_cart" class="btn p-0 w-100 h-100 text-start border-0" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                <div class="card product-card-pos p-3 <?php echo $isDisabled ? 'card-disabled' : ''; ?>">
                                    <div class="d-flex flex-column justify-content-between h-100">
                                        <div>
                                            <h6 class="card-title fw-bold mb-1 text-dark text-truncate" title="<?php echo $row['PRODNAME']; ?>">
                                                <?php echo $row['PRODNAME']; ?>
                                            </h6>
                                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">ID: <?php echo $row['PRODID']; ?></small>
                                            
                                            <?php if($isExpired): ?>
                                                <span class="badge bg-danger mb-2">EXPIRED</span>
                                            <?php elseif($isSoldOut): ?>
                                                <span class="badge bg-secondary mb-2">SOLD OUT</span>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo $row['PRODCATEGORY']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 d-flex justify-content-between align-items-end">
                                            <span class="text-primary fw-bold">RM <?php echo number_format($row['PRODPRICE'], 2); ?></span>
                                            <?php if(!$isDisabled): ?>
                                            <span class="badge bg-light text-dark border rounded-pill"><?php echo $row['PRODQUANTITY']; ?> left</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <span class="position-absolute top-0 end-0 m-2 text-muted" onclick="event.preventDefault(); showPosDetails('<?php echo htmlspecialchars($row['PRODNAME']); ?>', '<?php echo $desc; ?>')">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                </div>
                            </button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- No Results Message -->
                <div id="noResults" class="text-center py-5 d-none">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No products found matching your search.</p>
                </div>
            </div>

            <!-- RIGHT: Order Panel -->
            <div class="col-md-4 order-panel p-0 shadow-lg">
                <div class="p-3 bg-secondary text-white d-flex justify-content-between align-items-center">
                    <div><h5 class="m-0 fw-bold">Current Order</h5></div>
                    <form method="POST">
                        <button type="submit" name="clear_cart" class="btn btn-sm btn-danger rounded-pill px-3">Clear</button>
                    </form>
                </div>
                
                <div class="order-list p-3">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="text-muted small text-uppercase">
                            <tr>
                                <th>Item</th>
                                <th style="width: 60px;">Qty</th>
                                <th class="text-end">Total</th>
                                <th style="width: 20px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subTotal = 0;
                            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): 
                                foreach ($_SESSION['cart'] as $item):
                                    $lineTotal = $item['price'] * $item['qty'];
                                    $subTotal += $lineTotal;
                            ?>
                            <tr>
                                <td class="fw-bold small"><?php echo $item['name']; ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="prod_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="update_qty" value="1">
                                        <input type="number" name="qty" value="<?php echo $item['qty']; ?>" class="form-control form-control-sm px-1 text-center" min="1" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td class="text-end small">RM <?php echo number_format($lineTotal, 2); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="prod_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="remove_item" class="btn btn-sm text-danger p-0 border-0"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- DISCOUNT & TOTAL SECTION -->
                <div class="p-3 bg-light border-top">
                    <?php 
                        $discountRate = $_SESSION['discount'];
                        $discountAmount = $subTotal * ($discountRate / 100);
                        $finalTotal = $subTotal - $discountAmount;
                    ?>
                    
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Subtotal:</span>
                        <span>RM <?php echo number_format($subTotal, 2); ?></span>
                    </div>

                    <!-- Discount Input -->
                    <form method="POST" class="mb-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-primary"><i class="fas fa-percent"></i></span>
                            <input type="number" name="discount_val" class="form-control border-start-0" placeholder="Discount %" min="0" max="100" value="<?php echo ($discountRate > 0 ? $discountRate : ''); ?>">
                            <button type="submit" name="apply_discount" class="btn btn-outline-secondary">Apply</button>
                        </div>
                        <?php if($discountRate > 0): ?>
                            <div class="text-end text-success small mt-1">
                                - RM <?php echo number_format($discountAmount, 2); ?> (<?php echo $discountRate; ?>%)
                            </div>
                        <?php endif; ?>
                    </form>

                    <hr>

                    <div class="d-flex justify-content-between h4 mb-3">
                        <span>Total:</span>
                        <span class="text-primary fw-bold">RM <?php echo number_format($finalTotal, 2); ?></span>
                    </div>
                    
                    <!-- CHECKOUT FORM -->
                    <form action="process_checkout.php" method="POST">
                        <div class="mb-2">
                            <div class="input-group">
                                <select name="cust_id" class="form-select form-select-sm">
                                    <option value="4000">Walk-in Customer</option>
                                    <option disabled>──────────</option>
                                    <?php
                                    $sqlC = "SELECT custId, custName FROM CUSTOMER WHERE custId != 4000 ORDER BY custName ASC";
                                    $stmtC = oci_parse($conn, $sqlC);
                                    oci_execute($stmtC);
                                    while ($rc = oci_fetch_assoc($stmtC)) {
                                        echo "<option value='".$rc['CUSTID']."'>".$rc['CUSTNAME']."</option>";
                                    }
                                    ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCustModal">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="checkout_btn" class="btn btn-success btn-lg shadow-sm fw-bold" <?php echo (empty($_SESSION['cart']) ? 'disabled' : ''); ?>>
                                <i class="fas fa-check-circle me-2"></i> CONFIRM PAYMENT
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>

        </div>
    </div>

    <!-- Modals (Add Cust & Info) -->
    <div class="modal fade" id="addCustModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" name="cName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Phone</label>
                            <input type="text" name="cPhone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Email (Optional)</label>
                            <input type="email" name="cEmail" class="form-control" placeholder="Leave blank to auto-generate">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="save_cust_btn" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="infoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold" id="infoTitle">Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><p id="infoDesc" class="text-muted"></p></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPosDetails(name, desc) {
            document.getElementById('infoTitle').innerText = name;
            document.getElementById('infoDesc').innerText = desc;
            new bootstrap.Modal(document.getElementById('infoModal')).show();
        }

        // Real-time Search Logic
        document.getElementById('posSearch').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const items = document.querySelectorAll('.product-item');
            let visibleCount = 0;

            items.forEach(item => {
                const name = item.getAttribute('data-name');
                const id = item.getAttribute('data-id');
                
                // Search by Name OR ID
                if (name.includes(searchText) || id.includes(searchText)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Show 'No Results' if everything is hidden
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        });
    </script>
</body>
</html>