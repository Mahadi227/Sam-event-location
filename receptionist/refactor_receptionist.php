<?php
$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    $basename = basename($file);
    if (in_array($basename, ['refactor_receptionist.php', 'print_penalty.php'])) continue;

    $content = file_get_contents($file);

    $pattern = '/(?:<div class="sidebar-overlay"><\/div>\s*)?<div class="admin-sidebar">.*?<\/div>\s*(?=<div class="main-content">)/s';
    
    if (preg_match($pattern, $content)) {
        $replacement = "<?php include '../includes/receptionist_sidebar.php'; ?>\n\n        ";
        $new_content = preg_replace($pattern, $replacement, $content);

        if ($new_content !== $content) {
            file_put_contents($file, $new_content);
            echo "Updated $basename\n";
        }
    }
}
echo "Done.\n";
