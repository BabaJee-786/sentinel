<?php
require_once __DIR__ . '/backend/session_auth.php';

logout_user();
header('Location: login.php');
exit;
