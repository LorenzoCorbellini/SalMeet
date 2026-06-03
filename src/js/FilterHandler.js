// function controlloSliderMin(sliderMin, idMax) {
//     const sliderMax = document.getElementById(idMax);
//     if (parseInt(sliderMin.value) > parseInt(sliderMax.value)) {
//         sliderMin.value = sliderMax.value;
//     }
//     document.getElementById('val_' + sliderMin.id).innerText = sliderMin.value;
// }

// function controlloSliderMax(sliderMax, idMin) {
//     const sliderMin = document.getElementById(idMin);
//     if (parseInt(sliderMax.value) < parseInt(sliderMin.value)) {
//         sliderMax.value = sliderMin.value;
//     }
//     document.getElementById('val_' + sliderMax.id).innerText = sliderMax.value;
// }

function formatFileSize(value) {
    let size = parseFloat(value);
    if (Number.isNaN(size)) return value;

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let index = 0;

    while (size >= 1000 && index < units.length - 1) {
        size /= 1000;
        index += 1;
    }

    return `${Math.round(size * 100) / 100} ${units[index]}`;
}
 
function parseSliderScale(element) {
    const scale = element.dataset.scale || 'linear';
    const steps = parseInt(element.dataset.steps, 10) || 1000;
    const min = parseFloat(element.dataset.min) || 0;
    const max = parseFloat(element.dataset.max) || 100;
    return { scale, steps, min, max };
}

function logPositionToValue(position, min, max, steps) {
    const effectiveMin = Math.max(1, min);
    if (position <= 0) return min;
    if (position >= steps) return max;
    const logMin = Math.log(effectiveMin);
    const logMax = Math.log(Math.max(max, effectiveMin + 1));
    const ratio = position / steps;
    return Math.round(Math.exp(logMin + (logMax - logMin) * ratio));
}

function valueToLogPosition(value, min, max, steps) {
    const effectiveMin = Math.max(1, min);
    if (value <= min) return 0;
    if (value >= max) return steps;
    const logMin = Math.log(effectiveMin);
    const logMax = Math.log(Math.max(max, effectiveMin + 1));
    const logValue = Math.log(Math.max(value, effectiveMin));
    const ratio = (logValue - logMin) / Math.max(1e-9, logMax - logMin);
    return Math.round(Math.max(0, Math.min(steps, ratio * steps)));
}

function aggiornaDoppioSlider() {
    const inputMin = document.getElementById('dimensione_min_slider');
    const inputMax = document.getElementById('dimensione_max_slider');
    const container = document.querySelector('.multi-range-container');

    const txtMin = document.getElementById('val_dimensione_min');
    const txtMax = document.getElementById('val_dimensione_max');
    const hiddenMin = document.getElementById('dimensione_min');
    const hiddenMax = document.getElementById('dimensione_max');

    if (!inputMin || !inputMax || !container) return;

    const minAttr = parseFloat(inputMin.min) || 0;
    const maxAttr = parseFloat(inputMin.max) || 100;
    const minConfig = parseSliderScale(inputMin);
    const maxConfig = parseSliderScale(inputMax);

    const minVal = minConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMin.value), minConfig.min, minConfig.max, minConfig.steps)
        : parseFloat(inputMin.value);
    const maxVal = maxConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMax.value), maxConfig.min, maxConfig.max, maxConfig.steps)
        : parseFloat(inputMax.value);

    if (minVal > maxVal) {
        if (this === inputMin) inputMin.value = maxVal;
        else inputMax.value = minVal;
    }

    if (txtMin) txtMin.innerText = formatFileSize(minVal);
    if (txtMax) txtMax.innerText = formatFileSize(maxVal);

    if (hiddenMin) hiddenMin.value = minVal;
    if (hiddenMax) hiddenMax.value = maxVal;

    const pctMin = ((parseFloat(inputMin.value) - minAttr) / (maxAttr - minAttr)) * 100;
    const pctMax = ((parseFloat(inputMax.value) - minAttr) / (maxAttr - minAttr)) * 100;

    container.style.background = `linear-gradient(
        to right, 
        #e5e7eb ${pctMin}%, 
        var(--primary) ${pctMin}%, 
        var(--primary) ${pctMax}%, 
        #e5e7eb ${pctMax}%
    )`;
}

// =========================================================
// LOGICA DEI FILTRI ISTANTANEI E GESTIONE DOM UNIFICATA
// =========================================================
document.addEventListener('DOMContentLoaded', () => {

    // 1. Inizializzazione degli Slider grafici
    const inputMin = document.getElementById('dimensione_min_slider');
    const inputMax = document.getElementById('dimensione_max_slider');

    if (inputMin && inputMax) {
        inputMin.addEventListener('input', aggiornaDoppioSlider);
        inputMax.addEventListener('input', aggiornaDoppioSlider);
        aggiornaDoppioSlider();
    }

    // 2. Intercettazione filtri
    const filterForm = document.querySelector('#filtro form, .sidebar form');
    if (!filterForm) return;

    let debounceTimer;

    // Funzione di supporto aggiornata per gestire la digitazione fluida
    function validaEAvvicinaData(input, forza = false) {
        if (input && input.type === 'date' && input.value) {
            const dataInserita = input.value; // Formato YYYY-MM-DD
            const parti = dataInserita.split('-');
            const anno = parseInt(parti[0], 10);

            // Se l'utente sta ancora scrivendo (ha il focus) e l'anno è palesemente incompleto
            // (es. sotto il 1000, tipo 0002 mentre scrive 2026), non sovrascriviamo per non bloccarlo.
            if (!forza && document.activeElement === input && anno < 1000) {
                return false;
            }

            // Calcola la data odierna dinamica
            const oggi = new Date();
            const yyyy = oggi.getFullYear();
            const mm = String(oggi.getMonth() + 1).padStart(2, '0');
            const dd = String(oggi.getDate()).padStart(2, '0');
            const oggiStr = `${yyyy}-${mm}-${dd}`;

            const limiteMin = '1900-01-01';

            // Controllo e avvicinamento ai limiti correnti
            if (dataInserita < limiteMin) {
                input.value = limiteMin;
            } else if (dataInserita > oggiStr) {
                input.value = oggiStr;
            }
            return true;
        }
        return false;
    }

    // Aggiunge l'evento 'blur' (uscita dal campo) a tutti gli input data per forzare la correzione
    filterForm.querySelectorAll('input[type="date"]').forEach(dateInput => {
        dateInput.addEventListener('blur', function () {
            validaEAvvicinaData(this, true); // forza = true
            applicaFiltriAJAX(); // Aggiorna i dati dopo la correzione finale
        });
    });

    // Questa funzione crea un URL con i filtri e chiama direttamente l'AJAX
    function applicaFiltriAJAX() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = window.location.pathname + '?' + params.toString();

        if (typeof caricaPaginaAjax === 'function') {
            caricaPaginaAjax(url);
        }
    }

    // Intercetta la scrittura del testo e i movimenti degli slider
    filterForm.addEventListener('input', function (e) {
        if (e.target.type === 'text' || e.target.tagName === 'TEXTAREA') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applicaFiltriAJAX, 250);
        } else if (e.target.type === 'range') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applicaFiltriAJAX, 150);
        }
    });

    // Intercetta menu a tendina, date, checkbox e rilascio slider
    filterForm.addEventListener('change', function (e) {
        if (e.target.type === 'date') {
            // Esegue un controllo morbido durante la digitazione
            validaEAvvicinaData(e.target, false);

            // Se l'anno è temporaneamente incompleto (es. sotto 1900), evita di inviare 
            // chiamate AJAX parziali e inutili al server per l'anno 0002
            const parti = e.target.value.split('-');
            if (parti[0] && parseInt(parti[0], 10) < 1900) {
                return;
            }
        }

        if (e.target.type !== 'text' && e.target.tagName !== 'TEXTAREA') {
            clearTimeout(debounceTimer);
            applicaFiltriAJAX();
        }
    });

    // Gestione del tasto "Invio"
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Prima dell'invio definitivo, forza la validazione rigida di tutte le date
        filterForm.querySelectorAll('input[type="date"]').forEach(el => validaEAvvicinaData(el, true));

        clearTimeout(debounceTimer);
        applicaFiltriAJAX();
    });

    // =========================================================
    // GESTIONE CANCELLAZIONE SINGOLO INPUT (Tasto X)
    // =========================================================
    filterForm.addEventListener('click', function (e) {
        // Se l'elemento cliccato è la nostra X
        if (e.target.classList.contains('clear-input-btn')) {
            const wrapper = e.target.closest('.input-clearable-wrapper');
            if (wrapper) {
                const input = wrapper.querySelector('input');
                if (input) {
                    input.value = ''; // Svuota il campo
                    
                    // Richiama immediatamente l'aggiornamento AJAX della tabella
                    applicaFiltriAJAX();
                }
            }
        }
    });
});