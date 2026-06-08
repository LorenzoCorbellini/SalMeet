document.addEventListener("DOMContentLoaded", function () {

    // Gestione dei link per paginazione e TAB
    document.addEventListener("click", function (e) {
        const targetLink = e.target.closest("a");
        if (!targetLink) return;

        const url = targetLink.getAttribute("href");
        if (!url || url.startsWith("javascript:")) return;

        // 1. Controlla se è un link della paginazione
        const isPagination = targetLink.closest(".pagination-container a.page-item");

        // 2. Controlla se è un link di una TAB (contiene "tab=")
        let paginaCorrente = window.location.pathname.split('/').pop() || 'index.php';
        const isTab = url.includes("tab=") && (url.startsWith("?") || url.includes(paginaCorrente));

        if (isPagination) {
            // Se è la PAGINAZIONE: usiamo AJAX come hai sempre fatto
            e.preventDefault();
            caricaPaginaAjax(url);
        } else if (isTab) {
            // Se è una TAB: facciamo un caricamento di pagina completo per aggiornare i filtri,
            // ma usiamo "replace" per NON aggiungere passaggi alla cronologia!
            e.preventDefault();
            window.location.replace(url);
        }
    });
});

// Funzione globale che esegue la chiamata al server SOLO per la paginazione
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
            if (!response.ok) throw new Error("Errore nel caricamento dei dati");
            return response.text();
        })
        .then(html => {
            // Sovrascrive SOLO la tabella o la porzione dei risultati
            content.innerHTML = html;
            
            // MODIFICA CRUCIALE: usiamo replaceState invece di pushState.
            // Sostituisce l'URL corrente senza accumulare step nella cronologia del browser.
            // Al primo clic su "Indietro", l'utente uscirà completamente dal dettaglio!
            history.replaceState(null, "", url);
        })
        .catch(error => {
            console.error("Errore AJAX:", error);
        });
}