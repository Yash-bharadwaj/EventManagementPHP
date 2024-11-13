<?php
// admin/events/edit.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Edit Event';
$errors = [];
$success = false;

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    redirect('/event_management/admin/events/list.php', 'Invalid event ID', 'danger');
}

$conn = Database::getInstance()->getConnection();

// Fetch categories for dropdown
$stmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch event details
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    redirect('/event_management/admin/events/list.php', 'Event not found', 'danger');
}

// Initialize event data with default values
$event = array_merge([
    'title' => '',
    'description' => '',
    'category_id' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'location' => '',
    'capacity' => 0,
    'price' => 0.00,
    'status' => 'draft',
    'image_url' => ''
], $event);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $location = sanitize($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'draft');

    // Validation
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if (empty($start_date) || empty($start_time)) {
        $errors[] = "Start date and time are required";
    }
    
    if (empty($end_date) || empty($end_time)) {
        $errors[] = "End date and time are required";
    }
    
    // Combine date and time
    $start_datetime = date('Y-m-d H:i:s', strtotime("$start_date $start_time"));
    $end_datetime = date('Y-m-d H:i:s', strtotime("$end_date $end_time"));
    
    if ($end_datetime <= $start_datetime) {
        $errors[] = "End date must be after start date";
    }

    // Handle image upload
    $image_url = $event['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Invalid image type. Please upload JPG, PNG, or GIF";
        }
        
        if ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image size must be less than 5MB";
        }
        
        if (empty($errors)) {
            $upload_dir = '../../uploads/events/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if ($event['image_url'] && file_exists('../../' . ltrim($event['image_url'], '/'))) {
                    unlink('../../' . ltrim($event['image_url'], '/'));
                }
                $image_url = '/event_management/uploads/events/' . $filename;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }

    // If no errors, update event
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE events SET
                    title = ?,
                    description = ?,
                    category_id = ?,
                    image_url = ?,
                    start_date = ?,
                    end_date = ?,
                    location = ?,
                    capacity = ?,
                    price = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $description,
                $category_id,
                $image_url,
                $start_datetime,
                $end_datetime,
                $location,
                $capacity,
                $price,
                $status,
                $event_id
            ]);

            // Check if this event has any bookings before changing status to cancelled
            if ($status === 'cancelled') {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM bookings 
                    WHERE event_id = ? AND status = 'confirmed'
                ");
                $stmt->execute([$event_id]);
                $bookingCount = $stmt->fetchColumn();

                if ($bookingCount > 0) {
                    // Send notification emails to booked users
                    $stmt = $conn->prepare("
                        SELECT u.email, u.first_name, b.id as booking_id
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        WHERE b.event_id = ? AND b.status = 'confirmed'
                    ");
                    $stmt->execute([$event_id]);
                    $bookedUsers = $stmt->fetchAll();

                    foreach ($bookedUsers as $user) {
                        // In a real application, you would send actual emails here
                        error_log("Would send cancellation email to: " . $user['email']);
                    }
                }
            }

            redirect('/event_management/admin/events/list.php', 'Event updated successfully', 'success');
        } catch (PDOException $e) {
            $errors[] = "Error updating event: " . $e->getMessage();
        }
    }
}

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
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Edit Event</h1>
                        <a href="list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Basic Information</h5>
                                        
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Event Title *</label>
                                            <input type="text" class="form-control" id="title" name="title" 
                                                   value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description *</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="category_id" class="form-label">Category *</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"
                                                            <?php echo ($event['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="location" class="form-label">Location *</label>
                                                <input type="text" class="form-control" id="location" name="location" 
                                                       value="<?php echo htmlspecialchars($event['location']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Date and Time -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Date and Time</h5>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="start_date" class="form-label">Start Date *</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                                       value="<?php echo date('Y-m-d', strtotime($event['start_date'])); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="start_time" class="form-label">Start Time *</label>
                                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                                       value="<?php echo date('H:i', strtotime($event['start_date'])); ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="end_date" class="form-label">End Date *</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                                       value="<?php echo date('Y-m-d', strtotime($event['end_date'])); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="end_time" class="form-label">End Time *</label>
                                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                                       value="<?php echo date('H:i', strtotime($event['end_date'])); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar Options -->
                            <div class="col-md-4">
                                <!-- Status and Visibility -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Status</h5>
                                        
                                        <div class="mb-3">
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="draft" <?php echo ($event['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo ($event['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                                <option value="cancelled" <?php echo ($event['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Image Upload -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Event Image</h5>
                                        
                                        <?php if (!empty($event['image_url'])): ?>
                                            <div class="mb-3">
                                                <img src="<?php echo htmlspecialchars($event['image_url']); ?>" 
                                                     class="img-thumbnail mb-2" alt="Current event image">
                                                <p class="text-muted mb-0">Current image</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <label for="image" class="form-label">Upload New Image</label>
                                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                            <small class="text-muted">Max size: 5MB. Supported formats: JPG, PNG, GIF</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Capacity and Price -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Capacity & Price</h5>
                                        
                                        <div class="mb-3">
                                            <label for="capacity" class="form-label">Capacity *</label>
                                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                                   value="<?php echo (int)$event['capacity']; ?>" required min="1">
                                        </div>

                                        <div class="mb-3">
                                        <label for="price" class="form-label">Price ($) *</label>
                                            <input type="number" class="form-control" id="price" name="price" 
                                                   value="<?php echo number_format((float)$event['price'], 2, '.', ''); ?>" 
                                                   required step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Update Event</button>
                                <a href="list.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
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
})()

// Date validation
document.getElementById('end_date').addEventListener('change', function() {
    var startDate = document.getElementById('start_date').value;
    var endDate = this.value;
    
    if (startDate && endDate < startDate) {
        this.setCustomValidity('End date must be after start date');
    } else {
        this.setCustomValidity('');
    }
});

// Status change warning
document.getElementById('status').addEventListener('change', function() {
    if (this.value === 'cancelled') {
        if (!confirm('Are you sure you want to cancel this event? This will notify all registered attendees.')) {
            this.value = this.dataset.previousValue || 'draft';
            return;
        }
    }
    this.dataset.previousValue = this.value;
});

// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var preview = document.createElement('img');
            preview.src = e.target.result;
            preview.className = 'img-thumbnail mb-2';
            preview.style.maxWidth = '200px';
            
            var container = document.getElementById('image').parentElement;
            var existingPreview = container.querySelector('img');
            if (existingPreview) {
                container.removeChild(existingPreview);
            }
            container.insertBefore(preview, document.getElementById('image'));
        }
        
        reader.readAsDataURL(e.target.files[0]);
    }
});

// Time validation
function validateTimes() {
    var startDate = document.getElementById('start_date').value;
    var startTime = document.getElementById('start_time').value;
    var endDate = document.getElementById('end_date').value;
    var endTime = document.getElementById('end_time').value;
    
    if (startDate && startTime && endDate && endTime) {
        var startDateTime = new Date(startDate + 'T' + startTime);
        var endDateTime = new Date(endDate + 'T' + endTime);
        
        if (endDateTime <= startDateTime) {
            document.getElementById('end_time').setCustomValidity('End time must be after start time');
        } else {
            document.getElementById('end_time').setCustomValidity('');
        }
    }
}

document.getElementById('start_time').addEventListener('change', validateTimes);
document.getElementById('end_time').addEventListener('change', validateTimes);
</script>

<?php include '../../includes/footer.php'; ?>