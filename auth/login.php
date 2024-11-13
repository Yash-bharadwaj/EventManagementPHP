<?php
// auth/login.php
require_once '../includes/init.php';

$pageTitle = 'Login';
$errors = [];
$debug = []; // Debug information array

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug information
    $debug[] = "Login attempt started";
    $debug[] = "Email provided: " . $email;
    
    try {
        $conn = Database::getInstance()->getConnection();
        
        // Simple direct query
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug[] = "User found in database: " . ($user ? 'Yes' : 'No');
        
        if ($user) {
            $debug[] = "User ID: " . $user['id'];
            $debug[] = "User Role: " . $user['role'];
            
            if (password_verify($password, $user['password'])) {
                $debug[] = "Password verified successfully";
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['is_admin'] = ($user['role'] === 'admin');
                
                $debug[] = "Session variables set";
                $debug[] = "is_admin value: " . ($_SESSION['is_admin'] ? 'true' : 'false');
                
                // Determine redirect URL
                $redirectUrl = $_SESSION['is_admin'] 
                    ? '/event_management/admin/dashboard.php'
                    : '/event_management/user/dashboard.php';
                
                $debug[] = "Redirect URL: " . $redirectUrl;
                
                // Perform redirect
                header("Location: " . $redirectUrl);
                exit();
            } else {
                $debug[] = "Password verification failed";
                $errors[] = "Invalid email or password";
            }
        } else {
            $debug[] = "No user found with this email";
            $errors[] = "Invalid email or password";
        }
        
    } catch (Exception $e) {
        $debug[] = "Error occurred: " . $e->getMessage();
        $errors[] = "A system error occurred. Please try again later.";
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-5">
                <h1 class="text-center mb-4">Login</h1>

                <!-- <?php if (isset($_SESSION)): ?>
                    <div class="alert alert-info">
                        <strong>Session Status:</strong>
                        <pre><?php print_r($_SESSION); ?></pre>
                    </div>
                <?php endif; ?> -->
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($debug)): ?>
                    <div class="alert alert-info">
                        <strong>Debug Information:</strong>
                        <ul class="mb-0">
                            <?php foreach ($debug as $message): ?>
                                <li><?php echo htmlspecialchars($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>