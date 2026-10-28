<?php
session_start();
include '../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// 2. Handle Date Filtering (Default to current month)
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// 3. Handle Pagination for Ledger
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 8;
$offset = ($page - 1) * $recordsPerPage;

// --- ANALYTICAL QUERIES ---

// 4. KPI Summary
$sqlStats = "SELECT 
                NVL(SUM(d.total), 0) AS REVENUE,
                COUNT(DISTINCT o.orderId) AS ORDERS,
                NVL(SUM(d.total - (SELECT NVL(AVG(unitCost), 0) FROM INVOICE_DETAIL WHERE prodId = d.prodId) * d.quantity), 0) AS PROFIT
             FROM CUSTOMER_ORDER o 
             LEFT JOIN ORDER_DETAIL d ON o.orderId = d.orderId 
             WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')";
$stStats = oci_parse($conn, $sqlStats);
oci_bind_by_name($stStats, ":sdate", $startDate);
oci_bind_by_name($stStats, ":edate", $endDate);
oci_execute($stStats);
$stats = oci_fetch_assoc($stStats);

// 5. Category Share Analysis
$sqlCat = "SELECT p.prodCategory, SUM(d.total) AS REVENUE
           FROM ORDER_DETAIL d
           JOIN PRODUCT p ON d.prodId = p.prodId
           JOIN CUSTOMER_ORDER o ON d.orderId = o.orderId
           WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
           GROUP BY p.prodCategory";
$stCat = oci_parse($conn, $sqlCat);
oci_bind_by_name($stCat, ":sdate", $startDate);
oci_bind_by_name($stCat, ":edate", $endDate);
oci_execute($stCat);
$catData = []; while($r = oci_fetch_assoc($stCat)) { $catData[] = $r; }

// 6. Peak Hour Analysis
$sqlHour = "SELECT SUBSTR(orderTime, 1, 2) AS HR, COUNT(*) AS VOL
            FROM CUSTOMER_ORDER 
            WHERE TRUNC(orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
            GROUP BY SUBSTR(orderTime, 1, 2) ORDER BY HR ASC";
$stHour = oci_parse($conn, $sqlHour);
oci_bind_by_name($stHour, ":sdate", $startDate);
oci_bind_by_name($stHour, ":edate", $endDate);
oci_execute($stHour);
$hourData = []; while($r = oci_fetch_assoc($stHour)) { $hourData[] = $r; }

// 7. Customer Loyalty
$sqlCust = "SELECT c.custName, COUNT(o.orderId) AS VISITS, SUM(d.total) AS SPENT
            FROM CUSTOMER c
            JOIN CUSTOMER_ORDER o ON c.custId = o.custId
            JOIN ORDER_DETAIL d ON o.orderId = d.orderId
            WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
            GROUP BY c.custName ORDER BY SPENT DESC FETCH FIRST 5 ROWS ONLY";
$stCust = oci_parse($conn, $sqlCust);
oci_bind_by_name($stCust, ":sdate", $startDate);
oci_bind_by_name($stCust, ":edate", $endDate);
oci_execute($stCust);

// 8. Staff Performance
$sqlStaff = "SELECT s.staffName, s.role, COUNT(o.orderId) AS ORDERS, SUM(d.total) AS SALES
             FROM STAFF s 
             JOIN CUSTOMER_ORDER o ON s.staffId = o.staffId 
             JOIN ORDER_DETAIL d ON o.orderId = d.orderId
             WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
             GROUP BY s.staffName, s.role 
             ORDER BY SALES DESC";
$stStaff = oci_parse($conn, $sqlStaff);
oci_bind_by_name($stStaff, ":sdate", $startDate);
oci_bind_by_name($stStaff, ":edate", $endDate);
oci_execute($stStaff);

// 9. NEW: Top Selling Products (Favorite Menu)
$sqlTop = "SELECT p.prodName, SUM(d.quantity) as TOTAL_QTY 
           FROM ORDER_DETAIL d 
           JOIN PRODUCT p ON d.prodId = p.prodId
           JOIN CUSTOMER_ORDER o ON d.orderId = o.orderId
           WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
           GROUP BY p.prodName 
           ORDER BY TOTAL_QTY DESC FETCH FIRST 5 ROWS ONLY";
$stTop = oci_parse($conn, $sqlTop);
oci_bind_by_name($stTop, ":sdate", $startDate);
oci_bind_by_name($stTop, ":edate", $endDate);
oci_execute($stTop);

// 10. Detailed Ledger (Itemized)
$sqlList = "SELECT o.orderId, o.orderDate, o.orderTime, c.custName, 
            (SELECT SUM(total) FROM ORDER_DETAIL WHERE orderId = o.orderId) AS AMT,
            (SELECT LISTAGG(p.prodName || ' (x' || d.quantity || ')', ', ') WITHIN GROUP (ORDER BY p.prodName)
             FROM ORDER_DETAIL d 
             JOIN PRODUCT p ON d.prodId = p.prodId 
             WHERE d.orderId = o.orderId) AS ITEMS
            FROM CUSTOMER_ORDER o JOIN CUSTOMER c ON o.custId = c.custId
            WHERE TRUNC(o.orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')
            ORDER BY o.orderId DESC OFFSET :off ROWS FETCH NEXT :rpp ROWS ONLY";
$stList = oci_parse($conn, $sqlList);
oci_bind_by_name($stList, ":sdate", $startDate);
oci_bind_by_name($stList, ":edate", $endDate);
oci_bind_by_name($stList, ":off", $offset);
oci_bind_by_name($stList, ":rpp", $recordsPerPage);
oci_execute($stList);

$sqlCount = "SELECT COUNT(*) AS CNT FROM CUSTOMER_ORDER WHERE TRUNC(orderDate) BETWEEN TO_DATE(:sdate, 'YYYY-MM-DD') AND TO_DATE(:edate, 'YYYY-MM-DD')";
$stC = oci_parse($conn, $sqlCount);
oci_bind_by_name($stC, ":sdate", $startDate);
oci_bind_by_name($stC, ":edate", $endDate);
oci_execute($stC);
$rC = oci_fetch_assoc($stC); $totalRecords = $rC['CNT'];
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Executive Analysis - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar { height: 100vh !important; position: sticky; top: 0; }
        .kpi-card { border: none; border-radius: 15px; color: white; }
        .chart-container { position: relative; height: 300px; width: 100%; }
        .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        @media print { .no-print, .sidebar { display: none !important; } }
    </style>
</head>
<body class="bg-light">

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Sales Analysis</h2>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-dark me-2"><i class="fas fa-print me-2"></i>Print Report</button>
                <a href="../logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4"><label class="small fw-bold">Start Date</label><input type="date" name="start" class="form-control" value="<?php echo $startDate; ?>"></div>
                    <div class="col-md-4"><label class="small fw-bold">End Date</label><input type="date" name="end" class="form-control" value="<?php echo $endDate; ?>"></div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Update Report</button>
                        <a href="reports.php" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card kpi-card p-3 shadow-sm bg-success">
                    <small class="text-white-50 fw-bold">TOTAL REVENUE</small>
                    <h2 class="fw-bold mb-0">RM <?php echo number_format($stats['REVENUE'], 2); ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card p-3 shadow-sm bg-primary">
                    <small class="text-white-50 fw-bold">GROSS PROFIT (EST.)</small>
                    <h2 class="fw-bold mb-0">RM <?php echo number_format($stats['PROFIT'], 2); ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card p-3 shadow-sm bg-info">
                    <small class="text-white-50 fw-bold">AVG ORDER VALUE</small>
                    <h2 class="fw-bold mb-0">RM <?php echo ($stats['ORDERS'] > 0) ? number_format($stats['REVENUE']/$stats['ORDERS'], 2) : '0.00'; ?></h2>
                </div>
            </div>
        </div>

        <!-- Row 1: Peak Hour Traffic (Full Width) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="fas fa-clock me-2 text-warning"></i>Peak Hour Traffic</div>
            <div class="card-body"><div class="chart-container" style="height: 250px;"><canvas id="hourChart"></canvas></div></div>
        </div>

        <!-- Row 2: Category Revenue & Top Selling Items -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Revenue by Category</div>
                    <div class="card-body d-flex justify-content-center">
                        <div class="chart-container" style="max-width: 350px;"><canvas id="catChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold text-success"><i class="fas fa-crown me-2"></i>Top 5 Favorite Menu Items</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th class="text-end">Qty Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                while($top = oci_fetch_assoc($stTop)): 
                                    $badge = ($rank == 1) ? '🥇' : (($rank == 2) ? '🥈' : '🥉');
                                ?>
                                <tr>
                                    <td>
                                        <span class="me-2"><?php echo ($rank <= 3) ? $badge : $rank.'.'; ?></span>
                                        <strong><?php echo $top['PRODNAME']; ?></strong>
                                    </td>
                                    <td class="text-end fw-bold text-success"><?php echo $top['TOTAL_QTY']; ?></td>
                                </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Loyal Customers & Staff -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold text-danger"><i class="fas fa-heart me-2"></i>Top Loyal Customers</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Customer</th><th class="text-center">Visits</th><th class="text-end">Total Spent</th></tr></thead>
                            <tbody>
                                <?php while($c = oci_fetch_assoc($stCust)): ?>
                                <tr><td><?php echo $c['CUSTNAME']; ?></td><td class="text-center"><?php echo $c['VISITS']; ?></td><td class="text-end fw-bold">RM <?php echo number_format($c['SPENT'], 2); ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold text-primary"><i class="fas fa-medal me-2"></i>Staff Productivity</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Staff</th><th>Role</th><th class="text-center">Orders</th><th class="text-end">Sales</th></tr></thead>
                            <tbody>
                                <?php while($s = oci_fetch_assoc($stStaff)): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $s['STAFFNAME']; ?></td>
                                    <td><span class="badge <?php echo ($s['ROLE'] == 'Manager') ? 'bg-dark' : 'bg-secondary'; ?>"><?php echo $s['ROLE']; ?></span></td>
                                    <td class="text-center"><?php echo $s['ORDERS']; ?></td>
                                    <td class="text-end fw-bold">RM <?php echo number_format($s['SALES'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Ledger -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Transaction Ledger (Itemized)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Date/Time</th>
                                <th>Customer</th>
                                <th style="width: 35%;">Items Purchased</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($l = oci_fetch_assoc($stList)): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?php echo $l['ORDERID']; ?></span></td>
                                <td class="small"><?php echo $l['ORDERDATE']." ".$l['ORDERTIME']; ?></td>
                                <td class="fw-bold"><?php echo $l['CUSTNAME']; ?></td>
                                <td><small class="text-muted text-truncate-2" title="<?php echo htmlspecialchars($l['ITEMS']); ?>"><?php echo $l['ITEMS']; ?></small></td>
                                <td class="text-end fw-bold text-primary">RM <?php echo number_format($l['AMT'], 2); ?></td>
                                <td class="text-center no-print"><a href="order_details.php?oid=<?php echo $l['ORDERID']; ?>" class="btn btn-sm btn-outline-primary">Details</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 no-print">
                <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page==$i)?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&start=<?php echo $startDate; ?>&end=<?php echo $endDate; ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                </ul></nav>
            </div>
        </div>
    </div>
</div>

<script>
// Peak Hour Chart
new Chart(document.getElementById('hourChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($hourData, 'HR')); ?>.map(h => h + ':00'),
        datasets: [{ label: 'Orders', data: <?php echo json_encode(array_column($hourData, 'VOL')); ?>, borderColor: '#f39c12', tension: 0.3, fill: true, backgroundColor: 'rgba(243, 156, 18, 0.1)' }]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

// Category Revenue Chart
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($catData, 'PRODCATEGORY')); ?>,
        datasets: [{ data: <?php echo json_encode(array_column($catData, 'REVENUE')); ?>, backgroundColor: ['#2ecc71', '#3498db', '#9b59b6', '#f1c40f'] }]
    },
    options: { maintainAspectRatio: false }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>