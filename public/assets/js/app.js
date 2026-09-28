'use strict';
// Progressive enhancement: all navigation and submissions work without this file.
document.documentElement.classList.add('js');
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#site-navigation');
if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
        const opened = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!opened));
        navigation.classList.toggle('is-open', !opened);
    });
    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
            menuButton.setAttribute('aria-expanded', 'false');
            navigation.classList.remove('is-open');
            menuButton.focus();
        }
    });
}

// Native constraints provide convenience; PHP always validates submitted values.
const password = document.querySelector('#password');
const confirmation = document.querySelector('#confirm_password');
if (password && confirmation) {
    const validatePasswords = () => {
        confirmation.setCustomValidity(confirmation.value && password.value !== confirmation.value
            ? 'The passwords must match.' : '');
        password.setCustomValidity(new TextEncoder().encode(password.value).length > 72
            ? 'Use no more than 72 UTF-8 bytes for your passphrase.' : '');
    };
    password.addEventListener('input', validatePasswords);
    confirmation.addEventListener('input', validatePasswords);
}

// A deep link to an expandable event form should reveal its target.
if (location.hash) {
    const target = document.getElementById(location.hash.slice(1));
    if (target) {
        const details = target.closest('details');
        if (details) details.open = true;
    }
}
