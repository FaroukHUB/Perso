<?php
/**
 * PERSONNALY - Admin Logout
 */

require_once __DIR__ . '/../app/core/Auth.php';

Auth::logout();
header('Location: /admin/login.php');
exit;
