<?php
// user/ticket.php
require_once '../includes/init.php';
requireLogin();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if (!$booking_id) {
    redirect('bookings.php', 'Invalid booking', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch booking details
$stmt = $conn->prepare("
    SELECT b.*, 
           e.title as event_title, 
           e.start_date,
           e.end_date,
           e.location,
           e.image_url,
           c.name as category_name
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    redirect('bookings.php', 'Booking not found', 'danger');
}

$pageTitle = 'Event Ticket';
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body p-5">
                    <!-- Ticket Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Event Ticket</h1>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>

                    <!-- QR Code -->
                    <div class="text-center mb-4">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php 
                            echo urlencode('BOOKING:' . $booking_id . '-' . $_SESSION['user_id']); 
                        ?>" alt="Ticket QR Code" class="img-fluid">
                        <div class="mt-2">
                            <small class="text-muted">
                                Booking Reference: <?php echo str_pad($booking['id'], 8, '0', STR_PAD_LEFT); ?>
                            </small>
                        </div>
                    </div>

                    <!-- Event Details -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <?php if ($booking['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                     class="img-fluid rounded" 
                                     alt="<?php echo htmlspecialchars($booking['event_title']); ?>">
                            <?php else: ?>
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h4><?php echo htmlspecialchars($booking['event_title']); ?></h4>
                            <p class="text-muted mb-2">
                                <i class="fas fa-calendar me-2"></i>
                                <?php 
                                $start = new DateTime($booking['start_date']);
                                $end = new DateTime($booking['end_date']);
                                
                                if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                                    echo $start->format('F d, Y');
                                    echo '<br>';
                                    echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                } else {
                                    echo $start->format('F d, Y g:i A');
                                    echo '<br>to<br>';
                                    echo $end->format('F d, Y g:i A');
                                }
                                ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($booking['location']); ?>
                            </p>
                            <p class="mb-0">
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($booking['category_name']); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Ticket Details -->
                    <div class="card bg-light">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="card-title">Ticket Details</h6>
                                    <p class="card-text mb-1">
                                        <strong>Quantity:</strong> <?php echo $booking['quantity']; ?> ticket(s)
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Type:</strong> General Admission
                                    </p>
                                    <p class="card-text mb-0">
                                        <strong>Price:</strong> $<?php echo number_format($booking['total_amount'], 2); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="card-title">Attendee Information</h6>
                                    <p class="card-text mb-1">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Booking Date:</strong> 
                                        <?php echo date('F d, Y', strtotime($booking['created_at'])); ?>
                                    </p>
                                    <p class="card-text mb-0">
                                        <strong>Status:</strong>
                                        <span class="badge bg-success">Confirmed</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Important Information -->
                    <div class="mt-4">
                        <h6>Important Information:</h6>
                        <ul class="text-muted small">
                            <li>Please arrive at least 30 minutes before the event start time</li>
                            <li>Present this ticket (printed or digital) at the venue</li>
                            <li>This ticket is valid for one-time entry only</li>
                            <li>No refunds or exchanges are permitted</li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 text-center">
                        <a href="bookings.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Bookings
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print me-1"></i> Print Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style type="text/css" media="print">
    @page {
        size: auto;
        margin: 0mm;
    }
    
    body {
        margin: 1cm;
    }
    
    .btn, 
    .navbar,
    footer {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
</style>

<?php include '../includes/footer.php'; ?>