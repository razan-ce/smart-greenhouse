<?php
$qs = $_GET;
$qs['tab'] = 'users';
header('Location: admin.php?' . http_build_query($qs));
exit;
