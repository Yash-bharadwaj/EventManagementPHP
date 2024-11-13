<?php
// admin/users/list.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Manage Users';
$conn = Database::getInstance()->getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = sanitize($_POST['new_status']);
    
    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET status = ?, updated_at = NOW() 
            WHERE id = ? AND role != 'admin'
        ");
        $stmt->execute([$new_status, $user_id]);
        
        redirect('/event_management/admin/users/list.php', 'User status updated successfully', 'success');
    } catch (PDOException $e) {
        $error = 'Error updating user status';
        error_log($e->getMessage());
    }
}

// Build query conditions
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

// Fetch users with related information
$query = "
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) as total_bookings,
        (SELECT SUM(total_amount) FROM bookings b WHERE b.user_id = u.id AND b.status = 'confirmed') as total_spent,
        (SELECT MAX(created_at) FROM bookings b WHERE b.user_id = u.id) as last_booking
    FROM users u
    WHERE " . implode(" AND ", $where) . "
    ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

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
                <h1 class="h3 mb-0">Manage Users</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-1"></i> Add New User
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search users..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="role">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="list.php" class="btn btn-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Bookings</th>
                                    <th>Total Spent</th>
                                    <th>Last Booking</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['status'] === 'active' ? 'success' : 
                                                    ($user['status'] === 'inactive' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($user['total_bookings']); ?></td>
                                        <td>$<?php echo number_format((float)$user['total_spent'], 2); ?></td>
                                        <td>
                                            <?php echo $user['last_booking'] ? 
                                                date('M d, Y', strtotime($user['last_booking'])) : 
                                                'Never'; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="view.php?id=<?php echo $user['id']; ?>">
                                                            <i class="fas fa-eye me-1"></i> View Details
                                                        </a>
                                                    </li>
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                        <li>
                                                            <a class="dropdown-item" 
                                                               href="edit.php?id=<?php echo $user['id']; ?>">
                                                                <i class="fas fa-edit me-1"></i> Edit User
                                                            </a>
                                                        </li>
                                                        <?php if ($user['status'] !== 'active'): ?>
                                                            <li>
                                                                <form method="POST" class="dropdown-item">
                                                                    <input type="hidden" name="user_id" 
                                                                           value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="active">
                                                                    <button type="submit" name="update_status" 
                                                                            class="btn btn-link text-success p-0">
                                                                        <i class="fas fa-check me-1"></i> Activate User
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if ($user['status'] !== 'banned'): ?>
                                                            <li>
                                                                <form method="POST" class="dropdown-item">
                                                                    <input type="hidden" name="user_id" 
                                                                           value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="banned">
                                                                    <button type="submit" name="update_status" 
                                                                            class="btn btn-link text-danger p-0"
                                                                            onclick="return confirm('Are you sure you want to ban this user?');">
                                                                        <i class="fas fa-ban me-1"></i> Ban User
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </ul>
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