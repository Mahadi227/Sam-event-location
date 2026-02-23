<?php
// booking.php
require_once 'includes/db.php';
session_start();

$stmt = $pdo->query("SELECT c.name as category_name, i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.status = 'available' ORDER BY c.name, i.name");
$items_by_cat = [];
while ($row = $stmt->fetch()) {
    $items_by_cat[$row['category_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moteur de Réservation Intelligent - Sam Event</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.socket.io/4.6.0/socket.io.min.js"></script>
    <script src="assets/js/realtime.js"></script>
    <script src="assets/js/main.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .step { flex: 1; text-align: center; color: #ccc; font-weight: 800; border-bottom: 4px solid #eee; padding-bottom: 15px; position: relative; }
        .step.active { color: var(--primary-blue); border-bottom-color: var(--accent-gold); }
        .step-panel { display: none; background: white; padding: 40px; border-radius: 20px; box-shadow: var(--shadow-strong); }
        .step-panel.active { display: block; }
        .booking-layout { display: grid; grid-template-columns: 1fr 380px; gap: 40px; margin-bottom: 80px; }
        .live-summary { position: sticky; top: 120px; background: white; padding: 30px; border-radius: 20px; border-top: 6px solid var(--accent-gold); box-shadow: var(--shadow-strong); height: fit-content; }
        .stock-tag { font-size: 0.75rem; color: #666; background: #f0f0f0; padding: 2px 8px; border-radius: 5px; }
    </style>
</head>
<body style="background: var(--light-gray);">

<header>
    <a href="index.php" class="logo-container">
        <div style="width: 50px; height: 50px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 3px solid var(--primary-blue);">S</div>
        <div class="logo-text">Sam Event <span>LOCATION</span></div>
    </a>
    <button class="hamburger">
        <div></div>
        <div></div>
        <div></div>
    </button>
    <nav>
        <ul>
            <li><a href="index.php">Accueil</a></li>
            <li><a href="track_reservation.php">Suivi</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="client/dashboard.php">Mon Compte</a></li>
            <?php else: ?>
                <li><a href="login.php">Connexion</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<div class="container" style="margin-top: 50px;">
    <div class="section-title">
        <h2>Moteur Intelligent</h2>
        <p>Réservez avec disponibilité en temps réel</p>
    </div>

    <div class="step-indicator">
        <div class="step active" id="s1">1. CHOIX MATÉRIEL</div>
        <div class="step" id="s2">2. DATE & LIEU</div>
        <div class="step" id="s3">3. RÉCAPITULATIF</div>
        <div class="step" id="s4">4. TERMINÉ</div>
    </div>

    <form id="aiBookingForm" action="process_booking_ai.php" method="POST">
        <div class="booking-layout">
            <div class="wizard-content">
                <!-- STEP 1: ITEMS -->
                <div class="step-panel active" id="panel1">
                    <h3>Sélectionnez vos articles</h3>
                    <?php foreach ($items_by_cat as $cat_name => $cat_items): ?>
                        <div style="margin: 30px 0 15px; font-weight: 800; color: #333; border-bottom: 2px solid #eee;"><?php echo htmlspecialchars($cat_name); ?></div>
                        <?php foreach ($cat_items as $item): ?>
                            <div class="item-row" style="padding: 15px; background: #fff; border-radius: 15px; margin-bottom: 10px; border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: var(--dark-blue);"><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                    <span class="stock-tag">Total: <?php echo $item['quantity_total']; ?></span>
                                    <small><?php echo number_format($item['price_per_day'], 0); ?> F/j</small>
                                </div>
                                <input type="number" name="items[<?php echo $item['id']; ?>]" class="item-qty" 
                                       data-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                       value="0" min="0" max="<?php echo $item['quantity_total']; ?>" 
                                       style="width: 70px; padding: 8px; border-radius: 8px; border: 1px solid #ccc; text-align: center;">
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <button type="button" class="contact-btn" onclick="nextStep(2)" style="width: 100%; margin-top: 30px; border: none; cursor: pointer;">Continuer vers Date & Lieu →</button>
                </div>

                <!-- STEP 2: DATE & LOCATION -->
                <div class="step-panel" id="panel2">
                    <h3>Où et quand ?</h3>
                    <div class="responsive-grid">
                        <div class="form-group">
                            <label>Date de l'événement</label>
                            <input type="date" name="event_date" id="event_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Durée (Nombre de jours)</label>
                            <input type="number" name="duration" id="duration" value="1" min="1" class="form-control" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div class="responsive-grid">
                        <div class="form-group">
                            <label>Nom complet</label>
                            <input type="text" name="customer_name" class="form-control" value="<?php echo $_SESSION['name'] ?? ''; ?>" required style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Téléphone (WhatsApp)</label>
                            <input type="tel" name="customer_phone" class="form-control" value="<?php echo $_SESSION['phone'] ?? ''; ?>" required placeholder="Ex: 96112233" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div class="responsive-grid">
                        <div class="form-group">
                            <label>Lieu exact à Niamey</label>
                            <input type="text" name="location" class="form-control" placeholder="Quartier, Rue..." required style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Distance approx. (KM)</label>
                            <input type="number" name="distance" id="distance" value="0" min="0" class="form-control" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 20px; margin-top: 40px;">
                        <button type="button" class="btn-reserve" onclick="nextStep(1)" style="flex:1; background: #888;">← Retour</button>
                        <button type="button" class="contact-btn" onclick="nextStep(3)" style="flex:2; border:none; cursor: pointer;">Vérifier Disponibilité & Total →</button>
                    </div>
                </div>

                <!-- STEP 3: REVIEW -->
                <div class="step-panel" id="panel3">
                    <h3>Récapitulatif & Remises</h3>
                    <div id="finalReview">
                        <!-- Ajax loaded content -->
                    </div>
                    <div class="form-group" style="margin-top: 30px;">
                        <label>Code Promo</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="promo_code" class="form-control" placeholder="Ex: WELCOME10" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                            <button type="button" onclick="applyPromo()" class="btn-reserve" style="background: var(--dark-blue);">Appliquer</button>
                        </div>
                    </div>
                    <div style="display: flex; gap: 20px; margin-top: 40px;">
                        <button type="button" class="btn-reserve" onclick="nextStep(2)" style="flex:1; background: #888;">← Retour</button>
                        <button type="submit" class="contact-btn" style="flex:2; border:none; cursor: pointer;">Confirmer la Réservation ✓</button>
                    </div>
                </div>
            </div>

            <div class="live-summary">
                <h4 style="margin-bottom: 20px;">Votre Devis en Direct</h4>
                <div id="summaryItems">
                    <p style="color: #aaa; text-align: center;">Sélectionnez vos articles...</p>
                </div>
                <hr style="margin: 20px 0; border: none; border-top: 1px dashed #eee;">
                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1.4rem; color: var(--secondary-orange);">
                    <span>TOTAL</span>
                    <span id="displayTotal">0 F</span>
                </div>
                <div style="margin-top: 15px; font-size: 0.8rem; color: #666;">
                    <i class="fas fa-truck" style="margin-right: 5px;"></i> Livraison: <span id="displayDelivery">--</span><br>
                    <i class="fas fa-tag" style="margin-right: 5px;"></i> Remise: <span id="displayDiscount">0 F</span>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let currentStep = 1;

    function nextStep(step) {
        document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('panel' + step).classList.add('active');
        document.getElementById('s' + step).classList.add('active');
        currentStep = step;
        
        if (step === 3) updateFullDevis();
    }

    // Dynamic Updates
    const inputs = ['input', 'change'].forEach(evt => {
        document.getElementById('aiBookingForm').addEventListener(evt, () => {
            updateLiveSummary();
        });
    });

    async function updateLiveSummary() {
        const items = {};
        document.querySelectorAll('.item-qty').forEach(i => {
           if (i.value > 0) items[i.dataset.id] = i.value;
        });

        const data = {
            items: items,
            duration: document.getElementById('duration').value,
            distance: document.getElementById('distance').value,
            is_weekend: isWeekend(document.getElementById('event_date').value),
            promo: document.getElementById('promo_code').value
        };

        const res = await fetch('api/calculate_price.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const result = await res.json();

        // Update UI
        document.getElementById('displayTotal').innerText = result.total.toLocaleString() + ' F';
        document.getElementById('displayDelivery').innerText = result.delivery.toLocaleString() + ' F';
        document.getElementById('displayDiscount').innerText = result.discount.toLocaleString() + ' F';

        updateSummaryItems(items);
    }

    function updateSummaryItems(items) {
        let html = '';
        let count = 0;
        document.querySelectorAll('.item-qty').forEach(i => {
            if (i.value > 0) {
                count++;
                html += `<div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.9rem;">
                            <span>${i.dataset.name} x${i.value}</span>
                            <span>${(i.dataset.price * i.value).toLocaleString()} F</span>
                         </div>`;
            }
        });
        document.getElementById('summaryItems').innerHTML = count > 0 ? html : '<p style="color: #aaa; text-align: center;">Sélectionnez vos articles...</p>';
    }

    function isWeekend(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        return d.getDay() === 0 || d.getDay() === 6;
    }

    function applyPromo() {
        updateLiveSummary();
    }
</script>

</body>
</html>
