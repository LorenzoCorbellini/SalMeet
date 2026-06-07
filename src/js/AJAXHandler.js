document.addEventListener("DOMContentLoaded", function () {

    // Gestione dei link per paginazione e TAB per farli funzionare via AJAX
    document.addEventListener("click", function (e) {
        const targetLink = e.target.closest("a");
        if (!targetLink) return;

        const url = targetLink.getAttribute("href");
        if (!url) return;

        // 1. Controlla se è un link della paginazione
        const isPagination = targetLink.closest(".pagination-container a.page-item");

        // 2. Controlla se è un link di una TAB (contiene "tab=")
        // Assicuriamoci che sia un link interno alla pagina corrente, 
        // per non bloccare la navigazione da una sezione all'altra
        let paginaCorrente = window.location.pathname.split('/').pop() || 'index.php';
        const isTab = url.includes("tab=") && (url.startsWith("?") || url.includes(paginaCorrente));

        // Se è paginazione O una tab interna, blocchiamo il caricamento normale e usiamo AJAX
        if (isPagination || isTab) {
            e.preventDefault();
            caricaPaginaAjax(url);
        }
    });
});

// Funzione globale che esegue la chiamata al server (LA TUA FUNZIONA GIA' BENE)
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
            
            // MODIFICA: Controlliamo se l'URL contiene il parametro "tab"
            if (url.includes('tab=')) {
                // Se stiamo cambiando tab, sovrascriviamo lo stato corrente senza creare un checkpoint
                history.replaceState(null, "", url);
            } else {
                // Se stiamo cambiando pagina nella paginazione, creiamo un checkpoint normale
                history.pushState(null, "", url);
            }
        })
        .catch(error => {
            console.error("Errore AJAX:", error);
        });
}