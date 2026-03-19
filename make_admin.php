<?php
$plain_password = 'admin123';  // baguhin mo 'to sa gusto mong password
$hashed = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hashed . "<br>";
echo "Gamitin mo 'to sa SQL INSERT para sa admin.";
?>