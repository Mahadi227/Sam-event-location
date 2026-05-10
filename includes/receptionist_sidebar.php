<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay"></div>
<div class="admin-sidebar">
    <h2 style="color: white; margin-bottom: 30px;">Reception Sam</h2>
    <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> &nbsp; Accueil</a>
    <a href="walk_in.php" class="<?php echo $current_page == 'walk_in.php' ? 'active' : ''; ?>"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
    <a href="reservations.php" class="<?php echo ($current_page == 'reservations.php' || $current_page == 'manage.php') ? 'active' : ''; ?>"><i class="fas fa-list"></i> &nbsp; Reservations</a>
    <a href="calendar.php" class="<?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
    <a href="returns.php" class="<?php echo ($current_page == 'returns.php' || $current_page == 'process_return.php' || $current_page == 'edit_return.php') ? 'active' : ''; ?>"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
    <a href="../admin/inventory_scan.php" class="<?php echo ($current_page == 'inventory_scan.php' || $current_page == 'barcode_scan.php') ? 'active' : ''; ?>"><i class="fas fa-search-location"></i> &nbsp; Audit Inventaire</a>
    <a href="caisse.php" class="<?php echo $current_page == 'caisse.php' ? 'active' : ''; ?>"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
    <a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
    <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
</div>
