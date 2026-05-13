<?php
session_start();
session_destroy();
header('Location: /loanapp/html/login.html');
exit;
?>
