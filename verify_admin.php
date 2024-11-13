<?php
// test_payment.php - Place this in your project root temporarily
require_once 'includes/init.php';

echo "<h2>Payment System Test</h2>";

try {
    $conn = Database::getInstance()->getConnection();
    
    // 1. Verify database connection
    echo "✓ Database connection successful<br>";
    
    // 2. Check if test user exists
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute(['test@example.com']);
    $user = $stmt->fetch();
    echo $user ? "✓ Test user found: {$user['email']}<br>" : "✗ Test user not found<br>";
    
    // 3. Check for pending bookings
    $stmt = $conn->prepare("
        SELECT b.*, e.title as event_title
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        WHERE b.user_id = ? AND b.status = 'pending'
    ");
    $stmt->execute([$user['id']]);
    $pending_bookings = $stmt->fetchAll();
    
    echo count($pending_bookings) . " pending bookings found:<br>";
    foreach ($pending_bookings as $booking) {
        echo "- Booking ID {$booking['id']}: {$booking['event_title']} 
              (\${$booking['total_amount']})<br>";
    }
    
    // 4. Display test links
    if (!empty($pending_bookings)) {
        echo "<br>Test Links:<br>";
        foreach ($pending_bookings as $booking) {
            echo "<a href='/event_management/payment/checkout.php?booking_id={$booking['id']}'>
                    Test Checkout for Booking {$booking['id']}
                  </a><br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>