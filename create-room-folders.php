<?php
$folders = [__DIR__ . '/assets/uploads/rooms/', __DIR__ . '/assets/uploads/rooms/thumbs/'];
foreach ($folders as $folder) {
    if (!file_exists($folder)) { mkdir($folder, 0777, true); echo 'Created: ' . $folder . '<br>'; }
    else { echo 'Exists: ' . $folder . '<br>'; }
}
echo '<br>Room upload folders ready!';
?>
