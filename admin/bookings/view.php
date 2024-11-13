<?php
// admin/bookings/view.php
require_once '../../includes/init.php';
requireAdmin();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$booking_id) {
    redirect('list.php', 'Invalid booking ID', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch booking details
$stmt = $conn->prepare("
    SELECT b.*, 
           e.title as event_title,
           e.description as event_description,
           e.start_date,
           e.end_date,
           e.location,
           e.image_url,
           c.name as category_name,
           u.first_name,
           u.last_name,
           u.email as user_email,
           u.created_at as user_since
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    LEFT JOIN categories c ON e.category_id = c.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    redirect('list.php', 'Booking not found', 'danger');
}

// Fetch booking history
$stmt = $conn->prepare("
    SELECT *
    FROM booking_logs
    WHERE booking_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$booking_id]);
$booking_logs = $stmt->fetchAll();

$pageTitle = 'Booking Details';
include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <?php include '../admin_sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    Booking #<?php echo str_pad($booking['id'], 8, '0', STR_PAD_LEFT); ?>
                </h1>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <div class="row">
                <!-- Booking Details -->
                <div class="col-md-8">
                    <!-- Event Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Event Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
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
                                    <h5><?php echo htmlspecialchars($booking['event_title']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar me-2"></i>
                                        <?php echo date('F d, Y - g:i A', strtotime($booking['start_date'])); ?>
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
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Booking Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Booking Date:</strong><br>
                                        <?php echo date('F d, Y g:i A', strtotime($booking['created_at'])); ?>
                                    </p>
                                    <p><strong>Quantity:</strong><br>
                                        <?php echo $booking['quantity']; ?> tickets
                                    </p>
                                    <p><strong>Total Amount:</strong><br>
                                        $<?php echo number_format($booking['total_amount'], 2); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong><br>
                                        <span class="badge bg-<?php 
                                            echo $booking['status'] === 'confirmed' ? 'success' : 
                                                ($booking['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Payment Status:</strong><br>
                                        <span class="badge bg-<?php 
                                            echo $booking['payment_status'] === 'completed' ? 'success' : 
                                                ($booking['payment_status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Booking History</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($booking_logs as $log): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <p class="mb-0"><?php echo htmlspecialchars($log['action']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Name:</strong><br>
                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                            </p>
                            <p><strong>Email:</strong><br>
                                <?php echo htmlspecialchars($booking['user_email']); ?>
                            </p>
                            <p><strong>Member Since:</strong><br>
                                <?php echo date('F d, Y', strtotime($booking['user_since'])); ?>
                            </p>
                            <hr>
                            <div class="d-grid gap-2">
                                <a href="mailto:<?php echo htmlspecialchars($booking['user_email']); ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-envelope me-1"></i> Send Email
                                </a>
                                <a href="../users/view.php?id=<?php echo $booking['user_id']; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-user me-1"></i> View Customer Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($booking['status'] !== 'confirmed'): ?>
                                    <form method="POST" action="list.php">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="new_status" value="confirmed">
                                        <button type="submit" name="update_status" class="btn btn-success w-100">
                                            <i class="fas fa-check me-1"></i> Confirm Booking
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($booking['status'] !== 'cancelled'): ?>
                                    <form method="POST" action="list.php">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="new_status" value="cancelled">
                                        <button type="submit" name="update_status" class="btn btn-danger w-100">
                                            <i class="fas fa-times me-1"></i> Cancel Booking
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="print.php?id=<?php echo $booking['id']; ?>" 
                                   class="btn btn-primary" target="_blank">
                                    <i class="fas fa-print me-1"></i> Print Booking Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: -20px;
    width: 2px;
    background: #e9ecef;
}

.timeline-item:last-child:before {
    bottom: 0;
}

.timeline-item:after {
    content: '';
    position: absolute;
    left: -4px;
    top: 8px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #007bff;
}

.timeline-date {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}
</style>

<?php include '../../includes/footer.php'; ?>