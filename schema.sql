-- Database structure for Sam Event Location - COMPLETE UPGRADE
-- Drop existing tables to ensure clean structure (WARNING: All data will be lost)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reservation_items;

DROP TABLE IF EXISTS payments;

DROP TABLE IF EXISTS reservations;

DROP TABLE IF EXISTS items;

DROP TABLE IF EXISTS categories;

DROP TABLE IF EXISTS settings;

DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM(
        'super_admin',
        'mini_admin',
        'receptionist',
        'client'
    ) DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price_per_day DECIMAL(10, 2) NOT NULL,
    quantity_total INT NOT NULL,
    status ENUM(
        'available',
        'out_of_stock',
        'maintenance'
    ) DEFAULT 'available',
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id)
);

CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- NULL if walk-in or guest
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    event_date DATE NOT NULL,
    event_location TEXT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0,
    duration_days INT DEFAULT 1,
    distance_km INT DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    promo_code_id INT,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'completed',
        'cancelled',
        'in_preparation'
    ) DEFAULT 'pending',
    payment_proof VARCHAR(255),
    delivery_option BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS reservation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    item_id INT,
    quantity INT NOT NULL,
    price_at_time DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items (id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM(
        'cash',
        'orange_money',
        'MyNiTa',
        'AmanaTa',
        'moov_money',
        'card'
    ) DEFAULT 'cash',
    transaction_ref VARCHAR(100),
    processed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations (id),
    FOREIGN KEY (processed_by) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    discount_percent DECIMAL(5, 2) NOT NULL,
    valid_until DATE,
    usage_limit INT DEFAULT 100,
    times_used INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS pricing_rules (
    rule_key VARCHAR(50) PRIMARY KEY,
    rule_value DECIMAL(10, 2),
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS draft_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    form_data LONGTEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    reservation_id INT,
    change_qty INT,
    reason VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items (id),
    FOREIGN KEY (reservation_id) REFERENCES reservations (id)
);

-- Seed Pricing Rules
INSERT INTO
    pricing_rules (
        rule_key,
        rule_value,
        description
    )
VALUES (
        'weekend_surcharge',
        '1.20',
        'Multiplier pour les réservations Samedi/Dimanche'
    ),
    (
        'delivery_per_km',
        '200',
        'Prix additionnel par KM après le périmètre gratuit'
    ),
    (
        'free_delivery_radius',
        '5',
        'Rayon de livraison gratuite en KM'
    );

-- Seed a Promo Code
INSERT INTO
    promo_codes (
        code,
        discount_percent,
        valid_until
    )
VALUES (
        'WELCOME10',
        10.00,
        '2026-12-31'
    );

-- Default Categories
INSERT INTO
    categories (name, icon)
VALUES ('Bâches', 'fa-tents'),
    ('Chaises', 'fa-chair'),
    ('Tables', 'fa-table'),
    (
        'Décoration',
        'fa-holly-berry'
    ),
    ('Toilettes', 'fa-restroom');

-- Default Super Admin (password: admin123)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO
    users (name, email, password, role)
VALUES (
        'Super Admin',
        'admin@samevent.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'super_admin'
    );

-- Default Staff Accounts
INSERT INTO
    users (name, email, password, role)
VALUES (
        'Mini Admin',
        'mini@samevent.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'mini_admin'
    ),
    (
        'Receptionist',
        'reception@samevent.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'receptionist'
    );

-- Default pricing settings
INSERT INTO
    settings (
        setting_key,
        setting_value,
        description
    )
VALUES (
        'delivery_fee',
        '5000',
        'Frais de livraison par défaut'
    ),
    (
        'tax_rate',
        '0',
        'Taux de taxe (%)'
    ),
    (
        'currency',
        'FCFA',
        'Devise locale'
    );

-- Initial Items with Category IDs
INSERT INTO
    items (
        category_id,
        name,
        price_per_day,
        quantity_total
    )
VALUES (
        1,
        'Bâche standard 5x5',
        5000,
        10
    ),
    (
        1,
        'Bâche luxe 10x10',
        15000,
        5
    ),
    (2, 'Chaise simple', 100, 500),
    (
        2,
        'Chaise VIP avec housse',
        500,
        200
    ),
    (3, 'Petite Table', 1000, 50),
    (
        5,
        'Toilettes Mobiles',
        25000,
        2
    );