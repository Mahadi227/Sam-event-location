<?php
// admin/branches.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin(); // Seul le super admin gère les succursales

$msg = '';
$error = '';

// Delete
if (isset($_GET['delete'])) {
    if ($_GET['delete'] == 1) {
        $error = "Impossible de supprimer la Succursale Principale.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            header("Location: branches.php");
            exit;
        } catch (PDOException $e) {
            $error = "Erreur : Impossible de supprimer cette succursale car elle contient des données (utilisateurs, articles, réservations).";
        }
    }
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $location = $_POST['location'];
    $phone = $_POST['phone'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE branches SET name = ?, location = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $location, $phone, $id]);
        $msg = "Succursale mise à jour !";
    } else {
        $stmt = $pdo->prepare("INSERT INTO branches (name, location, phone) VALUES (?, ?, ?)");
        $stmt->execute([$name, $location, $phone]);
        $msg = "Succursale ajoutée !";
    }
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Succursales - Sam Event Location</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=7">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media (max-width: 768px) {
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
        }
    </style>
</head>
<body>
    <div class="admin-mobile-header">
        <div style="font-weight: 800; color: white;">Sam Admin</div>
        <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
    </div>

    <div class="admin-container">
        <div class="sidebar-overlay"></div>
        <div class="admin-sidebar">
            <h2 style="color: white; margin-bottom: 30px;">Sam Admin</h2>
            <a href="dashboard.php"><i class="fas fa-chart-line"></i> &nbsp; Dashboard</a>
            <a href="create_reservation.php"><i class="fas fa-plus"></i> &nbsp; Nouvelle Rès.</a>
            <a href="reservations.php"><i class="fas fa-list"></i> &nbsp; Réservations</a>
            <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
            <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
            <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
            <a href="items.php"><i class="fas fa-box"></i> &nbsp; Matériel</a>
            <a href="branches.php" class="active"><i class="fas fa-code-branch"></i> &nbsp; Succursales</a>
            <a href="users.php"><i class="fas fa-users"></i> &nbsp; Utilisateurs</a>
            <a href="user_history.php"><i class="fas fa-history"></i> &nbsp; Historique</a>
            <a href="settings.php"><i class="fas fa-cog"></i> &nbsp; Paramètres</a>
            <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
        </div>

        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0; font-size: 1.8rem; color: #1a1c23;">Gestion des Succursales</h1>
                <button class="contact-btn" onclick="openModal()"><i class="fas fa-plus"></i> Ajouter</button>
            </div>

            <?php if ($msg): ?>
                <div style="background: #10b981; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?php echo $msg; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card" style="padding: 0; overflow: hidden; margin-top: 20px;">
                <div class="table-responsive">
                    <table class="mobile-stacked-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee; background: #fafafa;">
                                <th style="padding: 15px; font-weight: 600; color: #64748b;">Statut</th>
                                <th style="padding: 15px; font-weight: 600; color: #64748b;">Nom</th>
                                <th style="padding: 15px; font-weight: 600; color: #64748b;">Emplacement</th>
                                <th style="padding: 15px; font-weight: 600; color: #64748b;">Contact</th>
                                <th style="padding: 15px; font-weight: 600; color: #64748b;">Date d'ajout</th>
                                <th style="padding: 15px; font-weight: 600; color: #64748b; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branches)): ?>
                                <tr><td colspan="6" style="padding: 30px; text-align: center; color: #999;">Aucune succursale trouvée.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($branches as $branch): 
                                $isMain = ($branch['id'] == 1);
                            ?>
                            <tr style="border-bottom: 1px solid #f9f9f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td data-label="Statut" style="padding: 15px;">
                                    <?php if($isMain): ?>
                                        <span style="background: #e6f7ff; color: #0ea5e9; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">SIÈGE PRINCIPAL</span>
                                    <?php else: ?>
                                        <span style="background: #f3f4f6; color: #6b7280; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">SUCCURSALE</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Nom" style="padding: 15px; color: #1a1c23; font-weight: 600;">
                                    <i class="fas fa-building" style="color: <?php echo $isMain ? '#10b981' : '#4338ca'; ?>; opacity: 0.8; margin-right: 8px;"></i> 
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </td>
                                <td data-label="Emplacement" style="padding: 15px; color: #475569;">
                                    <i class="fas fa-map-marker-alt" style="color: #cbd5e1; margin-right: 5px;"></i> <?php echo htmlspecialchars($branch['location'] ?: '-'); ?>
                                </td>
                                <td data-label="Contact" style="padding: 15px; color: #475569;">
                                    <i class="fas fa-phone-alt" style="color: #cbd5e1; margin-right: 5px;"></i> <?php echo htmlspecialchars($branch['phone'] ?: '-'); ?>
                                </td>
                                <td data-label="Date d'ajout" style="padding: 15px; color: #64748b; font-size: 0.9rem;">
                                    <?php echo date('d/m/Y', strtotime($branch['created_at'])); ?>
                                </td>
                                <td data-label="Actions" style="padding: 15px; text-align: right;">
                                    <button onclick="openModal(
                                        <?php echo $branch['id']; ?>, 
                                        '<?php echo addslashes($branch['name']); ?>', 
                                        '<?php echo addslashes($branch['location']); ?>', 
                                        '<?php echo addslashes($branch['phone']); ?>'
                                    )" style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;" title="Modifier"><i class="fas fa-edit"></i></button>
                                    
                                    <?php if (!$isMain): ?>
                                    <a href="?delete=<?php echo $branch['id']; ?>" onclick="return confirm('Êtes-vous sûr ? Supprimer une succursale est dangereux si elle contient des données.');" style="color: #ef4444;" title="Supprimer"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="branchModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <h2 id="modalTitle" style="margin-top: 0;">Ajouter une Succursale</h2>
            <form method="POST">
                <input type="hidden" name="id" id="branch_id">
                
                <div class="form-group">
                    <label>Nom de la Succursale</label>
                    <input type="text" name="name" id="name" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Emplacement / Adresse</label>
                    <input type="text" name="location" id="location" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Téléphone de contact</label>
                    <input type="text" name="phone" id="phone" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" name="save_branch" style="flex: 1; padding: 12px; background: #4338ca; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Enregistrer</button>
                    <button type="button" onclick="closeModal()" style="flex: 1; padding: 12px; background: #f3f4f6; color: #4b5563; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id = '', name = '', location = '', phone = '') {
            document.getElementById('branch_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('location').value = location;
            document.getElementById('phone').value = phone;
            document.getElementById('modalTitle').innerText = id ? 'Modifier Succursale' : 'Ajouter Succursale';
            document.getElementById('branchModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('branchModal').style.display = 'none';
        }
    </script>
    <script src="../assets/js/admin.js?v=7"></script>
</body>
</html>
