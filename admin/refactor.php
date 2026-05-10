<?php
// admin/refactor.php
$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    $basename = basename($file);
    if (in_array($basename, ['login.php', 'logout.php', 'print_penalty.php', 'update_sidebar.php', 'update_sidebar_logs.php', 'refactor.php', 'init_reserved.php', 'alter_items.php', 'revert_items.php'])) {
        continue;
    }

    $content = file_get_contents($file);

    // Some files have <div class="sidebar-overlay"></div> right before <div class="admin-sidebar">
    // We want to match from an optional sidebar-overlay up to the end of admin-sidebar
    // The admin-sidebar ends with </div> just before <div class="main-content">
    
    // We will use a regex that looks for <div class="admin-sidebar"> and captures everything up to </div>\s*<div class="main-content">
    // But since admin-sidebar can contain nested <div>s (like the Super Admin header), we must be careful.
    // Actually, we can match from <div class="admin-sidebar"> to the last </div> before <div class="main-content">.
    // The easiest way is: /(<div class="sidebar-overlay"><\/div>\s*)?<div class="admin-sidebar">.*?<\/div>\s*(?=<div class="main-content">)/s
    
    $pattern = '/(?:<div class="sidebar-overlay"><\/div>\s*)?<div class="admin-sidebar">.*?<\/div>\s*(?=<div class="main-content">)/s';
    
    // We only replace if we actually find <div class="main-content"> right after
    if (preg_match($pattern, $content)) {
        $replacement = "<?php include '../includes/admin_sidebar.php'; ?>\n\n        ";
        $new_content = preg_replace($pattern, $replacement, $content);

        if ($new_content !== $content) {
            file_put_contents($file, $new_content);
            echo "Updated $basename\n";
        }
    } else {
        echo "No match in $basename\n";
    }
}
echo "Done.\n";
