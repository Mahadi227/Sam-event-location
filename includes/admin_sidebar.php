<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay"></div>
<div class="admin-sidebar">
    <h2>Sam Management</h2>
    <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
    <a href="calendar.php" class="<?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>

    <a href="items.php" class="<?php echo ($current_page == 'items.php' || $current_page == 'update_items.php') ? 'active' : ''; ?>"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
    <a href="reservations.php" class="<?php echo ($current_page == 'reservations.php' || $current_page == 'create_reservation.php' || $current_page == 'manage.php') ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
    <a href="returns.php" class="<?php echo ($current_page == 'returns.php' || $current_page == 'process_return.php' || $current_page == 'edit_return.php') ? 'active' : ''; ?>"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
    <a href="barcode_generator.php" class="<?php echo ($current_page == 'barcode_generator.php' || $current_page == 'print_barcodes.php') ? 'active' : ''; ?>"><i class="fas fa-barcode"></i> &nbsp; Codes-Barres</a>
    <a href="inventory_scan.php" class="<?php echo ($current_page == 'inventory_scan.php' || $current_page == 'barcode_scan.php') ? 'active' : ''; ?>"><i class="fas fa-search-location"></i> &nbsp; Audit Inventaire</a>
    <a href="payments.php" class="<?php echo $current_page == 'payments.php' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
    <a href="transfers.php" class="<?php echo $current_page == 'transfers.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
    <a href="caisse.php" class="<?php echo $current_page == 'caisse.php' ? 'active' : ''; ?>"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
    <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>

    <?php if (hasRole('super_admin')): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e214a4ff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Super Admin</div>
        <a href="branches.php" class="<?php echo $current_page == 'branches.php' ? 'active' : ''; ?>"><i class="fas fa-building"></i> &nbsp; Succursales</a>
        <a href="analytics.php" class="<?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> &nbsp; Statistiques</a>
    <?php endif; ?>
    <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
        <a href="users.php" class="<?php echo ($current_page == 'users.php' || $current_page == 'user_history.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
        <a href="logs.php" class="<?php echo $current_page == 'logs.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i> &nbsp; Journal d'Activité</a>
    <?php endif; ?>
    <?php if (hasRole('super_admin')): ?>
        <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
    <?php endif; ?>

    <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
</div>
