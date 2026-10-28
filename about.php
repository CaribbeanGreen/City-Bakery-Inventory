<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Story - City Bakery</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts (Premium Typography) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #fff; }
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Playfair Display', serif; }

        /* Parallax Hero */
        .about-hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://source.unsplash.com/1600x900/?bakery,kitchen');
            background-attachment: fixed;
            background-position: center;
            background-size: cover;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
        }
        
        .about-hero::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 0;
            width: 100%;
            height: 100px;
            background: white;
            transform: skewY(-2deg);
        }

        /* Story Section */
        .story-img-wrap {
            position: relative;
            padding: 20px;
        }
        .story-img-wrap::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100px; height: 100px;
            border-top: 5px solid var(--bakery-primary);
            border-left: 5px solid var(--bakery-primary);
            z-index: 0;
        }
        .story-img {
            border-radius: 10px;
            position: relative;
            z-index: 1;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        /* Value Cards */
        .value-card {
            padding: 40px 30px;
            background: #fdf2e9;
            border-radius: 20px;
            transition: 0.3s;
            height: 100%;
            border: 1px solid transparent;
        }
        .value-card:hover {
            background: white;
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: #eee;
        }
        .value-icon {
            width: 70px; height: 70px;
            background: white;
            color: var(--bakery-primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .value-card:hover .value-icon {
            background: var(--bakery-primary);
            color: white;
        }

        /* Team Section */
        .team-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .team-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .team-img { height: 300px; object-fit: cover; filter: grayscale(20%); transition: 0.5s; }
        .team-card:hover .team-img { filter: grayscale(0%); transform: scale(1.05); }

        /* Navbar Scrolled State */
        .navbar-scrolled {
            background-color: var(--bakery-dark) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }
        
        /* CTA Section */
        .cta-section {
            background: var(--bakery-dark);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Transparent Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top p-4 transition-all" id="mainNav">
        <div class="container">
            <a class="navbar-brand fs-3" href="index.php"><i class="fas fa-bread-slice me-2 text-warning"></i>City Bakery</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="customer/menu.php">Menu</a></li>
                    <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="#">About Us</a></li>
                    <li class="nav-item"><a class="btn btn-warning btn-sm ms-lg-3 text-dark fw-bold px-4 rounded-pill shadow-sm" href="login.php">Staff Portal</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="about-hero">
        <div class="container" data-aos="zoom-in" data-aos-duration="1000">
            <h5 class="text-uppercase letter-spacing-2 text-warning fw-bold mb-3">Since 2020</h5>
            <h1 class="display-3 fw-bold">Baking Memories</h1>
            <p class="lead fs-5 text-white-50">The heart of Kuala Terengganu's pastry scene.</p>
        </div>
    </header>

    <!-- Our Story -->
    <section class="py-5 mt-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-md-6 mb-5 mb-md-0" data-aos="fade-right">
                    <div class="story-img-wrap">
                        <!-- Using your local image as requested -->
                        <img src="assets/img/baking_process.jpg" 
                             class="img-fluid story-img" 
                             alt="Baking Process"
                             onerror="this.src='https://source.unsplash.com/800x600/?kneading,dough'">
                    </div>
                </div>
                <div class="col-md-6 ps-md-5" data-aos="fade-left">
                    <h6 class="text-warning fw-bold text-uppercase mb-3">Our Origin Story</h6>
                    <h2 class="fw-bold display-5 mb-4">Made from Scratch, Baked with Love.</h2>
                    <p class="text-muted lead">City Bakery started as a small home kitchen project by <strong>Sophia Bakar</strong>. Her passion for creating the perfect croissant led to the opening of our first flagship store.</p>
                    <p class="text-muted mb-4">Today, we are proud to serve hundreds of customers daily, ensuring that every bun, cake, and pastry is baked fresh every single morning using only the finest local ingredients.</p>
                    
                    <div class="row text-center g-4">
                        <div class="col-4">
                            <h2 class="fw-bold text-dark m-0">5+</h2>
                            <small class="text-uppercase text-muted fw-bold">Years</small>
                        </div>
                        <div class="col-4 border-start border-end">
                            <h2 class="fw-bold text-dark m-0">50+</h2>
                            <small class="text-uppercase text-muted fw-bold">Menu Items</small>
                        </div>
                        <div class="col-4">
                            <h2 class="fw-bold text-dark m-0">10k+</h2>
                            <small class="text-uppercase text-muted fw-bold">Happy Customers</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h6 class="text-warning fw-bold text-uppercase">Our Philosophy</h6>
                <h2 class="fw-bold display-5">Why We Bake</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="value-card">
                        <div class="value-icon"><i class="fas fa-leaf"></i></div>
                        <h4>100% Fresh</h4>
                        <p class="text-muted">We bake in small batches throughout the day to ensure warm, fresh goodness every time you walk in.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="value-card">
                        <div class="value-icon"><i class="fas fa-certificate"></i></div>
                        <h4>Halal Certified</h4>
                        <p class="text-muted">We strictly adhere to Halal standards in all our ingredients and processes for your peace of mind.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="value-card">
                        <div class="value-icon"><i class="fas fa-heart"></i></div>
                        <h4>Community First</h4>
                        <p class="text-muted">We source seasonal ingredients from local Terengganu farmers whenever possible to support our neighbors.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Meet the Team -->
    <section class="py-5 bg-light">
        <div class="container py-5">
            <div class="row mb-5 align-items-end">
                <div class="col-md-8" data-aos="fade-right">
                    <h6 class="text-warning fw-bold text-uppercase">The Team</h6>
                    <h2 class="fw-bold display-5">People Behind the Flour</h2>
                </div>
                <div class="col-md-4 text-md-end" data-aos="fade-left">
                    <p class="text-muted mb-0">Meet the dedicated artisans who make your mornings brighter.</p>
                </div>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Sophia -->
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="team-card bg-white h-100">
                        <img src="https://ui-avatars.com/api/?name=Sophia+Bakar&background=d35400&color=fff&size=512" class="team-img card-img-top w-100" alt="Sophia">
                        <div class="card-body text-center p-4">
                            <h4 class="card-title fw-bold">Sophia Bakar</h4>
                            <p class="card-text text-primary small text-uppercase fw-bold ls-1">Founder & Manager</p>
                            <p class="text-muted small">"Baking is not just a job, it's a way of sharing love with the community."</p>
                        </div>
                    </div>
                </div>
                <!-- Ahmad -->
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="team-card bg-white h-100">
                        <img src="https://ui-avatars.com/api/?name=Ahmad+Ammar&background=2980b9&color=fff&size=512" class="team-img card-img-top w-100" alt="Ahmad">
                        <div class="card-body text-center p-4">
                            <h4 class="card-title fw-bold">Ahmad Ammar</h4>
                            <p class="card-text text-primary small text-uppercase fw-bold ls-1">Assistant Manager</p>
                            <p class="text-muted small">Ensuring operations run smoothly so you get your coffee on time.</p>
                        </div>
                    </div>
                </div>
                <!-- Firdaus -->
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="300">
                    <div class="team-card bg-white h-100">
                        <img src="https://ui-avatars.com/api/?name=Firdaus+Omar&background=27ae60&color=fff&size=512" class="team-img card-img-top w-100" alt="Firdaus">
                        <div class="card-body text-center p-4">
                            <h4 class="card-title fw-bold">Firdaus Omar</h4>
                            <p class="card-text text-primary small text-uppercase fw-bold ls-1">Head Baker</p>
                            <p class="text-muted small">The mastermind behind our signature Sourdough and Croissants.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container" data-aos="fade-up">
            <h2 class="display-5 fw-bold mb-4">Ready to Taste the Difference?</h2>
            <p class="lead mb-5 opacity-75">Order online now and pick up your fresh treats in store.</p>
            <a href="customer/menu.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-dark shadow hover-scale">
                View Menu <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-5 border-top border-secondary">
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
        // Initialize Animations
        AOS.init({
            once: true, // Only animate once when scrolling down
            offset: 100
        });

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