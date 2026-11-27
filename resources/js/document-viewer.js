import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx';

const viewer = document.querySelector('[data-document-viewer]');

if (viewer) {
    const kind = viewer.dataset.previewKind;
    const contentUrl = viewer.dataset.contentUrl;
    const loading = viewer.querySelector('[data-preview-loading]');
    const stage = viewer.querySelector('[data-preview-stage]');
    const error = viewer.querySelector('[data-preview-error]');
    const errorMessage = viewer.querySelector('[data-preview-error-message]');
    const zoomTarget = viewer.querySelector('[data-preview-zoom-target]');
    const zoomValue = viewer.querySelector('[data-preview-zoom-value]');
    const zoomIn = viewer.querySelector('[data-preview-zoom-in]');
    const zoomOut = viewer.querySelector('[data-preview-zoom-out]');
    const zoomReset = viewer.querySelector('[data-preview-zoom-reset]');
    const docxBody = viewer.querySelector('[data-docx-body]');
    const docxStyles = viewer.querySelector('[data-docx-styles]');
    const spreadsheetBody = viewer.querySelector('[data-spreadsheet-body]');
    const nativePreview = viewer.querySelector('[data-native-preview]');

    let zoom = 1;
    let objectUrl = null;

    const setLoading = (isLoading) => {
        if (loading) loading.hidden = !isLoading;
    };

    const setError = (message) => {
        setLoading(false);
        if (stage) stage.hidden = true;
        if (errorMessage) errorMessage.textContent = message;
        if (error) error.hidden = false;
    };

    const setReady = () => {
        setLoading(false);
        if (error) error.hidden = true;
        if (stage) stage.hidden = false;
    };

    const updateZoom = () => {
        if (zoomTarget) {
            zoomTarget.style.setProperty('--preview-zoom', String(zoom));
            zoomTarget.style.zoom = String(zoom);
        }
        if (zoomValue) zoomValue.textContent = `${Math.round(zoom * 100)}%`;
        if (zoomReset) zoomReset.disabled = zoom === 1;
    };

    const changeZoom = (amount) => {
        zoom = Math.max(0.65, Math.min(2, Number((zoom + amount).toFixed(2))));
        updateZoom();
    };

    zoomIn?.addEventListener('click', () => changeZoom(0.1));
    zoomOut?.addEventListener('click', () => changeZoom(-0.1));
    zoomReset?.addEventListener('click', () => {
        zoom = 1;
        updateZoom();
    });

    document.addEventListener('keydown', (event) => {
        if (!viewer.contains(document.activeElement)) return;
        if (event.key === '+' || event.key === '=') changeZoom(0.1);
        if (event.key === '-') changeZoom(-0.1);
        if (event.key === '0') {
            zoom = 1;
            updateZoom();
        }
    });

    const fetchPreview = async () => {
        if (!contentUrl) throw new Error('The preview URL is unavailable.');

        const response = await fetch(contentUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/octet-stream, application/pdf, image/*' },
        });

        if (!response.ok) {
            if (response.status === 403 || response.status === 401) {
                throw new Error('You no longer have permission to view this document.');
            }
            if (response.status === 413) {
                throw new Error('This document is too large to preview safely.');
            }
            throw new Error('The document could not be loaded from ProjectLink.');
        }

        const blob = await response.blob();
        if (!blob.size) throw new Error('The uploaded document is empty or corrupted.');

        return blob;
    };

    const waitForNativePreview = (blob) => new Promise((resolve, reject) => {
        if (!nativePreview) {
            reject(new Error('The native preview container is unavailable.'));
            return;
        }

        objectUrl = URL.createObjectURL(blob);
        nativePreview.hidden = false;
        nativePreview.addEventListener('load', resolve, { once: true });
        nativePreview.addEventListener('error', () => reject(new Error('The browser could not render this document.')), { once: true });
        nativePreview.src = objectUrl;
    });

    const renderSpreadsheet = async (blob) => {
        if (!spreadsheetBody) {
            throw new Error('The spreadsheet preview container is unavailable.');
        }

        const workbook = XLSX.read(await blob.arrayBuffer(), {
            type: 'array',
            cellDates: true,
            cellNF: false,
            cellStyles: false,
        });

        if (!workbook.SheetNames.length) {
            throw new Error('This workbook does not contain any sheets.');
        }

        spreadsheetBody.replaceChildren();

        const tabs = document.createElement('div');
        tabs.className = 'tpl-viewer-sheet-tabs';
        tabs.setAttribute('role', 'tablist');

        const sheetStage = document.createElement('div');
        sheetStage.className = 'tpl-viewer-sheet-stage';

        const renderSheet = (sheetName, activeTab) => {
            const worksheet = workbook.Sheets[sheetName];
            const rows = XLSX.utils.sheet_to_json(worksheet, {
                header: 1,
                raw: false,
                defval: '',
            });
            const limitedRows = rows.slice(0, 200);
            const columnCount = Math.min(
                limitedRows.reduce((largest, row) => Math.max(largest, row.length), 0),
                30
            );

            sheetStage.replaceChildren();

            if (!columnCount || !limitedRows.length) {
                const empty = document.createElement('p');
                empty.className = 'tpl-viewer-sheet-empty';
                empty.textContent = 'This sheet is empty.';
                sheetStage.appendChild(empty);
                return;
            }

            const table = document.createElement('table');
            table.className = 'tpl-viewer-spreadsheet-table';

            limitedRows.forEach((row, rowIndex) => {
                const tableRow = document.createElement('tr');
                const rowNumber = document.createElement('th');
                rowNumber.className = 'tpl-viewer-spreadsheet-row-number';
                rowNumber.scope = 'row';
                rowNumber.textContent = String(rowIndex + 1);
                tableRow.appendChild(rowNumber);

                for (let columnIndex = 0; columnIndex < columnCount; columnIndex += 1) {
                    const cell = document.createElement(rowIndex === 0 ? 'th' : 'td');
                    if (rowIndex === 0) cell.scope = 'col';
                    cell.textContent = row[columnIndex] == null ? '' : String(row[columnIndex]);
                    tableRow.appendChild(cell);
                }

                table.appendChild(tableRow);
            });

            const note = document.createElement('p');
            note.className = 'tpl-viewer-sheet-note';
            note.textContent = rows.length > limitedRows.length || columnCount < Math.max(...rows.map((row) => row.length), 0)
                ? `Preview limited to ${limitedRows.length} rows and ${columnCount} columns for safe browser rendering.`
                : `${limitedRows.length} row${limitedRows.length === 1 ? '' : 's'} displayed.`;

            sheetStage.append(table, note);
            activeTab.setAttribute('aria-selected', 'true');
        };

        workbook.SheetNames.forEach((sheetName, index) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'tpl-viewer-sheet-tab';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            tab.textContent = sheetName;
            tab.addEventListener('click', () => {
                tabs.querySelectorAll('[role="tab"]').forEach((otherTab) => {
                    otherTab.setAttribute('aria-selected', otherTab === tab ? 'true' : 'false');
                });
                renderSheet(sheetName, tab);
            });
            tabs.appendChild(tab);
        });

        spreadsheetBody.append(tabs, sheetStage);
        renderSheet(workbook.SheetNames[0], tabs.querySelector('[role="tab"]'));
    };

    const renderDocument = async () => {
        if (kind === 'unsupported') {
            setError('This file type cannot be rendered in the browser. Use Download to open it.');
            return;
        }

        try {
            const blob = await fetchPreview();

            if (kind === 'docx') {
                await renderAsync(blob, docxBody, docxStyles, {
                    className: 'docx',
                    breakPages: true,
                    renderHeaders: true,
                    renderFooters: true,
                    renderFootnotes: true,
                    renderEndnotes: true,
                    renderAltChunks: false,
                });
            } else if (kind === 'spreadsheet') {
                await renderSpreadsheet(blob);
            } else {
                await waitForNativePreview(blob);
            }

            setReady();
        } catch (renderError) {
            console.error('Template preview failed:', renderError);
            setError(renderError instanceof Error
                ? renderError.message
                : 'The document could not be rendered.');
        }
    };

    updateZoom();
    renderDocument();

    window.addEventListener('beforeunload', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
}
