<?php
$files = [
    'verify.php', 'track_reservation.php', 'receptionist/walk_in.php', 
    'receptionist/manage.php', 'receptionist/dashboard.php', 'index.php', 
    'includes/mailer.php', 'confirmation.php', 'client/invoice.php', 
    'client/history.php', 'client/details.php', 'admin/payments.php', 
    'admin/manage.php', 'admin/dashboard.php', 'admin/create_reservation.php', 
    'admin/caisse.php', 'admin/analytics.php', 'admin/items.php', 'booking.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Match FCFA in HTML context
        $content = preg_replace('/(\s+)FCFA/i', '$1<?php echo getCurrency(); ?>', $content);
        
        // Match FCFA in single quotes (like in includes/mailer.php)
        $content = preg_replace("/' FCFA'/i", "' ' . getCurrency()", $content);
        
        // Match FCFA in double quotes
        $content = preg_replace('/" FCFA"/i', '" " . getCurrency()', $content);
        
        // Also look for just ' F<' or ' F ' that might be used for currency
        // Actually, just replacing FCFA is enough for now. Let's see if there are stray ' F<' 
        $content = preg_replace('/(\s+)F(<\/span>|<\/div>|<\/td>|<\/th>)/', '$1<?php echo getCurrency(); ?>$2', $content);

        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
