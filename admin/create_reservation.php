<?php
// admin/create_reservation.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Get items
$stmt = $pdo->query("SELECT c.name as cat_name, i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.status = 'available'");
$items = [];
while ($row = $stmt->fetch()) {
    $items[$row['cat_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Réservation - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php" class="active"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Nouvelle Réservation</h2>

    <form id="walkinForm" action="../process_booking_ai.php" method="POST">
        <div class="walkin-grid" style="margin-top: 20px;">
            <div class="main-form">
                <div class="card">
                    <h3>Infos Client</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Nom Client</label>
                            <input type="text" name="customer_name" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="customer_phone" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Email (Optionnel - Pour le reçu)</label>
                        <input type="email" name="customer_email" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Date de l'événement</label>
                            <input type="date" name="event_date" id="event_date" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Durée (Jours)</label>
                            <input type="number" name="duration" id="duration" value="1" min="1" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Lieu</label>
                            <!-- NOTE: process_booking_ai exiges 'location' pas 'event_location' -->
                            <input type="text" name="location" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Distance (KM)</label>
                            <input type="number" name="distance" id="distance" value="0" min="0" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <h3 style="margin-top: 30px;">Matériel</h3>
                    <?php foreach ($items as $cat => $cat_items): ?>
                        <div class="category-title"><strong><?php echo htmlspecialchars($cat); ?></strong></div>
                        <?php foreach ($cat_items as $it): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f9f9f9;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <?php if (!empty($it['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="color: var(--dark-blue);"><?php echo htmlspecialchars($it['name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo number_format($it['price_per_day'], 0); ?> F</small>
                                    </div>
                                </div>
                                <input type="number" name="items[<?php echo $it['id']; ?>]" class="item-qty" value="0" min="0" 
                                       data-id="<?php echo $it['id']; ?>" data-price="<?php echo $it['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($it['name']); ?>"
                                       style="width: 60px; text-align: center; padding: 5px; border-radius: 5px; border: 1px solid #ccc;">
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="summary-side">
                <div class="card" style="position: sticky; top: 30px;">
                    <h3>Récapitulatif</h3>
                    <div id="selectionSummary" style="margin: 15px 0; font-size: 0.9rem; color: #666;">
                        <em>Aucun article sélectionné</em>
                    </div>
                    <hr>
                    <div class="form-group" style="margin: 15px 0;">
                        <label>Code Promo</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="promo_code" name="promo_code" class="form-control" placeholder="Ex: WELCOME10" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            <button type="button" onclick="applyPromo()" class="btn-reserve" style="background: var(--dark-blue); padding: 10px;">Appliquer</button>
                        </div>
                    </div>
                    <div style="margin-top: 15px; font-size: 0.85rem; color: #666;">
                        <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> Durée: <span id="displayDuration">1 jour(s)</span><br>
                        <i class="fas fa-truck" style="margin-right: 5px;"></i> Livraison: <span id="displayDelivery">0 F</span><br>
                        <i class="fas fa-tag" style="margin-right: 5px;"></i> Remise: <span id="displayDiscount">0 F</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 15px 0; font-size: 1.2rem; font-weight: 800; color: var(--secondary-orange);">
                        <span>Total</span>
                        <span id="displayTotal">0 FCFA</span>
                    </div>
                    <button type="submit" id="submitBtn" class="contact-btn" style="width: 100%; border: none; cursor: pointer; padding: 15px;">Enregistrer la Réservation</button>
                    <div id="successDetails" style="margin-top: 15px;"></div>
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
    function isWeekend(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        return d.getDay() === 0 || d.getDay() === 6;
    }

    async function updateLiveSummary() {
        const items = {};
        document.querySelectorAll('.item-qty').forEach(i => {
            if (i.value > 0) items[i.dataset.id] = i.value;
        });

        const durInput = document.getElementById('duration');
        const distInput = document.getElementById('distance');
        const dateInput = document.getElementById('event_date');
        const promoInput = document.getElementById('promo_code');

        const data = {
            items: items,
            duration: durInput ? durInput.value : 1,
            distance: distInput ? distInput.value : 0,
            is_weekend: isWeekend(dateInput ? dateInput.value : null),
            promo: promoInput ? promoInput.value : ''
        };

        const res = await fetch('../api/calculate_price.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const result = await res.json();

        // Update UI
        document.getElementById('displayDuration').innerText = data.duration + ' jour(s)';
        document.getElementById('displayTotal').innerText = result.total.toLocaleString() + ' F';
        document.getElementById('displayDelivery').innerText = result.delivery.toLocaleString() + ' F';
        
        const discSpan = document.getElementById('displayDiscount');
        if (result.discount > 0) {
            discSpan.innerText = '- ' + result.discount.toLocaleString() + ' F';
            discSpan.style.color = '#15803d'; // dark green
            discSpan.style.fontWeight = 'bold';
        } else {
            discSpan.innerText = '0 F';
            discSpan.style.color = 'inherit';
            discSpan.style.fontWeight = 'normal';
        }

        updateSummaryItems(items);
    }

    function updateSummaryItems(items) {
        let html = '';
        let count = 0;
        document.querySelectorAll('.item-qty').forEach(i => {
            if (i.value > 0) {
                count++;
                html += `<div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>${i.dataset.name} x${i.value}</span>
                            <span>${(i.dataset.price * i.value).toLocaleString()} F</span>
                         </div>`;
            }
        });
        document.getElementById('selectionSummary').innerHTML = count > 0 ? html : '<em>Aucun article sélectionné</em>';
    }

    const inputs = ['input', 'change'].forEach(evt => {
        document.getElementById('walkinForm').addEventListener(evt, () => {
            updateLiveSummary();
        });
    });

    async function applyPromo() {
        const btn = document.querySelector('button[onclick="applyPromo()"]');
        const originalText = btn.innerText;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        await updateLiveSummary();
        btn.innerText = 'Appliqué ✓';
        setTimeout(() => { btn.innerText = originalText; }, 2000);
    }

    document.getElementById('walkinForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerText;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData(this);
            const res = await fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            
            const data = await res.json();
            
            if (data.success) {
                document.getElementById('successDetails').innerHTML = `
                    <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; text-align: center;">
                        <strong>Réservation #${data.reservation_id} Créée !</strong><br>
                        <a href="../client/invoice.php?id=${data.reservation_id}" target="_blank" class="contact-btn" style="display: inline-block; margin-top: 10px; width: 100%; text-align: center; background: var(--dark-blue); border: none;"><i class="fas fa-print"></i> Imprimer Facture</a>
                        <br>
                        <a href="reservations.php" style="display: inline-block; margin-top: 10px; width: 100%; color: var(--primary-blue); font-weight: bold;">Retour aux réservations</a>
                    </div>
                `;
                submitBtn.style.display = 'none'; 
            } else {
                document.getElementById('successDetails').innerHTML = `
                    <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 15px 20px; border-radius: 8px; text-align: left; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.1); margin-top: 15px;">
                        <div style="display: flex; align-items: center; margin-bottom: 5px;">
                            <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.2rem; margin-right: 10px;"></i>
                            <strong style="font-size: 1.1rem;">Action requise</strong>
                        </div>
                        <div style="margin-left: 28px; line-height: 1.4; font-size: 0.95rem;">
                            ${data.error || 'Une erreur est survenue lors de la réservation.'}
                        </div>
                    </div>
                `;
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        } catch (err) {
            alert('Erreur de connexion.');
            submitBtn.innerText = originalText;
            submitBtn.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        updateLiveSummary();
    });
</script>

</body>
</html>
