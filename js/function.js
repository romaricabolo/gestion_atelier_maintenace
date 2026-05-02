var charts = {};
var currentPage = 'dashboard';

// Initialisation
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
        });
    }
});

function loadPage(page) {
    currentPage = page;
    var titles = { dashboard: 'Tableau de bord', clients: 'Gestion des clients', reparations: 'Gestion des réparations', pieces: 'Gestion des pièces' };
    document.getElementById('pageTitle').innerHTML = titles[page];
    document.getElementById('pageContent').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    if (page === 'dashboard') loadDashboard();
    else if (page === 'clients') loadClients();
    else if (page === 'reparations') loadReparations();
    else if (page === 'pieces') loadPieces();
}

function loadDashboard() {
    fetch('?api=dashboard')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            return fetch('?api=statistiques').then(function(r) { return r.json(); }).then(function(statsData) {
                var html = '<div class="stats-grid">' +
                    '<div class="stat-card"><div class="stat-info"><h3>Réparations en cours</h3><div class="stat-number">' + (data.reparations_cours || 0) + '</div></div><div class="stat-icon"><i class="fas fa-wrench"></i></div></div>' +
                    '<div class="stat-card"><div class="stat-info"><h3>Terminées ce mois</h3><div class="stat-number">' + (data.reparations_terminees || 0) + '</div></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>' +
                    '<div class="stat-card"><div class="stat-info"><h3>Chiffre d\'affaires</h3><div class="stat-number">' + formatPrice(data.ca_mois || 0) + '</div></div><div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div></div>' +
                    '<div class="stat-card"><div class="stat-info"><h3>Clients</h3><div class="stat-number">' + (data.total_clients || 0) + '</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>' +
                    '</div>' +
                    '<div class="charts-grid">' +
                    '<div class="chart-card"><div class="chart-title"><i class="fas fa-chart-line"></i><span>Évolution mensuelle</span></div><div class="chart-container"><canvas id="evolutionChart"></canvas></div></div>' +
                    '<div class="chart-card"><div class="chart-title"><i class="fas fa-chart-bar"></i><span>CA mensuel</span></div><div class="chart-container"><canvas id="caChart"></canvas></div></div>' +
                    '</div>' +
                    '<div class="table-container"><div class="table-header"><h2><i class="fas fa-history"></i> Dernières réparations</h2><button class="btn btn-primary btn-sm" onclick="loadPage(\'reparations\')"><i class="fas fa-plus"></i> Voir tout</button></div>' +
                    '<table><thead><tr><th>Client</th><th>Appareil</th><th>Statut</th><th>Priorité</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
                
                for (var i = 0; i < (data.dernieres_reparations || []).length; i++) {
                    var r = data.dernieres_reparations[i];
                    html += '<tr><td>' + escapeHtml(r.client_nom) + '</td><td>' + escapeHtml(r.appareil_type) + ' ' + escapeHtml(r.marque || '') + '</td>' +
                        '<td><span class="badge badge-' + r.statut + '">' + getStatutLabel(r.statut) + '</span></td>' +
                        '<td><span class="badge badge-' + r.priorite + '">' + getPrioriteLabel(r.priorite) + '</span></td>' +
                        '<td>' + formatDate(r.date_depot) + '</td>' +
                        '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editReparation(' + r.id + ')"><i class="fas fa-edit"></i></button></td></tr>';
                }
                html += '</tbody></table></div>';
                document.getElementById('pageContent').innerHTML = html;
                
                setTimeout(function() {
                    if (statsData.reparations_mois && statsData.reparations_mois.length > 0) {
                        var labels = statsData.reparations_mois.map(function(m) { return m.mois; });
                        var total = statsData.reparations_mois.map(function(m) { return m.total; });
                        var terminees = statsData.reparations_mois.map(function(m) { return m.terminees; });
                        
                        new Chart(document.getElementById('evolutionChart'), {
                            type: 'line', data: { labels: labels, datasets: [
                                { label: 'Total réparations', data: total, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.4, fill: true },
                                { label: 'Terminées', data: terminees, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.4, fill: true }
                            ]}, options: { responsive: true, maintainAspectRatio: true }
                        });
                        
                        var ca = statsData.ca_mois.map(function(c) { return c.ca; });
                        new Chart(document.getElementById('caChart'), {
                            type: 'bar', data: { labels: labels, datasets: [{ label: 'CA (F)', data: ca, backgroundColor: '#10b981', borderRadius: 8 }] },
                            options: { responsive: true, maintainAspectRatio: true, plugins: { tooltip: { callbacks: { label: function(ctx) { return formatPrice(ctx.raw); } } } } }
                        });
                    }
                }, 100);
            });
        })
        .catch(function(e) { console.error(e); document.getElementById('pageContent').innerHTML = '<div style="padding:20px;text-align:center">Erreur de chargement</div>'; });
}

function loadClients() {
    fetch('?api=clients')
        .then(function(response) { return response.json(); })
        .then(function(clients) {
            var html = '<div style="margin-bottom:20px"><button class="btn btn-primary" onclick="showAddClientModal()"><i class="fas fa-plus"></i> Nouveau client</button></div>' +
                '<div class="table-container"><div class="table-header"><h2>Liste des clients</h2><div class="search-bar"><input type="text" id="searchClient" placeholder="Rechercher..." onkeyup="searchClients()"></div></div>' +
                '<table><thead><tr><th>Nom</th><th>Téléphone</th><th>Email</th><th>Date</th><th>Actions</th></tr></thead><tbody id="clientsList">';
            for (var i = 0; i < clients.length; i++) {
                var c = clients[i];
                html += '<tr><td>' + escapeHtml(c.nom) + '</td><td>' + escapeHtml(c.telephone) + '</td><td>' + escapeHtml(c.email || '-') + '</td><td>' + formatDate(c.date_creation) + '</td>' +
                    '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editClient(' + c.id + ')"><i class="fas fa-edit"></i></button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deleteClient(' + c.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }
            html += '</tbody></table></div>';
            document.getElementById('pageContent').innerHTML = html;
        })
        .catch(function(e) { console.error(e); });
}

function loadReparations() {
    fetch('?api=reparations')
        .then(function(response) { return response.json(); })
        .then(function(reparations) {
            var html = '<div style="margin-bottom:20px"><button class="btn btn-primary" onclick="showAddReparationModal()"><i class="fas fa-plus"></i> Nouvelle réparation</button></div>' +
                '<div class="table-container"><div class="table-header"><h2>Liste des réparations</h2></div>' +
                '<table><thead><tr><th>ID</th><th>Client</th><th>Appareil</th><th>Panne</th><th>Statut</th><th>Priorité</th><th>Coût</th><th>Actions</th></tr></thead><tbody>';
            for (var i = 0; i < reparations.length; i++) {
                var r = reparations[i];
                html += '<tr><td>#' + r.id + '</td><td>' + escapeHtml(r.client_nom) + '</td><td>' + escapeHtml(r.appareil_type) + ' ' + escapeHtml(r.marque || '') + '</td>' +
                    '<td>' + escapeHtml((r.description_panne || '').substring(0,40)) + '</td>' +
                    '<td><span class="badge badge-' + r.statut + '">' + getStatutLabel(r.statut) + '</span></td>' +
                    '<td><span class="badge badge-' + r.priorite + '">' + getPrioriteLabel(r.priorite) + '</span></td>' +
                    '<td>' + formatPrice(r.cout_total) + '</td>' +
                    '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editReparation(' + r.id + ')"><i class="fas fa-edit"></i></button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deleteReparation(' + r.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }
            html += '</tbody></table></div>';
            document.getElementById('pageContent').innerHTML = html;
        })
        .catch(function(e) { console.error(e); });
}

function loadPieces() {
    fetch('?api=pieces')
        .then(function(response) { return response.json(); })
        .then(function(pieces) {
            var html = '<div style="margin-bottom:20px"><button class="btn btn-primary" onclick="showAddPieceModal()"><i class="fas fa-plus"></i> Nouvelle pièce</button></div>' +
                '<div class="table-container"><div class="table-header"><h2>Stock de pièces</h2><div class="search-bar"><input type="text" id="searchPiece" placeholder="Rechercher..." onkeyup="searchPieces()"></div></div>' +
                '<table><thead><tr><th>Référence</th><th>Nom</th><th>Prix achat</th><th>Prix vente</th><th>Stock</th><th>Actions</th></tr></thead><tbody id="piecesList">';
            for (var i = 0; i < pieces.length; i++) {
                var p = pieces[i];
                var stockClass = p.quantite_stock <= p.seuil_alerte ? 'style="color:#ef4444;font-weight:600"' : '';
                html += '<tr><td>' + escapeHtml(p.reference) + '</td><td>' + escapeHtml(p.nom) + '</td><td>' + formatPrice(p.prix_achat) + '</td><td>' + formatPrice(p.prix_vente) + '</td>' +
                    '<td ' + stockClass + '>' + p.quantite_stock + (p.quantite_stock <= p.seuil_alerte ? ' ⚠️' : '') + '</td>' +
                    '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editPiece(' + p.id + ')"><i class="fas fa-edit"></i></button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deletePiece(' + p.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }
            html += '</tbody></table></div>';
            document.getElementById('pageContent').innerHTML = html;
        })
        .catch(function(e) { console.error(e); });
}

// CRUD Clients
function showAddClientModal() {
    showModal('Ajouter un client', 
        '<div class="form-group"><label>Nom *</label><input type="text" id="clientNom" required></div>' +
        '<div class="form-group"><label>Téléphone *</label><input type="tel" id="clientTel" required></div>' +
        '<div class="form-group"><label>Email</label><input type="email" id="clientEmail"></div>' +
        '<div class="form-group"><label>Adresse</label><textarea id="clientAdresse" rows="2"></textarea></div>',
        function() {
            var data = { 
                nom: document.getElementById('clientNom').value, 
                telephone: document.getElementById('clientTel').value, 
                email: document.getElementById('clientEmail').value, 
                adresse: document.getElementById('clientAdresse').value 
            };
            fetch('?api=clients', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                .then(function(r) { return r.json(); })
                .then(function(result) { if(result.success) { showNotification('Client ajouté'); loadPage('clients'); closeModal(); } });
        });
}

function editClient(id) {
    fetch('?api=clients&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(c) {
            showModal('Modifier client',
                '<input type="hidden" id="clientId" value="' + c.id + '">' +
                '<div class="form-group"><label>Nom *</label><input type="text" id="clientNom" value="' + escapeHtml(c.nom) + '" required></div>' +
                '<div class="form-group"><label>Téléphone *</label><input type="tel" id="clientTel" value="' + escapeHtml(c.telephone) + '" required></div>' +
                '<div class="form-group"><label>Email</label><input type="email" id="clientEmail" value="' + escapeHtml(c.email || '') + '"></div>' +
                '<div class="form-group"><label>Adresse</label><textarea id="clientAdresse" rows="2">' + escapeHtml(c.adresse || '') + '</textarea></div>',
                function() {
                    var data = { 
                        id: parseInt(document.getElementById('clientId').value), 
                        nom: document.getElementById('clientNom').value, 
                        telephone: document.getElementById('clientTel').value, 
                        email: document.getElementById('clientEmail').value, 
                        adresse: document.getElementById('clientAdresse').value 
                    };
                    fetch('?api=clients', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                        .then(function() { showNotification('Client modifié'); loadPage('clients'); closeModal(); });
                });
        });
}

function deleteClient(id) {
    if (confirm('Supprimer ce client ?')) {
        fetch('?api=clients&id=' + id, { method: 'DELETE' })
            .then(function() { showNotification('Client supprimé'); loadPage('clients'); });
    }
}

// CRUD Réparations
function showAddReparationModal() {
    getClientsOptions(function(options) {
        showModal('Nouvelle réparation',
            '<div class="form-group"><label>Client *</label><select id="repClientId">' + options + '</select></div>' +
            '<div class="form-group"><label>Type d\'appareil *</label><select id="repType"><option>Smartphone</option><option>Laptop</option><option>TV</option><option>Tablette</option></select></div>' +
            '<div class="form-group"><label>Marque</label><input type="text" id="repMarque"></div>' +
            '<div class="form-group"><label>Modèle</label><input type="text" id="repModele"></div>' +
            '<div class="form-group"><label>Description panne *</label><textarea id="repDescription" rows="2" required></textarea></div>' +
            '<div class="form-group"><label>Priorité</label><select id="repPriorite"><option value="basse">Basse</option><option value="normale" selected>Normale</option><option value="urgente">Urgente</option></select></div>',
            function() {
                var data = {
                    client_id: parseInt(document.getElementById('repClientId').value),
                    type: document.getElementById('repType').value,
                    marque: document.getElementById('repMarque').value,
                    modele: document.getElementById('repModele').value,
                    description_panne: document.getElementById('repDescription').value,
                    priorite: document.getElementById('repPriorite').value
                };
                fetch('?api=reparations', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                    .then(function(r) { return r.json(); })
                    .then(function(result) { if(result.success) { showNotification('Réparation créée'); loadPage('reparations'); closeModal(); } });
            });
    });
}

function editReparation(id) {
    fetch('?api=reparations&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(r) {
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
                '<div class="form-group"><label>Coût total (F)</label><input type="number" id="repCout" step="0.01" value="' + (r.cout_total || 0) + '"></div>',
                function() {
                    var data = {
                        id: parseInt(document.getElementById('repId').value),
                        statut: document.getElementById('repStatut').value,
                        priorite: document.getElementById('repPriorite').value,
                        description_panne: document.getElementById('repDescription').value,
                        diagnostic_technique: document.getElementById('repDiagnostic').value,
                        cout_total: parseFloat(document.getElementById('repCout').value)
                    };
                    fetch('?api=reparations', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                        .then(function() { showNotification('Réparation mise à jour'); loadPage('reparations'); if(currentPage=='dashboard') loadDashboard(); closeModal(); });
                });
        });
}

function deleteReparation(id) {
    if (confirm('Supprimer cette réparation ?')) {
        fetch('?api=reparations&id=' + id, { method: 'DELETE' })
            .then(function() { showNotification('Réparation supprimée'); loadPage('reparations'); });
    }
}

// CRUD Pièces
function showAddPieceModal() {
    showModal('Ajouter une pièce',
        '<div class="form-group"><label>Référence *</label><input type="text" id="pieceRef" required></div>' +
        '<div class="form-group"><label>Nom *</label><input type="text" id="pieceNom" required></div>' +
        '<div class="form-group"><label>Prix achat (F)</label><input type="number" id="pieceAchat" step="0.01" value="0"></div>' +
        '<div class="form-group"><label>Prix vente (F)</label><input type="number" id="pieceVente" step="0.01" value="0"></div>' +
        '<div class="form-group"><label>Stock</label><input type="number" id="pieceStock" value="0"></div>',
        function() {
            var data = {
                reference: document.getElementById('pieceRef').value,
                nom: document.getElementById('pieceNom').value,
                prix_achat: parseFloat(document.getElementById('pieceAchat').value),
                prix_vente: parseFloat(document.getElementById('pieceVente').value),
                quantite_stock: parseInt(document.getElementById('pieceStock').value)
            };
            fetch('?api=pieces', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                .then(function(r) { return r.json(); })
                .then(function(result) { if(result.success) { showNotification('Pièce ajoutée'); loadPage('pieces'); closeModal(); } });
        });
}

function editPiece(id) {
    fetch('?api=pieces&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(p) {
            showModal('Modifier pièce',
                '<input type="hidden" id="pieceId" value="' + p.id + '">' +
                '<div class="form-group"><label>Référence *</label><input type="text" id="pieceRef" value="' + escapeHtml(p.reference) + '" required></div>' +
                '<div class="form-group"><label>Nom *</label><input type="text" id="pieceNom" value="' + escapeHtml(p.nom) + '" required></div>' +
                '<div class="form-group"><label>Prix achat (F)</label><input type="number" id="pieceAchat" step="0.01" value="' + p.prix_achat + '"></div>' +
                '<div class="form-group"><label>Prix vente (F)</label><input type="number" id="pieceVente" step="0.01" value="' + p.prix_vente + '"></div>' +
                '<div class="form-group"><label>Stock</label><input type="number" id="pieceStock" value="' + p.quantite_stock + '"></div>',
                function() {
                    var data = {
                        id: parseInt(document.getElementById('pieceId').value),
                        reference: document.getElementById('pieceRef').value,
                        nom: document.getElementById('pieceNom').value,
                        prix_achat: parseFloat(document.getElementById('pieceAchat').value),
                        prix_vente: parseFloat(document.getElementById('pieceVente').value),
                        quantite_stock: parseInt(document.getElementById('pieceStock').value)
                    };
                    fetch('?api=pieces', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                        .then(function() { showNotification('Pièce modifiée'); loadPage('pieces'); closeModal(); });
                });
        });
}

function deletePiece(id) {
    if (confirm('Supprimer cette pièce ?')) {
        fetch('?api=pieces&id=' + id, { method: 'DELETE' })
            .then(function() { showNotification('Pièce supprimée'); loadPage('pieces'); });
    }
}

// Helper functions
function getClientsOptions(callback) {
    fetch('?api=clients')
        .then(function(r) { return r.json(); })
        .then(function(clients) {
            var options = '';
            for (var i = 0; i < clients.length; i++) {
                options += '<option value="' + clients[i].id + '">' + escapeHtml(clients[i].nom) + ' - ' + clients[i].telephone + '</option>';
            }
            callback(options);
        });
}

function searchClients() {
    var search = document.getElementById('searchClient').value.toLowerCase();
    fetch('?api=clients')
        .then(function(r) { return r.json(); })
        .then(function(clients) {
            var filtered = [];
            for (var i = 0; i < clients.length; i++) {
                if (clients[i].nom.toLowerCase().indexOf(search) !== -1 || clients[i].telephone.indexOf(search) !== -1) {
                    filtered.push(clients[i]);
                }
            }
            var html = '';
            for (var i = 0; i < filtered.length; i++) {
                var c = filtered[i];
                html += '<tr><td>' + escapeHtml(c.nom) + '</td><td>' + escapeHtml(c.telephone) + '</td><td>' + escapeHtml(c.email || '-') + '</td><td>' + formatDate(c.date_creation) + '</td>' +
                    '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editClient(' + c.id + ')"><i class="fas fa-edit"></i></button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deleteClient(' + c.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }
            document.getElementById('clientsList').innerHTML = html;
        });
}

function searchPieces() {
    var search = document.getElementById('searchPiece').value.toLowerCase();
    fetch('?api=pieces')
        .then(function(r) { return r.json(); })
        .then(function(pieces) {
            var filtered = [];
            for (var i = 0; i < pieces.length; i++) {
                if (pieces[i].nom.toLowerCase().indexOf(search) !== -1 || pieces[i].reference.toLowerCase().indexOf(search) !== -1) {
                    filtered.push(pieces[i]);
                }
            }
            var html = '';
            for (var i = 0; i < filtered.length; i++) {
                var p = filtered[i];
                var stockClass = p.quantite_stock <= p.seuil_alerte ? 'style="color:#ef4444;font-weight:600"' : '';
                html += '<tr><td>' + escapeHtml(p.reference) + '</td><td>' + escapeHtml(p.nom) + '</td><td>' + formatPrice(p.prix_achat) + '</td><td>' + formatPrice(p.prix_vente) + '</td>' +
                    '<td ' + stockClass + '>' + p.quantite_stock + (p.quantite_stock <= p.seuil_alerte ? ' ⚠️' : '') + '</td>' +
                    '<td class="action-buttons"><button class="btn btn-sm btn-primary" onclick="editPiece(' + p.id + ')"><i class="fas fa-edit"></i></button>' +
                    '<button class="btn btn-sm btn-danger" onclick="deletePiece(' + p.id + ')"><i class="fas fa-trash"></i></button></td></tr>';
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
    modal.innerHTML = '<div class="modal-content">' +
        '<div class="modal-header"><h3>' + title + '</h3><button class="modal-close" onclick="closeModal()">✕</button></div>' +
        '<div class="modal-body">' + bodyHtml + '</div>' +
        '<div class="modal-footer"><button class="btn btn-primary" onclick="saveModal()">Enregistrer</button><button class="btn" onclick="closeModal()">Annuler</button></div>' +
        '</div>';
    document.body.appendChild(modal);
}
// Menu mobile
function initMobileMenu() {
    // Créer le bouton menu mobile s'il n'existe pas
    if (!document.querySelector('.mobile-menu-btn')) {
        const btn = document.createElement('button');
        btn.className = 'mobile-menu-btn';
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        btn.onclick = toggleMobileMenu;
        document.body.insertBefore(btn, document.body.firstChild);
    }
    
    // Créer l'overlay s'il n'existe pas
    if (!document.querySelector('.mobile-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'mobile-overlay';
        overlay.onclick = closeMobileMenu;
        document.body.appendChild(overlay);
    }
}

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
}

function closeMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Fermer menu quand on clique sur un lien
function setupMobileNavLinks() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    });
}

// Appeler l'initialisation
document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    setupMobileNavLinks();
    
    // Réinitialiser si redimensionnement
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
});

// Gestion du mode sombre
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        document.body.classList.add('dark-mode');
        updateThemeIcon(true);
    }
}

function toggleTheme() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

function updateThemeIcon(isDark) {
    const icon = document.querySelector('#themeToggle i');
    if (icon) {
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Appeler au chargement
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', toggleTheme);
    }
});

function saveModal() { if(currentModalCallback) currentModalCallback(); }
function closeModal() { var modal = document.getElementById('dynamicModal'); if(modal) modal.remove(); currentModalCallback = null; }

function formatPrice(price) { if(!price && price!==0) return '0 F'; return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(price); }
function formatDate(dateString) { if(!dateString) return '-'; return new Date(dateString).toLocaleDateString('fr-FR'); }
function getStatutLabel(s) { var labels = { 'en_attente':'En attente', 'diagnostic':'Diagnostic', 'en_reparation':'En réparation', 'termine':'Terminé', 'restitue':'Restitué' }; return labels[s] || s; }
function getPrioriteLabel(p) { var labels = { 'basse':'Basse', 'normale':'Normale', 'urgente':'Urgente' }; return labels[p] || p; }
function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m) { if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m; }); }
function showNotification(msg, type) { if (type === undefined) type = 'success'; var n = document.createElement('div'); n.className = 'notification ' + type; n.innerHTML = '<i class="fas ' + (type=='success'?'fa-check-circle':'fa-exclamation-circle') + '"></i><span>' + msg + '</span>'; document.body.appendChild(n); setTimeout(function() { n.remove(); }, 3000); }
