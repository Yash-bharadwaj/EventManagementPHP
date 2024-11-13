<?php
// auth/logout.php
require_once '../includes/functions.php';

session_start();
session_destroy();
redirect('/event_management/index.php', 'You have been logged out successfully');