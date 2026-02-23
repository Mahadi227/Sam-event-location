<?php
// receptionist/walk_in.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();

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
    <title>Walk-in Reservation - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Reception</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2 style="color: white; margin-bottom: 30px;">Reception Sam</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Accueil</a>
        <a href="walk_in.php" class="active"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
        <a href="reservations.php"><i class="fas fa-list"></i> &nbsp; Reservations</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Nouvelle Réservation (Sur place)</h2>

    <form action="../process_booking.php" method="POST">
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
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group">
                            <label>Date de l'événement</label>
                            <input type="date" name="event_date" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Lieu</label>
                            <input type="text" name="event_location" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <h3 style="margin-top: 30px;">Matériel</h3>
                    <?php foreach ($items as $cat => $cat_items): ?>
                        <div class="category-title"><strong><?php echo $cat; ?></strong></div>
                        <?php foreach ($cat_items as $it): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f9f9f9;">
                                <span><?php echo htmlspecialchars($it['name']); ?> <small>(<?php echo number_format($it['price_per_day'], 0); ?> F)</small></span>
                                <input type="number" name="items[<?php echo $it['id']; ?>]" class="item-qty" value="0" min="0" 
                                       data-price="<?php echo $it['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($it['name']); ?>"
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
                    <div style="display: flex; justify-content: space-between; margin: 15px 0; font-size: 1.2rem; font-weight: 800; color: var(--secondary-orange);">
                        <span>Total</span>
                        <span id="totalPrice">0 FCFA</span>
                    </div>
                    <input type="hidden" name="total_price" id="totalPriceHidden" value="0">
                    <button type="submit" class="contact-btn" style="width: 100%; border: none; cursor: pointer; padding: 15px;">Enregistrer</button>
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
    const qtyInputs = document.querySelectorAll('.item-qty');
    const summaryDiv = document.getElementById('selectionSummary');
    const totalPriceSpan = document.getElementById('totalPrice');
    const totalPriceHidden = document.getElementById('totalPriceHidden');

    function updateSummary() {
        let total = 0;
        let html = '';
        let count = 0;
        qtyInputs.forEach(input => {
            const q = parseInt(input.value) || 0;
            if (q > 0) {
                count++;
                const p = parseFloat(input.dataset.price);
                const sub = q * p;
                total += sub;
                html += `<div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span>${input.dataset.name} x${q}</span>
                            <span>${sub.toLocaleString()} F</span>
                         </div>`;
            }
        });
        summaryDiv.innerHTML = count > 0 ? html : '<em>Aucun article sélectionné</em>';
        totalPriceSpan.innerText = total.toLocaleString() + ' FCFA';
        totalPriceHidden.value = total;
    }

    qtyInputs.forEach(input => input.addEventListener('input', updateSummary));
</script>

</body>
</html>
