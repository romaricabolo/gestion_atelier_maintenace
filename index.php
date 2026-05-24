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
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $pdo->exec("USE " . DB_NAME);
        
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
                description_panne TEXT,
                diagnostic_technique TEXT,
                statut ENUM('en_attente', 'diagnostic', 'en_reparation', 'termine') DEFAULT 'en_attente',
                priorite ENUM('basse', 'normale', 'urgente') DEFAULT 'normale',
                cout_total DECIMAL(10,2) DEFAULT 0,
                FOREIGN KEY (appareil_id) REFERENCES appareils(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS pieces (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reference VARCHAR(100) UNIQUE NOT NULL,
                nom VARCHAR(200) NOT NULL,
                prix_achat DECIMAL(10,2),
                prix_vente DECIMAL(10,2),
                quantite_stock INT DEFAULT 0,
                seuil_alerte INT DEFAULT 5,
                fournisseur VARCHAR(100)
            );
        ");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM techniciens");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("
                INSERT INTO techniciens (nom, specialite) VALUES 
                ('Jean Dupont', 'Électronique'),
                ('Marie Martin', 'Smartphones');
                
                INSERT INTO clients (nom, telephone, email, adresse) VALUES 
                ('Sophie Laurent', '612345678', 'sophie@email.com', 'Douala'),
                ('Thomas Bernard', '623456789', 'thomas@email.com', 'Yaoundé'),
                ('Julie Petit', '634567890', 'julie@email.com', 'Douala');
                
                INSERT INTO appareils (client_id, type, marque, modele) VALUES 
                (1, 'Smartphone', 'Apple', 'iPhone 12'),
                (2, 'Laptop', 'Dell', 'XPS 15'),
                (3, 'TV', 'Samsung', 'QLED 55');
                
                INSERT INTO reparations (appareil_id, description_panne, statut, priorite, cout_total) VALUES 
                (1, 'Écran cassé', 'en_reparation', 'urgente', 35000),
                (2, 'Ne démarre pas', 'diagnostic', 'normale', 0),
                (3, 'Plus de son', 'termine', 'basse', 25000);
                
                INSERT INTO pieces (reference, nom, prix_achat, prix_vente, quantite_stock, seuil_alerte) VALUES 
                ('BAT-001', 'Batterie iPhone', 15000, 30000, 8, 5),
                ('ECR-001', 'Écran LCD', 75000, 125000, 3, 5),
                ('CHG-001', 'Chargeur USB', 5000, 10000, 15, 5);
            ");
        }
        
    } catch(PDOException $e) {
        die("Erreur: " . $e->getMessage());
    }

    // Gestion des requêtes API
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $api = $_GET['api'];
            
            // Dashboard
            if ($api == 'dashboard') {
                $stats = array();
                $stmt = $conn->query("SELECT COUNT(*) as total FROM reparations WHERE statut NOT IN ('termine')");
                $stats['reparations_cours'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COUNT(*) as total FROM reparations WHERE MONTH(date_depot) = MONTH(CURRENT_DATE()) AND statut = 'termine'");
                $stats['reparations_terminees'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COALESCE(SUM(cout_total), 0) as total FROM reparations WHERE MONTH(date_depot) = MONTH(CURRENT_DATE()) AND statut = 'termine'");
                $stats['ca_mois'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("SELECT COUNT(*) as total FROM clients");
                $stats['total_clients'] = $stmt->fetch()['total'];
                
                $stmt = $conn->query("
                    SELECT statut, COUNT(*) as count FROM reparations GROUP BY statut
                ");
                $stats['stats_par_statut'] = $stmt->fetchAll();
                
                $stmt = $conn->query("
                    SELECT priorite, COUNT(*) as count FROM reparations GROUP BY priorite
                ");
                $stats['stats_par_priorite'] = $stmt->fetchAll();
                
                $stmt = $conn->query("
                    SELECT DATE_FORMAT(date_depot, '%b') as mois, COUNT(*) as total,
                        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as terminees
                    FROM reparations 
                    WHERE date_depot >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                    GROUP BY MONTH(date_depot)
                    ORDER BY date_depot ASC
                ");
                $stats['evolution_6_mois'] = $stmt->fetchAll();
                
                $stmt = $conn->query("
                    SELECT r.*, c.nom as client_nom, a.type as appareil_type 
                    FROM reparations r
                    JOIN appareils a ON r.appareil_id = a.id
                    JOIN clients c ON a.client_id = c.id
                    ORDER BY r.date_depot DESC LIMIT 5
                ");
                $stats['dernieres_reparations'] = $stmt->fetchAll();
                
                echo json_encode($stats);
            }
            // Clients
            elseif ($api == 'clients') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $stmt = $conn->query("SELECT id, nom, telephone, email, adresse, date_creation FROM clients ORDER BY date_creation DESC");
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("INSERT INTO clients (nom, telephone, email, adresse) VALUES (?, ?, ?, ?)");
                    $adresse = isset($data['adresse']) ? $data['adresse'] : '';
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
            }
            // Réparations
            elseif ($api == 'reparations') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("
                            SELECT r.*, c.nom as client_nom, a.type as appareil_type, a.marque, a.modele
                            FROM reparations r
                            JOIN appareils a ON r.appareil_id = a.id
                            JOIN clients c ON a.client_id = c.id
                            WHERE r.id = ?
                        ");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $sql = "
                            SELECT r.id, r.date_depot, r.description_panne, r.statut, r.priorite, r.cout_total,
                                c.nom as client_nom, a.type as appareil_type, a.marque, a.modele
                            FROM reparations r
                            JOIN appareils a ON r.appareil_id = a.id
                            JOIN clients c ON a.client_id = c.id
                            ORDER BY r.date_depot DESC
                        ";
                        $stmt = $conn->query($sql);
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("INSERT INTO appareils (client_id, type, marque, modele) VALUES (?, ?, ?, ?)");
                    $stmt->execute(array($data['client_id'], $data['type'], $data['marque'], $data['modele']));
                    $appareil_id = $conn->lastInsertId();
                    
                    $stmt = $conn->prepare("INSERT INTO reparations (appareil_id, description_panne, priorite) VALUES (?, ?, ?)");
                    $stmt->execute(array($appareil_id, $data['description_panne'], $data['priorite']));
                    echo json_encode(array('success' => true, 'id' => $conn->lastInsertId()));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("UPDATE reparations SET statut=?, priorite=?, description_panne=?, diagnostic_technique=?, cout_total=? WHERE id=?");
                    $stmt->execute(array($data['statut'], $data['priorite'], $data['description_panne'], $data['diagnostic_technique'], $data['cout_total'], $data['id']));
                    echo json_encode(array('success' => true));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM reparations WHERE id = ?");
                    $stmt->execute(array($_GET['id']));
                    echo json_encode(array('success' => true));
                }
            }
            // Pièces
            elseif ($api == 'pieces') {
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    if (isset($_GET['id'])) {
                        $stmt = $conn->prepare("SELECT * FROM pieces WHERE id = ?");
                        $stmt->execute(array($_GET['id']));
                        echo json_encode($stmt->fetch());
                    } else {
                        $stmt = $conn->query("SELECT id, reference, nom, prix_achat, prix_vente, quantite_stock, seuil_alerte FROM pieces ORDER BY nom");
                        echo json_encode($stmt->fetchAll());
                    }
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("INSERT INTO pieces (reference, nom, prix_achat, prix_vente, quantite_stock, seuil_alerte) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute(array($data['reference'], $data['nom'], $data['prix_achat'], $data['prix_vente'], $data['quantite_stock'], $data['seuil_alerte']));
                    echo json_encode(array('success' => true, 'id' => $conn->lastInsertId()));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $stmt = $conn->prepare("UPDATE pieces SET reference=?, nom=?, prix_achat=?, prix_vente=?, quantite_stock=?, seuil_alerte=? WHERE id=?");
                    $stmt->execute(array($data['reference'], $data['nom'], $data['prix_achat'], $data['prix_vente'], $data['quantite_stock'], $data['seuil_alerte'], $data['id']));
                    echo json_encode(array('success' => true));
                }
                elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
                    $stmt = $conn->prepare("DELETE FROM pieces WHERE id = ?");
                    $stmt->execute(array($_GET['id']));
                    echo json_encode(array('success' => true));
                }
            }
            // Export CSV
            elseif ($api == 'export_clients') {
                $stmt = $conn->query("SELECT id, nom, telephone, email, adresse, DATE_FORMAT(date_creation, '%d/%m/%Y') as date_inscription FROM clients ORDER BY nom");
                $clients = $stmt->fetchAll();
                
                $output = "ID;Nom;Téléphone;Email;Adresse;Date d'inscription\n";
                foreach ($clients as $c) {
                    $output .= $c['id'] . ";" . $c['nom'] . ";" . $c['telephone'] . ";" . $c['email'] . ";" . str_replace(';', ',', $c['adresse']) . ";" . $c['date_inscription'] . "\n";
                }
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');
                echo "\xEF\xBB\xBF" . $output;
                exit;
            }
            elseif ($api == 'export_reparations') {
                $stmt = $conn->query("
                    SELECT r.id, c.nom as client, a.type as appareil, r.description_panne, r.statut, r.priorite, 
                        DATE_FORMAT(r.date_depot, '%d/%m/%Y') as date_depot, r.cout_total
                    FROM reparations r
                    JOIN appareils a ON r.appareil_id = a.id
                    JOIN clients c ON a.client_id = c.id
                    ORDER BY r.date_depot DESC
                ");
                $reparations = $stmt->fetchAll();
                
                $output = "ID;Client;Appareil;Panne;Statut;Priorité;Date;Coût (FCFA)\n";
                foreach ($reparations as $r) {
                    $output .= $r['id'] . ";" . $r['client'] . ";" . $r['appareil'] . ";" . 
                            str_replace(';', ',', $r['description_panne']) . ";" . 
                            $r['statut'] . ";" . $r['priorite'] . ";" . 
                            $r['date_depot'] . ";" . number_format($r['cout_total'], 0, ',', ' ') . "\n";
                }
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="reparations_' . date('Y-m-d') . '.csv"');
                echo "\xEF\xBB\xBF" . $output;
                exit;
            }
            elseif ($api == 'export_pieces') {
                $stmt = $conn->query("SELECT reference, nom, prix_achat, prix_vente, quantite_stock FROM pieces ORDER BY nom");
                $pieces = $stmt->fetchAll();
                
                $output = "Référence;Nom;Prix achat (FCFA);Prix vente (FCFA);Stock\n";
                foreach ($pieces as $p) {
                    $output .= $p['reference'] . ";" . $p['nom'] . ";" . 
                            number_format($p['prix_achat'], 0, ',', ' ') . ";" . 
                            number_format($p['prix_vente'], 0, ',', ' ') . ";" . 
                            $p['quantite_stock'] . "\n";
                }
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="pieces_' . date('Y-m-d') . '.csv"');
                echo "\xEF\xBB\xBF" . $output;
                exit;
            }
            // Impression reçu client (HTML)
            elseif ($api == 'print_receipt') {
                $id = isset($_GET['id']) ? $_GET['id'] : 0;
                
                $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute(array($id));
                $client = $stmt->fetch();
                
                if (!$client) {
                    die("Client non trouvé");
                }
                
                $stmt = $conn->prepare("
                    SELECT r.*, a.type, a.marque, a.modele
                    FROM reparations r
                    JOIN appareils a ON r.appareil_id = a.id
                    WHERE a.client_id = ?
                    ORDER BY r.date_depot DESC
                    LIMIT 5
                ");
                $stmt->execute(array($id));
                $reparations = $stmt->fetchAll();
                
                $total = 0;
                foreach($reparations as $r) $total += $r['cout_total'];
                
                // Ne pas inclure de code HTML ici, juste envoyer la page
                header('Content-Type: text/html; charset=utf-8');
                ?>
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <title>Reçu - <?php echo htmlspecialchars($client['nom']); ?></title>
                <link rel="stylesheet" href="./css/style.css">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
            </head>
            <body>
                <div class="container">
                    <div class="btn-group">
                        <button class="btn btn-print" onclick="window.print()">🖨️ Imprimer</button>
                        <button class="btn btn-pdf" onclick="generatePDF()">📄 Exporter PDF</button>
                    </div>
                    <div class="receipt" id="receiptContent">
                        <div class="header">
                            <h1>🔧 ROMAIN ROLAND ELECTRONIQUE</h1>
                            <p>Votre expert en maintenance informatique</p>                          
                            <div class="company-info">
                                <span>📞 +237 679 174 413</span>
                                <span>✉ romainroland@gmail.com</span>
                                <span>📍 Sangmelima, Cameroun</span>
                            </div>
                        </div>
                        
                        <div class="body">
                            <div class="info-client">
                                <div id="currentDate" style="color: rgb(52, 45, 45); font-size: 0.8rem; font-style: italic; font-weight:bold; margin-top: 12px; position:absolute; padding-left:43%;"></div>
                                <h3>📋 INFORMATIONS CLIENT</h3>                                
                                <p><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom']); ?></p>
                                <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['telephone']); ?></p>
                                <p><strong>Email :</strong> <?php echo htmlspecialchars($client['email'] ? $client['email'] : 'Non renseigné'); ?></p>
                                <p><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse'] ? $client['adresse'] : 'Non renseignée'); ?></p>
                                <p><strong>Client depuis :</strong> <?php echo date('d/m/Y', strtotime($client['date_creation'])); ?></p>
                            </div>
                            
                            <div class="stats">
                                <div class="stat"><div class="number"><?php echo count($reparations); ?></div><div class="label">Réparations</div></div>
                                <div class="stat"><div class="number"><?php echo number_format($total, 0, ',', ' '); ?></div><div class="label">Total dépensé (FCFA)</div></div>
                            </div>
                            
                            <h3 style="margin-bottom: 10px;">📱 HISTORIQUE DES RÉPARATIONS</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Appareil</th>
                                        <th>Panne</th>
                                        <th>Statut</th>
                                        <th>Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($reparations as $r): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($r['date_depot'])); ?></td>
                                        <td><?php echo htmlspecialchars($r['type'] . ' ' . $r['marque']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($r['description_panne'], 0, 40)); ?></td>
                                        <td><span class="badge badge-<?php echo $r['statut']; ?>"><?php echo $r['statut']; ?></span></td>
                                        <td><?php echo number_format($r['cout_total'], 0, ',', ' '); ?> FCFA</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if ($total > 0): ?>
                            <div class="total">
                                Total général : <?php echo number_format($total, 0, ',', ' '); ?> FCFA
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="footer">
                            <p>Garantie 1 mois sur toutes nos réparations</p>
                            <p>Merci de votre confiance !</p>
                            <p>Ce document fait foi</p>
                        </div>
                    </div>
                </div>
                <script>
                    function generatePDF() {
                        var element = document.getElementById('receiptContent');
                        var opt = {
                            margin: [0.5, 0.5, 0.5, 0.5],
                            filename: 'recu_<?php echo $client['nom']; ?>.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, letterRendering: true },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                        };
                        html2pdf().set(opt).from(element).save();
                    }
                    document.addEventListener('DOMContentLoaded', function() {
                        var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        document.getElementById('currentDate').innerHTML = new Date().toLocaleDateString('fr-FR', options);
                        loadPage('dashboard');
                        
                        var navItems = document.querySelectorAll('.nav-item');
                        for (var i = 0; i < navItems.length; i++) {
                            navItems[i].addEventListener('click', function() {
                                var navs = document.querySelectorAll('.nav-item');
                                for (var j = 0; j < navs.length; j++) navs[j].classList.remove('active');
                                this.classList.add('active');
                                loadPage(this.getAttribute('data-page'));
                                if (window.innerWidth <= 768) closeMobileMenu();
                            });
                        }
                    });
                </script>
            </body>
            </html>
            <?php
            exit;
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
        <link rel="stylesheet" href="./css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    </head>
    <body>
        <div class="app">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
            <div class="mobile-overlay" onclick="closeMobileMenu()"></div>
            
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h1>🔧 ATELIER</h1>
                    <p>Maintenance Électronique</p>
                </div>
                <nav class="sidebar-nav">
                    <div class="nav-item active" data-page="dashboard"><i class="fas fa-chart-line"></i> Tableau de bord</div>
                    <div class="nav-item" data-page="clients"><i class="fas fa-users"></i> Clients</div>
                    <div class="nav-item" data-page="reparations"><i class="fas fa-tools"></i> Réparations</div>
                    <div class="nav-item" data-page="pieces"><i class="fas fa-microchip"></i> Pièces</div>
                </nav>
            </aside>
            
            <main class="main-content">
                <header class="main-header">
                    <h1 class="page-title" id="pageTitle">Tableau de bord</h1>
                    <div class="date-display" id="currentDate"></div>
                </header>
                <div id="pageContent"><div class="loading"><div class="spinner"></div></div></div>
            </main>
        </div>

        <script>
            
            var currentPage = 'dashboard';

            document.addEventListener('DOMContentLoaded', function() {
                var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                document.getElementById('currentDate').innerHTML = new Date().toLocaleDateString('fr-FR', options);
                loadPage('dashboard');
                
                var navItems = document.querySelectorAll('.nav-item');
                for (var i = 0; i < navItems.length; i++) {
                    navItems[i].addEventListener('click', function() {
                        var navs = document.querySelectorAll('.nav-item');
                        for (var j = 0; j < navs.length; j++) navs[j].classList.remove('active');
                        this.classList.add('active');
                        loadPage(this.getAttribute('data-page'));
                        if (window.innerWidth <= 768) closeMobileMenu();
                    });
                }
            });

            function toggleMobileMenu() {
                document.getElementById('sidebar').classList.toggle('mobile-open');
                document.querySelector('.mobile-overlay').classList.toggle('active');
            }

            function closeMobileMenu() {
                document.getElementById('sidebar').classList.remove('mobile-open');
                document.querySelector('.mobile-overlay').classList.remove('active');
            }

            function loadPage(page) {
                currentPage = page;
                var titles = { dashboard: 'Tableau de bord', clients: 'Clients', reparations: 'Réparations', pieces: 'Pièces' };
                document.getElementById('pageTitle').innerHTML = titles[page];
                document.getElementById('pageContent').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
                
                if (page === 'dashboard') loadDashboard();
                else if (page === 'clients') loadClients();
                else if (page === 'reparations') loadReparations();
                else if (page === 'pieces') loadPieces();
            }

            function loadDashboard() {
                fetch('?api=dashboard')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var html = '<div class="stats-grid">' +
                            '<div class="stat-card"><div class="stat-info"><h3>Réparations en cours</h3><div class="stat-number">' + (data.reparations_cours || 0) + '</div></div><div class="stat-icon"><i class="fas fa-wrench"></i></div></div>' +
                            '<div class="stat-card"><div class="stat-info"><h3>Terminées ce mois</h3><div class="stat-number">' + (data.reparations_terminees || 0) + '</div></div><div class="stat-icon"><i class="fas fa-check"></i></div></div>' +
                            '<div class="stat-card"><div class="stat-info"><h3>Chiffre d\'affaires</h3><div class="stat-number">' + formatPrice(data.ca_mois || 0) + '</div></div><div class="stat-icon"><i class="fas fa-coins"></i></div></div>' +
                            '<div class="stat-card"><div class="stat-info"><h3>Clients</h3><div class="stat-number">' + (data.total_clients || 0) + '</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>' +
                            '</div>' +
                            '<div class="charts-grid">' +
                            '<div class="chart-card"><h3><i class="fas fa-chart-pie"></i> Réparations par statut</h3><div class="chart-container"><canvas id="statutChart"></canvas></div></div>' +
                            '<div class="chart-card"><h3><i class="fas fa-chart-bar"></i> Réparations par priorité</h3><div class="chart-container"><canvas id="prioriteChart"></canvas></div></div>' +
                            '<div class="chart-card"><h3><i class="fas fa-chart-line"></i> Évolution 6 mois</h3><div class="chart-container"><canvas id="evolutionChart"></canvas></div></div>' +
                            '</div>' +
                            '<div class="table-container"><div class="table-header"><h2><i class="fas fa-history"></i> Dernières réparations</h2><button class="btn btn-primary btn-sm" onclick="loadPage(\'reparations\')">Voir tout</button></div>' +
                            '<table><thead><tr><th>Client</th><th>Appareil</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
                        
                        if (data.dernieres_reparations) {
                            for (var i = 0; i < data.dernieres_reparations.length; i++) {
                                var r = data.dernieres_reparations[i];
                                html += '<tr>' +
                                    '<td>' + escapeHtml(r.client_nom) + '</td>' +
                                    '<td>' + escapeHtml(r.appareil_type) + '</td>' +
                                    '<td><span class="badge badge-' + r.statut + '">' + r.statut + '</span></td>' +
                                    '<td>' + formatDate(r.date_depot) + '</td>' +
                                    '<td><button class="btn btn-sm btn-primary" onclick="editReparation(' + r.id + ')"><i class="fas fa-edit"></i></button></td>' +
                                    '</tr>';
                            }
                        }
                        html += '</tbody> </div>';
                        document.getElementById('pageContent').innerHTML = html;
                        
                        setTimeout(function() {
                            if (data.stats_par_statut && data.stats_par_statut.length > 0) {
                                var statutLabels = [], statutCounts = [];
                                for (var i = 0; i < data.stats_par_statut.length; i++) {
                                    statutLabels.push(data.stats_par_statut[i].statut);
                                    statutCounts.push(parseInt(data.stats_par_statut[i].count));
                                }
                                new Chart(document.getElementById('statutChart'), {
                                    type: 'pie',
                                    data: { labels: statutLabels, datasets: [{ data: statutCounts, backgroundColor: ['#f39c12', '#3498db', '#e74c3c', '#2ecc71'] }] },
                                    options: { responsive: true, maintainAspectRatio: true }
                                });
                            }
                            
                            if (data.stats_par_priorite && data.stats_par_priorite.length > 0) {
                                var prioriteLabels = [], prioriteCounts = [];
                                for (var i = 0; i < data.stats_par_priorite.length; i++) {
                                    prioriteLabels.push(data.stats_par_priorite[i].priorite);
                                    prioriteCounts.push(parseInt(data.stats_par_priorite[i].count));
                                }
                                new Chart(document.getElementById('prioriteChart'), {
                                    type: 'bar',
                                    data: { labels: prioriteLabels, datasets: [{ label: 'Nombre', data: prioriteCounts, backgroundColor: ['#55efc4', '#74b9ff', '#ff7675'] }] },
                                    options: { responsive: true, maintainAspectRatio: true }
                                });
                            }
                            
                            if (data.evolution_6_mois && data.evolution_6_mois.length > 0) {
                                var moisLabels = [], totalData = [], termineesData = [];
                                for (var i = 0; i < data.evolution_6_mois.length; i++) {
                                    moisLabels.push(data.evolution_6_mois[i].mois);
                                    totalData.push(parseInt(data.evolution_6_mois[i].total));
                                    termineesData.push(parseInt(data.evolution_6_mois[i].terminees));
                                }
                                new Chart(document.getElementById('evolutionChart'), {
                                    type: 'line',
                                    data: { labels: moisLabels, datasets: [
                                        { label: 'Total', data: totalData, borderColor: '#3b82f6', fill: false },
                                        { label: 'Terminées', data: termineesData, borderColor: '#2ecc71', fill: false }
                                    ]},
                                    options: { responsive: true, maintainAspectRatio: true }
                                });
                            }
                        }, 100);
                    })
                    .catch(function(e) { console.error(e); document.getElementById('pageContent').innerHTML = '<div style="text-align:center;padding:40px">Erreur de chargement</div>'; });
            }

            function loadClients() {
                fetch('?api=clients')
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        var html = '<div style="margin-bottom:15px">' +
                            '<button class="btn btn-primary" onclick="showAddClientModal()"><i class="fas fa-plus"></i> Nouveau client</button>' +
                            '<button class="btn btn-success" onclick="exportClients()" style="margin-left:10px"><i class="fas fa-download"></i> Export CSV</button>' +
                            '</div>' +
                            '<div class="table-container">' +
                            '<div class="table-header"><h2><i class="fas fa-users"></i> Liste des clients</h2><div class="search-bar"><input type="text" id="searchClient" placeholder="Rechercher..." onkeyup="searchClients()"></div></div>' +
                            '<table><thead>' +
                            '<tr><th>Nom</th><th>Téléphone</th><th>Email</th><th>Date inscription</th><th>Actions</th></tr>' +
                            '</thead><tbody id="clientsList">';
                        for (var i = 0; i < clients.length; i++) {
                            var c = clients[i];
                            html += '<tr>' +
                                '<td>' + escapeHtml(c.nom) + '</td>' +
                                '<td>' + escapeHtml(c.telephone) + '</td>' +
                                '<td>' + escapeHtml(c.email || '-') + '</td>' +
                                '<td>' + formatDate(c.date_creation) + '</td>' +
                                '<td class="action-buttons">' +
                                '<button class="btn btn-sm btn-warning" onclick="printReceipt(' + c.id + ')"><i class="fas fa-print"></i> Reçu</button>' +
                                '<button class="btn btn-sm btn-primary" onclick="editClient(' + c.id + ')"><i class="fas fa-edit"></i></button>' +
                                '<button class="btn btn-sm btn-danger" onclick="deleteClient(' + c.id + ')"><i class="fas fa-trash"></i></button>' +
                                '</td></tr>';
                        }
                        html += '</tbody> </div>';
                        document.getElementById('pageContent').innerHTML = html;
                    });
            }

            function loadReparations() {
                fetch('?api=reparations')
                    .then(function(r) { return r.json(); })
                    .then(function(reparations) {
                        var html = '<div style="margin-bottom:15px">' +
                            '<button class="btn btn-primary" onclick="showAddReparationModal()"><i class="fas fa-plus"></i> Nouvelle réparation</button>' +
                            '<button class="btn btn-success" onclick="exportReparations()" style="margin-left:10px"><i class="fas fa-download"></i> Export CSV</button>' +
                            '</div>' +
                            '<div class="table-container">' +
                            '<div class="table-header"><h2><i class="fas fa-tools"></i> Liste des réparations</h2><div class="search-bar"><input type="text" id="searchReparation" placeholder="Rechercher..." onkeyup="searchReparations()"></div></div>' +
                            '<table><thead>' +
                            '<tr><th>ID</th><th>Client</th><th>Appareil</th><th>Panne</th><th>Statut</th><th>Priorité</th><th>Coût</th><th>Actions</th></tr>' +
                            '</thead><tbody id="reparationsList">';
                        for (var i = 0; i < reparations.length; i++) {
                            var r = reparations[i];
                            html += '<tr>' +
                                '<td>#' + r.id + '</td>' +
                                '<td>' + escapeHtml(r.client_nom) + '</td>' +
                                '<td>' + escapeHtml(r.appareil_type) + ' ' + escapeHtml(r.marque || '') + '</td>' +
                                '<td>' + escapeHtml((r.description_panne || '').substring(0, 40)) + '</td>' +
                                '<td><span class="badge badge-' + r.statut + '">' + r.statut + '</span></td>' +
                                '<td><span class="badge badge-' + r.priorite + '">' + r.priorite + '</span></td>' +
                                '<td>' + formatPrice(r.cout_total) + '</td>' +
                                '<td class="action-buttons">' +
                                '<button class="btn btn-sm btn-primary" onclick="editReparation(' + r.id + ')"><i class="fas fa-edit"></i></button>' +
                                '<button class="btn btn-sm btn-danger" onclick="deleteReparation(' + r.id + ')"><i class="fas fa-trash"></i></button>' +
                                '</td></tr>';
                        }
                        html += '</tbody> </div>';
                        document.getElementById('pageContent').innerHTML = html;
                    });
            }

            function loadPieces() {
                fetch('?api=pieces')
                    .then(function(r) { return r.json(); })
                    .then(function(pieces) {
                        var html = '<div style="margin-bottom:15px">' +
                            '<button class="btn btn-primary" onclick="showAddPieceModal()"><i class="fas fa-plus"></i> Nouvelle pièce</button>' +
                            '<button class="btn btn-success" onclick="exportPieces()" style="margin-left:10px"><i class="fas fa-download"></i> Export CSV</button>' +
                            '</div>' +
                            '<div class="table-container">' +
                            '<div class="table-header"><h2><i class="fas fa-microchip"></i> Stock de pièces</h2><div class="search-bar"><input type="text" id="searchPiece" placeholder="Rechercher..." onkeyup="searchPieces()"></div></div>' +
                            '<table><thead>' +
                            '<tr><th>Référence</th><th>Nom</th><th>Prix achat</th><th>Prix vente</th><th>Stock</th><th>Actions</th></tr>' +
                            '</thead><tbody id="piecesList">';
                        for (var i = 0; i < pieces.length; i++) {
                            var p = pieces[i];
                            var stockClass = (p.quantite_stock <= p.seuil_alerte) ? 'style="color:#d63031;font-weight:bold"' : '';
                            html += '<tr>' +
                                '<td>' + escapeHtml(p.reference) + '</td>' +
                                '<td>' + escapeHtml(p.nom) + '</td>' +
                                '<td>' + formatPrice(p.prix_achat) + '</td>' +
                                '<td>' + formatPrice(p.prix_vente) + '</td>' +
                                '<td ' + stockClass + '>' + p.quantite_stock + (p.quantite_stock <= p.seuil_alerte ? ' ⚠️' : '') + '</td>' +
                                '<td class="action-buttons">' +
                                '<button class="btn btn-sm btn-primary" onclick="editPiece(' + p.id + ')"><i class="fas fa-edit"></i></button>' +
                                '<button class="btn btn-sm btn-danger" onclick="deletePiece(' + p.id + ')"><i class="fas fa-trash"></i></button>' +
                                '</td></tr>';
                        }
                        html += '</tbody> </div>';
                        document.getElementById('pageContent').innerHTML = html;
                    });
            }

            // Export functions
            function exportClients() { window.open('?api=export_clients', '_blank'); showNotification('Export clients CSV...'); }
            function exportReparations() { window.open('?api=export_reparations', '_blank'); showNotification('Export réparations CSV...'); }
            function exportPieces() { window.open('?api=export_pieces', '_blank'); showNotification('Export pièces CSV...'); }
            function printReceipt(id) { window.open('?api=print_receipt&id=' + id, '_blank'); }

            function showAddClientModal() {
                showModal('Ajouter un client', 
                    '<div class="form-group"><label>Nom</label><input type="text" id="clientNom" required></div>' +
                    '<div class="form-group"><label>Téléphone</label><input type="tel" id="clientTel" required></div>' +
                    '<div class="form-group"><label>Email</label><input type="email" id="clientEmail"></div>' +
                    '<div class="form-group"><label>Adresse</label><textarea id="clientAdresse" rows="2"></textarea></div>',
                    function() {
                        var data = { nom: document.getElementById('clientNom').value, telephone: document.getElementById('clientTel').value, email: document.getElementById('clientEmail').value, adresse: document.getElementById('clientAdresse').value };
                        fetch('?api=clients', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                            .then(function(r) { return r.json(); })
                            .then(function(result) { if(result.success) { showNotification('Client ajouté'); loadPage('clients'); closeModal(); } });
                    });
            }

            function editClient(id) {
                fetch('?api=clients&id=' + id).then(function(r) { return r.json(); }).then(function(c) {
                    showModal('Modifier client',
                        '<input type="hidden" id="clientId" value="' + c.id + '">' +
                        '<div class="form-group"><label>Nom</label><input type="text" id="clientNom" value="' + escapeHtml(c.nom) + '" required></div>' +
                        '<div class="form-group"><label>Téléphone</label><input type="tel" id="clientTel" value="' + escapeHtml(c.telephone) + '" required></div>' +
                        '<div class="form-group"><label>Email</label><input type="email" id="clientEmail" value="' + escapeHtml(c.email || '') + '"></div>' +
                        '<div class="form-group"><label>Adresse</label><textarea id="clientAdresse" rows="2">' + escapeHtml(c.adresse || '') + '</textarea></div>',
                        function() {
                            var data = { id: parseInt(document.getElementById('clientId').value), nom: document.getElementById('clientNom').value, telephone: document.getElementById('clientTel').value, email: document.getElementById('clientEmail').value, adresse: document.getElementById('clientAdresse').value };
                            fetch('?api=clients', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                                .then(function() { showNotification('Client modifié'); loadPage('clients'); closeModal(); });
                        });
                });
            }

            function deleteClient(id) {
                if (confirm('Supprimer ce client ?')) {
                    fetch('?api=clients&id=' + id, { method: 'DELETE' }).then(function() { showNotification('Client supprimé'); loadPage('clients'); });
                }
            }

            function showAddReparationModal() {
                fetch('?api=clients').then(function(r) { return r.json(); }).then(function(clients) {
                    var options = '<option value="">Sélectionner un client</option>';
                    for (var i = 0; i < clients.length; i++) { options += '<option value="' + clients[i].id + '">' + escapeHtml(clients[i].nom) + '</option>'; }
                    showModal('Nouvelle réparation',
                        '<div class="form-group"><label>Client</label><select id="repClientId">' + options + '</select></div>' +
                        '<div class="form-group"><label>Type appareil</label><input type="text" id="repType"></div>' +
                        '<div class="form-group"><label>Marque</label><input type="text" id="repMarque"></div>' +
                        '<div class="form-group"><label>Modèle</label><input type="text" id="repModele"></div>' +
                        '<div class="form-group"><label>Description panne</label><textarea id="repDescription" rows="2" required></textarea></div>' +
                        '<div class="form-group"><label>Priorité</label><select id="repPriorite"><option value="basse">Basse</option><option value="normale">Normale</option><option value="urgente">Urgente</option></select></div>',
                        function() {
                            var data = { client_id: parseInt(document.getElementById('repClientId').value), type: document.getElementById('repType').value, marque: document.getElementById('repMarque').value, modele: document.getElementById('repModele').value, description_panne: document.getElementById('repDescription').value, priorite: document.getElementById('repPriorite').value };
                            fetch('?api=reparations', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                                .then(function(r) { return r.json(); }).then(function(result) { if(result.success) { showNotification('Réparation créée'); loadPage('reparations'); closeModal(); } });
                        });
                });
            }

            function editReparation(id) {
                fetch('?api=reparations&id=' + id).then(function(r) { return r.json(); }).then(function(r) {
                    showModal('Modifier réparation',
                        '<input type="hidden" id="repId" value="' + r.id + '">' +
                        '<div class="form-group"><label>Statut</label><select id="repStatut">' +
                        '<option value="en_attente"' + (r.statut=='en_attente' ? ' selected' : '') + '>En attente</option>' +
                        '<option value="diagnostic"' + (r.statut=='diagnostic' ? ' selected' : '') + '>Diagnostic</option>' +
                        '<option value="en_reparation"' + (r.statut=='en_reparation' ? ' selected' : '') + '>En réparation</option>' +
                        '<option value="termine"' + (r.statut=='termine' ? ' selected' : '') + '>Terminé</option></select></div>' +
                        '<div class="form-group"><label>Priorité</label><select id="repPriorite">' +
                        '<option value="basse"' + (r.priorite=='basse' ? ' selected' : '') + '>Basse</option>' +
                        '<option value="normale"' + (r.priorite=='normale' ? ' selected' : '') + '>Normale</option>' +
                        '<option value="urgente"' + (r.priorite=='urgente' ? ' selected' : '') + '>Urgente</option></select></div>' +
                        '<div class="form-group"><label>Description panne</label><textarea id="repDescription" rows="2">' + escapeHtml(r.description_panne || '') + '</textarea></div>' +
                        '<div class="form-group"><label>Diagnostic</label><textarea id="repDiagnostic" rows="2">' + escapeHtml(r.diagnostic_technique || '') + '</textarea></div>' +
                        '<div class="form-group"><label>Coût (FCFA)</label><input type="number" id="repCout" value="' + (r.cout_total || 0) + '"></div>',
                        function() {
                            var data = { id: parseInt(document.getElementById('repId').value), statut: document.getElementById('repStatut').value, priorite: document.getElementById('repPriorite').value, description_panne: document.getElementById('repDescription').value, diagnostic_technique: document.getElementById('repDiagnostic').value, cout_total: parseFloat(document.getElementById('repCout').value) };
                            fetch('?api=reparations', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                                .then(function() { showNotification('Réparation mise à jour'); loadPage('reparations'); if(currentPage=='dashboard') loadDashboard(); closeModal(); });
                        });
                });
            }

            function deleteReparation(id) {
                if (confirm('Supprimer cette réparation ?')) {
                    fetch('?api=reparations&id=' + id, { method: 'DELETE' }).then(function() { showNotification('Réparation supprimée'); loadPage('reparations'); });
                }
            }

            function showAddPieceModal() {
                showModal('Ajouter une pièce',
                    '<div class="form-group"><label>Référence</label><input type="text" id="pieceRef" required></div>' +
                    '<div class="form-group"><label>Nom</label><input type="text" id="pieceNom" required></div>' +
                    '<div class="form-group"><label>Prix achat (FCFA)</label><input type="number" id="pieceAchat" value="0"></div>' +
                    '<div class="form-group"><label>Prix vente (FCFA)</label><input type="number" id="pieceVente" value="0"></div>' +
                    '<div class="form-group"><label>Stock initial</label><input type="number" id="pieceStock" value="0"></div>' +
                    '<div class="form-group"><label>Seuil alerte</label><input type="number" id="pieceSeuil" value="5"></div>',
                    function() {
                        var data = { reference: document.getElementById('pieceRef').value, nom: document.getElementById('pieceNom').value, prix_achat: parseFloat(document.getElementById('pieceAchat').value), prix_vente: parseFloat(document.getElementById('pieceVente').value), quantite_stock: parseInt(document.getElementById('pieceStock').value), seuil_alerte: parseInt(document.getElementById('pieceSeuil').value) };
                        fetch('?api=pieces', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                            .then(function(r) { return r.json(); }).then(function(result) { if(result.success) { showNotification('Pièce ajoutée'); loadPage('pieces'); closeModal(); } });
                    });
            }

            function editPiece(id) {
                fetch('?api=pieces&id=' + id).then(function(r) { return r.json(); }).then(function(p) {
                    showModal('Modifier pièce',
                        '<input type="hidden" id="pieceId" value="' + p.id + '">' +
                        '<div class="form-group"><label>Référence</label><input type="text" id="pieceRef" value="' + escapeHtml(p.reference) + '" required></div>' +
                        '<div class="form-group"><label>Nom</label><input type="text" id="pieceNom" value="' + escapeHtml(p.nom) + '" required></div>' +
                        '<div class="form-group"><label>Prix achat (FCFA)</label><input type="number" id="pieceAchat" value="' + p.prix_achat + '"></div>' +
                        '<div class="form-group"><label>Prix vente (FCFA)</label><input type="number" id="pieceVente" value="' + p.prix_vente + '"></div>' +
                        '<div class="form-group"><label>Stock</label><input type="number" id="pieceStock" value="' + p.quantite_stock + '"></div>' +
                        '<div class="form-group"><label>Seuil alerte</label><input type="number" id="pieceSeuil" value="' + p.seuil_alerte + '"></div>',
                        function() {
                            var data = { id: parseInt(document.getElementById('pieceId').value), reference: document.getElementById('pieceRef').value, nom: document.getElementById('pieceNom').value, prix_achat: parseFloat(document.getElementById('pieceAchat').value), prix_vente: parseFloat(document.getElementById('pieceVente').value), quantite_stock: parseInt(document.getElementById('pieceStock').value), seuil_alerte: parseInt(document.getElementById('pieceSeuil').value) };
                            fetch('?api=pieces', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                                .then(function() { showNotification('Pièce modifiée'); loadPage('pieces'); closeModal(); });
                        });
                });
            }

            function deletePiece(id) {
                if (confirm('Supprimer cette pièce ?')) {
                    fetch('?api=pieces&id=' + id, { method: 'DELETE' }).then(function() { showNotification('Pièce supprimée'); loadPage('pieces'); });
                }
            }

            function searchClients() {
                var search = document.getElementById('searchClient').value.toLowerCase();
                fetch('?api=clients').then(function(r) { return r.json(); }).then(function(clients) {
                    var filtered = clients.filter(function(c) { return c.nom.toLowerCase().indexOf(search) !== -1 || c.telephone.indexOf(search) !== -1; });
                    var html = '';
                    for (var i = 0; i < filtered.length; i++) {
                        var c = filtered[i];
                        html += '<tr>' +
                            '<td>' + escapeHtml(c.nom) + '</td>' +
                            '<td>' + escapeHtml(c.telephone) + '</td>' +
                            '<td>' + escapeHtml(c.email || '-') + '</td>' +
                            '<td>' + formatDate(c.date_creation) + '</td>' +
                            '<td class="action-buttons">' +
                            '<button class="btn btn-sm btn-warning" onclick="printReceipt(' + c.id + ')"><i class="fas fa-print"></i> Reçu</button>' +
                            '<button class="btn btn-sm btn-primary" onclick="editClient(' + c.id + ')"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-sm btn-danger" onclick="deleteClient(' + c.id + ')"><i class="fas fa-trash"></i></button>' +
                            '</td></tr>';
                    }
                    document.getElementById('clientsList').innerHTML = html;
                });
            }

            function searchReparations() {
                var search = document.getElementById('searchReparation').value.toLowerCase();
                fetch('?api=reparations').then(function(r) { return r.json(); }).then(function(reparations) {
                    var filtered = reparations.filter(function(r) { return r.client_nom.toLowerCase().indexOf(search) !== -1 || r.appareil_type.toLowerCase().indexOf(search) !== -1; });
                    var html = '';
                    for (var i = 0; i < filtered.length; i++) {
                        var r = filtered[i];
                        html += '<tr>' +
                            '<td>#' + r.id + '</td>' +
                            '<td>' + escapeHtml(r.client_nom) + '</td>' +
                            '<td>' + escapeHtml(r.appareil_type) + ' ' + escapeHtml(r.marque || '') + '</td>' +
                            '<td>' + escapeHtml((r.description_panne || '').substring(0, 40)) + '</td>' +
                            '<td><span class="badge badge-' + r.statut + '">' + r.statut + '</span></td>' +
                            '<td><span class="badge badge-' + r.priorite + '">' + r.priorite + '</span></td>' +
                            '<td>' + formatPrice(r.cout_total) + '</td>' +
                            '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editReparation(' + r.id + ')"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-sm btn-danger" onclick="deleteReparation(' + r.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
                    }
                    document.getElementById('reparationsList').innerHTML = html;
                });
            }

            function searchPieces() {
                var search = document.getElementById('searchPiece').value.toLowerCase();
                fetch('?api=pieces').then(function(r) { return r.json(); }).then(function(pieces) {
                    var filtered = pieces.filter(function(p) { return p.nom.toLowerCase().indexOf(search) !== -1 || p.reference.toLowerCase().indexOf(search) !== -1; });
                    var html = '';
                    for (var i = 0; i < filtered.length; i++) {
                        var p = filtered[i];
                        var stockClass = (p.quantite_stock <= p.seuil_alerte) ? 'style="color:#d63031;font-weight:bold"' : '';
                        html += '<td>' +
                            '<td>' + escapeHtml(p.reference) + '</td>' +
                            '<td>' + escapeHtml(p.nom) + '</td>' +
                            '<td>' + formatPrice(p.prix_achat) + '</td>' +
                            '<td>' + formatPrice(p.prix_vente) + '</td>' +
                            '<td ' + stockClass + '>' + p.quantite_stock + (p.quantite_stock <= p.seuil_alerte ? ' ⚠️' : '') + '</td>' +
                            '<td class="action-buttons">' +
                            '<button class="btn btn-sm btn-primary" onclick="editPiece(' + p.id + ')"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-sm btn-danger" onclick="deletePiece(' + p.id + ')"><i class="fas fa-trash"></i></button>' +
                            '</td></tr>';
                    }
                    document.getElementById('piecesList').innerHTML = html;
                });
            }

            var currentModalCallback = null;
            function showModal(title, bodyHtml, onSave) {
                closeModal();
                currentModalCallback = onSave;
                var modal = document.createElement('div');
                modal.className = 'modal active';
                modal.id = 'dynamicModal';
                modal.innerHTML = '<div class="modal-content"><div class="modal-header"><h3>' + title + '</h3><button class="modal-close" onclick="closeModal()" style="background:none;border:none;font-size:20px;cursor:pointer">✕</button></div><div class="modal-body">' + bodyHtml + '</div><div class="modal-footer"><button class="btn btn-primary" onclick="saveModal()">Enregistrer</button><button class="btn" onclick="closeModal()">Annuler</button></div></div>';
                document.body.appendChild(modal);
            }

            function saveModal() { if(currentModalCallback) currentModalCallback(); }
            function closeModal() { var modal = document.getElementById('dynamicModal'); if(modal) modal.remove(); currentModalCallback = null; }

            function formatPrice(price) { if(!price && price!==0) return '0 FCFA'; return new Intl.NumberFormat('fr-FR').format(price) + ' FCFA'; }
            function formatDate(dateString) { if(!dateString) return '-'; return new Date(dateString).toLocaleDateString('fr-FR'); }
            function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m) { if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m; }); }
            function showNotification(msg) { var n = document.createElement('div'); n.className = 'notification'; n.innerHTML = msg; document.body.appendChild(n); setTimeout(function() { n.remove(); }, 2500); }

        </script>
    </body>
</html>