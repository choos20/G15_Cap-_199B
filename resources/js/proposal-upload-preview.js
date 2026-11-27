import { renderAsync } from 'docx-preview';

const uploadForm = document.querySelector('[data-proposal-upload-form]');

if (uploadForm) {
    const previewableExtensions = new Set(['docx', 'pdf', 'png', 'jpg', 'jpeg', 'webp']);
    const imageExtensions = new Set(['png', 'jpg', 'jpeg', 'webp']);
    let modal = null;
    let objectUrl = null;
    let renderToken = 0;
    let lastFocusedElement = null;
    let previousBodyOverflow = '';
    let zoom = 1;

    const extensionOf = (file) => {
        const name = String(file?.name || '');
        const parts = name.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    };

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    };

    const ensureModal = () => {
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'proposal-preview-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="proposal-preview-modal__backdrop" data-preview-close></div>
            <section class="proposal-preview-modal__panel" role="dialog" aria-modal="true" aria-labelledby="proposal-preview-title">
                <header class="proposal-preview-modal__toolbar">
                    <div class="proposal-preview-modal__heading">
                        <span class="proposal-preview-modal__eyebrow">Local file preview</span>
                        <h2 id="proposal-preview-title" data-preview-title></h2>
                        <p data-preview-meta></p>
                    </div>
                    <div class="proposal-preview-modal__controls" aria-label="Preview controls">
                        <button type="button" class="proposal-preview-control" data-preview-zoom-out aria-label="Zoom out">−</button>
                        <span class="proposal-preview-zoom-value" data-preview-zoom-value>100%</span>
                        <button type="button" class="proposal-preview-control" data-preview-zoom-in aria-label="Zoom in">+</button>
                        <button type="button" class="proposal-preview-control proposal-preview-control--reset" data-preview-zoom-reset>Reset</button>
                        <button type="button" class="proposal-preview-control proposal-preview-control--close" data-preview-close aria-label="Close preview">×</button>
                    </div>
                </header>
                <div class="proposal-preview-modal__viewport">
                    <div class="proposal-preview-loading" data-preview-loading role="status" aria-live="polite">
                        <span class="proposal-preview-spinner" aria-hidden="true"></span>
                        <span>Loading document preview...</span>
                    </div>
                    <div class="proposal-preview-stage" data-preview-stage hidden>
                        <div class="proposal-preview-zoom-target" data-preview-zoom-target>
                            <div class="proposal-preview-paper" data-preview-paper></div>
                        </div>
                    </div>
                    <div class="proposal-preview-error" data-preview-error role="alert" hidden>
                        <h3>Preview unavailable</h3>
                        <p data-preview-error-message>We could not render this file in the browser.</p>
                        <a class="proposal-preview-download" data-preview-download download hidden>Download file</a>
                    </div>
                </div>
            </section>
        `;

        document.body.appendChild(modal);

        modal.querySelectorAll('[data-preview-close]').forEach((element) => {
            element.addEventListener('click', closePreview);
        });
        modal.querySelector('[data-preview-zoom-in]')?.addEventListener('click', () => changeZoom(0.1));
        modal.querySelector('[data-preview-zoom-out]')?.addEventListener('click', () => changeZoom(-0.1));
        modal.querySelector('[data-preview-zoom-reset]')?.addEventListener('click', () => {
            zoom = 1;
            updateZoom();
        });

        return modal;
    };

    const updateZoom = () => {
        if (!modal) return;

        const target = modal.querySelector('[data-preview-zoom-target]');
        const value = modal.querySelector('[data-preview-zoom-value]');
        const reset = modal.querySelector('[data-preview-zoom-reset]');

        if (target) target.style.zoom = String(zoom);
        if (value) value.textContent = `${Math.round(zoom * 100)}%`;
        if (reset) reset.disabled = zoom === 1;
    };

    const changeZoom = (amount) => {
        zoom = Math.max(0.65, Math.min(2, Number((zoom + amount).toFixed(2))));
        updateZoom();
    };

    const revokeObjectUrl = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const setLoading = (isLoading) => {
        if (!modal) return;
        const loading = modal.querySelector('[data-preview-loading]');
        if (loading) loading.hidden = !isLoading;
    };

    const setReady = () => {
        if (!modal) return;
        setLoading(false);
        const stage = modal.querySelector('[data-preview-stage]');
        const error = modal.querySelector('[data-preview-error]');
        if (stage) stage.hidden = false;
        if (error) error.hidden = true;
    };

    const setError = (message, file) => {
        if (!modal) return;
        setLoading(false);

        const stage = modal.querySelector('[data-preview-stage]');
        const error = modal.querySelector('[data-preview-error]');
        const errorMessage = modal.querySelector('[data-preview-error-message]');
        const download = modal.querySelector('[data-preview-download]');

        if (stage) stage.hidden = true;
        if (errorMessage) errorMessage.textContent = message;
        if (error) error.hidden = false;

        if (download && file) {
            revokeObjectUrl();
            objectUrl = URL.createObjectURL(file);
            download.href = objectUrl;
            download.download = file.name;
            download.hidden = false;
        }
    };

    const cleanContent = () => {
        renderToken += 1;
        revokeObjectUrl();

        if (!modal) return;
        const paper = modal.querySelector('[data-preview-paper]');
        const download = modal.querySelector('[data-preview-download]');
        const error = modal.querySelector('[data-preview-error]');
        const stage = modal.querySelector('[data-preview-stage]');

        paper?.replaceChildren();
        if (download) {
            download.removeAttribute('href');
            download.hidden = true;
        }
        if (error) error.hidden = true;
        if (stage) stage.hidden = true;
    };

    const renderNativePreview = (file, extension, token) => new Promise((resolve, reject) => {
        if (!modal) {
            reject(new Error('The preview window is unavailable.'));
            return;
        }

        const paper = modal.querySelector('[data-preview-paper]');
        if (!paper) {
            reject(new Error('The preview canvas is unavailable.'));
            return;
        }

        objectUrl = URL.createObjectURL(file);
        const element = extension === 'pdf'
            ? document.createElement('iframe')
            : document.createElement('img');

        element.className = extension === 'pdf'
            ? 'proposal-preview-native proposal-preview-pdf'
            : 'proposal-preview-native proposal-preview-image';
        element.title = file.name;

        const onLoad = () => {
            if (token === renderToken) resolve();
        };
        const onError = () => reject(new Error('The browser could not render this file.'));

        element.addEventListener('load', onLoad, { once: true });
        element.addEventListener('error', onError, { once: true });
        element.src = objectUrl;
        paper.appendChild(element);
    });

    const renderDocxPreview = async (file, token) => {
        if (!modal) throw new Error('The preview window is unavailable.');

        const paper = modal.querySelector('[data-preview-paper]');
        if (!paper) throw new Error('The preview canvas is unavailable.');

        const styles = document.createElement('div');
        styles.className = 'proposal-preview-docx-styles';
        styles.setAttribute('aria-hidden', 'true');
        const body = document.createElement('div');
        body.className = 'proposal-preview-docx-body';
        paper.append(styles, body);

        await renderAsync(file, body, styles, {
            className: 'docx',
            breakPages: true,
            renderHeaders: true,
            renderFooters: true,
            renderFootnotes: true,
            renderEndnotes: true,
            renderAltChunks: false,
        });

        if (token !== renderToken) throw new Error('Preview closed before rendering completed.');
    };

    const renderUnsupportedPreview = (file, extension) => {
        if (!modal) return;
        const paper = modal.querySelector('[data-preview-paper]');
        if (!paper) return;

        const message = document.createElement('div');
        message.className = 'proposal-preview-unsupported';
        const icon = document.createElement('span');
        icon.className = 'proposal-preview-unsupported__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '📄';
        const heading = document.createElement('h3');
        heading.textContent = 'This file type is not rendered in the browser';
        const details = document.createElement('p');
        details.textContent = (extension ? extension.toUpperCase() : 'This format')
            + ' files can still be attached safely. Close this preview to remove the file or continue your submission.';
        message.append(icon, heading, details);
        paper.appendChild(message);

        const download = modal.querySelector('[data-preview-download]');
        if (download) {
            objectUrl = URL.createObjectURL(file);
            download.href = objectUrl;
            download.download = file.name;
            download.textContent = 'Download file';
            download.hidden = false;
        }
    };

    const openPreview = async (file) => {
        if (!file || !file.name) return;

        const currentModal = ensureModal();
        const extension = extensionOf(file);
        const previouslyFocused = document.activeElement;

        lastFocusedElement = previouslyFocused instanceof HTMLElement ? previouslyFocused : null;
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        currentModal.hidden = false;
        zoom = 1;
        updateZoom();
        cleanContent();
        const token = renderToken;

        currentModal.querySelector('[data-preview-title]').textContent = file.name;
        currentModal.querySelector('[data-preview-meta]').textContent = `${extension ? extension.toUpperCase() : 'FILE'} · ${formatBytes(file.size)}`;
        currentModal.querySelector('[data-preview-error-message]').textContent = 'We could not render this file in the browser.';
        currentModal.querySelector('[data-preview-download]').hidden = true;
        setLoading(true);
        currentModal.querySelector('.proposal-preview-control--close')?.focus();

        if (!file.size) {
            setError('This file is empty or corrupted.', file);
            return;
        }

        try {
            if (extension === 'docx') {
                await renderDocxPreview(file, token);
            } else if (extension === 'pdf' || imageExtensions.has(extension)) {
                await renderNativePreview(file, extension, token);
            } else {
                renderUnsupportedPreview(file, extension);
            }

            if (token === renderToken) setReady();
        } catch (error) {
            if (token !== renderToken) return;
            console.error('Proposal upload preview failed:', error);
            setError(error instanceof Error ? error.message : 'The file could not be rendered.', file);
        }
    };

    function closePreview() {
        if (!modal || modal.hidden) return;

        cleanContent();
        modal.hidden = true;
        document.body.style.overflow = previousBodyOverflow;
        lastFocusedElement?.focus?.();
        lastFocusedElement = null;
    }

    document.addEventListener('keydown', (event) => {
        if (!modal || modal.hidden) return;
        if (event.key === 'Escape') closePreview();
        if (event.key === '+' || event.key === '=') changeZoom(0.1);
        if (event.key === '-') changeZoom(-0.1);
        if (event.key === '0') {
            zoom = 1;
            updateZoom();
        }
    });

    window.ProjectLinkFilePreview = {
        open: openPreview,
        close: closePreview,
        canPreview: (file) => previewableExtensions.has(extensionOf(file)),
    };

    window.addEventListener('beforeunload', revokeObjectUrl);
}
