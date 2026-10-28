<?php
/* ==================================================
   ORACLE DB CONNECTION
   File: config/db_connect.php
   ================================================== */

$db_user = "citybakery";       // The user we just created
$db_pass = "12345";            // The password
$db_host = "localhost/freepdb1"; // <--- IMPORTANT: Match the Service Name here!

$conn = oci_connect($db_user, $db_pass, $db_host);

if (!$conn) {
    $e = oci_error();
    die("<div class='alert alert-danger'>Oracle Connection Failed: " . $e['message'] . "</div>");
}
?>

