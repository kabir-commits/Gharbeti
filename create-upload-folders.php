<?php
$folders = [
    __DIR__ . '/assets/uploads/',
    __DIR__ . '/assets/uploads/avatars/',
    __DIR__ . '/assets/uploads/documents/',
    __DIR__ . '/assets/uploads/rooms/'
];

foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
        echo 'Created: ' . $folder . '<br>';
    } else {
        echo 'Exists: ' . $folder . '<br>';
    }
}

echo '<br>Upload folders ready!';
?>
