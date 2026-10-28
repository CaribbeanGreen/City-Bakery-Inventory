<?php
session_start();
include '../config/db_connect.php';

// 1. Fetch Products
// Removed "WHERE prodQuantity > 0" to show Sold Out items as well
$sql = "SELECT * FROM PRODUCT ORDER BY prodCategory, prodName ASC";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

// 2. Handle Add to Cart
$toastMessage = "";
if (isset($_POST['add_to_cart'])) {
    $pId = $_POST['pid'];
    $pName = $_POST['pname'];
    $pPrice = $_POST['pprice'];
    
    // Check stock one last time before adding (Race condition protection)
    $sqlStock = "SELECT prodQuantity FROM PRODUCT WHERE prodId = :pid";
    $stmtStock = oci_parse($conn, $sqlStock);
    oci_bind_by_name($stmtStock, ":pid", $pId);
    oci_execute($stmtStock);
    $stockRow = oci_fetch_assoc($stmtStock);

    if ($stockRow && $stockRow['PRODQUANTITY'] > 0) {
        if (!isset($_SESSION['cust_cart'])) {
            $_SESSION['cust_cart'] = array();
        }

        $found = false;
        foreach ($_SESSION['cust_cart'] as $key => $item) {
            if ($item['id'] == $pId) {
                $_SESSION['cust_cart'][$key]['qty'] += 1;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $item = array('id' => $pId, 'name' => $pName, 'price' => $pPrice, 'qty' => 1);
            $_SESSION['cust_cart'][] = $item;
        }
        
        $toastMessage = "Added " . htmlspecialchars($pName) . " to your tray!";
    } else {
        $toastMessage = "Sorry, " . htmlspecialchars($pName) . " just sold out!";
    }
}

$cartCount = 0;
if (isset($_SESSION['cust_cart'])) {
    $cartCount = count($_SESSION['cust_cart']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu - City Bakery</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #fdf2e9; }
        h1, h2, h3, .navbar-brand { font-family: 'Playfair Display', serif; }

        /* Hero Section */
        .menu-hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://source.unsplash.com/1600x600/?bakery,display');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 350px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Filter Buttons */
        .filter-btn {
            border: 2px solid var(--bakery-dark);
            color: var(--bakery-dark);
            background: transparent;
            border-radius: 50px;
            padding: 8px 25px;
            margin: 0 5px 10px;
            font-weight: 600;
            transition: 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--bakery-dark);
            color: white;
            transform: translateY(-2px);
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
            max-width: 400px;
            margin: 0 auto 30px;
        }
        .search-input {
            border-radius: 50px;
            padding: 12px 20px 12px 45px;
            border: 2px solid #eee;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .search-input:focus {
            border-color: var(--bakery-primary);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            outline: none;
        }
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }

        /* Product Cards */
        .menu-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
            position: relative;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        .menu-img-wrap {
            height: 220px;
            overflow: hidden;
            position: relative;
        }
        .menu-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.5s;
        }
        .menu-card:hover .menu-img { transform: scale(1.1); }
        
        /* Sold Out Styling */
        .menu-card.sold-out .menu-img {
            filter: grayscale(100%);
            opacity: 0.6;
        }
        .sold-out-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.1);
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sold-out-text {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 8px 20px;
            font-weight: bold;
            transform: rotate(-10deg);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1rem;
            border: 2px solid white;
        }
        
        .category-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.9);
            color: var(--bakery-dark);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 3;
        }

        /* Floating Cart */
        .cart-float {
            position: fixed; bottom: 30px; right: 30px; z-index: 1000;
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transition: 0.3s;
            animation: bounceIn 1s;
        }
        .cart-float:hover { transform: scale(1.1); }

        /* Toast Notification */
        .custom-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #2ecc71;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 9999;
            display: none;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp { from { bottom: -50px; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        @keyframes bounceIn { 0% { transform: scale(0); } 60% { transform: scale(1.2); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top p-3 shadow">
        <div class="container">
            <a class="navbar-brand fs-4" href="../index.php"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</a>
            <div class="ms-auto d-flex align-items-center">
                <a href="track_order.php" class="text-white text-decoration-none me-4 d-none d-md-block">
                    <i class="fas fa-search me-1"></i> Track Order
                </a>
                <a href="cart.php" class="btn btn-outline-light rounded-pill position-relative">
                    <i class="fas fa-shopping-cart"></i> Cart
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $cartCount; ?>
                    </span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Header -->
    <header class="menu-hero">
        <div class="container" data-aos="fade-up">
            <h1 class="display-4 fw-bold">Our Fresh Selection</h1>
            <p class="lead text-white-50">Baked fresh every morning, just for you.</p>
        </div>
    </header>

    <div class="container my-5">
        
        <!-- Search & Filter -->
        <div class="text-center mb-5" data-aos="fade-up">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="form-control search-input" placeholder="Search for croissants, cakes...">
            </div>

            <div id="filterContainer">
                <button class="filter-btn active" data-filter="all" onclick="filterMenu('all')">All</button>
                <button class="filter-btn" data-filter="Bread" onclick="filterMenu('Bread')">Breads</button>
                <button class="filter-btn" data-filter="Cakes" onclick="filterMenu('Cakes')">Cakes</button>
                <button class="filter-btn" data-filter="Pastries" onclick="filterMenu('Pastries')">Pastries</button>
                <button class="filter-btn" data-filter="Beverage" onclick="filterMenu('Beverage')">Beverages</button>
            </div>
        </div>

        <div class="row g-4" id="menuGrid">
            <?php while (($row = oci_fetch_assoc($stmt)) != false): ?>
                <?php 
                    // Image Logic
                    if (!empty($row['PRODIMAGE'])) {
                        $imgSrc = "../assets/img/products/" . $row['PRODIMAGE'];
                    } else {
                        $imgSrc = "https://source.unsplash.com/600x400/?" . strtolower($row['PRODCATEGORY']) . ",food";
                    }
                    
                    // Description Logic
                    $desc = !empty($row['PRODDESC']) ? $row['PRODDESC'] : "Delicious handmade " . strtolower($row['PRODCATEGORY']);

                    // Stock Logic
                    $isSoldOut = ($row['PRODQUANTITY'] <= 0);
                    $stockBadge = "";
                    
                    // Only show 'Low Stock' if NOT sold out
                    if (!$isSoldOut && $row['PRODQUANTITY'] < 10) {
                        $stockBadge = '<span class="badge bg-danger position-absolute top-0 start-0 m-3 shadow-sm px-3 py-2 rounded-pill" style="z-index:2; font-size:0.75rem;">Only '.$row['PRODQUANTITY'].' Left!</span>';
                    }
                ?>
                
                <div class="col-md-3 col-sm-6 menu-item" data-category="<?php echo $row['PRODCATEGORY']; ?>" data-name="<?php echo strtolower($row['PRODNAME']); ?>" data-aos="fade-up">
                    <div class="menu-card h-100 d-flex flex-column <?php echo $isSoldOut ? 'sold-out' : ''; ?>">
                        <div class="menu-img-wrap">
                            <?php if ($isSoldOut): ?>
                                <div class="sold-out-overlay">
                                    <span class="sold-out-text">Sold Out</span>
                                </div>
                            <?php else: ?>
                                <?php echo $stockBadge; ?>
                            <?php endif; ?>
                            
                            <img src="<?php echo $imgSrc; ?>" class="menu-img" alt="Product">
                            <span class="category-badge"><?php echo $row['PRODCATEGORY']; ?></span>
                        </div>
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <h5 class="fw-bold text-dark mb-1"><?php echo $row['PRODNAME']; ?></h5>
                            <p class="text-muted small mb-3 flex-grow-1"><?php echo $desc; ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <h5 class="text-warning fw-bold mb-0">RM <?php echo number_format($row['PRODPRICE'], 2); ?></h5>
                                
                                <?php if ($isSoldOut): ?>
                                    <button class="btn btn-secondary rounded-circle shadow-sm" style="width: 40px; height: 40px;" disabled title="Currently Unavailable">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="pid" value="<?php echo $row['PRODID']; ?>">
                                        <input type="hidden" name="pname" value="<?php echo $row['PRODNAME']; ?>">
                                        <input type="hidden" name="pprice" value="<?php echo $row['PRODPRICE']; ?>">
                                        <button type="submit" name="add_to_cart" class="btn btn-dark rounded-circle shadow-sm" style="width: 40px; height: 40px;">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Empty State Message -->
        <div id="no-items" class="text-center py-5" style="display: none;">
            <i class="fas fa-cookie-bite fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No treats found matching your search.</h4>
            <button class="btn btn-outline-dark mt-2" onclick="resetFilters()">Show All Items</button>
        </div>
    </div>

    <!-- Floating Cart Button (Mobile Friendly) -->
    <a href="cart.php" class="btn btn-warning cart-float text-dark">
        <i class="fas fa-shopping-basket"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 12px;">
            <?php echo $cartCount; ?>
        </span>
    </a>

    <!-- Toast Message -->
    <div id="toast" class="custom-toast">
        <i class="fas fa-check-circle me-2"></i> <span id="toastText">Item Added!</span>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <p class="mb-0">&copy; 2026 City Bakery. Freshness Guaranteed.</p>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        let currentCategory = 'all';
        const searchInput = document.getElementById('searchInput');
        const noItemsMsg = document.getElementById('no-items');

        // Unified Filter Logic
        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase();
            const items = document.querySelectorAll('.menu-item');
            let visibleCount = 0;

            items.forEach(item => {
                const itemCat = item.getAttribute('data-category');
                const itemName = item.getAttribute('data-name');
                
                const matchesCategory = (currentCategory === 'all' || itemCat === currentCategory);
                const matchesSearch = itemName.includes(searchTerm);

                if (matchesCategory && matchesSearch) {
                    item.style.display = 'block';
                    // Re-trigger animation nicely
                    if (!item.classList.contains('aos-animate')) {
                        item.classList.add('aos-animate');
                    }
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Toggle Empty State
            noItemsMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }

        // Category Click Handler
        function filterMenu(category) {
            currentCategory = category;
            
            // Update Active Class
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-filter') === category) {
                    btn.classList.add('active');
                }
            });

            applyFilters();
        }

        // Search Listener
        searchInput.addEventListener('keyup', applyFilters);

        function resetFilters() {
            searchInput.value = '';
            filterMenu('all');
        }

        // Toast Notification Logic (Triggered by PHP)
        <?php if ($toastMessage): ?>
            const toast = document.getElementById('toast');
            document.getElementById('toastText').innerText = "<?php echo $toastMessage; ?>";
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>