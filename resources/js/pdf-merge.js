/*
 * Joining PDFs, in the browser.
 *
 * Nothing here talks to the server: the files are read off the user's disk,
 * stitched together in memory and handed back as a download. That is the point
 * of doing it client-side — waybills and invoices never leave the machine, and
 * the host's upload limit never comes into it.
 *
 * Kept in a module of its own so that pdf-lib (a few hundred kilobytes) is only
 * fetched by the one page that joins PDFs; app.js imports this lazily.
 */
import { PDFDocument } from 'pdf-lib';

/** Why a file cannot be used, in words the page can show as they are. */
function explain(error) {
    const text = String(error?.message ?? error);

    if (/encrypted/i.test(text)) {
        return 'Fișierul este protejat cu parolă și nu poate fi unit.';
    }

    return 'Fișierul nu a putut fi citit — nu pare un PDF valid.';
}

/**
 * Open one file far enough to count its pages, which is also the earliest
 * moment a password-protected or broken file can be told from a good one —
 * better said beside the file as it is added than after "Unește" is pressed.
 *
 * @returns {Promise<{pages: number} | {error: string}>}
 */
export async function inspectPdf(file) {
    try {
        const document = await PDFDocument.load(await file.arrayBuffer());

        return { pages: document.getPageCount() };
    } catch (error) {
        return { error: explain(error) };
    }
}

/**
 * Every page of every file, in the order given, as one document.
 *
 * @param {Array<Blob>} files
 * @returns {Promise<Uint8Array>}
 */
export async function mergePdfs(files) {
    const merged = await PDFDocument.create();

    for (const file of files) {
        const source = await PDFDocument.load(await file.arrayBuffer());
        const pages = await merged.copyPages(source, source.getPageIndices());

        pages.forEach((page) => merged.addPage(page));
    }

    return merged.save();
}
