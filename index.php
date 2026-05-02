<?php
    // Configuration de la base de données
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'atelier_maintenance');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // Créer la base de données si elle n'existe pas
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $pdo->exec("USE " . DB_NAME);
        
        // Créer les tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clients (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                telephone VARCHAR(20) NOT NULL,
                email VARCHAR(100),
                adresse TEXT,
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS appareils (
                id INT PRIMARY KEY AUTO_INCREMENT,
                client_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                marque VARCHAR(50),
                modele VARCHAR(100),
                numero_serie VARCHAR(100),
                accessoires TEXT,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS techniciens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                specialite VARCHAR(100),
                actif BOOLEAN DEFAULT TRUE
            );
            
            CREATE TABLE IF NOT EXISTS reparations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                appareil_id INT NOT NULL,
                technicien_id INT,
                date_depot DATETIME DEFAULT CURRENT_TIMESTAMP,
                date_fin_reparation DATETIME,
                description_panne TEXT,
                diagnostic_technique TEXT,
                statut ENUM('en_attente', 'diagnostic', 'pieces_commandees', 'en_reparation', 'termine', 'restitue') DEFAULT 'en_attente',
                priorite ENUM('basse', 'normale', 'urgente') DEFAULT 'normale',
                cout_total DECIMAL(10,2) DEFAULT 0,
                FOREIGN KEY (appareil_id) REFERENCES appareils(id) ON DELETE CASCADE,
                FOREIGN KEY (technicien_id) REFERENCES techniciens(id) ON DELETE SET NULL
            );
            
            CREATE TABLE IF NOT EXISTS pieces (
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
            
            CREATE TABLE IF NOT EXISTS utilisation_pieces (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reparation_id INT NOT NULL,
                piece_id INT NOT NULL,
                quantite INT NOT NULL,
                prix_unitaire_vente DECIMAL(10,2),
                FOREIGN KEY (reparation_id) REFERENCES reparations(id) ON DELETE CASCADE,
                FOREIGN KEY (piece_id) REFERENCES pieces(id)
            );
        ");
        
        // Insérer des données de démonstration
        $stmt = $pdo->query("SELECT COUNT(*) FROM techniciens");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("
                INSERT INTO techniciens (nom, specialite) VALUES 
                ('Jean Dupont', 'Électronique générale'),
                ('Marie Martin', 'Smartphones & Tablettes'),
                ('Ahmed Benali', 'Informatique');
                
                INSERT INTO clients (nom, telephone, email) VALUES 
                ('Sophie Laurent', '0612345678', 'sophie@email.com'),
                ('Thomas Bernard', '0623456789', 'thomas@email.com'),
                ('Julie Petit', '0634567890', 'julie@email.com');
                
                INSERT INTO appareils (client_id, type, marque, modele) VALUES 
                (1, 'Smartphone', 'Apple', 'iPhone 12'),
                (2, 'Laptop', 'Dell', 'XPS 15'),
                (3, 'TV', 'Samsung', 'QLED 55');
                
                INSERT INTO reparations (appareil_id, description_panne, statut, priorite) VALUES 
                (1, 'Écran cassé', 'en_reparation', 'urgente'),
                (2, 'Ne démarre pas', 'diagnostic', 'normale'),
                (3, 'Plus de son', 'en_attente', 'basse');
                
                INSERT INTO pieces (reference, nom, prix_achat, prix_vente, quantite_stock) VALUES 
                ('BAT-IP12', 'Batterie iPhone 12', 25.00, 49.00, 8),
                ('ECR-DELL', 'Écran Dell XPS 15', 120.00, 199.00, 3),
                ('ALIM-SAMS', 'Alimentation TV Samsung', 35.00, 69.00, 5);
            ");
        }
        
    } catch(PDOException $e) {
        die("Erreur base de données: " . $e->getMessage());
    }

    // Gestion des requêtes API
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $api = $_GET['api'];
            
            // API Dashboard
            if ($api == 'dashboard') {
                $stats = array();
                $stmt = $conn->query("SELECT COUNT(*) as total FROM reparations WHERE statut NOT IN ('termine', 'restitue')");
                $stats['reparations_cours'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COUNT(*) as total FROM reparations WHERE MONTH(date_depot) = MONTH(CURRENT_DATE()) AND statut = 'termine'");
                $stats['reparations_terminees'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COALESCE(SUM(cout_total), 0) as total FROM reparations WHERE MONTH(date_depot) = MONTH(CURRENT_DATE()) AND statut = 'termine'");
                $stats['ca_mois'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COUNT(*) as total FROM clients");
                $stats['total_clients'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("
                    SELECT r.*, c.nom as client_nom, a.type as appareil_type, a.marque, a.modele 
                    FROM reparations r
                    JOIN appareils a ON r.appareil_id = a.id
                    JOIN clients c ON a.client_id = c.id
                    ORDER BY r.date_depot DESC LIMIT 5
                ");
                $stats['dernieres_reparations'] = $stmt->fetchAll();
                
                echo json_encode($stats);
            }
            // API Clients
            elseif ($api == 'clients') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $stmt = $conn->query("SELECT * FROM clients ORDER BY date_creation DESC");
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("INSERT INTO clients (nom, telephone, email, adresse) VALUES (?, ?, ?, ?)");
                    $adresse = isset($data['adresse']) ? $data['adresse'] : null;
                    $stmt->execute(array($data['nom'], $data['telephone'], $data['email'], $adresse));
                    echo json_encode(array('success' => true, 'id' => $conn->lastInsertId()));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("UPDATE clients SET nom=?, telephone=?, email=?, adresse=? WHERE id=?");
                    $stmt->execute(array($data['nom'], $data['telephone'], $data['email'], $data['adresse'], $data['id']));
                    echo json_encode(array('success' => true));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
                    $stmt->execute(array($_GET['id']));
                    echo json_encode(array('success' => true));
                }
                // Export Clients

            }
            // API Réparations
            elseif ($api == 'reparations') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("
                            SELECT r.*, c.nom as client_nom, a.type as appareil_type, a.marque, a.modele, a.client_id
                            FROM reparations r
                            JOIN appareils a ON r.appareil_id = a.id
                            JOIN clients c ON a.client_id = c.id
                            WHERE r.id = ?
                        ");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $query = "
                            SELECT r.*, c.nom as client_nom, a.type as appareil_type, a.marque, a.modele, t.nom as technicien_nom
                            FROM reparations r
                            JOIN appareils a ON r.appareil_id = a.id
                            JOIN clients c ON a.client_id = c.id
                            LEFT JOIN techniciens t ON r.technicien_id = t.id
                            ORDER BY r.date_depot DESC
                        ";
                        $stmt = $conn->query($query);
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    
                    // Créer l'appareil
                    $stmt = $conn->prepare("INSERT INTO appareils (client_id, type, marque, modele) VALUES (?, ?, ?, ?)");
                    $stmt->execute(array($data['client_id'], $data['type'], $data['marque'], $data['modele']));
                    $appareil_id = $conn->lastInsertId();
                    
                    // Créer la réparation
                    $stmt = $conn->prepare("INSERT INTO reparations (appareil_id, description_panne, priorite) VALUES (?, ?, ?)");
                    $stmt->execute(array($appareil_id, $data['description_panne'], $data['priorite']));
                    
                    echo json_encode(array('success' => true, 'id' => $conn->lastInsertId()));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("
                        UPDATE reparations 
                        SET statut=?, priorite=?, description_panne=?, diagnostic_technique=?, cout_total=?
                        WHERE id=?
                    ");
                    $stmt->execute(array($data['statut'], $data['priorite'], $data['description_panne'], $data['diagnostic_technique'], $data['cout_total'], $data['id']));
                    echo json_encode(array('success' => true));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM reparations WHERE id = ?");
                    $stmt->execute(array($_GET['id']));
                    echo json_encode(array('success' => true));
                }
            }
            // API Pièces
            elseif ($api == 'pieces') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("SELECT * FROM pieces WHERE id = ?");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $stmt = $conn->query("SELECT * FROM pieces ORDER BY nom");
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("INSERT INTO pieces (reference, nom, description, prix_achat, prix_vente, quantite_stock, seuil_alerte, fournisseur) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $description = isset($data['description']) ? $data['description'] : null;
                    $fournisseur = isset($data['fournisseur']) ? $data['fournisseur'] : null;
                    $stmt->execute(array($data['reference'], $data['nom'], $description, $data['prix_achat'], $data['prix_vente'], $data['quantite_stock'], $data['seuil_alerte'], $fournisseur));
                    echo json_encode(array('success' => true, 'id' => $conn->lastInsertId()));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("UPDATE pieces SET reference=?, nom=?, description=?, prix_achat=?, prix_vente=?, quantite_stock=?, seuil_alerte=?, fournisseur=? WHERE id=?");
                    $description = isset($data['description']) ? $data['description'] : null;
                    $fournisseur = isset($data['fournisseur']) ? $data['fournisseur'] : null;
                    $stmt->execute(array($data['reference'], $data['nom'], $description, $data['prix_achat'], $data['prix_vente'], $data['quantite_stock'], $data['seuil_alerte'], $fournisseur, $data['id']));
                    echo json_encode(array('success' => true));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM pieces WHERE id = ?");
                    $stmt->execute(array($_GET['id']));
                    echo json_encode(array('success' => true));
                }
            }
            // API Statistiques
            elseif ($api == 'statistiques') {
                $data = array();
                
                $stmt = $conn->query("
                    SELECT DATE_FORMAT(date_depot, '%Y-%m') as mois, COUNT(*) as total,
                        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as terminees
                    FROM reparations 
                    WHERE date_depot >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(date_depot, '%Y-%m')
                    ORDER BY mois ASC
                ");
                $data['reparations_mois'] = $stmt->fetchAll();
                
                $stmt = $conn->query("
                    SELECT DATE_FORMAT(date_depot, '%Y-%m') as mois, COALESCE(SUM(cout_total), 0) as ca
                    FROM reparations 
                    WHERE statut = 'termine' AND date_depot >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(date_depot, '%Y-%m')
                    ORDER BY mois ASC
                ");
                $data['ca_mois'] = $stmt->fetchAll();
                
                echo json_encode($data);
            }
            
        } catch(Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier Maintenance Électronique</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ========== STYLES RESPONSIVES ========== */
        .theme-toggle {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--border);
        }

        /* Mode sombre actif */
        body.dark-mode {
            --bg-primary: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
        }

        body.dark-mode .stat-card,
        body.dark-mode .chart-card,
        body.dark-mode .table-container,
        body.dark-mode .main-header {
            background: var(--bg-card);
            color: var(--text-primary);
        }

        body.dark-mode input,
        body.dark-mode select,
        body.dark-mode textarea {
            background: #334155;
            color: white;
            border-color: #475569;
        }
       
        body.dark-mode .badge-en_attente { background: #713f12; color: #fde68a; }
        body.dark-mode .badge-diagnostic { background: #1e3a5f; color: #93c5fd; }
        body.dark-mode .badge-en_reparation { background: #7c2d12; color: #fdba74; }
        body.dark-mode .badge-termine { background: #064e3b; color: #6ee7b7; }
        body.dark-mode .badge-urgente { background: #7f1d1d; color: #fecaca; }
        /* Variables pour thème sombre/clair */
        :root {
            --bg-primary: #f1f5f9;
            --bg-card: white;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --sidebar-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --nav-active: linear-gradient(135deg, #3b82f6, #2563eb);
            --shadow: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-hover: 0 10px 30px rgba(0,0,0,0.1);
            --border: #e2e8f0;
        }

        /* Mode sombre automatique selon préférence système */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: #0f172a;
                --bg-card: #1e293b;
                --text-primary: #f1f5f9;
                --text-secondary: #94a3b8;
                --sidebar-bg: linear-gradient(135deg, #020617 0%, #0f172a 100%);
                --border: #334155;
            }
        }

        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }

        /* Layout responsive */
        .app {
            display: flex;
            min-height: 100vh;
        }

        /* Bouton menu mobile */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--nav-active);
            border: none;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }

        .mobile-menu-btn:hover {
            transform: scale(1.05);
        }

        /* Overlay pour fermer le menu mobile */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Sidebar responsive */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 999;
        }

        /* Sidebar sur mobile */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 85%;
                max-width: 300px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 5px 0 20px rgba(0,0,0,0.3);
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .main-content {
                margin-left: 0;
                padding: 70px 15px 20px 15px !important;
            }
            
            .main-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
                padding: 15px !important;
            }
            
            .page-title {
                font-size: 20px !important;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .charts-grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 30px;
            transition: all 0.3s ease;
        }

        /* Header */
        .main-header {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            background: linear-gradient(135deg, #0f172a, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .date-display {
            color: var(--text-secondary);
            font-size: 20px;
            font-weight:bold;
        }

        /* Stats grid responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stat-info h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: var(--nav-active);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }

        /* Charts responsive */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 900px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .chart-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        @media (max-width: 600px) {
            .chart-container {
                height: 250px;
            }
        }

        /* Tables responsives */
        .table-container {
            background: var(--bg-card);
            border-radius: 20px;
            overflow-x: auto;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        @media (max-width: 600px) {
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-header h2 {
                font-size: 16px;
            }
        }

        .table-header h2 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        th, td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--bg-primary);
            font-weight: 600;
            color: var(--text-secondary);
        }

        tr:hover {
            background: var(--bg-primary);
        }

        @media (max-width: 600px) {
            th, td {
                padding: 10px 12px;
                font-size: 13px;
            }
        }

        /* Boutons responsives */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--nav-active);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,130,246,0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            .btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .btn-sm {
                padding: 5px 10px;
                font-size: 11px;
            }
        }

        /* Search bar responsive */
        .search-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-bar input {
            width: 250px;
        }

        @media (max-width: 600px) {
            .search-bar {
                width: 100%;
            }
            
            .search-bar input {
                width: 100%;
            }
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-en_attente { background: #fef3c7; color: #d97706; }
        .badge-diagnostic { background: #dbeafe; color: #2563eb; }
        .badge-en_reparation { background: #fed7aa; color: #ea580c; }
        .badge-termine { background: #d1fae5; color: #059669; }
        .badge-urgente { background: #fee2e2; color: #dc2626; }
        .badge-normale { background: #dbeafe; color: #2563eb; }
        .badge-basse { background: #d1fae5; color: #059669; }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 600px) {
            .action-buttons {
                gap: 5px;
            }
        }

        /* Modal responsive */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @media (max-width: 600px) {
            .modal-content {
                width: 95%;
                margin: 20px;
            }
            
            .modal-header h3 {
                font-size: 18px;
            }
            
            .modal-body {
                padding: 20px;
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Form responsive */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Notification responsive */
        .notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1100;
            animation: slideIn 0.3s ease;
            color: var(--text-primary);
        }

        @media (max-width: 600px) {
            .notification {
                bottom: 20px;
                right: 20px;
                left: 20px;
                padding: 12px 20px;
            }
        }

        .notification.success { border-left: 4px solid #10b981; }
        .notification.error { border-left: 4px solid #ef4444; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Sidebar navigation */
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h1 {
            font-size: 22px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 24px;
            margin: 4px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            font-size: 18px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: var(--nav-active);
        }

        /* Mode sombre pour les badges */
        @media (prefers-color-scheme: dark) {
            .badge-en_attente { background: #713f12; color: #fde68a; }
            .badge-diagnostic { background: #1e3a5f; color: #93c5fd; }
            .badge-en_reparation { background: #7c2d12; color: #fdba74; }
            .badge-termine { background: #064e3b; color: #6ee7b7; }
            .badge-urgente { background: #7f1d1d; color: #fecaca; }
            .badge-normale { background: #1e3a5f; color: #93c5fd; }
            .badge-basse { background: #064e3b; color: #6ee7b7; }
        }

        /* Scrollbar personnalisée */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-secondary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #3b82f6;
        }

        /* Impressions */
        @media print {
            .sidebar, .mobile-menu-btn, .btn, .action-buttons, .modal {
                display: none !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .stat-card, .chart-card {
                break-inside: avoid;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; color: #0f172a; }
        
        /* Layout */
        .app { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h1 { font-size: 22px; }
        .sidebar-header p { font-size: 12px; opacity: 0.7; margin-top: 5px; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item { padding: 12px 24px; margin: 4px 12px; border-radius: 12px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 12px; }
        .nav-item:hover { background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-item.active { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .nav-item i { width: 24px; }
        
        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; padding: 20px 30px; }
        .main-header { background: white; border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title { font-size: 30px; font-weight: bold; background: linear-gradient(135deg, #0f172a, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .date-display { color: #64748b; font-size: 14px; }
        
        /* Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-info h3 { font-size: 14px; color: #64748b; margin-bottom: 8px; }
        .stat-number { font-size: 32px; font-weight: 700; color: #0f172a; }
        .stat-icon { width: 55px; height: 55px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; }
        
        /* Charts */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-title { font-size: 16px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .chart-container { position: relative; height: 300px; }
        
        /* Tables */
        .table-container { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .table-header h2 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 20px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
        tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-en_attente { background: #fef3c7; color: #d97706; }
        .badge-diagnostic { background: #dbeafe; color: #2563eb; }
        .badge-en_reparation { background: #fed7aa; color: #ea580c; }
        .badge-termine { background: #d1fae5; color: #059669; }
        .badge-urgente { background: #fee2e2; color: #dc2626; }
        .badge-normale { background: #dbeafe; color: #2563eb; }
        .badge-basse { background: #d1fae5; color: #059669; }
        
        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 12px; cursor: pointer; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59,130,246,0.3); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 24px; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        
        /* Form */
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #334155; }
        input, select, textarea { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; font-family: inherit; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #3b82f6; }
        
        /* Search */
        .search-bar { display: flex; gap: 10px; }
        .search-bar input { width: 250px; }
        
        /* Loading */
        .loading { display: flex; justify-content: center; align-items: center; padding: 50px; }
        .spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Notification */
        .notification { position: fixed; bottom: 30px; right: 30px; padding: 15px 25px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; z-index: 1100; animation: slideIn 0.3s ease; }
        .notification.success { border-left: 4px solid #10b981; }
        .notification.error { border-left: 4px solid #ef4444; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .action-buttons { display: flex; gap: 8px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } .charts-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>🔧 Atelier</h1>
            <p>Maintenance Électronique</p>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item active" data-page="dashboard"><i class="fas fa-chart-line"></i><span>Tableau de bord</span></div>
            <div class="nav-item" data-page="clients"><i class="fas fa-users"></i><span>Clients</span></div>
            <div class="nav-item" data-page="reparations"><i class="fas fa-tools"></i><span>Réparations</span></div>
            <div class="nav-item" data-page="pieces"><i class="fas fa-microchip"></i><span>Pièces</span></div>
        </nav>
    </aside>
    
    <main class="main-content">
        <header class="main-header">
            <h1 class="page-title" id="pageTitle">Tableau de bord</h1>
            <div class="date-display" id="currentDate"></div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>
        <div id="pageContent"><div class="loading"><div class="spinner"></div></div></div>
    </main>
</div>

    <script src="./js/function.js"></script>
</body>
</html>