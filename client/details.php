<?php
// client/details.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';

// Token-based access check
$id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;
$is_token_access = false;

if ($id && $token) {
    $stmt = $pdo->prepare("SELECT customer_phone FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res_check = $stmt->fetch();
    if ($res_check && md5($id . $res_check['customer_phone']) === $token) {
        $is_token_access = true;
    }
}

if (!$is_token_access) {
    requireClient();
    $user_id = $_SESSION['user_id'];
}

if (!$id) {
    header("Location: history.php");
    exit;
}

// Get reservation
if ($is_token_access) {
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name, b.name as branch_name FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id LEFT JOIN branches b ON r.branch_id = b.id WHERE r.id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT r.*, pc.code as promo_code_name, b.name as branch_name FROM reservations r LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id LEFT JOIN branches b ON r.branch_id = b.id WHERE r.id = ? AND r.user_id = ?");
    $stmt->execute([$id, $user_id]);
}
$res = $stmt->fetch();

if (!$res) {
    die("Réservation introuvable ou accès non autorisé.");
}

$is_token_access_param = $is_token_access ? "&token=" . urlencode($token) : "";

if ($res['status'] === 'pending') {
    if (isset($_POST['update_items'])) {
        $new_items = $_POST['items'] ?? [];
        $processed_items = [];
        foreach ($new_items as $item_id => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) $processed_items[$item_id] = $qty;
        }
        
        $is_weekend = (date('N', strtotime($res['event_date'])) >= 6);
        $params = [
            'duration_days' => $res['duration_days'],
            'distance_km' => $res['distance_km'],
            'is_weekend' => $is_weekend,
            'promo_code' => $res['promo_code_name']
        ];
        
        $pricing = calculateTotalPrice($processed_items, $params);
        
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM reservation_items WHERE reservation_id = ?");
            $stmt->execute([$id]);
            
            foreach ($processed_items as $item_id => $qty) {
                $stmt = $pdo->prepare("SELECT price_per_day FROM items WHERE id = ?");
                $stmt->execute([$item_id]);
                $price_at_time = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO reservation_items (reservation_id, item_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id, $item_id, $qty, $price_at_time]);
            }
            
            $stmt = $pdo->prepare("UPDATE reservations SET total_price = ?, discount_amount = ?, promo_code_id = ? WHERE id = ?");
            $stmt->execute([$pricing['total'], $pricing['discount'], $pricing['promo_code_id'], $id]);
            $pdo->commit();
            
            header("Location: details.php?id=" . $id . $is_token_access_param . "&success_items=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erreur articles: " . $e->getMessage());
        }
    }

    if (isset($_POST['update_info'])) {
        $name = $_POST['customer_name'];
        $phone = $_POST['customer_phone'];
        $date = $_POST['event_date'];
        $location = $_POST['event_location'];
        $duration_days = (int)($_POST['duration_days'] ?? 1);
        $distance_km = (int)($_POST['distance_km'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT item_id, quantity FROM reservation_items WHERE reservation_id = ?");
        $stmt->execute([$id]);
        $recalc_items = [];
        while($rit = $stmt->fetch()) {
             $recalc_items[$rit['item_id']] = $rit['quantity'];
        }
        
        $is_weekend = (date('N', strtotime($date)) >= 6);
        $params = [
            'duration_days' => $duration_days,
            'distance_km' => $distance_km,
            'is_weekend' => $is_weekend,
            'promo_code' => $res['promo_code_name']
        ];
        
        $pricing = calculateTotalPrice($recalc_items, $params);
        
        $stmt = $pdo->prepare("UPDATE reservations SET customer_name = ?, customer_phone = ?, event_date = ?, event_location = ?, duration_days = ?, distance_km = ?, total_price = ?, discount_amount = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $date, $location, $duration_days, $distance_km, $pricing['total'], $pricing['discount'], $id]);
        
        header("Location: details.php?id=" . $id . $is_token_access_param . "&success_info=1");
        exit;
    }
}

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name, i.price_per_day, i.image_url FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Get all items catalog
$stmt = $pdo->query("SELECT c.name as cat_name, i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.status = 'available'");
$all_items_catalog = [];
while ($row = $stmt->fetch()) {
    $all_items_catalog[$row['cat_name']][] = $row;
}


// Handle payment proof upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof'])) {
    $target_dir = "../uploads/proofs/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $file_name = "proof_" . $id . "_" . time() . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
        $stmt = $pdo->prepare("UPDATE reservations SET payment_proof = ? WHERE id = ?");
        $stmt->execute([$file_name, $id]);
        $msg = "Preuve de paiement soumise avec succès !";
        $res['payment_proof'] = $file_name;
    } else {
        $msg = "Erreur lors de l'envoi du fichier.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réservation #<?php echo $id; ?> - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<?php if (!$is_token_access): ?>
<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Event</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <div style="padding: 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 2px solid white; margin: 0 auto 10px;">S</div>
            <h2 style="color: white; font-size: 1.2rem;">Espace Client</h2>
        </div>
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Tableau de bord</a>
        <a href="../booking.php"><i class="fas fa-calendar-plus"></i> &nbsp; Réserver</a>
        <a href="history.php" class="active"><i class="fas fa-history"></i> &nbsp; Mes Réservations</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
<?php else: ?>
<div class="container" style="padding-top: 40px;">
<?php endif; ?>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;">
            <i class="fas fa-check-circle"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0;">Réservation #<?php echo $id; ?></h2>
        <a href="invoice.php?id=<?php echo $id; ?>" class="contact-btn" style="background: #444;"><i class="fas fa-file-pdf"></i> Facture PDF</a>
    </div>

    <div class="manage-grid">
        <div class="main-details">
            <div class="card">
                <?php
                $status_fr = [
                    'pending' => 'En attente',
                    'approved' => 'Approuvée',
                    'in_preparation' => 'En préparation',
                    'completed' => 'Terminée',
                    'cancelled' => 'Annulée',
                    'rejected' => 'Rejetée',
                    'returned' => 'Retournée'
                ];
                $display_status = $status_fr[$res['status']] ?? ucfirst($res['status']);
                ?>
                <span class="status-badge <?php echo $res['status']; ?>">Statut : <?php echo $display_status; ?></span>
                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Articles réservés</h3>
                    <?php if ($res['status'] === 'pending'): ?>
                        <button type="button" id="openEditItemsBtn" onclick="document.getElementById('editItemsForm').style.display='block'; document.getElementById('viewItems').style.display='none'; updateLiveEditSummary();" class="contact-btn" style="padding: 5px 15px; font-size: 0.85rem; background: #6366f1; border: none; cursor: pointer;"><i class="fas fa-edit"></i> Modifier</button>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($_GET['success_items'])): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; font-weight: 600;">Articles mis à jour ✅</div>
                <?php endif; ?>

                <div id="viewItems">
                    <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.8rem;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                <small><?php echo number_format($item['price_at_time'], 0); ?> <?php echo getCurrency(); ?> x <?php echo $item['quantity']; ?></small>
                            </div>
                        </div>
                        <div style="font-weight: 700;">
                            <?php echo number_format($item['price_at_time'] * $item['quantity'], 0); ?> <?php echo getCurrency(); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="display: flex; justify-content: space-between; margin-top: 30px; font-size: 1.3rem; font-weight: 800; color: var(--secondary-orange);">
                        <span>Total</span>
                        <span><?php echo number_format($res['total_price'], 0); ?> <?php echo getCurrency(); ?></span>
                    </div>
                </div>

                <?php if ($res['status'] === 'pending'): ?>
                <form id="editItemsForm" method="POST" style="display:none;">
                    <?php 
                    $curr_qtys = [];
                    foreach ($items as $it) {
                        $curr_qtys[$it['item_id']] = $it['quantity'];
                    }
                    ?>
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 15px;">
                        <?php foreach ($all_items_catalog as $cat => $cat_items): ?>
                            <div class="category-title" style="margin-top: 15px; font-size: 0.9rem; color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px;"><strong><?php echo htmlspecialchars($cat); ?></strong></div>
                            <?php foreach ($cat_items as $it): 
                                $q = $curr_qtys[$it['id']] ?? 0;
                            ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f9f9f9;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if (!empty($it['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <strong style="color: var(--dark-blue); font-size: 0.9rem;"><?php echo htmlspecialchars($it['name']); ?></strong><br>
                                            <small style="color: #666;"><?php echo number_format($it['price_per_day'], 0); ?> F</small>
                                        </div>
                                    </div>
                                    <input type="number" name="items[<?php echo $it['id']; ?>]" class="item-qty-edit" value="<?php echo $q; ?>" min="0" 
                                           data-id="<?php echo $it['id']; ?>" data-price="<?php echo $it['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($it['name']); ?>"
                                           style="width: 60px; text-align: center; padding: 5px; border-radius: 5px; border: 1px solid #ccc;">
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #64748b; font-size: 0.9rem;">
                            <span>Nouveau Total Estimé :</span>
                            <span id="newTotalPreview" style="font-weight: 800; color: var(--secondary-orange); font-size: 1.2rem;">CALCUL...</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" name="update_items" class="contact-btn" style="border:none; padding: 12px 15px; cursor: pointer; flex: 1;">Enregistrer Articles</button>
                        <button type="button" onclick="document.getElementById('editItemsForm').style.display='none'; document.getElementById('viewItems').style.display='block';" style="padding: 12px 15px; background: #e5e7eb; border: none; border-radius: 8px; cursor: pointer; color: #374151;">Annuler</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

                        <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">Infos Événement</h3>
                    <?php if ($res['status'] === 'pending'): ?>
                        <button type="button" onclick="document.getElementById('editInfoForm').style.display='block'; document.getElementById('viewInfo').style.display='none';" class="contact-btn" style="padding: 5px 15px; font-size: 0.85rem; background: #6366f1; border: none; cursor: pointer;"><i class="fas fa-edit"></i> Modifier</button>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['success_info'])): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; font-weight: 600;">Informations mises à jour ✅</div>
                <?php endif; ?>

                <div id="viewInfo">
                    <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($res['event_date'])); ?></p>
                    <p><strong>Durée :</strong> <?php echo htmlspecialchars($res['duration_days'] ?? 1); ?> jour(s)</p>
                    <p><strong>Branch  :</strong> <?php echo htmlspecialchars($res['branch_name'] ?? 'Principale'); ?></p>
                    <p><strong>Lieu :</strong> <?php echo htmlspecialchars($res['event_location']); ?> <?php if($res['distance_km']) echo '(Distance : '.$res['distance_km'].' km)'; ?></p>
                    <p><strong>Client :</strong> <?php echo htmlspecialchars($res['customer_name']); ?></p>
                    <p><strong>Tel :</strong> <?php echo htmlspecialchars($res['customer_phone']); ?></p>
                </div>
                
                <?php if ($res['status'] === 'pending'): ?>
                <form id="editInfoForm" method="POST" style="display: none; margin-top: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Nom</label>
                            <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($res['customer_name']); ?>" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Téléphone</label>
                            <input type="text" name="customer_phone" class="form-control" value="<?php echo htmlspecialchars($res['customer_phone']); ?>" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 600;">Date</label>
                                <input type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($res['event_date']); ?>" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 600;">Durée (Jours)</label>
                                <input type="number" name="duration_days" class="form-control" value="<?php echo $res['duration_days']; ?>" required min="1" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 600;">Lieu</label>
                                <input type="text" name="event_location" class="form-control" value="<?php echo htmlspecialchars($res['event_location']); ?>" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.85rem; font-weight: 600;">KM</label>
                                <input type="number" name="distance_km" class="form-control" value="<?php echo $res['distance_km']; ?>" min="0" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="submit" name="update_info" class="contact-btn" style="border:none; padding: 10px; cursor: pointer; flex: 1;">Enregistrer</button>
                        <button type="button" onclick="document.getElementById('editInfoForm').style.display='none'; document.getElementById('viewInfo').style.display='block';" style="padding: 10px; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer; color: #374151;">Annuler</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="side-panel">
            <div class="card">
                <h3>Paiement</h3>
                <div style="margin: 20px 0;">
                    <div style="font-size: 0.9rem; color: #666;">Montant payé :</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #166534;"><?php echo number_format($res['amount_paid'], 0); ?> <?php echo getCurrency(); ?></div>
                </div>

                <?php if ($res['amount_paid'] < $res['total_price']): ?>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 20px;">
                        Veuillez charger votre preuve de paiement (Orange Money, Moov ou Espèces) ci-dessous.
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <input type="file" name="proof" required style="width: 100%; border: 1px dashed #ccc; padding: 10px; border-radius: 10px;">
                        </div>
                        <button type="submit" class="contact-btn" style="width: 100%; margin-top: 15px; border: none; cursor: pointer;">Envoyer la preuve</button>
                    </form>
                <?php endif; ?>

                <?php if ($res['payment_proof']): ?>
                    <div style="margin-top: 30px; border-top: 1px solid #eee; pt: 20px;">
                        <p><strong>Preuve soumise :</strong></p>
                        <a href="../uploads/proofs/<?php echo $res['payment_proof']; ?>" target="_blank" style="color: var(--primary-blue); font-size: 0.8rem;">
                            <i class="fas fa-image"></i> Voir le fichier
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if (!$is_token_access): ?>
</div>
</div>
<?php endif; ?>

<script src="../assets/js/admin.js?v=7"></script>
<script>
    const resData = {
        duration: <?php echo $res['duration_days']; ?>,
        distance: <?php echo $res['distance_km'] ?? 0; ?>,
        is_weekend: <?php echo (date('N', strtotime($res['event_date'])) >= 6) ? 'true' : 'false'; ?>,
        promo: "<?php echo htmlspecialchars($res['promo_code_name'] ?? ''); ?>"
    };

    async function updateLiveEditSummary() {
        const items = {};
        document.querySelectorAll('.item-qty-edit').forEach(i => {
            if (i.value > 0) items[i.dataset.id] = i.value;
        });

        const data = {
            items: items,
            duration: resData.duration,
            distance: resData.distance,
            is_weekend: resData.is_weekend === true || resData.is_weekend === 'true',
            promo: resData.promo
        };

        try {
            const response = await fetch('../api/calculate_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if(document.getElementById('newTotalPreview')){
                document.getElementById('newTotalPreview').innerText = (result.total || 0).toLocaleString() + ' <?php echo getCurrency(); ?>';
            }
        } catch(e) {
            console.error('Erreur API:', e);
        }
    }

    document.querySelectorAll('.item-qty-edit').forEach(i => {
        i.addEventListener('input', updateLiveEditSummary);
        i.addEventListener('change', updateLiveEditSummary);
    });
</script>
</body>
</html>
