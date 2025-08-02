<?php
$servername = "localhost";
$username = "kasunpre_av";
$password = "Kasun2052";
$dbname = "kasunpre_av";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>