document.addEventListener('DOMContentLoaded', function() {
    if (!getCookie('cookieConsent')) {
        showCookieConsent();
    }
});

function showCookieConsent() {
    const cookieConsent = document.createElement('div');
    cookieConsent.className = 'cookie-consent';
    cookieConsent.innerHTML = `
        <div class="cookie-consent-content">
            <h3>Aviso de Cookies</h3>
            <p>Utilizamos cookies para mejorar tu experiencia en nuestro sitio web. Al continuar navegando, aceptas nuestra política de cookies.</p>
        </div>
        <div class="cookie-consent-buttons">
            <button class="cookie-consent-button cookie-consent-accept">Aceptar</button>
            <button class="cookie-consent-button cookie-consent-decline">Rechazar</button>
        </div>
    `;

    document.body.appendChild(cookieConsent);
    
    setTimeout(() => {
        cookieConsent.classList.add('show');
    }, 100);

    cookieConsent.querySelector('.cookie-consent-accept').addEventListener('click', function() {
        setCookie('cookieConsent', 'accepted', 365);
        hideCookieConsent(cookieConsent);
    });

    cookieConsent.querySelector('.cookie-consent-decline').addEventListener('click', function() {
        setCookie('cookieConsent', 'declined', 365);
        hideCookieConsent(cookieConsent);
    });
}

function hideCookieConsent(element) {
    element.classList.remove('show');
    setTimeout(() => {
        element.remove();
    }, 300);
}

function setCookie(name, value, days) {
    let expires = '';
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + value + expires + '; path=/';
}

function getCookie(name) {
    const nameEQ = name + '=';
    const ca = document.cookie.split(';');
    for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}