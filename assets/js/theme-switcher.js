// assets/js/theme-switcher.js
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtnMobile = document.getElementById('themeToggleBtnMobile');
    const themeToggleBtnDesktop = document.getElementById('themeToggleBtnDesktop');
    const currentTheme = localStorage.getItem('theme');

    function updateButtonAppearance(buttonElement, theme) {
        if (!buttonElement) return; // বাটন না থাকলে রিটার্ন

        // SVG আইকনগুলো এখানে রাখুন
        const sunIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sun-fill" viewBox="0 0 16 16"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0m0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13m8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5M3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8m10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0m-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0m9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707M4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708"/></svg>';
        const moonIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.312 6.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 12.312.07l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097z"/></svg>';
        const lightModeText = ' <span class="d-none d-sm-inline">লাইট মোড</span>';
        const darkModeText = ' <span class="d-none d-sm-inline">ডার্ক মোড</span>';

        if (theme === 'dark') {
            buttonElement.innerHTML = sunIcon + (buttonElement.id === 'themeToggleBtnDesktop' ? lightModeText : '');
            buttonElement.setAttribute('aria-label', 'লাইট মোডে পরিবর্তন করুন');
        } else {
            buttonElement.innerHTML = moonIcon + (buttonElement.id === 'themeToggleBtnDesktop' ? darkModeText : '');
            buttonElement.setAttribute('aria-label', 'ডার্ক মোডে পরিবর্তন করুন');
        }
    }

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
        // উভয় বাটনের অ্যাপিয়ারেন্স আপডেট করা
        updateButtonAppearance(themeToggleBtnMobile, theme);
        updateButtonAppearance(themeToggleBtnDesktop, theme);
    }

    // Load theme based on localStorage or default to 'light'
    if (currentTheme) {
        applyTheme(currentTheme);
    } else {
        applyTheme('light'); // Default to light theme
    }

    function toggleThemeAction() {
        let newTheme = 'light';
        if (!document.body.classList.contains('dark-mode')) {
            newTheme = 'dark';
        }
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    }

    if (themeToggleBtnMobile) {
        themeToggleBtnMobile.addEventListener('click', toggleThemeAction);
    }
    if (themeToggleBtnDesktop) {
        themeToggleBtnDesktop.addEventListener('click', toggleThemeAction);
    }
});