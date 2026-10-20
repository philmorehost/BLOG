/* Keep the live editor (including File inputs) intact when saving fails. */
(function () {
    function showError(form, message) {
        const alert = form.querySelector('[data-editor-error]');
        alert.textContent = message;
        alert.classList.remove('d-none');
        alert.focus();
    }

    function init() {
        document.querySelectorAll('[data-post-editor]').forEach(form => {
            form.addEventListener('submit', async event => {
                if (!window.fetch || !window.FormData || !window.DOMParser) return;
                event.preventDefault();
                if (form.dataset.saving === '1') return;
                const submitter = event.submitter || form.querySelector('button[type="submit"]');
                const body = new FormData(form);
                // FormData(form) does not include the clicked submit button.
                if (submitter && submitter.name) body.set(submitter.name, submitter.value || '1');
                form.dataset.saving = '1';
                const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
                buttons.forEach(button => { button.disabled = true; });
                form.querySelector('[data-editor-error]').classList.add('d-none');
                try {
                    const response = await fetch(form.action || window.location.href, {
                        method: 'POST', body, credentials: 'same-origin'
                    });
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const error = page.querySelector('[data-post-result="error"]');
                    if (error) {
                        showError(form, error.textContent.trim());
                    } else if (response.ok && !response.redirected && page.querySelector('[data-post-result="success"]')) {
                        // A GET refresh updates the registry without resubmitting the post.
                        window.location.assign(window.location.href);
                    } else {
                        showError(form, 'The server could not confirm the save. Your draft and selected image are still here. Check your login and the post registry before retrying to avoid a duplicate post.');
                    }
                } catch (error) {
                    showError(form, 'The connection was interrupted. Your draft and selected image are still here. Check the post registry before retrying in case the save completed.');
                } finally {
                    delete form.dataset.saving;
                    buttons.forEach(button => { button.disabled = false; });
                }
            });
        });

        const state = document.getElementById('failed-post-editor');
        if (!state) return;
        const draft = JSON.parse(state.textContent);
        if (!draft) return;
        const modal = document.getElementById(draft.modal);
        const form = modal.querySelector('[data-post-editor]');
        Object.entries(draft.fields).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field || field.type === 'file') return;
            if (field.type === 'checkbox') field.checked = Boolean(value);
            else field.value = value;
        });
        const slug = form.elements.namedItem('slug');
        if (slug) slug.dataset.edited = '1';
        const error = document.querySelector('[data-post-result="error"]');
        showError(form, (error ? error.textContent.trim() : 'Unable to save the post.') + ' Your text has been restored. Please reselect the image file if needed.');
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
