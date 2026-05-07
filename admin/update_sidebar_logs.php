<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    if (basename($file) == 'update_sidebar_logs.php' || basename($file) == 'logs.php') continue;
    
    $content = file_get_contents($file);
    
    // Pattern to find users link inside the mini_admin role block
    $pattern = '/(<\?php if \(hasRole\(\'super_admin\'\) \|\| hasRole\(\'mini_admin\'\)\): \?>\s*<a href="users\.php".*?<\/a>)/s';
    
    if (preg_match('/<a href="logs\.php"/', $content)) {
        continue; // Already added
    }
    
    $replacement = "$1\n            <a href=\"logs.php\"><i class=\"fas fa-history\"></i> &nbsp; Journal d'Activité</a>";
    
    $new_content = preg_replace($pattern, $replacement, $content);
    if ($new_content && $new_content !== $content) {
        file_put_contents($file, $new_content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Done.\n";
