<?php
// admin/settings.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg = '';

if (isset($_POST['save_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $msg = "Paramètres mis à jour !";
}

// Add Promo Code
if (isset($_POST['add_promo'])) {
    $code = strtoupper($_POST['code']);
    $discount = $_POST['discount'];
    $valid_until = $_POST['valid_until'];
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : 100;
    
    $stmt = $pdo->prepare("INSERT INTO promo_codes (code, discount_percent, valid_until, usage_limit) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $discount, $valid_until, $usage_limit]);
    $msg = "Code promo ajouté !";
}

// Edit Promo Code
if (isset($_POST['edit_promo'])) {
    $id = $_POST['promo_id'];
    $code = strtoupper($_POST['code']);
    $discount = $_POST['discount'];
    $valid_until = $_POST['valid_until'];
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : 100;
    
    $stmt = $pdo->prepare("UPDATE promo_codes SET code=?, discount_percent=?, valid_until=?, usage_limit=? WHERE id=?");
    $stmt->execute([$code, $discount, $valid_until, $usage_limit, $id]);
    $msg = "Code promo modifié !";
}

// Delete Promo Code
if (isset($_GET['delete_promo'])) {
    $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE id = ?");
    $stmt->execute([$_GET['delete_promo']]);
    $msg = "Code promo supprimé !";
}

$settings = $pdo->query("SELECT * FROM settings")->fetchAll();
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_records = $pdo->query("SELECT COUNT(*) FROM promo_codes")->fetchColumn();
$total_pages = ceil($total_records / $limit);
$promo_codes = $pdo->query("
    SELECT pc.*, 
           (SELECT COUNT(*) FROM reservations WHERE promo_code_id = pc.id AND status != 'cancelled') as dynamic_usage 
    FROM promo_codes pc 
    ORDER BY valid_until DESC
    LIMIT $limit OFFSET $offset
")->fetchAll();
$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) $base_url = '?' . http_build_query($query_string_params) . '&';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Sam SuperAdmin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 800;
            color: #888;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: 0.3s;
        }
        .tab-btn:hover {
            color: var(--primary-blue);
        }
        .tab-btn.active {
            color: var(--primary-blue);
            border-bottom-color: var(--accent-gold);
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.4s;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .responsive-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        @media (max-width: 576px) {
            .tabs {
                gap: 5px;
            }
            .tab-btn {
                padding: 10px 15px;
                font-size: 0.95rem;
                flex: 1;
                text-align: center;
            }
            .responsive-grid-2 {
                grid-template-columns: 1fr;
            }
            .action-link {
                margin-right: 8px !important;
            }
            .mobile-stacked-table thead {
                display: none;
            }
            .mobile-stacked-table tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 8px;
                padding: 10px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .mobile-stacked-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 5px !important;
                border-bottom: 1px solid #f9f9f9;
                text-align: right;
            }
            .mobile-stacked-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #555;
            }
            .mobile-stacked-table td:last-child {
                border-bottom: none;
                justify-content: center;
                gap: 20px;
                padding-top: 15px !important;
            }
            .mobile-stacked-table td:last-child::before {
                display: none;
            }
            .mobile-stacked-table td:last-child .action-link {
                margin-right: 0 !important;
            }
        }
    </style>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger "><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php" class="active"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Paramètres du Système</h2>
        <p style="color: #666; margin-bottom: 30px;">Configuration globale des tarifs et taxes.</p>

        <?php if ($msg): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: '<?php echo htmlspecialchars($msg); ?>',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                });
            </script>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('system')"><i class="fas fa-cogs"></i> Configuration Globale</button>
            <button class="tab-btn" onclick="switchTab('promo')"><i class="fas fa-tags"></i> Codes Promos</button>
        </div>

    <div id="tab-system" class="tab-content active">
        <div class="card" style="max-width: 700px;">
            <form method="POST">
            <?php foreach ($settings as $s): ?>
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 8px;"><?php echo htmlspecialchars($s['description']); ?></label>
                    <input type="text" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>" class="form-control" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                    <small style="color: #888;">Clé : <code><?php echo $s['setting_key']; ?></code></small>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" name="save_settings" class="contact-btn" style="width: 100%; border: none; padding: 15px;">Enregistrer les modifications</button>
        </form>
    </div>
    </div> <!-- End Tab System -->

    <!-- Promo Codes Section -->
    <div id="tab-promo" class="tab-content">
        <div class="manage-grid" style="align-items: start;">
            <!-- Add Promo Form -->
            <div class="card">
            <h4>Ajouter un Code</h4>
            <form method="POST" style="margin-top: 20px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Code (ex: ETE2024)</label>
                    <input type="text" name="code" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Réduction (%)</label>
                    <input type="number" step="0.01" name="discount" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="responsive-grid-2">
                    <div class="form-group">
                        <label>Valide jusqu'au</label>
                        <input type="date" name="valid_until" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                    <div class="form-group">
                        <label>Limite</label>
                        <input type="number" name="usage_limit" value="100" min="1" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                </div>
                <button type="submit" name="add_promo" class="contact-btn" style="width: 100%; border: none; padding: 12px; cursor: pointer;">Ajouter le code</button>
            </form>
        </div>

        <!-- Promo List -->
        <div class="card">
            <h4>Codes Actifs</h4>
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="mobile-stacked-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 10px;">Code</th>
                            <th style="padding: 10px;">Réduc.</th>
                            <th style="padding: 10px;">Validité</th>
                            <th style="padding: 10px;">Utilisé</th>
                            <th style="padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promo_codes as $pc): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td data-label="Code" style="padding: 10px;"><strong><?php echo htmlspecialchars($pc['code']); ?></strong></td>
                            <td data-label="Réduc." style="padding: 10px;"><?php echo number_format($pc['discount_percent'], 0); ?>%</td>
                            <td data-label="Validité" style="padding: 10px; font-size: 0.85rem; color: #666;"><?php echo date('d/m/Y', strtotime($pc['valid_until'])); ?></td>
                            <td data-label="Utilisé" style="padding: 10px;">
                                <strong style="color: var(--primary-blue);"><?php echo $pc['dynamic_usage']; ?></strong> 
                                <span style="color: #999; font-size: 0.8rem;">/ <?php echo $pc['usage_limit']; ?></span>
                            </td>
                            <td data-label="Action" style="padding: 10px; white-space: nowrap;">
                                <a href="reservations.php?promo_id=<?php echo $pc['id']; ?>" title="Voir les réservations qui l'ont utilisé" class="action-link" style="color: var(--secondary-orange); margin-right: 15px;"><i class="fas fa-eye"></i></a>
                                <a href="javascript:void(0);" onclick='openEditPromoModal(<?php echo htmlspecialchars(json_encode($pc), ENT_QUOTES, "UTF-8"); ?>)' class="action-link" style="color: var(--primary-blue); margin-right: 15px;"><i class="fas fa-edit"></i></a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $pc['id']; ?>)" class="action-link" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($promo_codes)): ?>
                            <tr><td colspan="5" style="padding: 20px; text-align: center; color: #888;">Aucun code promo actif</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; padding-bottom: 20px;">
            <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page - 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;"><i class="fas fa-chevron-left"></i> Précédent</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?php echo htmlspecialchars($base_url . 'page=' . $i); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; <?php echo $i === $page ? 'background: #03117a; color: white; border-color: #03117a;' : 'background: white; color: #333;'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page + 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;">Suivant <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
        </div> <!-- End Tab Promo wrapper -->
    </div> <!-- End Tab Promo -->
</div>

<!-- Edit Promo Modal -->
<div id="editPromoModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px);">
    <div style="background-color: #fff; margin: 10% auto; padding: 30px; width: 90%; max-width: 400px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); position: relative;">
        <span onclick="document.getElementById('editPromoModal').style.display='none'" style="position: absolute; top: 20px; right: 25px; font-size: 1.5rem; color: #888; cursor: pointer; transition: 0.2s;">&times;</span>
        <h3 style="margin-bottom: 25px; color: var(--primary-blue);">Modifier le Code</h3>
        <form method="POST">
            <input type="hidden" name="promo_id" id="edit_promo_id">
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Code</label>
                <input type="text" name="code" id="edit_promo_code" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Réduction (%)</label>
                <input type="number" step="0.01" name="discount" id="edit_promo_discount" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="responsive-grid-2">
                <div class="form-group">
                    <label>Valide jusqu'au</label>
                    <input type="date" name="valid_until" id="edit_promo_valid" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group">
                    <label>Limite</label>
                    <input type="number" name="usage_limit" id="edit_promo_limit" min="1" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
            </div>
            <button type="submit" name="edit_promo" class="contact-btn" style="width: 100%; border: none; padding: 12px; border-radius: 8px;">Enregistrer</button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    document.querySelector(`.tab-btn[onclick="switchTab('${tabId}')"]`).classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
    
    // Store active tab locally so page reloads don't reset view
    localStorage.setItem('activeSettingsTab', tabId);
}

// Restore active tab on load
document.addEventListener('DOMContentLoaded', () => {
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        switchTab(activeTab);
    }
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Confirmer la suppression',
        text: "Ce code promo ne pourra plus être utilisé par les clients.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_promo=' + id;
        }
    });
}

function openEditPromoModal(data) {
    document.getElementById('edit_promo_id').value = data.id;
    document.getElementById('edit_promo_code').value = data.code;
    document.getElementById('edit_promo_discount').value = parseFloat(data.discount_percent);
    document.getElementById('edit_promo_valid').value = data.valid_until;
    document.getElementById('edit_promo_limit').value = data.usage_limit;
    document.getElementById('editPromoModal').style.display = 'block';
}

window.onclick = function(event) {
    const modal = document.getElementById('editPromoModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
</html>

