<?php
$files = ['returns.php', 'process_return.php', 'edit_return.php', 'print_penalty.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    $content = file_get_contents($path);
    $content = str_replace('requireAdmin()', 'requireStaff()', $content);
    $content = str_replace('admin_sidebar.php', 'receptionist_sidebar.php', $content);
    file_put_contents($path, $content);
}
echo "Done.\n";
