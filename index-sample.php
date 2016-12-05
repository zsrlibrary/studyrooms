<?php
require 'zsr-studyrooms-reservation.php';
$studyrooms = new ZSR_Study_Rooms_Reservation();
$studyrooms->get_a_room();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="initial-scale=1.0,width=device-width">
<title><?php echo $studyrooms->get_assets('title'); ?></title>
<?php echo $studyrooms->get_assets('css',"\n"); ?>
</head>
<body>
<?php echo $studyrooms->get_output(); ?>
<?php echo $studyrooms->get_assets('js',"\n"); ?>
</body>
</html>
