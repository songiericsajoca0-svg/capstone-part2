<?php
// subscribe.php (pansamantala)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Dito mo ilagay ang code para mag-save o mag-send
        // Halimbawa: file_put_contents('subscribers.txt', $email . PHP_EOL, FILE_APPEND);
        echo "<script>alert('Salamat! Nakareceive na kami ng iyong subscription.');</script>";
    } else {
        echo "<script>alert('Invalid email address.');</script>";
    }
}
header("Location: index.php"); // balik sa home
exit;
?>