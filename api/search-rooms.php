<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1; $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 12;
$filters = ['location'=>$_GET['location'] ?? '','min_price'=>isset($_GET['min_price'])?(int)$_GET['min_price']:null,'max_price'=>isset($_GET['max_price'])?(int)$_GET['max_price']:null,'bedrooms'=>isset($_GET['bedrooms'])?(int)$_GET['bedrooms']:null,'verified_only'=>isset($_GET['verified_only']),'sort'=>$_GET['sort'] ?? 'newest','amenities'=>isset($_GET['amenities'])?explode(',',$_GET['amenities']):[],'search'=>$_GET['search'] ?? ''];
echo json_encode(searchRooms($filters, $page, $per_page));
?>
