<?php
$dir = __DIR__ . '/admin';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'returns.php') continue; // already has it
    
    $content = file_get_contents($file);
    
    // Check if it has a sidebar
    if (strpos($content, '<a href="reservations.php"') !== false && strpos($content, '<a href="returns.php"') === false) {
        
        $search1 = '<a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>';
        $search2 = '<a href="reservations.php" class="active"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>';
        $search3 = '<a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Reservations</a>';
        
        $replace1 = $search1 . "\n        <a href=\"returns.php\"><i class=\"fas fa-undo\"></i> &nbsp; Retours Matériel</a>";
        $replace2 = $search2 . "\n        <a href=\"returns.php\"><i class=\"fas fa-undo\"></i> &nbsp; Retours Matériel</a>";
        $replace3 = $search3 . "\n        <a href=\"returns.php\"><i class=\"fas fa-undo\"></i> &nbsp; Retours Matériel</a>";
        
        $content = str_replace($search1, $replace1, $content);
        $content = str_replace($search2, $replace2, $content);
        $content = str_replace($search3, $replace3, $content);
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
echo "Done.\n";
