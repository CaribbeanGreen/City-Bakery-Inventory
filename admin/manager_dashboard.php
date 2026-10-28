<?php
session_start();
include '../config/db_connect.php';

// Security Check: Only Managers allowed
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// --- ORACLE QUERIES FOR DASHBOARD ANALYTICS ---

// 1. Calculate Today's Revenue (Live Update)
$sqlRev = "SELECT SUM(d.total) AS TODAY_REV 
           FROM ORDER_DETAIL d 
           JOIN CUSTOMER_ORDER o ON d.orderId = o.orderId 
           WHERE TRUNC(o.orderDate) = TRUNC(SYSDATE)";
$stRev = oci_parse($conn, $sqlRev);
oci_execute($stRev);
$rowRev = oci_fetch_assoc($stRev);
$todayRevenue = $rowRev['TODAY_REV'] ?? 0;

// 2. Count Today's Orders
$sqlToday = "SELECT COUNT(*) AS TODAY_COUNT FROM CUSTOMER_ORDER WHERE TRUNC(orderDate) = TRUNC(SYSDATE)";
$stToday = oci_parse($conn, $sqlToday);
oci_execute($stToday);
$rowToday = oci_fetch_assoc($stToday);
$ordersToday = $rowToday['TODAY_COUNT'];

// 3. Count Registered Customers
$sqlCust = "SELECT COUNT(*) AS CUST_COUNT FROM CUSTOMER";
$stCust = oci_parse($conn, $sqlCust);
oci_execute($stCust);
$rowCust = oci_fetch_assoc($stCust);
$totalCustomers = $rowCust['CUST_COUNT'];

// 4. Low Stock Notifications
$sqlLow = "SELECT prodId, prodName, prodQuantity, prodMinimum FROM PRODUCT WHERE prodQuantity < prodMinimum";
$stLow = oci_parse($conn, $sqlLow);
oci_execute($stLow);

$lowStockItems = [];
while ($low = oci_fetch_assoc($stLow)) { $lowStockItems[] = $low; }
$lowStockCount = count($lowStockItems);

// 5. NEW: Expired Stock Detection (Safety Critical)
$sqlExp = "SELECT prodId, prodName, prodQuantity, prodExpDate 
           FROM PRODUCT 
           WHERE prodExpDate < TRUNC(SYSDATE) AND prodQuantity > 0";
$stExp = oci_parse($conn, $sqlExp);
oci_execute($stExp);

$expiredItems = [];
while ($ex = oci_fetch_assoc($stExp)) { $expiredItems[] = $ex; }
$expiredCount = count($expiredItems);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar { height: 100vh !important; position: sticky; top: 0; overflow-y: auto; }
        .stats-card { transition: transform 0.2s; border: none; border-radius: 12px; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        
        /* Checklist Styles */
        .task-item { transition: 0.2s; border-left: 3px solid transparent; }
        .task-item:hover { background-color: #f8f9fa; border-left: 3px solid #ccc; }
        .task-checked { border-left: 3px solid #198754; }
    </style>
</head>
<body>

    <div class="d-flex" style="min-height: 100vh;">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark">Welcome back, <?php echo explode(' ', $_SESSION['staffName'])[0]; ?>! 👋</h2>
                    <p class="text-muted mb-0">Overview for <?php echo date('d M Y'); ?>.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="restock.php" class="btn btn-outline-primary"><i class="fas fa-truck-loading me-2"></i>New Restock</a>
                    <a href="inventory_add.php" class="btn btn-bakery shadow-sm"><i class="fas fa-plus me-2"></i>New Product</a>
                </div>
            </div>

            <!-- EXPIRED ITEMS ALERT (Top Priority) -->
            <?php if ($expiredCount > 0): ?>
            <div class="alert alert-danger border-0 shadow-sm p-4 mb-4" role="alert">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3 text-danger"><i class="fas fa-biohazard fa-2x"></i></div>
                    <div>
                        <h5 class="alert-heading fw-bold m-0">Food Safety Alert: Expired Items</h5>
                        <p class="mb-0">The following items are past their expiry date and must be discarded immediately.</p>
                    </div>
                </div>
                <div class="table-responsive bg-white rounded p-2 border border-danger">
                    <table class="table table-sm table-borderless mb-0 align-middle">
                        <thead class="text-danger border-bottom">
                            <tr>
                                <th>Product Name</th>
                                <th class="text-center">Quantity to Waste</th>
                                <th class="text-end">Expired On</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($expiredItems as $ex): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $ex['PRODNAME']; ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo $ex['PRODQUANTITY']; ?> units</td>
                                <td class="text-end"><?php echo $ex['PRODEXPDATE']; ?></td>
                                <td class="text-center">
                                    <a href="dispose_stock.php?id=<?php echo $ex['PRODID']; ?>" 
                                       class="btn btn-sm btn-danger py-0 shadow-sm">
                                       <i class="fas fa-trash-alt me-1"></i> Manage Waste
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- LOW STOCK ALERT -->
            <?php if ($lowStockCount > 0): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center p-3 mb-4" role="alert">
                <i class="fas fa-exclamation-triangle fs-4 me-3 text-warning"></i>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1 fw-bold text-dark">Low Stock Warning</h6>
                    <p class="mb-0 small text-dark">There are <strong><?php echo $lowStockCount; ?></strong> items below minimum stock level.</p>
                </div>
                <a href="inventory_list.php" class="btn btn-sm btn-warning px-3 rounded-pill fw-bold text-dark">View Inventory</a>
            </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <!-- Revenue Card -->
                <div class="col-md-3">
                    <div class="card stats-card shadow-sm h-100 p-3 bg-white border-start border-success border-5">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase">Revenue Today</h6>
                                <h3 class="fw-bold mb-0">RM <?php echo number_format($todayRevenue, 2); ?></h3>
                                <small class="text-success"><i class="fas fa-sync-alt fa-spin me-1"></i> Live</small>
                            </div>
                            <div class="fs-1 text-success opacity-25"><i class="fas fa-coins"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Orders Card -->
                <div class="col-md-3">
                    <div class="card stats-card shadow-sm h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase">Orders Today</h6>
                                <h3 class="fw-bold mb-0"><?php echo $ordersToday; ?></h3>
                            </div>
                            <div class="fs-1 text-primary opacity-25"><i class="fas fa-shopping-basket"></i></div>
                        </div>
                    </div>
                </div>

                <!-- NEW: Customers Card -->
                <div class="col-md-3">
                    <div class="card stats-card shadow-sm h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase">Registered Users</h6>
                                <h3 class="fw-bold mb-0"><?php echo $totalCustomers; ?></h3>
                            </div>
                            <div class="fs-1 text-info opacity-25"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Expired Stock Card -->
                <div class="col-md-3">
                    <div class="card stats-card shadow-sm h-100 p-3 border-start border-danger border-5">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php if($expiredCount > 0): ?>
                                    <h6 class="text-danger small fw-bold text-uppercase">Expired Items</h6>
                                    <h3 class="fw-bold mb-0 text-danger"><?php echo $expiredCount; ?> Items</h3>
                                <?php else: ?>
                                    <h6 class="text-muted small fw-bold text-uppercase">Low Stock Alerts</h6>
                                    <h3 class="fw-bold mb-0 text-warning"><?php echo $lowStockCount; ?> Items</h3>
                                <?php endif; ?>
                            </div>
                            <div class="fs-1 text-danger opacity-25"><i class="fas fa-biohazard"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SPLIT VIEW: Recent Orders & Task Checklist -->
            <div class="row">
                <!-- Recent Orders -->
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 mb-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Recent In-Store & Online Orders</h5>
                            <a href="reports.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Customer</th>
                                            <th>Staff In-Charge</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sqlRecent = "SELECT o.orderId, c.custName, s.staffName,
                                                      (SELECT SUM(total) FROM ORDER_DETAIL WHERE orderId = o.orderId) AS AMT
                                                      FROM CUSTOMER_ORDER o
                                                      JOIN CUSTOMER c ON o.custId = c.custId
                                                      JOIN STAFF s ON o.staffId = s.staffId
                                                      ORDER BY o.orderId DESC FETCH FIRST 5 ROWS ONLY";
                                        $stRec = oci_parse($conn, $sqlRecent);
                                        oci_execute($stRec);
                                        while ($rec = oci_fetch_assoc($stRec)): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary">#<?php echo $rec['ORDERID']; ?></span></td>
                                            <td class="fw-bold"><?php echo $rec['CUSTNAME']; ?></td>
                                            <td><?php echo $rec['STAFFNAME']; ?></td>
                                            <td class="text-end fw-bold">RM <?php echo number_format($rec['AMT'], 2); ?></td>
                                            <td class="text-center">
                                                <a href="order_details.php?oid=<?php echo $rec['ORDERID']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW: Dynamic Operations Checklist -->
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 mb-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-clipboard-check me-2 text-success"></i>Daily Operations</h5>
                            <button class="btn btn-sm btn-success rounded-pill px-3" onclick="addNewTask()"><i class="fas fa-plus me-1"></i> Add</button>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush" id="dynamicTaskList">
                                <!-- Tasks render here -->
                            </ul>
                            <div id="emptyTaskMsg" class="text-center py-4 text-muted small d-none">
                                <i class="fas fa-check-double fa-2x mb-2 text-light"></i><br>All caught up!
                            </div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="resetDefaultTasks()">Reset to Default</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Task Management Script
        const defaultTasks = [
            { text: "Check Cash Drawer Balance", done: false },
            { text: "Inspect Refrigerator Temp", done: false },
            { text: "Review Expiring Inventory", done: false },
            { text: "Approve Staff Shifts", done: false },
            { text: "Clean Display Cases", done: false },
            { text: "Count Closing Float", done: false }
        ];

        // Load from LocalStorage or use Default
        let tasks = JSON.parse(localStorage.getItem('bakeryTasks')) || JSON.parse(JSON.stringify(defaultTasks));

        document.addEventListener("DOMContentLoaded", function() {
            renderTasks();
        });

        function renderTasks() {
            const list = document.getElementById('dynamicTaskList');
            const emptyMsg = document.getElementById('emptyTaskMsg');
            list.innerHTML = '';

            if (tasks.length === 0) {
                emptyMsg.classList.remove('d-none');
            } else {
                emptyMsg.classList.add('d-none');
                tasks.forEach((task, index) => {
                    const li = document.createElement('li');
                    li.className = `list-group-item task-item p-3 d-flex justify-content-between align-items-center ${task.done ? 'task-checked bg-light' : ''}`;
                    
                    // Left Side: Icon + Text (Click to Toggle)
                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'd-flex align-items-center flex-grow-1';
                    contentDiv.style.cursor = 'pointer';
                    contentDiv.onclick = () => toggleTask(index);

                    const icon = document.createElement('i');
                    icon.className = `far ${task.done ? 'fa-check-square text-success' : 'fa-square'} me-3 fa-lg`;
                    
                    const textSpan = document.createElement('span');
                    textSpan.className = task.done ? 'text-decoration-line-through text-muted' : '';
                    textSpan.innerText = task.text;

                    contentDiv.appendChild(icon);
                    contentDiv.appendChild(textSpan);

                    // Right Side: Edit/Delete Actions
                    const actionDiv = document.createElement('div');
                    
                    const editBtn = document.createElement('button');
                    editBtn.className = 'btn btn-link text-secondary p-1 me-1';
                    editBtn.innerHTML = '<i class="fas fa-pencil-alt small"></i>';
                    editBtn.onclick = (e) => { e.stopPropagation(); editTask(index); };
                    
                    const delBtn = document.createElement('button');
                    delBtn.className = 'btn btn-link text-danger p-1';
                    delBtn.innerHTML = '<i class="fas fa-trash-alt small"></i>';
                    delBtn.onclick = (e) => { e.stopPropagation(); deleteTask(index); };

                    actionDiv.appendChild(editBtn);
                    actionDiv.appendChild(delBtn);

                    li.appendChild(contentDiv);
                    li.appendChild(actionDiv);
                    list.appendChild(li);
                });
            }
            saveTasks();
        }

        function saveTasks() {
            localStorage.setItem('bakeryTasks', JSON.stringify(tasks));
        }

        function toggleTask(index) {
            tasks[index].done = !tasks[index].done;
            renderTasks();
        }

        function addNewTask() {
            const text = prompt("Enter new task description:");
            if (text && text.trim() !== "") {
                tasks.push({ text: text.trim(), done: false });
                renderTasks();
            }
        }

        function editTask(index) {
            const newText = prompt("Edit task:", tasks[index].text);
            if (newText !== null && newText.trim() !== "") {
                tasks[index].text = newText.trim();
                renderTasks();
            }
        }

        function deleteTask(index) {
            if(confirm('Are you sure you want to delete this task?')) {
                tasks.splice(index, 1);
                renderTasks();
            }
        }

        function resetDefaultTasks() {
            if(confirm('Reset list to default daily tasks? This will delete custom tasks.')) {
                tasks = JSON.parse(JSON.stringify(defaultTasks));
                renderTasks();
            }
        }
    </script>
</body>
</html>