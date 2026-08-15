<?php
/**
 * Ends the session and returns to the sign in screen.
 */
require_once __DIR__ . '/config/config.php';

logout();
flash('info', 'You have been signed out.');
redirect('login.php');
