document.addEventListener('DOMContentLoaded', function () {
    // 1. Legge il percorso completo dall'URL del browser e prende solo l'ultima parte (il nome del file)
    let paginaCorrente = window.location.pathname.split('/').pop();

    // Se l'utente si trova nella root del sito (es: miosito.com/), il nome del file è vuoto. 
    // In questo caso forziamo 'index.php'
    if (paginaCorrente === '') {
        paginaCorrente = 'index.php';
    }

    // 2. Cerca il link che ha come href esattamente il nome del file corrente
    const linkAttivo = document.querySelector(`.navbar a[href="${paginaCorrente}"]`);

    // 3. Se lo trova, gli aggiunge la classe 'active'
    if (linkAttivo) {
        linkAttivo.classList.add('active');
    }
});