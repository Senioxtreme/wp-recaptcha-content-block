window.rcbCaptchaInit = function() {
    const containers = document.querySelectorAll('.rcb-container');

    containers.forEach(container => {
        const button = container.querySelector('.rcb-reveal-button');
        const captchaWrapper = container.querySelector('.rcb-captcha-wrapper');
        const contentPlaceholder = container.querySelector('.rcb-protected-content-placeholder');
        const transientKey = container.dataset.transientKey;

        if (!button || !captchaWrapper || !contentPlaceholder || !transientKey) {
            console.error('RCB Block: Manca un elemento essenziale nel container.', container);
            return;
        }

        button.addEventListener('click', () => {
            button.style.display = 'none';
            const callback = (token) => verifyCaptcha(token, container);
            const theme = container.dataset.theme || 'light';
            const mode = container.dataset.mode || 'normal';

            const renderOptions = {
                'sitekey': rcb_data.site_key,
                'callback': callback,
                'theme': theme,
            };

            // Imposta la dimensione per reCAPTCHA/hCaptcha
            if (mode === 'invisible' && rcb_data.provider !== 'turnstile') {
                renderOptions.size = 'invisible';
            }
            
            // Per la modalità invisibile, mostriamo subito il caricamento
            if (mode === 'invisible') {
                 contentPlaceholder.innerHTML = `<p class="rcb-loading">${rcb_data.loading_message}</p>`;
            }

            switch (rcb_data.provider) {
                case 'hcaptcha':
                    const hcaptchaWidgetId = hcaptcha.render(captchaWrapper, renderOptions);
                    if (mode === 'invisible') {
                        hcaptcha.execute(hcaptchaWidgetId, { async: true });
                    }
                    break;
                case 'turnstile':
                    // Turnstile gestisce la modalità "invisible" tramite 'execution'
                    if (mode === 'invisible') {
                         renderOptions.execution = 'execute';
                    }
                    turnstile.render(captchaWrapper, renderOptions);
                    break;
                case 'recaptcha':
                default:
                    const recaptchaWidgetId = grecaptcha.render(captchaWrapper, renderOptions);
                    if (mode === 'invisible') {
                        grecaptcha.execute(recaptchaWidgetId);
                    }
                    break;
            }

        }, { once: true });
    });
};

async function verifyCaptcha(token, container) {
    const captchaWrapper = container.querySelector('.rcb-captcha-wrapper');
    const contentPlaceholder = container.querySelector('.rcb-protected-content-placeholder');

    captchaWrapper.innerHTML = ''; // Svuota il contenitore del captcha
    captchaWrapper.style.display = 'none';
    contentPlaceholder.innerHTML = `<p class="rcb-loading">${rcb_data.loading_message}</p>`;

    const formData = new FormData();
    formData.append('action', 'rcb_verify_captcha');
    formData.append('nonce', rcb_data.nonce);
    formData.append('token', token);
    formData.append('transient_key', container.dataset.transientKey);

    try {
        const response = await fetch(rcb_data.ajax_url, { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            contentPlaceholder.innerHTML = result.data.html;
        } else {
            contentPlaceholder.innerHTML = `<p class="rcb-error">${result.data.message || rcb_data.error_message}</p>`;
        }
    } catch (error) {
        console.error('RCB Block: La richiesta AJAX è fallita.', error);
        contentPlaceholder.innerHTML = `<p class="rcb-error">${rcb_data.error_message}</p>`;
    }
}

