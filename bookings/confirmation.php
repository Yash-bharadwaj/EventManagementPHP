<?php
// bookings/confirmation.php
require_once '../includes/init.php';
requireLogin();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if (!$booking_id) {
    redirect('/event_management/events', 'Invalid booking', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch booking details with event information
$stmt = $conn->prepare("
    SELECT b.*, 
           e.title as event_title, 
           e.start_date, 
           e.location,
           e.image_url
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    redirect('/event_management/events', 'Booking not found', 'danger');
}

$pageTitle = 'Booking Confirmation';
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success fa-4x"></i>
                    </div>
                    
                    <h1 class="h3 mb-4">Booking Confirmed!</h1>
                    <p class="text-muted mb-4">
                        Thank you for your purchase. Your booking has been confirmed and tickets have been sent to your email.
                    </p>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Booking Details</h5>
                            <p class="mb-1">
                                <strong>Booking Reference:</strong> 
                                <?php echo str_pad($booking['id'], 8, '0', STR_PAD_LEFT); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Event:</strong> 
                                <?php echo htmlspecialchars($booking['event_title']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Date:</strong>
                                <?php echo date('F d, Y - g:i A', strtotime($booking['start_date'])); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Tickets:</strong>
                                <?php echo $booking['quantity']; ?>
                            </p>
                            <p class="mb-0">
                                <strong>Total Paid:</strong>
                                $<?php echo number_format($booking['total_amount'], 2); ?>
                            </p>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="/event_management/user/bookings.php" class="btn btn-primary">
                            View My Bookings
                        </a>
                        <a href="/event_management/events" class="btn btn-outline-primary">
                            Browse More Events
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>