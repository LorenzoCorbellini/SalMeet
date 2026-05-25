function controlloSliderMin(sliderMin, idMax) {
    const sliderMax = document.getElementById(idMax);
    // Impedisce al minimo di superare il valore del massimo
    if (parseInt(sliderMin.value) > parseInt(sliderMax.value)) {
        sliderMin.value = sliderMax.value;
    }
    // Aggiorna il testo a schermo
    document.getElementById('val_' + sliderMin.id).innerText = sliderMin.value;
}

function controlloSliderMax(sliderMax, idMin) {
    const sliderMin = document.getElementById(idMin);
    // Impedisce al massimo di scendere sotto il valore del minimo
    if (parseInt(sliderMax.value) < parseInt(sliderMin.value)) {
        sliderMax.value = sliderMin.value;
    }
    // Aggiorna il testo a schermo
    document.getElementById('val_' + sliderMax.id).innerText = sliderMax.value;
}

function aggiornaDoppioSlider() {
    const inputMin = document.getElementById('dimensione_min');
    const inputMax = document.getElementById('dimensione_max');
    const container = document.querySelector('.multi-range-container');

    const txtMin = document.getElementById('val_dimensione_min');
    const txtMax = document.getElementById('val_dimensione_max');

    if (!inputMin || !inputMax || !container) return;

    // Converte i valori correnti
    const minVal = parseFloat(inputMin.value);
    const maxVal = parseFloat(inputMax.value);
    const minAttr = parseFloat(inputMin.min) || 0;
    const maxAttr = parseFloat(inputMin.max) || 100;

    // Impedisce ai cursori di superarsi a vicenda in modo errato
    if (minVal > maxVal) {
        if (this === inputMin) inputMin.value = maxVal;
        else inputMax.value = minVal;
    }

    // Aggiorna i testi descrittivi dei MB
    if (txtMin) txtMin.innerText = inputMin.value;
    if (txtMax) txtMax.innerText = inputMax.value;

    // Calcola le percentuali per il gradiente rosa
    const pctMin = ((inputMin.value - minAttr) / (maxAttr - minAttr)) * 100;
    const pctMax = ((inputMax.value - minAttr) / (maxAttr - minAttr)) * 100;

    // Genera la traccia bicolore (Grigio -> Rosa SalMeet -> Grigio)
    container.style.background = `linear-gradient(
        to right, 
        #e5e7eb ${pctMin}%, 
        var(--primary) ${pctMin}%, 
        var(--primary) ${pctMax}%, 
        #e5e7eb ${pctMax}%
    )`;
}

// Inizializzazione degli ascoltatori di eventi al caricamento del DOM
document.addEventListener('DOMContentLoaded', () => {
    const inputMin = document.getElementById('dimensione_min');
    const inputMax = document.getElementById('dimensione_max');

    if (inputMin && inputMax) {
        inputMin.addEventListener('input', aggiornaDoppioSlider);
        inputMax.addEventListener('input', aggiornaDoppioSlider);

        // Sincronizza lo stato grafico subito all'avvio (es. se ci sono parametri nel $_GET)
        aggiornaDoppioSlider();
    }
});

// =========================================================
// LOGICA DEI FILTRI ISTANTANEI
// =========================================================
document.addEventListener('DOMContentLoaded', () => {
    // 1. Inizializzazione degli Slider grafici
    const inputMin = document.getElementById('dimensione_min');
    const inputMax = document.getElementById('dimensione_max');

    if (inputMin && inputMax) {
        inputMin.addEventListener('input', aggiornaDoppioSlider);
        inputMax.addEventListener('input', aggiornaDoppioSlider);
        aggiornaDoppioSlider();
    }

    // 2. Intercettazione filtri
    const filterForm = document.querySelector('#filtro form, .sidebar form');
    if (!filterForm) return;

    let debounceTimer;

    // Questa funzione crea un URL con i filtri e chiama direttamente l'AJAX
    function applicaFiltriAJAX() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        // Crea l'URL combinando la pagina corrente e i nuovi parametri
        const url = window.location.pathname + '?' + params.toString();
        
        // Usa la funzione definita in AJAXHandler.js
        if (typeof caricaPaginaAjax === 'function') {
            caricaPaginaAjax(url);
        }
    }

    // Intercetta la scrittura del testo (con un ritardo di 400ms per non sovraccaricare)
    filterForm.addEventListener('input', function (e) {
        if (e.target.type === 'text' || e.target.tagName === 'TEXTAREA') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applicaFiltriAJAX, 100); // 100ms di ritardo dopo l'ultimo input
        } else if (e.target.type === 'range') {
            applicaFiltriAJAX(); // Gli slider partono istantaneamente
        }
    });

    // Intercetta menu a tendina, date e checkbox
    filterForm.addEventListener('change', function (e) {
        if (e.target.type !== 'text' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'range') {
            applicaFiltriAJAX();
        }
    });

    // Blocca il tasto "Invio" sulla tastiera per evitare che il form ricarichi la pagina
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        applicaFiltriAJAX();
    });
});