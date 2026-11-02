<?php
require_once __DIR__ . '/config.php';
require_login();

// Route to role-specific dashboard interface
header('Location: ' . get_dashboard_url());
exit;
