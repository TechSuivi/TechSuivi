// JavaScript pour sidebar et thème
document.addEventListener('DOMContentLoaded', function () {
    // Fonction pour ouvrir le bon menu selon la page actuelle
    function openMenuForCurrentPage() {
        // Nouvelle méthode robuste : se baser sur la classe 'active' mise par PHP
        var activeLink = document.querySelector('.menu-item > a.active');
        if (activeLink) {
            var parentMenuItem = activeLink.parentElement;
            // Si le lien actif est un sous-menu (dans un .submenu), ouvrir le parent du sous-menu
            var submenu = activeLink.closest('.submenu');
            if (submenu) {
                var parentOfSubmenu = submenu.closest('.menu-item');
                if (parentOfSubmenu) {
                    parentOfSubmenu.classList.add('open');
                }
            } else {
                // Si le lien actif est un menu principal direct, l'ouvrir s'il a un sous-menu
                parentMenuItem.classList.add('open');
            }
        }

        // Fallback : logique basée sur l'URL pour les cas où PHP n'aurait pas mis la classe active
        // (Garde la compatibilité avec l'ancien système si nécessaire)
        var currentPage = new URLSearchParams(window.location.search).get('page');
        if (currentPage) {
            // ... Code existant conservé pour sécurité ou cas spécifiques ...
            if (currentPage.startsWith('stock_') || currentPage.startsWith('inventory_') || currentPage.startsWith('orders_')) {
                var stockMenu = document.querySelector('.menu-item a[href*="stock_list"]')?.parentElement;
                if (stockMenu) stockMenu.classList.add('open');
            }
        }
    }

    // Ouvrir le menu approprié au chargement de la page
    openMenuForCurrentPage();

    // Submenu toggle - Robust Implementation
    document.querySelectorAll('.menu-item > a').forEach(function (element) {
        element.addEventListener('click', function (e) {
            // Check if this item has a submenu sibling
            var parent = element.parentElement;
            var submenu = parent.querySelector('.submenu');
            var href = element.getAttribute('href');

            if (submenu) {
                // Only prevent default if it's not a real link
                if (!href || href === '#' || href === 'javascript:void(0)') {
                    e.preventDefault();
                    // Toggle 'open' class only if we are taking over navigation
                    if (parent.classList.contains('open')) {
                        parent.classList.remove('open');
                    } else {
                        // Close other open menus
                        document.querySelectorAll('.menu-item.open').forEach(function (openItem) {
                            openItem.classList.remove('open');
                        });
                        parent.classList.add('open');
                    }
                }
                // If it IS a real link (e.g. index.php?page=...), we do nothing here.
                // The browser navigates, page reloads, and openMenuForCurrentPage() handles opening the menu.
            }
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
