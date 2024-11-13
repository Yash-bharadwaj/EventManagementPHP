<?php
// payment/checkout.php
require_once '../includes/init.php';
requireLogin();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if (!$booking_id) {
    redirect('/event_management/events', 'Invalid booking', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch booking details with event information
$stmt = $conn->prepare("
    SELECT b.*, e.title as event_title, e.start_date, e.image_url
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'pending'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    redirect('/event_management/events', 'Booking not found', 'danger');
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Update booking status
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'confirmed', 
                payment_status = 'completed',
                payment_date = NOW()
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Send confirmation email
            // In a real application, you would integrate with an email service
            
            $conn->commit();
            redirect('/event_management/bookings/confirmation.php?booking_id=' . $booking_id, 
                    'Payment successful! Your booking is confirmed.', 'success');
        } else {
            throw new Exception('Booking update failed');
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = 'Payment processing failed. Please try again.';
        error_log($e->getMessage());
    }
}

$pageTitle = 'Checkout';
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">Complete Your Booking</h1>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Order Summary -->
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Order Summary</h5>
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <?php if ($booking['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                             class="img-fluid rounded" 
                                             alt="<?php echo htmlspecialchars($booking['event_title']); ?>">
                                    <?php else: ?>
                                        <div class="text-center">
                                            <i class="fas fa-ticket-alt fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-10">
                                    <h6><?php echo htmlspecialchars($booking['event_title']); ?></h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('F d, Y - g:i A', strtotime($booking['start_date'])); ?>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-ticket-alt me-1"></i>
                                        <?php echo $booking['quantity']; ?> ticket<?php echo $booking['quantity'] > 1 ? 's' : ''; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Processing Fee:</span>
                                <span>$<?php echo number_format($booking['total_amount'] * 0.05, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total:</span>
                                <span>$<?php echo number_format($booking['total_amount'] * 1.05, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <form method="POST" id="payment-form" class="needs-validation" novalidate>
                        <h5 class="mb-3">Payment Details</h5>

                        <div class="mb-3">
                            <label for="card-name" class="form-label">Cardholder Name</label>
                            <input type="text" class="form-control" id="card-name" required>
                        </div>

                        <div class="mb-3">
                            <label for="card-number" class="form-label">Card Number</label>
                            <input type="text" class="form-control" id="card-number" 
                                   pattern="\d{16}" maxlength="16" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="card-expiry" class="form-label">Expiry Date</label>
                                <input type="text" class="form-control" id="card-expiry" 
                                       placeholder="MM/YY" pattern="\d{2}/\d{2}" maxlength="5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="card-cvv" class="form-label">CVV</label>
                                <input type="text" class="form-control" id="card-cvv" 
                                       pattern="\d{3,4}" maxlength="4" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the terms and conditions
                            </label>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            Pay $<?php echo number_format($booking['total_amount'] * 1.05, 2); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })

    // Card number formatting
    document.getElementById('card-number').addEventListener('input', function (e) {
        this.value = this.value.replace(/\D/g, '').substring(0, 16);
    });

    // Expiry date formatting
    document.getElementById('card-expiry').addEventListener('input', function (e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        this.value = value;
    });

    // CVV formatting
    document.getElementById('card-cvv').addEventListener('input', function (e) {
        this.value = this.value.replace(/\D/g, '').substring(0, 4);
    });
})()
</script>

<?php include '../includes/footer.php'; ?>