import { renderAsync } from 'docx-preview';

// One shared viewer also handles links added after the page has loaded.
if (!window.ProjectLinkFilePreview) {
    const previewableExtensions = new Set(['docx', 'pdf', 'png', 'jpg', 'jpeg', 'webp', 'xlsx', 'xls']);
    const imageExtensions = new Set(['png', 'jpg', 'jpeg', 'webp']);
    let modal = null;
    let objectUrl = null;
    let renderToken = 0;
    let lastFocusedElement = null;
    let previousBodyOverflow = '';
    let zoom = 1;
    let remoteRequest = null;
    let downloadUrl = '';

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
                        <span class="proposal-preview-modal__eyebrow">Document preview</span>
                        <h2 id="proposal-preview-title" data-preview-title></h2>
                        <p data-preview-meta></p>
                    </div>
                    <div class="proposal-preview-modal__controls" aria-label="Preview controls">
                        <a class="proposal-preview-download" data-preview-download download hidden>Download file</a>
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

    const setDownload = (file) => {
        const download = modal?.querySelector('[data-preview-download]');
        if (!download) return;

        if (downloadUrl) {
            download.href = downloadUrl;
        } else if (file instanceof Blob) {
            objectUrl ||= URL.createObjectURL(file);
            download.href = objectUrl;
        } else {
            return;
        }

        download.download = file?.name || '';
        download.hidden = false;
    };

    const setError = (message, file) => {
        if (!modal) return;
        setLoading(false);

        const stage = modal.querySelector('[data-preview-stage]');
        const error = modal.querySelector('[data-preview-error]');
        const errorMessage = modal.querySelector('[data-preview-error-message]');

        if (stage) stage.hidden = true;
        if (errorMessage) errorMessage.textContent = message;
        if (error) error.hidden = false;

        setDownload(file);
    };

    const cleanContent = () => {
        renderToken += 1;
        remoteRequest?.abort();
        remoteRequest = null;
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

        objectUrl ||= URL.createObjectURL(file);
        const element = extension === 'pdf'
            ? document.createElement('iframe')
            : document.createElement('img');

        element.className = extension === 'pdf'
            ? 'proposal-preview-native proposal-preview-pdf'
            : 'proposal-preview-native proposal-preview-image';
        element.title = file.name;
        if (extension !== 'pdf') element.alt = file.name;

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
            + ' files are available using the Download file button. Close this preview to return to your page.';
        message.append(icon, heading, details);
        paper.appendChild(message);

        setDownload(file);
    };

    const renderSpreadsheet = async (file, token) => {
        const XLSX = await import('xlsx');
        const bytes = await file.arrayBuffer();
        if (token !== renderToken) return;

        const workbook = XLSX.read(bytes, { type: 'array', cellDates: true, cellStyles: false });
        if (!workbook.SheetNames.length) throw new Error('This workbook does not contain any sheets.');

        const paper = modal.querySelector('[data-preview-paper]');
        const tabs = document.createElement('div');
        tabs.className = 'proposal-preview-sheet-tabs';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', 'Workbook sheets');
        const stage = document.createElement('div');
        stage.className = 'proposal-preview-sheet-stage';
        stage.setAttribute('role', 'tabpanel');

        const showSheet = (name, selectedTab) => {
            tabs.querySelectorAll('button').forEach((tab) => tab.setAttribute('aria-selected', String(tab === selectedTab)));
            stage.replaceChildren();
            const rows = XLSX.utils.sheet_to_json(workbook.Sheets[name], { header: 1, raw: false, defval: '' });
            const table = document.createElement('table');
            rows.slice(0, 200).forEach((row, index) => {
                const tr = document.createElement('tr');
                row.slice(0, 30).forEach((value) => {
                    const cell = document.createElement(index === 0 ? 'th' : 'td');
                    cell.textContent = String(value ?? '');
                    tr.appendChild(cell);
                });
                table.appendChild(tr);
            });
            const note = document.createElement('p');
            note.textContent = rows.length ? 'Preview shows up to 200 rows and 30 columns. Download the file for the complete workbook.' : 'This sheet is empty.';
            stage.append(table, note);
        };

        workbook.SheetNames.forEach((name, index) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', String(index === 0));
            tab.textContent = name;
            tab.addEventListener('click', () => showSheet(name, tab));
            tabs.appendChild(tab);
        });
        paper.append(tabs, stage);
        showSheet(workbook.SheetNames[0], tabs.firstElementChild);
    };

    const preparePreview = (name, description, remoteDownloadUrl = '') => {
        const currentModal = ensureModal();
        if (currentModal.hidden) {
            const previouslyFocused = document.activeElement;
            lastFocusedElement = previouslyFocused instanceof HTMLElement ? previouslyFocused : null;
            previousBodyOverflow = document.body.style.overflow;
        }
        document.body.style.overflow = 'hidden';
        currentModal.hidden = false;
        zoom = 1;
        updateZoom();
        cleanContent();
        downloadUrl = remoteDownloadUrl;

        currentModal.querySelector('[data-preview-title]').textContent = name;
        currentModal.querySelector('[data-preview-meta]').textContent = description;
        currentModal.querySelector('[data-preview-error-message]').textContent = 'We could not render this file in the browser.';
        currentModal.querySelector('[data-preview-download]').hidden = true;
        setLoading(true);
        setDownload({ name });
        currentModal.querySelector('.proposal-preview-control--close')?.focus();

        return renderToken;
    };

    const renderFile = async (file, token) => {
        if (token !== renderToken) return;
        const extension = extensionOf(file);
        modal.querySelector('[data-preview-meta]').textContent = `${extension ? extension.toUpperCase() : 'FILE'} · ${formatBytes(file.size)}`;
        setDownload(file);

        if (!file.size) {
            setError('This file is empty or corrupted.', file);
            return;
        }

        try {
            if (extension === 'docx') {
                await renderDocxPreview(file, token);
            } else if (extension === 'xlsx' || extension === 'xls') {
                await renderSpreadsheet(file, token);
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

    const openPreview = async (file) => {
        if (!file || !file.name) return;
        const token = preparePreview(file.name, 'Loading document preview...');
        await renderFile(file, token);
    };

    const openRemotePreview = async (link, url) => {
        const name = link.dataset.previewName || decodeURIComponent(url.pathname.split('/').pop()) || 'Document';
        const token = preparePreview(name, 'Loading document preview...', link.dataset.downloadUrl || link.href);
        const extension = extensionOf({ name });
        if (!previewableExtensions.has(extension)) {
            renderUnsupportedPreview({ name }, extension);
            setReady();
            return;
        }

        const controller = new AbortController();
        remoteRequest = controller;
        try {
            const response = await fetch(url.href, { credentials: 'same-origin', signal: controller.signal });
            if (token !== renderToken) return;
            if (response.status === 401 || response.status === 403) throw new Error('You no longer have permission to view this document.');
            if (response.status === 413) throw new Error('This document is too large to preview safely.');
            if (response.status === 422) throw new Error('This attachment is incomplete or corrupted. Ask the uploader to upload it again.');
            if (!response.ok || response.headers.get('content-type')?.includes('text/html')) {
                throw new Error('The document could not be loaded. Your session may have expired.');
            }
            const blob = await response.blob();
            if (token !== renderToken) return;
            await renderFile(new File([blob], name, { type: blob.type }), token);
        } catch (error) {
            if (token !== renderToken || controller.signal.aborted) return;
            setError(error instanceof Error ? error.message : 'The document could not be loaded.');
        } finally {
            if (remoteRequest === controller) remoteRequest = null;
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
        if (event.key === 'Escape') {
            event.preventDefault();
            closePreview();
        }
        if (event.key === 'Tab') {
            const controls = [...modal.querySelectorAll('button:not(:disabled), a[href], iframe')].filter((element) => !element.hidden);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
        if (event.key === '+' || event.key === '=') changeZoom(0.1);
        if (event.key === '-') changeZoom(-0.1);
        if (event.key === '0') {
            zoom = 1;
            updateZoom();
        }
    });

    // Capture preview clicks before unsaved-form navigation confirmation.
    // Ordinary links, forms, downloads, and modified clicks keep browser defaults.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (!(event.target instanceof Element)) return;
        const link = event.target.closest('a[data-file-preview]');
        if (!link || link.hasAttribute('download')) return;
        const url = new URL(link.dataset.previewUrl || link.href, window.location.href);
        if (url.origin !== window.location.origin || !['http:', 'https:', 'blob:'].includes(url.protocol)) return;
        event.preventDefault();
        void openRemotePreview(link, url);
    }, { capture: true });

    window.ProjectLinkFilePreview = {
        open: openPreview,
        close: closePreview,
        canPreview: (file) => previewableExtensions.has(extensionOf(file)),
    };

    window.addEventListener('pagehide', closePreview);
}
