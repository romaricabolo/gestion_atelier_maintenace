
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
            '<div class="form-group"><label>Type appareil</label><select id="repType"><option>Smartphone</option><option>Laptop</option><option>TV</option></select></div>' +
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
