export const docxPreviewOptions = {
    className: 'docx',
    breakPages: true,
    renderHeaders: true,
    renderFooters: true,
    renderFootnotes: true,
    renderEndnotes: true,
    renderAltChunks: false,
};

export async function previewBlob(response) {
    if (!response.ok) {
        const messages = {
            401: 'Your session has expired. Sign in again to preview this document.',
            403: 'You do not have permission to view this document at its current review stage.',
            404: 'This document is missing from storage. An administrator can check backups for an intact copy.',
            413: 'This document exceeds the browser preview limit. Download the original file instead.',
            415: 'This file type is available for download only.',
            422: 'This stored document failed its integrity check. An administrator can check for a recoverable copy.',
            503: 'Document preview is temporarily unavailable on the server. Please try again later.',
        };
        let message = messages[response.status] || 'The document could not be loaded from ProjectLink. Please try again later.';
        if ([413, 415, 422, 503].includes(response.status) && response.headers.get('content-type')?.includes('application/json')) {
            const error = await response.json().catch(() => null);
            if (typeof error?.message === 'string') message = error.message;
        }
        throw new Error(message);
    }
    if (response.headers.get('content-type')?.includes('text/html')) {
        throw new Error('The document response was replaced by a web page. Sign in again if your session has expired.');
    }
    const blob = await response.blob();
    if (!blob.size) throw new Error('The document response is empty. Please try again or ask an administrator to check storage.');
    const expected = response.headers.get('X-Document-SHA256');
    if (expected && globalThis.crypto?.subtle) {
        const digest = await globalThis.crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
        const actual = [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('');
        if (actual !== expected.toLowerCase()) throw new Error('The document changed while being transferred. Please retry the preview.');
    }
    return blob;
}
