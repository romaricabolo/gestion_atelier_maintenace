CREATE DATABASE IF NOT EXISTS atelier_maintenance;
USE atelier_maintenance;

-- Table des clients
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    adresse TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des appareils
CREATE TABLE appareils (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    marque VARCHAR(50),
    modele VARCHAR(100),
    numero_serie VARCHAR(100),
    accessoires TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Table des techniciens
CREATE TABLE techniciens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    specialite VARCHAR(100),
    actif BOOLEAN DEFAULT TRUE
);

-- Table des réparations
CREATE TABLE reparations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appareil_id INT NOT NULL,
    technicien_id INT,
    date_depot DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_fin_reparation DATETIME,
    description_panne TEXT,
    diagnostic_technique TEXT,
    statut ENUM('en_attente', 'diagnostic', 'pieces_commandees', 'en_reparation', 'termine', 'restitue') DEFAULT 'en_attente',
    priorite ENUM('basse', 'normale', 'urgente') DEFAULT 'normale',
    cout_pieces DECIMAL(10,2) DEFAULT 0,
    cout_main_oeuvre DECIMAL(10,2) DEFAULT 0,
    cout_total DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (appareil_id) REFERENCES appareils(id) ON DELETE CASCADE,
    FOREIGN KEY (technicien_id) REFERENCES techniciens(id) ON DELETE SET NULL
);

-- Table des pièces détachées
CREATE TABLE pieces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(100) UNIQUE NOT NULL,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    prix_achat DECIMAL(10,2),
    prix_vente DECIMAL(10,2),
    quantite_stock INT DEFAULT 0,
    seuil_alerte INT DEFAULT 5,
    fournisseur VARCHAR(100)
);

-- Table d'utilisation des pièces
CREATE TABLE utilisation_pieces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reparation_id INT NOT NULL,
    piece_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire_vente DECIMAL(10,2),
    FOREIGN KEY (reparation_id) REFERENCES reparations(id) ON DELETE CASCADE,
    FOREIGN KEY (piece_id) REFERENCES pieces(id)
);

-- Insertion des techniciens par défaut
INSERT INTO techniciens (nom, specialite) VALUES 
('Jean Dupont', 'Électronique générale'),
('Marie Martin', 'Smartphones & Tablettes'),
('Ahmed Benali', 'Informatique');

-- Insertion de quelques clients de démonstration
INSERT INTO clients (nom, telephone, email) VALUES 
('Sophie Laurent', '0612345678', 'sophie@email.com'),
('Thomas Bernard', '0623456789', 'thomas@email.com'),
('Julie Petit', '0634567890', 'julie@email.com');

-- Insertion de quelques appareils
INSERT INTO appareils (client_id, type, marque, modele) VALUES 
(1, 'Smartphone', 'Apple', 'iPhone 12'),
(2, 'Laptop', 'Dell', 'XPS 15'),
(3, 'TV', 'Samsung', 'QLED 55"');

-- Insertion de quelques pièces
INSERT INTO pieces (reference, nom, prix_achat, prix_vente, quantite_stock) VALUES 
('BAT-IP12', 'Batterie iPhone 12', 25.00, 49.00, 8),
('ECR-DELL', 'Écran Dell XPS 15', 120.00, 199.00, 3),
('ALIM-SAMS', 'Alimentation TV Samsung', 35.00, 69.00, 5);