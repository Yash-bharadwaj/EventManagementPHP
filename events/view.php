<?php
// events/view.php
require_once '../includes/init.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    redirect('/event_management/events', 'Invalid event ID', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch event details with category and creator information
$stmt = $conn->prepare("
    SELECT e.*, 
           c.name as category_name,
           u.first_name as creator_first_name,
           u.last_name as creator_last_name,
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id AND status = 'confirmed') as booking_count
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = ? AND e.status = 'published'
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    redirect('/event_management/events', 'Event not found', 'danger');
}

// Calculate available tickets
$available_tickets = $event['capacity'] - $event['booking_count'];

// Handle booking submission
$booking_error = '';
$booking_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_tickets'])) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = "/event_management/events/view.php?id=$event_id";
        redirect('/event_management/auth/login.php', 'Please login to book tickets', 'info');
    }

    $quantity = (int)$_POST['quantity'];
    $total_amount = $quantity * $event['price'];

    if ($quantity < 1) {
        $booking_error = 'Please select at least one ticket';
    } elseif ($quantity > $available_tickets) {
        $booking_error = 'Not enough tickets available';
    } else {
        try {
            $conn->beginTransaction();

            // Check availability again (prevent race condition)
            $stmt = $conn->prepare("
                SELECT (capacity - (
                    SELECT COUNT(*) FROM bookings 
                    WHERE event_id = ? AND status = 'confirmed'
                )) as available
                FROM events WHERE id = ?
            ");
            $stmt->execute([$event_id, $event_id]);
            $current_availability = $stmt->fetchColumn();

            if ($current_availability >= $quantity) {
                // Create booking
                $stmt = $conn->prepare("
                    INSERT INTO bookings (
                        event_id, user_id, quantity, total_amount, 
                        status, payment_status, created_at
                    ) VALUES (?, ?, ?, ?, 'pending', 'pending', NOW())
                ");
                $stmt->execute([
                    $event_id,
                    $_SESSION['user_id'],
                    $quantity,
                    $total_amount
                ]);

                $booking_id = $conn->lastInsertId();
                $conn->commit();

                // Redirect to payment page
                redirect("/event_management/payment/checkout.php?booking_id=$booking_id");
            } else {
                $conn->rollBack();
                $booking_error = 'Sorry, these tickets are no longer available';
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $booking_error = 'An error occurred while processing your booking';
            error_log($e->getMessage());
        }
    }
}

$pageTitle = $event['title'];
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Event Details -->
        <div class="col-lg-8">
            <!-- Event Image -->
            <?php if ($event['image_url']): ?>
                <img src="<?php echo htmlspecialchars($event['image_url']); ?>" 
                     class="img-fluid rounded mb-4" 
                     alt="<?php echo htmlspecialchars($event['title']); ?>">
            <?php endif; ?>

            <!-- Event Header -->
            <div class="mb-4">
                <h1 class="mb-3"><?php echo htmlspecialchars($event['title']); ?></h1>
                
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span class="badge bg-primary">
                        <?php echo htmlspecialchars($event['category_name']); ?>
                    </span>
                    
                    <?php if ($available_tickets <= 10): ?>
                        <span class="badge bg-danger">
                            Only <?php echo $available_tickets; ?> tickets left!
                        </span>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted">
                            <i class="fas fa-calendar fa-fw me-2"></i>
                            <span>
                                <?php 
                                $start_date = new DateTime($event['start_date']);
                                $end_date = new DateTime($event['end_date']);
                                
                                if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
                                    // Same day event
                                    echo $start_date->format('F d, Y');
                                    echo '<br>';
                                    echo $start_date->format('g:i A') . ' - ' . $end_date->format('g:i A');
                                } else {
                                    // Multi-day event
                                    echo $start_date->format('F d, Y g:i A');
                                    echo '<br>to<br>';
                                    echo $end_date->format('F d, Y g:i A');
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex align-items-center text-muted">
                            <i class="fas fa-map-marker-alt fa-fw me-2"></i>
                            <span><?php echo htmlspecialchars($event['location']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Description -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">About This Event</h5>
                    <div class="card-text">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>
            </div>

            <!-- Organizer Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Event Organizer</h5>
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-circle fa-3x text-muted"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">
                                <?php echo htmlspecialchars($event['creator_first_name'] . ' ' . $event['creator_last_name']); ?>
                            </h6>
                            <p class="text-muted mb-0">Event Organizer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-body">
                    <h5 class="card-title mb-4">Book Your Tickets</h5>
                    
                    <?php if ($booking_error): ?>
                        <div class="alert alert-danger">
                            <?php echo $booking_error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h3 class="text-primary mb-0">
                            $<?php echo number_format($event['price'], 2); ?>
                        </h3>
                        <small class="text-muted">per ticket</small>
                    </div>

                    <?php if ($available_tickets > 0): ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Number of Tickets</label>
                                <select class="form-select" id="quantity" name="quantity" required>
                                    <?php
                                    $max_tickets = min($available_tickets, 10); // Limit to 10 tickets per transaction
                                    for ($i = 1; $i <= $max_tickets; $i++) {
                                        echo "<option value=\"$i\">$i</option>";
                                    }
                                    ?>
                                </select>
                                <div class="form-text">
                                    <?php echo $available_tickets; ?> tickets available
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Price per ticket:</span>
                                    <span>$<?php echo number_format($event['price'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total:</span>
                                    <span id="totalAmount">$<?php echo number_format($event['price'], 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" name="book_tickets" class="btn btn-primary w-100 mb-3">
                                Book Now
                            </button>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-lock me-1"></i>
                                    Secure checkout
                                </small>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Sorry, this event is sold out!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update total amount when quantity changes
document.getElementById('quantity').addEventListener('change', function() {
    const quantity = parseInt(this.value);
    const pricePerTicket = <?php echo $event['price']; ?>;
    const total = quantity * pricePerTicket;
    document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);
});

// Form validation
(function() {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include '../includes/footer.php'; ?>