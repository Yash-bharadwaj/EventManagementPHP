<?php
// admin/settings.php
require_once '../includes/init.php';
requireAdmin();

$pageTitle = 'System Settings';
$conn = Database::getInstance()->getConnection();

// First, let's check if the settings table exists
try {
    $tableExists = $conn->query("
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "' 
        AND table_name = 'settings'
    ")->fetchColumn();

    if (!$tableExists) {
        // Create settings table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT,
                created_by INT,
                updated_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (updated_by) REFERENCES users(id)
            )
        ");

        // Insert default settings
        $defaultSettings = [
            'site_name' => 'EventHub',
            'max_tickets_per_booking' => '10',
            'booking_time_limit' => '30',
            'allow_cancellations' => '1',
            'email_notifications' => '1',
            'currency' => 'USD',
            'test_mode' => '1'
        ];

        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value, created_by, updated_by) 
            VALUES (?, ?, ?, ?)
        ");

        foreach ($defaultSettings as $key => $value) {
            $stmt->execute([$key, $value, $_SESSION['user_id'], $_SESSION['user_id']]);
        }
    }

    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
        try {
            $conn->beginTransaction();
            
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO settings (setting_key, setting_value, created_by, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    setting_value = ?, 
                    updated_by = ?,
                    updated_at = NOW()
                ");
                $stmt->execute([
                    $key, 
                    $value, 
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'],
                    $value,
                    $_SESSION['user_id']
                ]);
            }
            
            $conn->commit();
            $_SESSION['success_message'] = 'Settings updated successfully';
            header("Location: settings.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error updating settings: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }

    // Fetch current settings
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    $current_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    error_log("Settings page error: " . $e->getMessage());
    $error = "An error occurred while loading settings. Please try again.";
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <?php include 'admin_sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">System Settings</h1>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab">
                                    General
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="booking-tab" data-bs-toggle="tab" href="#booking" role="tab">
                                    Booking
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="email-tab" data-bs-toggle="tab" href="#email" role="tab">
                                    Email
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="payment-tab" data-bs-toggle="tab" href="#payment" role="tab">
                                    Payment
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <h5 class="card-title">General Settings</h5>
                                <p class="text-muted">Basic application settings</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" class="form-control" 
                                           name="settings[site_name]" 
                                           value="<?php echo htmlspecialchars($current_settings['site_name'] ?? 'EventHub'); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" 
                                           name="settings[contact_email]" 
                                           value="<?php echo htmlspecialchars($current_settings['contact_email'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" 
                                           name="settings[contact_phone]" 
                                           value="<?php echo htmlspecialchars($current_settings['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Booking Settings -->
                            <div class="tab-pane fade" id="booking" role="tabpanel">
                                <h5 class="card-title">Booking Settings</h5>
                                <p class="text-muted">Configure booking related options</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Maximum Tickets Per Booking</label>
                                    <input type="number" class="form-control" 
                                           name="settings[max_tickets_per_booking]" 
                                           value="<?php echo htmlspecialchars($current_settings['max_tickets_per_booking'] ?? '10'); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Booking Time Limit (minutes)</label>
                                    <input type="number" class="form-control" 
                                           name="settings[booking_time_limit]" 
                                           value="<?php echo htmlspecialchars($current_settings['booking_time_limit'] ?? '30'); ?>">
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="settings[allow_cancellations]" value="0">
                                        <input class="form-check-input" type="checkbox" 
                                               name="settings[allow_cancellations]" value="1"
                                               <?php echo ($current_settings['allow_cancellations'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Allow Booking Cancellations</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Settings -->
                            <div class="tab-pane fade" id="email" role="tabpanel">
                                <h5 class="card-title">Email Settings</h5>
                                <p class="text-muted">Configure email notifications</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" 
                                           name="settings[smtp_host]" 
                                           value="<?php echo htmlspecialchars($current_settings['smtp_host'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" 
                                           name="settings[smtp_port]" 
                                           value="<?php echo htmlspecialchars($current_settings['smtp_port'] ?? '587'); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" 
                                           name="settings[smtp_username]" 
                                           value="<?php echo htmlspecialchars($current_settings['smtp_username'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" 
                                           name="settings[smtp_password]" 
                                           value="<?php echo htmlspecialchars($current_settings['smtp_password'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="settings[email_notifications]" value="0">
                                        <input class="form-check-input" type="checkbox" 
                                               name="settings[email_notifications]" value="1"
                                               <?php echo ($current_settings['email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable Email Notifications</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Settings -->
                            <div class="tab-pane fade" id="payment" role="tabpanel">
                                <h5 class="card-title">Payment Settings</h5>
                                <p class="text-muted">Configure payment processing options</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" name="settings[currency]">
                                        <option value="USD" <?php echo ($current_settings['currency'] ?? 'USD') == 'USD' ? 'selected' : ''; ?>>USD</option>
                                        <option value="EUR" <?php echo ($current_settings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                        <option value="GBP" <?php echo ($current_settings['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Payment Gateway</label>
                                    <select class="form-select" name="settings[payment_gateway]">
                                        <option value="stripe" <?php echo ($current_settings['payment_gateway'] ?? '') == 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                        <option value="paypal" <?php echo ($current_settings['payment_gateway'] ?? '') == 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">API Key</label>
                                    <input type="password" class="form-control" 
                                           name="settings[payment_api_key]" 
                                           value="<?php echo htmlspecialchars($current_settings['payment_api_key'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">API Secret</label>
                                    <input type="password" class="form-control" 
                                           name="settings[payment_api_secret]" 
                                           value="<?php echo htmlspecialchars($current_settings['payment_api_secret'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="settings[test_mode]" value="0">
                                        <input class="form-check-input" type="checkbox" 
                                               name="settings[test_mode]" value="1"
                                               <?php echo ($current_settings['test_mode'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable Test Mode</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>