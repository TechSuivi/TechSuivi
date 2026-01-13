// JavaScript pour sidebar et thème
document.addEventListener('DOMContentLoaded', function () {
    // Fonction pour ouvrir le bon menu selon la page actuelle
    function openMenuForCurrentPage() {
        var currentPage = new URLSearchParams(window.location.search).get('page');
        if (currentPage) {
            if (currentPage.startsWith('interventions_')) {
                // Ouvrir le menu Interventions
                var interventionsMenu = document.querySelector('.menu-item a[href*="interventions_list"]').parentElement;
                interventionsMenu.classList.add('open');
            } else if (currentPage.startsWith('downloads_')) {
                // Ouvrir le menu Téléchargements
                var downloadsMenu = document.querySelector('.menu-item a[href*="downloads_list"]').parentElement;
                downloadsMenu.classList.add('open');
            } else if (currentPage.startsWith('liens_')) {
                // Ouvrir le menu Liens
                var liensMenu = document.querySelector('.menu-item a[href*="liens_list"]').parentElement;
                liensMenu.classList.add('open');
            } else if (currentPage === 'clients' || currentPage === 'add_client') {
                // Ouvrir le menu Clients
                var clientsMenu = document.querySelector('.menu-item a[href*="clients"]').parentElement;
                clientsMenu.classList.add('open');
            } else if (currentPage.startsWith('stock_') || currentPage.startsWith('inventory_') || currentPage.startsWith('orders_')) {
                // Ouvrir le menu Stock
                var stockMenu = document.querySelector('.menu-item a[href*="stock_list"]').parentElement;
                stockMenu.classList.add('open');
            } else if (currentPage === 'messages') {
                // Ouvrir le menu Messages
                var messagesMenu = document.querySelector('#messages-main-link').parentElement;
                messagesMenu.classList.add('open');
            } else if (currentPage === 'helpdesk_categories' || currentPage === 'fournisseurs_list' || currentPage === 'moyens_paiement' || currentPage === 'catalog_import') {
                // Ouvrir le menu Paramètres
                var parametresMenu = document.querySelector('.menu-item a[href="#"]').parentElement;
                if (parametresMenu && parametresMenu.querySelector('a[href*="helpdesk_categories"]')) {
                    parametresMenu.classList.add('open');
                }
            } else if (currentPage === 'dashboard_caisse' || currentPage === 'resume_journalier' || currentPage === 'tableau_recapitulatif' || currentPage.startsWith('feuille_caisse') || currentPage === 'moyens_paiement') {
                // Ouvrir le menu Gestion Caisse
                var menuItems = document.querySelectorAll('.menu-item > a');
                menuItems.forEach(function (item) {
                    if (item.textContent.includes('📋 Gestion Caisse')) {
                        item.parentElement.classList.add('open');
                    }
                });
            } else if (currentPage.startsWith('cyber_')) {
                // Ouvrir le menu Sessions Cyber
                var menuItems = document.querySelectorAll('.menu-item > a');
                menuItems.forEach(function (item) {
                    if (item.textContent.includes('🖥️ Sessions Cyber')) {
                        item.parentElement.classList.add('open');
                    }
                });
            } else if (currentPage.startsWith('transaction')) {
                // Ouvrir le menu Transactions
                var menuItems = document.querySelectorAll('.menu-item > a');
                menuItems.forEach(function (item) {
                    if (item.textContent.includes('💳 Transactions')) {
                        item.parentElement.classList.add('open');
                    }
                });
            }
        }
    }

    // Ouvrir le menu approprié au chargement de la page
    openMenuForCurrentPage();

    // Submenu toggle
    document.querySelectorAll('.menu-item > a').forEach(function (element) {
        element.addEventListener('click', function (e) {
            var parent = element.parentElement;
            var href = element.getAttribute('href');

            // Si le lien est "#", on empêche la navigation et on toggle le menu
            if (href === '#') {
                if (parent.classList.contains('open')) {
                    parent.classList.remove('open');
                } else {
                    // Close other open menus
                    document.querySelectorAll('.menu-item.open').forEach(function (openItem) {
                        openItem.classList.remove('open');
                    });
                    parent.classList.add('open');
                }
                e.preventDefault();
            }
            // Pour les liens avec vraie URL, on laisse la navigation se faire normalement
            // Le menu sera ouvert automatiquement par openMenuForCurrentPage() après le rechargement
        });
    });

    // Gérer les séparateurs de sous-menu (les rendre non-cliquables)
    document.querySelectorAll('.submenu-separator').forEach(function (separator) {
        separator.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });
        // Ajouter un style de curseur pour indiquer que ce n'est pas cliquable
        separator.style.cursor = 'default';
        separator.style.pointerEvents = 'none';
    });

    // Theme toggle
    var body = document.body;
    var themeToggle = document.getElementById('theme-toggle');
    function setTheme(theme) {
        body.className = theme;
        if (theme === 'dark') {
            themeToggle.innerHTML = '🌙 Light Mode';
        } else {
            themeToggle.innerHTML = '☀️ Dark Mode';
        }
    }
    // Load theme from localStorage or default dark
    var savedTheme = localStorage.getItem('theme') || 'dark';
    setTheme(savedTheme);
    themeToggle.addEventListener('click', function () {
        var newTheme = body.classList.contains('dark') ? 'light' : 'dark';
        setTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
});
