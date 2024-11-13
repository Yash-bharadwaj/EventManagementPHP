<?php
// config/constants.php

// Application settings
define('APP_NAME', 'EventHub');
define('APP_VERSION', '1.0.0');

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Pagination settings
define('ITEMS_PER_PAGE', 10);

// Session timeout (in seconds)
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Email settings (for later use)
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'your_username');
define('SMTP_PASS', 'your_password');