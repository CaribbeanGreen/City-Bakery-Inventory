<?php
// Start session for any potential nav logic (like showing user name if logged in)
session_start();
include 'config/db_connect.php';

$favorites = [];

// STRATEGY 1: Fetch Top 3 Best Selling Products
// We sum up the quantity sold in ORDER_DETAIL to find the "Favorites"
$sqlFav = "SELECT p.prodName, p.prodPrice, p.prodImage, p.prodCategory, p.prodDesc, SUM(d.quantity) as total_sold 
           FROM PRODUCT p 
           JOIN ORDER_DETAIL d ON p.prodId = d.prodId 
           GROUP BY p.prodId, p.prodName, p.prodPrice, p.prodImage, p.prodCategory, p.prodDesc
           ORDER BY total_sold DESC 
           FETCH FIRST 3 ROWS ONLY";

$stmtFav = oci_parse($conn, $sqlFav);
// Use @ to suppress errors if tables are empty/locked
@oci_execute($stmtFav);

while ($row = oci_fetch_assoc($stmtFav)) {
    $favorites[] = $row;
}

// STRATEGY 2: Fallback (If no sales history found)
// If the array is empty, fetch the 3 most expensive (Premium) items from the menu instead
if (empty($favorites)) {
    $sqlDefault = "SELECT prodName, prodPrice, prodImage, prodCategory, prodDesc 
                   FROM PRODUCT 
                   WHERE prodQuantity > 0 
                   ORDER BY prodPrice DESC 
                   FETCH FIRST 3 ROWS ONLY";
    $stmtDefault = oci_parse($conn, $sqlDefault);
    oci_execute($stmtDefault);
    while ($row = oci_fetch_assoc($stmtDefault)) {
        $favorites[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>City Bakery - Freshness Baked Daily</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts (Premium Typography) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        /* Typography Upgrade */
        body { font-family: 'Poppins', sans-serif; }
        h1, h2, h3, h4, .navbar-brand { font-family: 'Playfair Display', serif; }

        /* Enhanced Hero Section */
        .hero { 
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://source.unsplash.com/1600x900/?croissant,bakery');
            background-size: cover;
            background-position: center;
            background-attachment: fixed; /* Parallax Effect */
            height: 100vh;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }
        
        .hero-title { 
            font-size: 3.5rem; 
            text-shadow: 2px 2px 15px rgba(0,0,0,0.6); 
            margin-bottom: 20px; 
        }
        
        @media (min-width: 768px) {
            .hero-title { font-size: 5rem; }
        }

        .hero-text { 
            font-size: 1.2rem; 
            text-shadow: 1px 1px 5px rgba(0,0,0,0.5); 
            max-width: 700px; 
            margin-bottom: 40px;
        }

        /* Floating Scroll Indicator */
        .scroll-down {
            position: absolute; bottom: 30px;
            animation: bounce 2s infinite;
            color: white; opacity: 0.8;
            font-size: 2rem;
        }

        /* Feature Icons */
        .feature-box {
            padding: 40px 20px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .feature-box:hover { transform: translateY(-10px); }
        
        .icon-circle {
            width: 80px; height: 80px;
            background: var(--bakery-light);
            color: var(--bakery-primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            transition: 0.3s;
        }
        .feature-box:hover .icon-circle {
            background: var(--bakery-primary);
            color: white;
        }

        /* Sneak Peek Cards */
        .preview-card {
            border: none;
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: 0.3s;
            height: 100%; /* Ensure full height */
            background: white;
        }
        .preview-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
        .preview-card img { height: 250px; object-fit: cover; transition: 0.5s; width: 100%; }
        .preview-card:hover img { transform: scale(1.1); }
        .img-wrap { overflow: hidden; }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        
        /* Navbar Scrolled State */
        .navbar-scrolled {
            background-color: var(--bakery-dark) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }
    </style>
</head>
<body>

    <!-- Transparent Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top p-4 transition-all" id="mainNav">
        <div class="container">
            <a class="navbar-brand fs-3" href="#"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="customer/menu.php">Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                    <li class="nav-item"><a class="btn btn-warning btn-sm ms-lg-3 text-dark fw-bold px-4 rounded-pill shadow-sm" href="login.php">Staff Portal</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Banner -->
    <div class="hero">
        <div class="container" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-uppercase letter-spacing-2 text-warning mb-3 fw-bold">Est. 2020</h5>
            <h1 class="hero-title">Taste the Tradition</h1>
            <p class="hero-text mx-auto">From our oven to your heart. Experience the finest handcrafted pastries and artisanal breads in Kuala Terengganu.</p>
            
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="customer/menu.php" class="btn btn-warning btn-lg px-5 py-3 fw-bold rounded-pill shadow hover-scale">
                    Order Now <i class="fas fa-arrow-right ms-2"></i>
                </a>
                <a href="about.php" class="btn btn-outline-light btn-lg px-5 py-3 fw-bold rounded-pill">
                    Our Story
                </a>
            </div>
        </div>
        
        <a href="#features" class="scroll-down">
            <i class="fas fa-chevron-down"></i>
        </a>
    </div>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-warning fw-bold text-uppercase">Why Choose Us</h6>
                <h2 class="fw-bold display-5">Baked to Perfection</h2>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box text-center">
                        <div class="icon-circle"><i class="fas fa-wheat"></i></div>
                        <h4>Premium Ingredients</h4>
                        <p class="text-muted">We use only the finest high-protein flour and premium butter imported for that perfect flake.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box text-center">
                        <div class="icon-circle"><i class="fas fa-clock"></i></div>
                        <h4>Baked Daily</h4>
                        <p class="text-muted">Our ovens fire up at 4 AM every single morning to ensure your breakfast is warm and fresh.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-box text-center">
                        <div class="icon-circle"><i class="fas fa-check-circle"></i></div>
                        <h4>100% Halal</h4>
                        <p class="text-muted">Certified clean preparation processes and ingredients you can trust for your family.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sneak Peek Section (Now Dynamic!) -->
    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="row align-items-center mb-5">
                <div class="col-md-8" data-aos="fade-right">
                    <h2 class="fw-bold display-5">Customer Favorites</h2>
                    <p class="lead text-muted">A glimpse of our <?php echo (count($favorites) > 0) ? 'most popular' : 'finest'; ?> treats.</p>
                </div>
                <div class="col-md-4 text-md-end" data-aos="fade-left">
                    <a href="customer/menu.php" class="btn btn-outline-dark rounded-pill px-4">View Full Menu</a>
                </div>
            </div>

            <div class="row g-4">
                <?php if (empty($favorites)): ?>
                    <!-- Backup Static Content if Database Fails Completely -->
                    <div class="col-12 text-center text-muted">
                        <p>Menu items are currently being updated.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $delay = 100;
                    foreach ($favorites as $item): 
                        // Image Handling Logic
                        if (!empty($item['PRODIMAGE'])) {
                            $imgSrc = "assets/img/products/" . $item['PRODIMAGE'];
                        } else {
                            $cat = strtolower($item['PRODCATEGORY'] ?? 'bakery');
                            $imgSrc = "https://source.unsplash.com/600x400/?" . $cat . ",food";
                        }
                        
                        // Description Handling Logic
                        $desc = !empty($item['PRODDESC']) ? $item['PRODDESC'] : "Freshly baked " . strtolower($item['PRODCATEGORY'] ?? 'delight') . " made with premium ingredients.";
                    ?>
                    <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
                        <div class="preview-card">
                            <div class="img-wrap">
                                <img src="<?php echo $imgSrc; ?>" alt="<?php echo $item['PRODNAME']; ?>">
                            </div>
                            <div class="p-4 d-flex flex-column h-100">
                                <h5 class="fw-bold text-truncate"><?php echo $item['PRODNAME']; ?></h5>
                                <p class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo $desc; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="text-warning fw-bold fs-5">RM <?php echo number_format($item['PRODPRICE'], 2); ?></span>
                                    <span class="badge bg-light text-dark border"><?php echo $item['PRODCATEGORY']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $delay += 100; 
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-5">
        <div class="container">
            <h2 class="fw-bold mb-4" style="font-family: 'Playfair Display', serif;">City Bakery</h2>
            <div class="mb-4">
                <a href="#" class="text-white text-decoration-none mx-3 hover-warning">Instagram</a>
                <a href="#" class="text-white text-decoration-none mx-3 hover-warning">Facebook</a>
                <a href="#" class="text-white text-decoration-none mx-3 hover-warning">TikTok</a>
            </div>
            <p class="text-white-50 small mb-0">&copy; 2026 City Bakery. All rights reserved.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize Animate On Scroll
        AOS.init();

        // Navbar Scroll Effect
        const nav = document.getElementById('mainNav');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.classList.add('navbar-scrolled');
            } else {
                nav.classList.remove('navbar-scrolled');
            }
        });
    </script>
</body>
</html>