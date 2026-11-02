<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: ' . get_dashboard_url());
} else {
    header('Location: login.php');
}
exit;
