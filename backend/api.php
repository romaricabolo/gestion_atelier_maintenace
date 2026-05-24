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
                            <h1>🔧 ATELIER MAINTENANCE</h1>
                            <p>Votre expert en réparation électronique</p>
                            <div class="company-info">
                                <span>📞 +237 679 174 413</span>
                                <span>✉ romaricabolo@gmail.com</span>
                                <span>📍 Sangmelima, Cameroun</span>
                            </div>
                        </div>
                        <div class="body">
                            <div class="info-client">
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