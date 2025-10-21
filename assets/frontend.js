window.rcbInit = function() {
    const containers = document.querySelectorAll('.rcb-container');

    containers.forEach(container => {
        const button = container.querySelector('.rcb-reveal-button');
        const recaptchaWrapper = container.querySelector('.rcb-recaptcha-wrapper');
        const contentPlaceholder = container.querySelector('.rcb-protected-content-placeholder');
        const transientKey = container.dataset.transientKey;

        if (!button || !recaptchaWrapper || !contentPlaceholder || !transientKey) {
            console.error('RCB Block: Manca un elemento essenziale nel container.', container);
            return;
        }

        button.addEventListener('click', () => {
            button.style.display = 'none';

            grecaptcha.render(recaptchaWrapper, {
                'sitekey': rcb_data.site_key,
                'callback': (token) => {
                    verifyRecaptcha(token, container);
                }
            });
        }, { once: true });
    });
};

async function verifyRecaptcha(token, container) {
    const recaptchaWrapper = container.querySelector('.rcb-recaptcha-wrapper');
    const contentPlaceholder = container.querySelector('.rcb-protected-content-placeholder');

    recaptchaWrapper.style.display = 'none';
    contentPlaceholder.innerHTML = `<p class="rcb-loading">${rcb_data.loading_message}</p>`;

    const formData = new FormData();
    formData.append('action', 'rcb_verify_recaptcha');
    formData.append('nonce', rcb_data.nonce);
    formData.append('token', token);

    formData.append('transient_key', container.dataset.transientKey);

    try {
        const response = await fetch(rcb_data.ajax_url, {
            method: 'POST',
            body: formData
        });

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

