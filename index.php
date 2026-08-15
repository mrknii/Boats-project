<?php
/**
 * Front door. Sends the visitor either to their dashboard or to sign in.
 */
require_once __DIR__ . '/config/config.php';

redirect(is_logged_in() ? 'pages/dashboard.php' : 'login.php');
