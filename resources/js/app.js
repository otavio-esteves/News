const toggle = document.getElementById('theme-toggle');
const siteMenu = document.getElementById('site-menu');
const siteMenuToggle = document.getElementById('site-menu-toggle');
const siteMenuClose = document.getElementById('site-menu-close');
const categoryNav = document.querySelector('nav[aria-label="Editorias"]');

if (categoryNav) {
    const activeCategory = categoryNav.querySelector('[aria-current="page"]');

    if (activeCategory && categoryNav.scrollWidth > categoryNav.clientWidth) {
        const navLeft = categoryNav.getBoundingClientRect().left;
        const activeLeft = activeCategory.getBoundingClientRect().left;
        categoryNav.scrollLeft += activeLeft - navLeft - (categoryNav.clientWidth - activeCategory.clientWidth) / 2;
    }
}

if (siteMenu && siteMenuToggle && siteMenuClose) {
    siteMenuToggle.addEventListener('click', () => {
        siteMenu.showModal();
        siteMenuToggle.setAttribute('aria-expanded', 'true');
        siteMenuClose.focus();
    });

    siteMenuClose.addEventListener('click', () => siteMenu.close());

    siteMenu.addEventListener('click', (event) => {
        if (event.target === siteMenu) siteMenu.close();
    });

    siteMenu.addEventListener('close', () => {
        siteMenuToggle.setAttribute('aria-expanded', 'false');
        siteMenuToggle.focus();
    });
}

if (toggle) {
    const systemTheme = window.matchMedia('(prefers-color-scheme: light)');
    const currentTheme = () => document.documentElement.dataset.theme
        || 'dark';

    const updateLabel = () => {
        const isDark = currentTheme() === 'dark';
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('aria-label', isDark ? 'Ativar tema claro' : 'Ativar tema escuro');
    };

    updateLabel();

    systemTheme.addEventListener('change', (event) => {
        let savedTheme = null;

        try {
            savedTheme = localStorage.getItem('news-theme');
        } catch (_) {}

        if (savedTheme !== 'light' && savedTheme !== 'dark') {
            document.documentElement.dataset.theme = event.matches ? 'light' : 'dark';
            updateLabel();
        }
    });

    toggle.addEventListener('click', () => {
        const theme = currentTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = theme;

        try {
            localStorage.setItem('news-theme', theme);
        } catch (_) {}

        updateLabel();
    });
}
