<?php
// logout.php
require_once 'config.php';

logoutUser();

// Redirect to main login portal
header('Location: index.php');
exit;
?>