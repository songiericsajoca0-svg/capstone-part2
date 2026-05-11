<?php
require_once '../includes/config.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
require_once '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in as driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login as driver.']);
    exit;
}

$driver_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$send_email = isset($_POST['send_email']) ? (int)$_POST['send_email'] : 0;

if ($booking_id == 0 || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request: missing booking_id or action']);
    exit;
}

// Verify booking belongs to this driver
$verify_query = $conn->prepare("SELECT id, passenger_id, booking_code, pickup_landmark, dropoff_landmark, fare_amount, total_pax, trike_units, distance, toda_name, payment_method, status FROM bookings WHERE id = ? AND driver_id = ?");
$verify_query->bind_param("ii", $booking_id, $driver_id);
$verify_query->execute();
$booking = $verify_query->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => 'Unknown action'];

if ($action === 'pickup') {
    $update = $conn->prepare("UPDATE bookings SET status = 'PASSENGER PICKED UP', updated_at = NOW() WHERE id = ?");
    $update->bind_param("i", $booking_id);
    
    if ($update->execute()) {
        $response = ['success' => true, 'message' => 'Status updated to passenger picked up'];
    } else {
        $response = ['success' => false, 'message' => 'Failed to update status: ' . $conn->error];
    }
    
} elseif ($action === 'complete') {
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL");
    
    $update = $conn->prepare("UPDATE bookings SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $update->bind_param("i", $booking_id);
    
    if ($update->execute()) {
        $email_sent = false;
        
        if ($send_email == 1) {
            $passenger_query = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'passenger'");
            $passenger_query->bind_param("i", $booking['passenger_id']);
            $passenger_query->execute();
            $passenger = $passenger_query->get_result()->fetch_assoc();
            
            if ($passenger && !empty($passenger['email'])) {
                $mail = new PHPMailer(true);
                
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'songiericsajoca0@gmail.com';
                    $mail->Password   = 'xlqoyuisayfzoply';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    
                    // Recipients
                    $mail->setFrom('songiericsajoca0@gmail.com', 'GoTrike');
                    $mail->addAddress($passenger['email'], $passenger['name']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = "Trip Completed - GoTrike Booking #" . $booking['booking_code'];
                    
                    $formatted_date = date('F j, Y');
                    $formatted_time = date('g:i A');
                    $payment_method_display = ($booking['payment_method'] === 'gcash') ? 'GCash' : 'Cash on Pickup';
                    
                    $mail->Body = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Trip Completed</title>
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f0f8ff; margin: 0; padding: 20px; }
                            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                            .header { background: #87CEEB; color: black; padding: 25px; text-align: center; }
                            .header h1 { margin: 0; font-size: 28px; }
                            .content { padding: 25px; }
                            .booking-card { background: #f0f8ff; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid #87CEEB; }
                            .detail-row { display: flex; margin-bottom: 12px; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
                            .detail-label { width: 140px; font-weight: bold; color: black; }
                            .detail-value { flex: 1; color: black; }
                            .fare-box { background: #e6f3ff; padding: 15px; border-radius: 8px; text-align: center; }
                            .fare-amount { font-size: 28px; font-weight: bold; color: black; }
                            .footer { background: #f0f8ff; padding: 15px; text-align: center; font-size: 12px; color: black; }
                            p { color: black; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>Trip Completed!</h1>
                                <p>Thank you for riding with GoTrike</p>
                            </div>
                            <div class="content">
                                <p>Hello <strong>' . htmlspecialchars($passenger['name']) . '</strong>,</p>
                                <p>Your trip has been successfully completed. Here are your trip details:</p>
                                
                                <div class="booking-card">
                                    <div class="detail-row">
                                        <div class="detail-label">Booking Code:</div>
                                        <div class="detail-value">' . htmlspecialchars($booking['booking_code']) . '</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">From:</div>
                                        <div class="detail-value">' . htmlspecialchars($booking['pickup_landmark']) . '</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">To:</div>
                                        <div class="detail-value">' . htmlspecialchars($booking['dropoff_landmark']) . '</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Passengers:</div>
                                        <div class="detail-value">' . $booking['total_pax'] . '</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Distance:</div>
                                        <div class="detail-value">' . number_format($booking['distance'], 2) . ' km</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">TODA:</div>
                                        <div class="detail-value">' . htmlspecialchars($booking['toda_name']) . '</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Date Completed:</div>
                                        <div class="detail-value">' . $formatted_date . ' at ' . $formatted_time . '</div>
                                    </div>
                                    
                                    <div class="fare-box">
                                        <div class="fare-label">Total Fare Paid</div>
                                        <div class="fare-amount">₱' . number_format($booking['fare_amount'], 2) . '</div>
                                        <div class="fare-label" style="margin-top: 8px;">Payment: ' . $payment_method_display . '</div>
                                    </div>
                                </div>
                                
                                <p style="font-size: 13px; color: black;">
                                    Thank you for choosing GoTrike! We hope to serve you again soon.
                                </p>
                            </div>
                            <div class="footer">
                                &copy; ' . date('Y') . ' GoTrike - Safe and Reliable Tricycle Booking
                            </div>
                        </div>
                    </body>
                    </html>';
                    
                    $mail->AltBody = "Trip Completed!\n\nBooking Code: " . $booking['booking_code'] . "\nFrom: " . $booking['pickup_landmark'] . "\nTo: " . $booking['dropoff_landmark'] . "\nFare: ₱" . number_format($booking['fare_amount'], 2);
                    
                    $mail->send();
                    $email_sent = true;
                } catch (Exception $e) {
                    error_log("Email failed: " . $mail->ErrorInfo);
                    $email_sent = false;
                }
            }
        }
        
        $response = ['success' => true, 'message' => 'Trip completed', 'email_sent' => $email_sent];
    } else {
        $response = ['success' => false, 'message' => 'Failed to complete trip: ' . $conn->error];
    }
    
} elseif ($action === 'cancel') {
    $update = $conn->prepare("UPDATE bookings SET status = 'CANCELLED', updated_at = NOW() WHERE id = ?");
    $update->bind_param("i", $booking_id);
    
    if ($update->execute()) {
        $response = ['success' => true, 'message' => 'Trip cancelled'];
    } else {
        $response = ['success' => false, 'message' => 'Failed to cancel trip: ' . $conn->error];
    }
}

echo json_encode($response);
?>