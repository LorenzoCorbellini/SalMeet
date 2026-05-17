document.addEventListener("DOMContentLoaded", function() {
    
    document.addEventListener("click", function(e) {
        
        const targetLink = e.target.closest(".pagination-container a.page-item");
        
        if (targetLink && targetLink.tagName === "A") {
            e.preventDefault();
            
            const url = targetLink.getAttribute("href");
            
            caricaPaginaAjax(url);
        }
    });
});

function caricaPaginaAjax(url) {
    const content = document.getElementById("ajax-results");
    
    // content.style.opacity = "0.5";

    // Facciamo la chiamata al server usando le Fetch API

    fetch(url, {
        headers: {
            // Questo header dice al PHP che la richiesta è un'operazione AJAX (XHR)
            "X-Requested-With": "XMLHttpRequest"
        },
        credentials: "same-origin"
    })
    .then(response => {
        if (!response.ok) throw new Error("Errore nel caricamento dei dati");
        return response.text();
    })
    .then(html => {

        content.innerHTML = html;
        // content.style.opacity = "1";
        history.pushState(null, "", url);
    })
    .catch(error => {
        console.error("Errore AJAX:", error);
        // content.style.opacity = "1";
    });
}

// Questo serve per gestire i tasti Avanti/Indietro del browser dopo che l'URL è cambiato con pushState
// Se l'utente preme "Indietro", ricarichiamo la pagina corretta via AJAX
window.addEventListener("popstate", function() {
    caricaPaginaAjax(window.location.href);
});