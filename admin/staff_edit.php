<?php
session_start();
include '../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['staffId']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];
$msg = "";

// 2. Fetch Existing Data (Join Parent + Both Child Tables)
if ($id) {
    // We use LEFT JOIN so we can get data regardless of whether they are Full Time or Part Time
    $sql = "SELECT s.*, f.benefit, p.compensation 
            FROM STAFF s 
            LEFT JOIN FULL_TIME f ON s.staffId = f.staffId 
            LEFT JOIN PART_TIME p ON s.staffId = p.staffId 
            WHERE s.staffId = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":id", $id);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);

    if (!$row) {
        die("Staff member not found!");
    }
}

// 3. Handle Update Logic
if (isset($_POST['update_btn'])) {
    $name = $_POST['sName'];
    $phone = $_POST['sPhone'];
    $email = $_POST['sEmail'];
    $pass = $_POST['sPass'];
    $manager = $_POST['sManager'];
    
    // Parent Table Update
    $mgrVal = ($manager == "0") ? null : $manager;
    $sqlParent = "UPDATE STAFF SET staffName=:nm, staffPhone=:ph, staffEmail=:em, managerId=:mgr, password=:pw WHERE staffId=:id";
    $stmtP = oci_parse($conn, $sqlParent);
    oci_bind_by_name($stmtP, ":nm", $name);
    oci_bind_by_name($stmtP, ":ph", $phone);
    oci_bind_by_name($stmtP, ":em", $email);
    oci_bind_by_name($stmtP, ":mgr", $mgrVal);
    oci_bind_by_name($stmtP, ":pw", $pass);
    oci_bind_by_name($stmtP, ":id", $id);
    
    $r1 = oci_execute($stmtP, OCI_NO_AUTO_COMMIT);

    // Child Table Update (Based on Type)
    $r2 = true;
    if ($row['STAFFTYPE'] == 'FullTime') {
        $benefit = $_POST['sBenefit'];
        $sqlC = "UPDATE FULL_TIME SET benefit=:ben WHERE staffId=:id";
        $stmtC = oci_parse($conn, $sqlC);
        oci_bind_by_name($stmtC, ":ben", $benefit);
        oci_bind_by_name($stmtC, ":id", $id);
        $r2 = oci_execute($stmtC, OCI_NO_AUTO_COMMIT);
    } else {
        $comp = $_POST['sComp'];
        $sqlC = "UPDATE PART_TIME SET compensation=:comp WHERE staffId=:id";
        $stmtC = oci_parse($conn, $sqlC);
        oci_bind_by_name($stmtC, ":comp", $comp);
        oci_bind_by_name($stmtC, ":id", $id);
        $r2 = oci_execute($stmtC, OCI_NO_AUTO_COMMIT);
    }

    if ($r1 && $r2) {
        oci_commit($conn);
        echo "<script>alert('Staff Details Updated!'); window.location.href='staff_manage.php';</script>";
    } else {
        oci_rollback($conn);
        $e = oci_error($stmtP);
        $msg = "<div class='alert alert-danger'>Error: " . $e['message'] . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Staff - City Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <h2 class="fw-bold mb-4">Edit Staff (ID: <?php echo $row['STAFFID']; ?>)</h2>
        <?php echo $msg; ?>

        <div class="card shadow-sm border-0 p-4" style="max-width: 600px;">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="sName" class="form-control" value="<?php echo $row['STAFFNAME']; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="sPhone" class="form-control" value="<?php echo $row['STAFFPHONE']; ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="sEmail" class="form-control" value="<?php echo $row['STAFFEMAIL']; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Password</label>
                    <input type="text" name="sPass" class="form-control" value="<?php echo $row['PASSWORD']; ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Reports To (Manager)</label>
                    <select name="sManager" class="form-select">
                        <option value="0">-- Top Level Manager --</option>
                        <?php
                        // List managers to allow reassignment
                        $sqlMgr = "SELECT staffId, staffName FROM STAFF WHERE role='Manager' AND status='Active'";
                        $stmtMgr = oci_parse($conn, $sqlMgr);
                        oci_execute($stmtMgr);
                        while ($m = oci_fetch_assoc($stmtMgr)) {
                            $selected = ($m['STAFFID'] == $row['MANAGERID']) ? 'selected' : '';
                            // Prevent selecting self as manager
                            if ($m['STAFFID'] != $row['STAFFID']) {
                                echo "<option value='".$m['STAFFID']."' $selected>".$m['STAFFNAME']."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <hr>
                
                <!-- Specific Fields based on Type -->
                <div class="mb-3">
                    <span class="badge bg-secondary mb-2"><?php echo $row['STAFFTYPE']; ?> Employee</span>
                    
                    <?php if ($row['STAFFTYPE'] == 'FullTime'): ?>
                        <label class="d-block form-label fw-bold">Benefits Package</label>
                        <input type="text" name="sBenefit" class="form-control" value="<?php echo $row['BENEFIT']; ?>">
                    <?php else: ?>
                        <label class="d-block form-label fw-bold">Hourly Rate (RM)</label>
                        <input type="number" step="0.50" name="sComp" class="form-control" value="<?php echo $row['COMPENSATION']; ?>">
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="staff_manage.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_btn" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>