<?php
/**
 * logout.php — خروج مشتری از حساب
 */
require_once __DIR__ . '/config.php';

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
header('Location: ' . BASE_URL . '/index.php');
exit;
