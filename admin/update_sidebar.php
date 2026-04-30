<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    if (basename($file) == 'update_sidebar.php') continue;
    $content = file_get_contents($file);
    
    // We want to replace:
    // <a href="users.php"(*)><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
    // with:
    // < ?php if (hasRole('super_admin') || hasRole('mini_admin')): ? >
    // <a href="users.php"(*)><i class="fas fa-users-cog"></i> &nbsp; < ?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ? ></a>
    // < ?php endif; ? >
    // But wait, it's already inside a `hasRole('super_admin')` block.
    // Let's do a regex replacement.
    
    // Pattern to find the block:
    // < ?php if \(hasRole\('super_admin'\)\): \? >
    // \s*<a href="branches\.php".*?<\/a>
    // \s*<a href="users\.php".*?<\/a>
    // \s*<a href="settings\.php".*?<\/a>
    // \s*< ?php endif; \? >

    
    $pattern = '/<\?php if \(hasRole\(\'super_admin\'\)\): \?>\s*<a href="branches\.php"(.*?)>.*?<\/a>\s*<a href="users\.php"(.*?)><i class="fas fa-users-cog"><\/i> &nbsp; Utilisateurs<\/a>\s*<a href="settings\.php"(.*?)>.*?<\/a>\s*<\?php endif; \?>/s';
    
    $replacement = "<?php if (hasRole('super_admin')): ?>\n            <a href=\"branches.php\"$1><i class=\"fas fa-building\"></i> &nbsp; Succursales</a>\n        <?php endif; ?>\n        <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>\n            <a href=\"users.php\"$2><i class=\"fas fa-users-cog\"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>\n        <?php endif; ?>\n        <?php if (hasRole('super_admin')): ?>\n            <a href=\"settings.php\"$3><i class=\"fas fa-tools\"></i> &nbsp; Paramètres</a>\n        <?php endif; ?>";

    $new_content = preg_replace($pattern, $replacement, $content);
    if ($new_content && $new_content !== $content) {
        file_put_contents($file, $new_content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Done.\n";
