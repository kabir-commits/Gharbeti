<?php
require_once 'config/app.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    http_response_code(403);
    die('Disabled in production');
}


$dirs = [
    UPLOAD_PATH . 'rooms/',
    UPLOAD_PATH . 'avatars/',
    __DIR__ . '/assets/images/'
];

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

function createDefaultRoomImageGd($path) {
    $width = 800;
    $height = 600;
    $image = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($image, 245, 245, 245);
    imagefill($image, 0, 0, $bg);
    $text_color = imagecolorallocate($image, 100, 100, 100);
    $roof_color = imagecolorallocate($image, 139, 30, 63);
    $wall_color = imagecolorallocate($image, 200, 200, 200);
    $door_color = imagecolorallocate($image, 150, 75, 0);

    $points = [
        $width / 2, 100,
        $width / 4, 200,
        3 * $width / 4, 200
    ];
    imagefilledpolygon($image, $points, 3, $roof_color);
    imagefilledrectangle($image, $width / 3, 200, 2 * $width / 3, 400, $wall_color);
    imagefilledrectangle($image, $width / 2 - 30, 300, $width / 2 + 30, 400, $door_color);

    $text = 'Room Image';
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_x = ($width - $text_width) / 2;
    imagestring($image, $font_size, (int) $text_x, 450, $text, $text_color);

    imagejpeg($image, $path, 90);
    imagedestroy($image);
    echo 'Created default room image: ' . htmlspecialchars($path) . '<br>';
}

function createDefaultAvatarGd($path) {
    $size = 200;
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    $bg = imagecolorallocate($image, 240, 240, 240);
    imagefill($image, 0, 0, $bg);
    $head_color = imagecolorallocate($image, 180, 180, 180);
    $body_color = imagecolorallocate($image, 160, 160, 160);

    imagefilledellipse($image, $size / 2, $size / 3, 60, 60, $head_color);
    imagefilledrectangle($image, $size / 2 - 40, $size / 2, $size / 2 + 40, 3 * $size / 4, $body_color);
    imagefilledrectangle($image, $size / 2 - 60, $size / 2 - 10, $size / 2 - 40, $size / 2 + 20, $body_color);
    imagefilledrectangle($image, $size / 2 + 40, $size / 2 - 10, $size / 2 + 60, $size / 2 + 20, $body_color);

    imagepng($image, $path);
    imagedestroy($image);
    echo 'Created default avatar: ' . htmlspecialchars($path) . '<br>';
}

function createDefaultRoomSvg($path) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <rect width="800" height="600" fill="#f5f5f5"/>
  <polygon points="400,100 200,220 600,220" fill="#8b1e3f"/>
  <rect x="265" y="220" width="270" height="190" rx="8" fill="#d9d9d9"/>
  <rect x="370" y="300" width="60" height="110" rx="4" fill="#964b00"/>
  <text x="400" y="475" font-family="Arial, sans-serif" font-size="30" text-anchor="middle" fill="#666666">Room Image</text>
</svg>
SVG;
    file_put_contents($path, $svg);
    echo 'Created SVG room placeholder: ' . htmlspecialchars($path) . '<br>';
}

function createDefaultAvatarSvg($path) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
  <rect width="200" height="200" rx="100" fill="#f0f0f0"/>
  <circle cx="100" cy="68" r="30" fill="#b4b4b4"/>
  <path d="M50 160c0-30 22-54 50-54s50 24 50 54" fill="#a0a0a0"/>
</svg>
SVG;
    file_put_contents($path, $svg);
    echo 'Created SVG avatar placeholder: ' . htmlspecialchars($path) . '<br>';
}

$hasGd = function_exists('imagecreatetruecolor');

if ($hasGd) {
    createDefaultRoomImageGd(__DIR__ . '/assets/images/default-room.jpg');
    createDefaultAvatarGd(__DIR__ . '/assets/images/default-avatar.png');

    copy(__DIR__ . '/assets/images/default-room.jpg', UPLOAD_PATH . 'rooms/default-room.jpg');
    copy(__DIR__ . '/assets/images/default-avatar.png', UPLOAD_PATH . 'avatars/default-avatar.png');

    echo '<br>Default raster images created successfully!';
} else {
    createDefaultRoomSvg(__DIR__ . '/assets/images/default-room.svg');
    createDefaultAvatarSvg(__DIR__ . '/assets/images/default-avatar.svg');

    copy(__DIR__ . '/assets/images/default-room.svg', UPLOAD_PATH . 'rooms/default-room.svg');
    copy(__DIR__ . '/assets/images/default-avatar.svg', UPLOAD_PATH . 'avatars/default-avatar.svg');

    echo '<br>GD extension is not enabled, so SVG placeholders were created instead.';
    echo '<br>If you want JPG/PNG defaults, enable the PHP GD extension in XAMPP and rerun this script.';
}
?>
