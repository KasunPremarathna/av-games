<?php
$password = 'admin123'; // Sample admin password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
echo $hashedPassword;
?>