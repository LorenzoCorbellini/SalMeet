document.addEventListener("DOMContentLoaded", function () {

    // Gestione dei link di paginazione per farli funzionare via AJAX
    document.addEventListener("click", function (e) {
        const targetLink = e.target.closest(".pagination-container a.page-item");

        if (targetLink && targetLink.tagName === "A") {
            e.preventDefault();
            const url = targetLink.getAttribute("href");
            caricaPaginaAjax(url);
        }
    });
});

// Funzione globale che esegue la chiamata al server
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
            // Sovrascrive SOLO la tabella dei risultati.
            // La tua sidebar (e il cursore) rimangono intatti!
            content.innerHTML = html;
            history.pushState(null, "", url);
        })
        .catch(error => {
            console.error("Errore AJAX:", error);
        });
}