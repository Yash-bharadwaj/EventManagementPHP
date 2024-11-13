<?php
// config/config.php

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'event_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

// Application configuration
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8888/event_management');
define('APP_ENV', getenv('APP_ENV') ?: 'development');