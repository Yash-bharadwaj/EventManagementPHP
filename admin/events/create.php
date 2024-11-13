<?php
// admin/events/create.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Create Event';
$errors = [];
$success = false;

// Fetch categories for dropdown
$conn = Database::getInstance()->getConnection();
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];
    $location = sanitize($_POST['location']);
    $capacity = (int)$_POST['capacity'];
    $price = (float)$_POST['price'];
    $status = sanitize($_POST['status']);

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
    $image_url = null;
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
                $image_url = '/event_management/uploads/events/' . $filename;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }

    // If no errors, insert event
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO events (
                    title, description, category_id, image_url, 
                    start_date, end_date, location, capacity,
                    price, status, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $title, $description, $category_id, $image_url,
                $start_datetime, $end_datetime, $location, $capacity,
                $price, $status, $_SESSION['user_id']
            ]);

            $success = true;
            redirect('/event_management/admin/events/list.php', 'Event created successfully', 'success');
        } catch (PDOException $e) {
            $errors[] = "Error creating event: " . $e->getMessage();
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
                    <h1 class="h3 mb-4">Create Event</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
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
                                                   value="<?php echo $_POST['title'] ?? ''; ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description *</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="5" required><?php echo $_POST['description'] ?? ''; ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="category_id" class="form-label">Category *</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"
                                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="location" class="form-label">Location *</label>
                                                <input type="text" class="form-control" id="location" name="location" 
                                                       value="<?php echo $_POST['location'] ?? ''; ?>" required>
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
                                                       value="<?php echo $_POST['start_date'] ?? ''; ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="start_time" class="form-label">Start Time *</label>
                                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                                       value="<?php echo $_POST['start_time'] ?? ''; ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="end_date" class="form-label">End Date *</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                                       value="<?php echo $_POST['end_date'] ?? ''; ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="end_time" class="form-label">End Time *</label>
                                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                                       value="<?php echo $_POST['end_time'] ?? ''; ?>" required>
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
                                                <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                                <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Image Upload -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Event Image</h5>
                                        
                                        <div class="mb-3">
                                            <label for="image" class="form-label">Upload Image</label>
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
                                                   value="<?php echo $_POST['capacity'] ?? '100'; ?>" required min="1">
                                        </div>

                                        <div class="mb-3">
                                            <label for="price" class="form-label">Price ($) *</label>
                                            <input type="number" class="form-control" id="price" name="price" 
                                                   value="<?php echo $_POST['price'] ?? '0.00'; ?>" required step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Create Event</button>
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
</script>

<?php include '../../includes/footer.php'; ?>