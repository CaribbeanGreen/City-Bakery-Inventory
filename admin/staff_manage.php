<?php
session_start();
include '../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// TOP MANAGER CHECK (Only Sophia / staffId 101 can change roles)
$isTopManager = ($_SESSION['staffId'] == 101);
$msg = "";

// 2. Handle Resign Action (Archive Staff) - RESTORED
if (isset($_GET['resign_id'])) {
    $rId = $_GET['resign_id'];
    // We update status instead of deleting to preserve order history
    $sqlResign = "UPDATE STAFF SET status = 'Resigned' WHERE staffId = :rid";
    $stmtResign = oci_parse($conn, $sqlResign);
    oci_bind_by_name($stmtResign, ":rid", $rId);
    
    if (oci_execute($stmtResign, OCI_COMMIT_ON_SUCCESS)) {
        echo "<script>alert('Staff member has been marked as Resigned.'); window.location.href='staff_manage.php';</script>";
    } else {
        $e = oci_error($stmtResign);
        if ($e['code'] == 904) {
            $msg = "<div class='alert alert-danger'><strong>Database Error:</strong> Missing 'status' column. Run ALTER TABLE.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
        }
    }
}

// 3. Handle Role Update (Only for Sophia) - KEPT
if (isset($_POST['update_role_btn']) && $isTopManager) {
    $targetId = $_POST['targetStaffId'];
    $newRole = $_POST['newRole'];
    
    $sqlRole = "UPDATE STAFF SET role = :role WHERE staffId = :sid";
    $stRole = oci_parse($conn, $sqlRole);
    oci_bind_by_name($stRole, ":role", $newRole);
    oci_bind_by_name($stRole, ":sid", $targetId);
    
    if (oci_execute($stRole, OCI_COMMIT_ON_SUCCESS)) {
        $msg = "<div class='alert alert-success'>Role updated successfully!</div>";
    }
}

// 4. Handle Add Staff Logic - RESTORED
if (isset($_POST['add_staff_btn'])) {
    $sName = $_POST['sName'];
    $sPhone = $_POST['sPhone'];
    $sEmail = $_POST['sEmail'];
    $sType = $_POST['sType']; 
    $sManager = $_POST['sManager'];
    $sPass = $_POST['sPass'];
    
    $sRole = 'Staff'; 
    $sStatus = 'Active'; 

    $sqlID = "SELECT NVL(MAX(staffId), 100) + 1 AS NEXT_ID FROM STAFF";
    $stmtID = oci_parse($conn, $sqlID);
    oci_execute($stmtID);
    $rowID = oci_fetch_assoc($stmtID);
    $newId = $rowID['NEXT_ID'];

    $mgrVal = ($sManager == "0") ? null : $sManager;

    $sqlParent = "INSERT INTO STAFF (staffId, staffName, staffPhone, staffEmail, staffType, managerId, password, role, status) 
                  VALUES (:sid, :sname, :sphone, :semail, :stype, :smgr, :spass, :srole, :sstat)";
    $stmtParent = oci_parse($conn, $sqlParent);

    oci_bind_by_name($stmtParent, ":sid", $newId);
    oci_bind_by_name($stmtParent, ":sname", $sName);
    oci_bind_by_name($stmtParent, ":sphone", $sPhone);
    oci_bind_by_name($stmtParent, ":semail", $sEmail);
    oci_bind_by_name($stmtParent, ":stype", $sType);
    oci_bind_by_name($stmtParent, ":smgr", $mgrVal);
    oci_bind_by_name($stmtParent, ":spass", $sPass);
    oci_bind_by_name($stmtParent, ":srole", $sRole);
    oci_bind_by_name($stmtParent, ":sstat", $sStatus);

    $r1 = @oci_execute($stmtParent, OCI_NO_AUTO_COMMIT);

    $r2 = false;
    if ($r1) {
        if ($sType == 'FullTime') {
            $benefit = "Standard Benefits (EPF/SOCSO)";
            $sqlChild = "INSERT INTO FULL_TIME (staffId, benefit) VALUES (:sid, :benefit)";
            $stmtChild = oci_parse($conn, $sqlChild);
            oci_bind_by_name($stmtChild, ":sid", $newId);
            oci_bind_by_name($stmtChild, ":benefit", $benefit);
            $r2 = oci_execute($stmtChild, OCI_NO_AUTO_COMMIT);
        } else {
            $rate = 7.50; 
            $sqlChild = "INSERT INTO PART_TIME (staffId, compensation) VALUES (:sid, :rate)";
            $stmtChild = oci_parse($conn, $sqlChild);
            oci_bind_by_name($stmtChild, ":sid", $newId);
            oci_bind_by_name($stmtChild, ":rate", $rate);
            $r2 = oci_execute($stmtChild, OCI_NO_AUTO_COMMIT);
        }
    }

    if ($r1 && $r2) {
        oci_commit($conn);
        $msg = "<div class='alert alert-success'>New Staff (ID: $newId) Added!</div>";
    } else {
        oci_rollback($conn);
        $e = oci_error($stmtParent);
        if ($e['code'] == 1) {
             $msg = "<div class='alert alert-danger'><strong>Error:</strong> Email already registered.</div>";
        } else {
             $msg = "<div class='alert alert-danger'>Error adding Staff: " . $e['message'] . "</div>";
        }
    }
}

// 5. Fetch Active Staff (With Manager Name)
$sqlActive = "SELECT e.*, m.staffName AS ManagerName FROM STAFF e 
              LEFT JOIN STAFF m ON e.managerId = m.staffId 
              WHERE e.status = 'Active' OR e.status IS NULL ORDER BY e.staffId ASC";
$stActive = oci_parse($conn, $sqlActive);
oci_execute($stActive);

// 6. Fetch Resigned Staff
$sqlResigned = "SELECT staffId, staffName, role FROM STAFF WHERE status = 'Resigned' ORDER BY staffId DESC";
$stResigned = oci_parse($conn, $sqlResigned);
oci_execute($stResigned);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sidebar { height: 100vh !important; position: sticky; top: 0; }
    </style>
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Staff Directory</h2>
            <?php if($isTopManager): ?>
                <span class="badge bg-dark p-2"><i class="fas fa-user-shield me-1"></i> Top Manager Access</span>
            <?php endif; ?>
        </div>

        <?php echo $msg; ?>

        <div class="row">
            <!-- LEFT: Staff Lists -->
            <div class="col-md-8">
                
                <!-- TABLE 1: ACTIVE EMPLOYEES -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-success"><i class="fas fa-user-check me-2"></i>Active Employees</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name & Role</th>
                                    <th>Contact Info</th>
                                    <th>Role Mgmt</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = oci_fetch_assoc($stActive)): ?>
                                <tr>
                                    <td><?php echo $row['STAFFID']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['STAFFNAME']; ?></div>
                                        <div class="small text-muted"><?php echo $row['STAFFTYPE']; ?></div>
                                        <div class="small text-muted fst-italic">Reports to: <?php echo $row['MANAGERNAME'] ? $row['MANAGERNAME'] : '-'; ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><i class="fas fa-envelope me-1 text-secondary"></i> <?php echo $row['STAFFEMAIL']; ?></div>
                                        <div class="small"><i class="fas fa-phone me-1 text-secondary"></i> <?php echo $row['STAFFPHONE']; ?></div>
                                    </td>
                                    <td>
                                        <?php if($isTopManager && $row['STAFFID'] != 101): ?>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="targetStaffId" value="<?php echo $row['STAFFID']; ?>">
                                                <select name="newRole" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="Staff" <?php if($row['ROLE'] == 'Staff') echo 'selected'; ?>>Staff</option>
                                                    <option value="Manager" <?php if($row['ROLE'] == 'Manager') echo 'selected'; ?>>Manager</option>
                                                </select>
                                                <input type="hidden" name="update_role_btn">
                                            </form>
                                        <?php else: ?>
                                            <span class="badge <?php echo ($row['ROLE'] == 'Manager') ? 'bg-dark' : 'bg-secondary'; ?>">
                                                <?php echo $row['ROLE']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="staff_edit.php?id=<?php echo $row['STAFFID']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php if($row['STAFFID'] != $_SESSION['staffId']): ?>
                                        <a href="staff_manage.php?resign_id=<?php echo $row['STAFFID']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Confirm resignation for <?php echo $row['STAFFNAME']; ?>?')" title="Resign"><i class="fas fa-user-times"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TABLE 2: FORMER EMPLOYEES (EX-STAFF) -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Former Employees (History)</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0 text-muted">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = oci_fetch_assoc($stResigned)): ?>
                                <tr>
                                    <td><?php echo $row['STAFFID']; ?></td>
                                    <td><?php echo $row['STAFFNAME']; ?></td>
                                    <td><?php echo $row['ROLE']; ?></td>
                                    <td><span class="badge bg-secondary">Resigned</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Add New Staff Form -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px; z-index: 1;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-plus"></i> Add Employee</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-2">
                                <label class="small fw-bold">Full Name</label>
                                <input type="text" name="sName" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Phone</label>
                                <input type="text" name="sPhone" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Email (Login ID)</label>
                                <input type="email" name="sEmail" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold">Password</label>
                                <input type="password" name="sPass" class="form-control" value="12345" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="small fw-bold">Employment Type</label>
                                <select name="sType" class="form-select">
                                    <option value="FullTime">Full Time</option>
                                    <option value="PartTime">Part Time</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold">Reports To (Manager)</label>
                                <select name="sManager" class="form-select">
                                    <option value="0">-- No Manager --</option>
                                    <?php
                                    $sqlMgr = "SELECT staffId, staffName FROM STAFF WHERE role='Manager' AND (status = 'Active' OR status IS NULL)";
                                    $stmtMgr = oci_parse($conn, $sqlMgr);
                                    oci_execute($stmtMgr);
                                    while ($mRow = oci_fetch_assoc($stmtMgr)) {
                                        echo "<option value='".$mRow['STAFFID']."'>".$mRow['STAFFNAME']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="add_staff_btn" class="btn btn-success">Create Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>