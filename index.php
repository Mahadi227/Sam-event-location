<?php
// index.php
require_once 'includes/db.php';

// Fetch available items
$stmt = $pdo->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.status = 'available' ORDER BY i.category_id, i.name");
$allItems = $stmt->fetchAll();
$items = [];
$seen = [];
foreach ($allItems as $item) {
    if (!isset($seen[$item['name']])) {
        $seen[$item['name']] = true;
        $items[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sam Event Location - Location de Bâches & Chaises à Niamey</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <header>
        <a href="index.php" class="logo-container">
            <!-- Logo Placeholder mapping to the circular design in the image -->
            <div
                style="width: 50px; height: 50px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 3px solid var(--primary-blue);">
                S</div>
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
                <li><a href="#services">Services</a></li>
                <li><a href="booking.php">Réservation</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="track_reservation.php" style="color: var(--accent-gold); font-weight: 700;"><i
                            class="fas fa-search"></i> Suivre</a></li>
                <li><a href="login.php" style="color: var(--secondary-orange); font-weight: 800;"><i
                            class="fas fa-user-circle"></i> Connexion</a></li>
            </ul>
            <a href="tel:+22794250113" class="contact-btn-mobile" style="display:none;">
                <i class="fas fa-phone-alt"></i> Appeler +227 94250113
            </a>
        </nav>
        <a href="tel:+22794250113" class="contact-btn">
            <i class="fas fa-phone-alt"></i> +227 94250113
        </a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Sublimez Vos Événements à Niamey</h1>
            <p>Location de bâches, chaises, tables et toilettes mobiles de haute qualité pour mariages, baptêmes et
                conférences.</p>
            <a href="booking.php" class="contact-btn"
                style="display:inline-flex; padding: 15px 40px; font-size: 1.1rem;">Réservez Dès Maintenant</a>
        </div>
    </section>

    <div class="container" id="services">
        <div class="section-title">
            <h2>Nos Services</h2>
            <p>Location de Bâches & Chaises à Niamey</p>
        </div>

        <div class="services-grid">
            <?php foreach ($items as $item): ?>
                <div class="service-card">
                    <?php
                    $bgImage = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&q=80&w=600';
                    ?>
                    <div class="service-img" style="background-image: url('<?php echo htmlspecialchars($bgImage); ?>');">
                    </div>
                    <div class="service-info">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p><?php echo htmlspecialchars($item['description'] ?? 'Location de ' . strtolower($item['category_name']) . ' pour tous vos événements.'); ?>
                        </p>
                        <div class="service-footer">
                            <span class="price-tag"><?php echo number_format($item['price_per_day'], 0, ',', ' '); ?> <?php echo getCurrency(); ?> /
                                Jour</span>
                            <a href="booking.php?item=<?php echo $item['id']; ?>" class="btn-reserve">Réserver</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <section style="background: var(--light-gray); padding: 80px 0; margin-top: 80px;">
        <div class="container">
            <div class="section-title">
                <h2>Pourquoi Nous Choisir ?</h2>
                <p>Rapides, professionnels et fiables à prix compétitifs.</p>
            </div>

            <div class="values-grid">
                <div class="value-item">
                    <div class="value-icon"><i class="fas fa-star"></i></div>
                    <h3>Qualité</h3>
                    <p>Matériel premium et service impeccable.</p>
                </div>
                <div class="value-item">
                    <div class="value-icon"><i class="fas fa-clock"></i></div>
                    <h3>Ponctualité</h3>
                    <p>Livraison et montage toujours à l'heure.</p>
                </div>
                <div class="value-item">
                    <div class="value-icon"><i class="fas fa-handshake"></i></div>
                    <h3>Fiabilité</h3>
                    <p>Un partenaire de confiance pour vos événements.</p>
                </div>
                <div class="value-item">
                    <div class="value-icon"><i class="fas fa-tag"></i></div>
                    <h3>Prix raisonnable</h3>
                    <p>Le meilleur rapport qualité-prix à Niamey.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container testimonials-grid">
        <div class="testimonial-card" style="margin: 0;">
            <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Aminata D." class="user-avatar">
            <div style="color: var(--accent-gold); margin-bottom: 10px;">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                    class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p style="font-style: italic; font-size: 1.1rem; color: #555;">"Service rapide, matériel impeccable ! Merci
                à toute l'équipe de Sam Event Location pour l'organisation parfaite."</p>
            <h4 style="margin-top: 20px;">Aminata D.</h4>
        </div>
        <div class="testimonial-card" style="margin: 0;">
            <img src="https://randomuser.me/api/portraits/men/67.jpg" alt="Moussa B." class="user-avatar">
            <div style="color: var(--accent-gold); margin-bottom: 10px;">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                    class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p style="font-style: italic; font-size: 1.1rem; color: #555;">"Les bâches sont de très bonne qualité et
                l'équipe est très professionnelle. Je recommande vivement pour tous vos mariages."</p>
            <h4 style="margin-top: 20px;">Moussa B.</h4>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div>
                <div class="footer-logo">Sam Event</div>
                <p>Spécialiste de la location de matériel événementiel à Niamey, Niger.</p>
                <div style="margin-top: 20px; display: flex; gap: 15px;">
                    <a href="#" style="color: white;"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" style="color: white;"><i class="fab fa-instagram"></i></a>
                    <a href="#" style="color: white;"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div>
                <h3>Contactez-nous</h3>
                <p><i class="fas fa-phone-alt"></i> +227 96 12 44 90</p>
                <p><i class="fas fa-envelope"></i> contact@samevent.com</p>
                <p><i class="fas fa-map-marker-alt"></i> Quartier Aéroport, Niamey - Niger</p>
            </div>
            <div>
                <h3>Nos Services</h3>
                <ul style="list-style: none;">
                    <li>Location de Bâches</li>
                    <li>Location de Chaises</li>
                    <li>Tables & Décoration</li>
                    <li>Toilettes Mobiles</li>
                </ul>
            </div>
        </div>
        <div class="footer-copy">
            &copy; <?php echo date('Y'); ?> Sam Event Location. Tous droits réservés.
        </div>
    </footer>

    <script src="assets/js/main.js"></script>

</body>

</html>