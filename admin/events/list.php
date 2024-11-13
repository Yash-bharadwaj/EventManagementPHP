<?php
// admin/events/list.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Manage Events';
$conn = Database::getInstance()->getConnection();

// Handle event deletion
if (isset($_POST['delete_event']) && isset($_POST['event_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$_POST['event_id']]);
        redirect('/event_management/admin/events/list.php', 'Event deleted successfully', 'success');
    } catch (PDOException $e) {
        $errors[] = "Error deleting event: " . $e->getMessage();
    }
}

// Fetch categories for filter
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Build query based on filters
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "title LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
}

if (!empty($_GET['category'])) {
    $where[] = "category_id = ?";
    $params[] = $_GET['category'];
}

if (!empty($_GET['status'])) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch events with category information
$query = "
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id) as booking_count
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.id
    $whereClause
    ORDER BY e.start_date DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

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
                <h1 class="h3">Manage Events</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-1"></i> Create Event
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search events..." 
                                   value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($_GET['status']) && $_GET['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                            <a href="list.php" class="btn btn-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Bookings</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td><?php echo htmlspecialchars($event['category_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo date('M d, Y', strtotime($event['start_date']));
                                            if ($event['start_date'] != $event['end_date']) {
                                                echo ' - ' . date('M d, Y', strtotime($event['end_date']));
                                            }
                                            ?>
                                        </td>
                                        <td>$<?php echo number_format($event['price'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $event['booking_count']; ?> bookings
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $event['status'] === 'published' ? 'success' : 
                                                    ($event['status'] === 'draft' ? 'warning' : 'danger'); 
                                                ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit.php?id=<?php echo $event['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $event['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>

                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $event['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Event</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete "<?php echo htmlspecialchars($event['title']); ?>"?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="POST">
                                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_event" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>