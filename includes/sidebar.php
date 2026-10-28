<?php
// 1. Low Stock Count
$sqlLowCount = "SELECT COUNT(*) AS CNT FROM PRODUCT WHERE prodQuantity < prodMinimum";
$stLow = oci_parse($conn, $sqlLowCount);
oci_execute($stLow);
$rowLow = oci_fetch_assoc($stLow);
$lowStockBadge = $rowLow['CNT'];

// 2. NEW: Expired Stock Count (Expired AND has Quantity)
$sqlExpCount = "SELECT COUNT(*) AS CNT FROM PRODUCT WHERE prodExpDate < TRUNC(SYSDATE) AND prodQuantity > 0";
$stExp = oci_parse($conn, $sqlExpCount);
oci_execute($stExp);
$rowExp = oci_fetch_assoc($stExp);
$expiredBadge = $rowExp['CNT'];
?>
<div class="sidebar d-flex flex-column p-3 text-white bg-dark" style="width: 260px; min-height: 100vh;">
    <a href="manager_dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-bread-slice me-2 fs-4"></i>
        <span class="fs-4 fw-bold">City Bakery</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="manager_dashboard.php" class="nav-link text-white">
                <i class="fas fa-chart-pie me-2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="inventory_list.php" class="nav-link text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-boxes me-2"></i> Inventory</span>
                <div class="d-flex gap-1">
                    <?php if($expiredBadge > 0): ?>
                        <span class="badge bg-danger rounded-pill" title="Expired Items"><i class="fas fa-biohazard"></i> <?php echo $expiredBadge; ?></span>
                    <?php endif; ?>
                    <?php if($lowStockBadge > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill" title="Low Stock"><?php echo $lowStockBadge; ?></span>
                    <?php endif; ?>
                </div>
            </a>
        </li>
        <!-- ... Keep remaining links same ... -->
        <li>
            <a href="restock.php" class="nav-link text-white">
                <i class="fas fa-truck-loading me-2"></i> Procurement
            </a>
        </li>
        <li>
            <a href="reports.php" class="nav-link text-white">
                <i class="fas fa-file-invoice-dollar me-2"></i> Sales Reports
            </a>
        </li>
        <li>
            <a href="staff_manage.php" class="nav-link text-white">
                <i class="fas fa-user-tie me-2"></i> Staff Management
            </a>
        </li>
        <li>
            <a href="customer_manage.php" class="nav-link text-white">
                <i class="fas fa-users me-2"></i> Customer Mgmt
            </a>
        </li>
    </ul>
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['staffName']; ?>&background=D35400&color=fff" alt="" width="32" height="32" class="rounded-circle me-2">
            <strong><?php echo $_SESSION['staffName']; ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="../logout.php">Sign out</a></li>
        </ul>
    </div>
</div>