/**
 * @file AJAXHandler.js
 * @description Gestisce le richieste asincrone per la paginazione e la navigazione a schede (TAB), 
 * intercettando gli eventi di click ed evitando i ricaricamenti non necessari della pagina o l'inquinamento della cronologia.
 */

document.addEventListener("DOMContentLoaded", function () {

    /**
     * Event listener globale delegato per intercettare i click sui link.
     * Valuta dinamicamente il contesto del link cliccato (paginazione vs tab) 
     * e indirizza il flusso verso un caricamento AJAX o una sostituzione dell'URL.
     * * @param {MouseEvent} e - L'evento di click nativo del browser.
     */
    document.addEventListener("click", function (e) {
        const targetLink = e.target.closest("a");
        
        if (!targetLink) return;

        const url = targetLink.getAttribute("href");
        
        if (!url || url.startsWith("javascript:")) return;

        const isPagination = targetLink.closest(".pagination-container a.page-item");
        
        let paginaCorrente = window.location.pathname.split('/').pop() || 'index.php';
        const isTab = url.includes("tab=") && (url.startsWith("?") || url.includes(paginaCorrente));

        if (isPagination) {
            e.preventDefault();
            caricaPaginaAjax(url);
        } else if (isTab) {
            e.preventDefault();
            window.location.replace(url);
        }
    });
});

/**
 * Esegue una richiesta asincrona al server tramite Fetch API per aggiornare 
 * il contenitore principale della pagina con i nuovi risultati della paginazione.
 * Aggiorna l'URL del browser tramite History API senza alterare la cronologia di navigazione.
 * * @param {string} url - Il percorso di destinazione contenente i parametri della query per la paginazione.
 */
function caricaPaginaAjax(url) {
    const content = document.getElementById("content");
    
    if (!content) return;

    fetch(url, {
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        credentials: "same-origin"
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("Errore nel caricamento dei dati");
        }
        return response.text();
    })
    .then(html => {
        content.innerHTML = html;
        history.replaceState(null, "", url);
    })
    .catch(error => {
        console.error("Errore AJAX:", error);
    });
}