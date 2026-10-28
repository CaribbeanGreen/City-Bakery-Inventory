<?php
session_start();
include '../config/db_connect.php';

// Handle Actions (Remove / Update)
if (isset($_POST['action'])) {
    $pId = $_POST['pid'];
    
    if ($_POST['action'] == 'remove') {
        foreach ($_SESSION['cust_cart'] as $key => $item) {
            if ($item['id'] == $pId) {
                unset($_SESSION['cust_cart'][$key]);
                break; // Stop after removing
            }
        }
        // Re-index array
        $_SESSION['cust_cart'] = array_values($_SESSION['cust_cart']);
    }
    
    if ($_POST['action'] == 'update') {
        $newQty = $_POST['qty'];
        if ($newQty < 1) $newQty = 1; // Minimum 1
        
        foreach ($_SESSION['cust_cart'] as $key => $item) {
            if ($item['id'] == $pId) {
                $_SESSION['cust_cart'][$key]['qty'] = $newQty;
                break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Tray - City Bakery</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #fdf2e9; }
        h1, h2, h3, h4, h5 { font-family: 'Playfair Display', serif; }
        
        .cart-header {
            background: white;
            padding: 40px 0;
            text-align: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .cart-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            background: white;
            overflow: hidden;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 2px solid #eee;
            border-radius: 10px;
            font-weight: bold;
        }
        .quantity-input:focus { border-color: var(--bakery-primary); outline: none; }
        
        .btn-remove {
            color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
            width: 35px; height: 35px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
        }
        .btn-remove:hover { background: #e74c3c; color: white; }
        
        .summary-card {
            background: var(--bakery-dark);
            color: white;
            border-radius: 15px;
            padding: 30px;
            position: sticky;
            top: 100px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top p-3 shadow">
        <div class="container">
            <a class="navbar-brand fs-4" href="../index.php"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</a>
            <a href="menu.php" class="btn btn-outline-light rounded-pill ms-auto btn-sm px-4">Back to Menu</a>
        </div>
    </nav>

    <div class="cart-header">
        <h2 class="fw-bold m-0">Your Tray</h2>
        <p class="text-muted small m-0">Review your fresh picks before checkout.</p>
    </div>

    <div class="container my-5">
        <div class="row g-5">
            <!-- Left: Cart Items -->
            <div class="col-lg-8">
                <?php if (empty($_SESSION['cust_cart'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-basket fa-4x text-muted mb-3 opacity-50"></i>
                        <h4 class="text-muted">Your tray is empty.</h4>
                        <a href="menu.php" class="btn btn-warning rounded-pill mt-3 px-4 fw-bold shadow-sm">Explore Menu</a>
                    </div>
                <?php else: ?>
                    <div class="cart-card">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 border-0">Item</th>
                                        <th class="border-0">Price</th>
                                        <th class="border-0 text-center">Qty</th>
                                        <th class="border-0 text-end">Total</th>
                                        <th class="pe-4 border-0"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $grandTotal = 0;
                                    foreach ($_SESSION['cust_cart'] as $item): 
                                        $lineTotal = $item['price'] * $item['qty'];
                                        $grandTotal += $lineTotal;
                                    ?>
                                    <tr>
                                        <td class="ps-4 py-3 fw-bold text-dark"><?php echo $item['name']; ?></td>
                                        <td class="text-muted">RM <?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-center">
                                            <form method="POST" class="d-flex justify-content-center">
                                                <input type="hidden" name="pid" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="number" name="qty" value="<?php echo $item['qty']; ?>" class="quantity-input form-control-sm" min="1" onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td class="text-end fw-bold text-dark">RM <?php echo number_format($lineTotal, 2); ?></td>
                                        <td class="pe-4 text-end">
                                            <form method="POST">
                                                <input type="hidden" name="pid" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <button type="submit" class="btn btn-remove border-0 mx-auto">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Summary -->
            <div class="col-lg-4">
                <div class="summary-card shadow-lg">
                    <h4 class="fw-bold mb-4">Order Summary</h4>
                    <div class="d-flex justify-content-between mb-2 opacity-75">
                        <span>Subtotal</span>
                        <span>RM <?php echo isset($grandTotal) ? number_format($grandTotal, 2) : '0.00'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-4 opacity-75">
                        <span>Tax (0%)</span>
                        <span>RM 0.00</span>
                    </div>
                    <hr class="border-light opacity-25">
                    <div class="d-flex justify-content-between fs-4 fw-bold mb-4">
                        <span>Total</span>
                        <span>RM <?php echo isset($grandTotal) ? number_format($grandTotal, 2) : '0.00'; ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn btn-warning w-100 py-3 fw-bold rounded-pill shadow <?php echo (empty($_SESSION['cust_cart'])) ? 'disabled' : ''; ?>">
                        Proceed to Checkout <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    
                    <div class="mt-4 text-center small opacity-50">
                        <i class="fas fa-lock me-1"></i> Secure Checkout
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>