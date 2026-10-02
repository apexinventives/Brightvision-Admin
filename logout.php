<?php
require_once 'config/session.php';
redirectIfNotLoggedIn();
session_destroy();
header('Location: login.php');
exit();
?>