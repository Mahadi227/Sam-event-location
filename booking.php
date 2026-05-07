<?php
// booking.php
require_once 'includes/db.php';
session_start();

$error = $_GET['error'] ?? '';
$branch_id = $_GET['branch_id'] ?? null;

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();

if ($branch_id) {
    $stmt = $pdo->prepare("SELECT c.name as category_name, i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.status = 'available' AND i.branch_id = ? ORDER BY c.name, i.name");
    $stmt->execute([$branch_id]);
    $items_by_cat = [];
    while ($row = $stmt->fetch()) {
        $items_by_cat[$row['category_name']][] = $row;
    }
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
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .step {
            flex: 1;
            text-align: center;
            color: #ccc;
            font-weight: 800;
            border-bottom: 4px solid #eee;
            padding-bottom: 15px;
            position: relative;
        }

        .step.active {
            color: var(--primary-blue);
            border-bottom-color: var(--accent-gold);
        }

        .step-panel {
            display: none;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-strong);
        }

        .step-panel.active {
            display: block;
        }

        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 80px;
        }

        .live-summary {
            position: sticky;
            top: 120px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            border-top: 6px solid var(--accent-gold);
            box-shadow: var(--shadow-strong);
            height: fit-content;
        }

        .stock-tag {
            font-size: 0.75rem;
            color: #666;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 5px;
        }
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
        </div>

        <?php if ($error): ?>
            <div style="background: #fee; border: 1px solid #f00; color: #c00; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;">
                <i class="fas fa-exclamation-triangle"></i> Erreur de réservation : <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form id="aiBookingForm" action="process_booking_ai.php" method="POST">
            <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($branch_id ?? ''); ?>">

            <?php if (!$branch_id): ?>
                <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: var(--shadow-strong); text-align: center;">
                    <h3>Veuillez sélectionner l'agence de retrait</h3>
                    <p style="color: #666; margin-bottom: 20px;">Nos stocks varient en fonction de nos branch .</p>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;">
                        <?php foreach($branches as $b): ?>
                            <a href="?branch_id=<?php echo $b['id']; ?>" style="display: block; width: 250px; padding: 25px; border: 2px solid var(--accent-gold); border-radius: 15px; text-decoration: none; color: #333; transition: 0.3s; background: #fff;" onmouseover="this.style.background='var(--accent-gold)'; this.style.color='white';" onmouseout="this.style.background='#fff'; this.style.color='#333';">
                                <i class="fas fa-building" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
                                <strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($b['name']); ?></strong><br>
                                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($b['location'] ?? ''); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>

            <div class="booking-layout">
                <div class="wizard-content">
                    <!-- STEP 1: ITEMS -->
                    <div class="step-panel active" id="panel1">
                        <h3>Sélectionnez vos articles</h3>
                        <?php if(empty($items_by_cat)): ?>
                            <p style="color:red; font-weight:bold;">Aucun article disponible dans cette succursale pour le moment.</p>
                        <?php endif; ?>
                        
                        <?php foreach ($items_by_cat as $cat_name => $cat_items): ?>
                            <div style="margin: 30px 0 15px; font-weight: 800; color: #333; border-bottom: 2px solid #eee;"><?php echo htmlspecialchars($cat_name); ?></div>
                            <?php foreach ($cat_items as $item): ?>

                                <div class="item-row" style="padding: 15px; background: #fff; border-radius: 15px; margin-bottom: 10px; border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 10px;">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #eee; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #999;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong style="color: var(--dark-blue);"><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                            <span class="stock-tag">Total: <?php echo $item['quantity_total']; ?></span>
                                            <small><?php echo number_format($item['price_per_day'], 0); ?> F/j</small>
                                        </div>
                                    </div>
                                    <?php $presetQty = (isset($_GET['item']) && $_GET['item'] == $item['id']) ? 1 : 0; ?>
                                    <input type="number" name="items[<?php echo $item['id']; ?>]" class="item-qty"
                                        data-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                        value="<?php echo $presetQty; ?>" min="0" max="<?php echo $item['quantity_total']; ?>"
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
                        <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                            <label>Email (Optionnel)</label>
                            <input type="email" name="customer_email" class="form-control" placeholder="Pour recevoir votre reçu de réservation" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
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
                                <input type="text" id="promo_code" name="promo_code" class="form-control" placeholder="Ex: WELCOME10" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                                <button type="button" onclick="applyPromo()" class="btn-reserve" style="background: var(--dark-blue);">Appliquer</button>
                            </div>
                        </div>
                        <div style="display: flex; gap: 20px; margin-top: 40px;">
                            <button type="button" class="btn-reserve" onclick="nextStep(2)" style="flex:1; background: #888;">← Retour</button>
                            <button type="submit" class="contact-btn" style="flex:2; border:none; cursor: pointer;">Confirmer la Réservation ✓</button>
                        </div>
                        <div id="errorDetails" style="margin-top: 15px;"></div>
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
                        <span id="displayTotal">0 <?php echo getCurrency(); ?></span>
                    </div>
                    <div style="margin-top: 15px; font-size: 0.8rem; color: #666;">
                        <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> Durée: <span id="displayDuration">1 jour(s)</span><br>
                        <i class="fas fa-truck" style="margin-right: 5px;"></i> Livraison: <span id="displayDelivery">--</span><br>
                        <i class="fas fa-tag" style="margin-right: 5px;"></i> Remise: <span id="displayDiscount">0 <?php echo getCurrency(); ?></span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        let currentStep = 1;

        function nextStep(step) {
            // Validation: Must select at least one item to proceed to Step 2
            if (currentStep === 1 && step === 2) {
                let hasItems = false;
                document.querySelectorAll('.item-qty').forEach(i => {
                    if (parseInt(i.value) > 0) hasItems = true;
                });
                if (!hasItems) {
                    alert("Veuillez sélectionner au moins un article avant de continuer.");
                    return;
                }
            }

            // Validation: Must fill all required fields to proceed to Step 3
            if (currentStep === 2 && step === 3) {
                const requiredInputs = document.querySelectorAll('#panel2 input[required]');
                let allFilled = true;
                
                // We use standard HTML5 validation reporting
                for (let input of requiredInputs) {
                    if (!input.checkValidity()) {
                        input.reportValidity();
                        allFilled = false;
                        break;
                    }
                }
                
                if (!allFilled) return;
            }

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

        document.addEventListener('DOMContentLoaded', () => {
            updateLiveSummary();
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
            const currency = '<?php echo getCurrency(); ?>';
            const formatMoney = (val) => Number(val).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' ' + currency;

            document.getElementById('displayDuration').innerText = data.duration + ' jour(s)';
            document.getElementById('displayTotal').innerText = formatMoney(result.total);
            document.getElementById('displayDelivery').innerText = formatMoney(result.delivery);
            
            const discSpan = document.getElementById('displayDiscount');
            if (result.discount > 0) {
                discSpan.innerText = '- ' + formatMoney(result.discount);
                discSpan.style.color = '#15803d'; // dark green
                discSpan.style.fontWeight = 'bold';
            } else {
                discSpan.innerText = formatMoney(0);
                discSpan.style.color = 'inherit';
                discSpan.style.fontWeight = 'normal';
            }

            updateSummaryItems(items, data.duration, data.is_weekend);
        }

        function updateSummaryItems(items, duration, isWeekend) {
            const currency = '<?php echo getCurrency(); ?>';
            const formatMoney = (val) => Number(val).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' ' + currency;

            let html = '';
            let count = 0;
            let itemsBaseTotal = 0;
            document.querySelectorAll('.item-qty').forEach(i => {
                if (i.value > 0) {
                    count++;
                    const qty = parseInt(i.value);
                    const price = parseFloat(i.dataset.price);
                    itemsBaseTotal += price * qty;
                    
                    html += `<div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.9rem;">
                            <span>${i.dataset.name} x${qty}</span>
                            <span>${formatMoney(price * qty)} /j</span>
                         </div>`;
                }
            });
            
            if (count > 0) {
                html += `<div style="border-top:1px dashed #ccc; margin-top:10px; padding-top:10px; display:flex; justify-content:space-between; font-weight:bold; font-size:0.95rem; color:var(--primary-blue);">
                            <span>Sous-total (Jour)</span>
                            <span>${formatMoney(itemsBaseTotal)}</span>
                         </div>`;
                         
                html += `<div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-top:5px; color:#666;">
                            <span>Durée de location</span>
                            <span>x ${duration} jour(s)</span>
                         </div>`;
                
                if (isWeekend) {
                    html += `<div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-top:5px; color:#d97706; font-weight:bold;">
                            <span>Majoration Week-end</span>
                            <span>Active</span>
                         </div>`;
                }
            }
            
            document.getElementById('summaryItems').innerHTML = count > 0 ? html : '<p style="color: #aaa; text-align: center;">Sélectionnez vos articles...</p>';
        }

        function isWeekend(dateStr) {
            if (!dateStr) return false;
            const d = new Date(dateStr);
            return d.getDay() === 0 || d.getDay() === 6;
        }

        async function applyPromo() {
            const btn = document.querySelector('button[onclick="applyPromo()"]');
            const originalText = btn.innerText;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            await updateLiveSummary();
            
            btn.innerText = 'Appliqué ✓';
            setTimeout(() => { btn.innerText = originalText; }, 2000);
            
            if (currentStep === 3) {
                updateFullDevis();
            }
        }

        function updateFullDevis() {
            const summaryHtml = document.getElementById('summaryItems').innerHTML;
            const totalText = document.getElementById('displayTotal').innerText;
            const deliveryText = document.getElementById('displayDelivery').innerText;
            const discountText = document.getElementById('displayDiscount').innerText;
            const durationText = document.getElementById('displayDuration').innerText;
            
            document.getElementById('finalReview').innerHTML = `
                <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; border: 1px solid #eee;">
                    <h4 style="margin-bottom: 15px; color: var(--primary-blue);">Matériel sélectionné</h4>
                    ${summaryHtml}
                    <hr style="margin: 15px 0; border: none; border-top: 1px dashed #ddd;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #666;">
                        <span>Durée de location:</span>
                        <span>${durationText}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #666; margin-top: 5px;">
                        <span>Frais de livraison:</span>
                        <span>${deliveryText}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #666; margin-top: 5px;">
                        <span>Remise promotionnelle:</span>
                        <span>${discountText}</span>
                    </div>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
                    <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 800; color: var(--secondary-orange);">
                        <span>Total à Payer:</span>
                        <span>${totalText}</span>
                    </div>
                </div>
            `;
        }

        document.getElementById('aiBookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerText;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
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
                    window.location.href = 'confirmation.php?id=' + data.reservation_id;
                } else {
                    document.getElementById('errorDetails').innerHTML = `
                        <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 15px 20px; border-radius: 8px; text-align: left; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.1); margin-top: 5px;">
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
    </script>
    <?php endif; ?>
</body>

</html>