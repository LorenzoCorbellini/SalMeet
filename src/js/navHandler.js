/**
 * @file navHandler.js
 * @description Gestisce l'evidenziazione dinamica della voce di navigazione attiva all'interno 
 * della barra di menu (navbar), basandosi sull'URL della pagina correntemente visualizzata.
 */

/**
 * Inizializzazione eseguita al caricamento completo del DOM.
 * Estrae il nome del file corrente dall'URL del browser, gestendo automaticamente 
 * il fallback alla pagina 'index.php' nel caso l'utente si trovi nella root del sito. 
 * Successivamente, individua il link di navigazione corrispondente e gli applica 
 * la classe CSS 'active' per evidenziarlo visivamente.
 */
document.addEventListener('DOMContentLoaded', function () {
    let paginaCorrente = window.location.pathname.split('/').pop();

    if (paginaCorrente === '') {
        paginaCorrente = 'index.php';
    }

    const linkAttivo = document.querySelector(`.navbar a[href="${paginaCorrente}"]`);

    if (linkAttivo) {
        linkAttivo.classList.add('active');
    }
});