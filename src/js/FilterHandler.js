/**
 * @file FilterHandler.js
 * @description Gestisce la logica lato client per i filtri di ricerca, includendo slider 
 * logaritmici doppi, validazione delle date e aggiornamento asincrono dei risultati via AJAX.
 */

/**
 * Converte un valore numerico in una stringa leggibile rappresentante la dimensione di un file.
 * Formatta il valore scalandolo progressivamente nelle unità di misura appropriate.
 * * @param {number|string} value - Il valore numerico (in byte) da formattare.
 * @returns {string} La stringa formattata con la relativa unità di misura (es. "1.5 MB") o il valore originale se non valido.
 */
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
 
/**
 * Estrae e analizza gli attributi 'data-*' di un elemento HTML (slider) per ricavarne 
 * la configurazione su scala, passi, e limiti minimo e massimo.
 * * @param {HTMLElement} element - L'elemento DOM da cui estrarre i dati.
 * @returns {Object} Oggetto contenente le proprietà della scala: {scale, steps, min, max}.
 */
function parseSliderScale(element) {
    const scale = element.dataset.scale || 'linear';
    const steps = parseInt(element.dataset.steps, 10) || 1000;
    
    const rawMin = element.dataset.min;
    const min = (rawMin === "null" || rawMin === undefined || isNaN(parseFloat(rawMin))) ? 0 : parseFloat(rawMin);
    
    const rawMax = element.dataset.max;
    const max = (rawMax === "null" || rawMax === undefined || isNaN(parseFloat(rawMax))) ? 0 : parseFloat(rawMax);
    
    return { scale, steps, min, max };
}

/**
 * Calcola il valore reale corrispondente a una determinata posizione dello slider
 * interpretato su una scala logaritmica.
 * * @param {number} position - La posizione attuale della maniglia dello slider.
 * @param {number} min - Il limite minimo reale consentito.
 * @param {number} max - Il limite massimo reale consentito.
 * @param {number} steps - Il numero totale di passi (risoluzione) dello slider.
 * @returns {number} Il valore reale convertito.
 */
function logPositionToValue(position, min, max, steps) {
    const effectiveMin = Math.max(1, min);
    if (position <= 0) return min;
    if (position >= steps) return max;
    const logMin = Math.log(effectiveMin);
    const logMax = Math.log(Math.max(max, effectiveMin + 1));
    const ratio = position / steps;
    return Math.round(Math.exp(logMin + (logMax - logMin) * ratio));
}

/**
 * Esegue l'inverso di logPositionToValue: calcola la posizione sulla barra dello slider 
 * partendo da un valore reale, utilizzando una scala logaritmica.
 * * @param {number} value - Il valore reale da posizionare.
 * @param {number} min - Il limite minimo reale.
 * @param {number} max - Il limite massimo reale.
 * @param {number} steps - Il numero totale di passi dello slider.
 * @returns {number} L'indice o posizione calcolata per la maniglia dello slider.
 */
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

/**
 * Gestisce l'interazione visiva e logica di uno slider a doppio cursore (min/max).
 * Previene la sovrapposizione delle maniglie, calcola i valori in tempo reale 
 * e aggiorna sia i campi di testo per l'utente, sia la traccia colorata di background, 
 * sia gli input hidden necessari per la sottomissione del form.
 */
function aggiornaDoppioSlider() {
    const inputMin = document.getElementById('dimensione_min_slider');
    const inputMax = document.getElementById('dimensione_max_slider');
    const container = document.querySelector('.multi-range-container');

    const txtMin = document.getElementById('val_dimensione_min');
    const txtMax = document.getElementById('val_dimensione_max');
    const hiddenMin = document.getElementById('dimensione_min');
    const hiddenMax = document.getElementById('dimensione_max');

    if (!inputMin || !inputMax || !container) return;

    const minConfig = parseSliderScale(inputMin);
    const maxConfig = parseSliderScale(inputMax);

    if (maxConfig.max === 0) {
        inputMin.value = 0;
        inputMax.value = 0;
    } else {
        const minGap = Math.max(1, Math.floor(minConfig.steps * 0.02)); 
        
        let posMin = parseInt(inputMin.value, 10);
        let posMax = parseInt(inputMax.value, 10);

        if (this === inputMin) {
            if (posMin > posMax - minGap) {
                inputMin.value = posMax - minGap;
                posMin = inputMin.value; 
            }
        } else if (this === inputMax) {
            if (posMax < posMin + minGap) {
                inputMax.value = parseInt(posMin) + minGap;
                posMax = inputMax.value; 
            }
        }

        if (inputMin.value > minConfig.steps - minGap) inputMin.value = minConfig.steps - minGap;
        if (inputMax.value < minGap) inputMax.value = minGap;
    }

    const minVal = minConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMin.value), minConfig.min, minConfig.max, minConfig.steps)
        : parseFloat(inputMin.value);
    const maxVal = maxConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMax.value), maxConfig.min, maxConfig.max, maxConfig.steps)
        : parseFloat(inputMax.value);

    if (txtMin) txtMin.innerText = formatFileSize(minVal);
    if (txtMax) txtMax.innerText = formatFileSize(maxVal);

    if (hiddenMin) hiddenMin.value = minVal;
    if (hiddenMax) hiddenMax.value = maxVal;

    const rawMinAttr = parseFloat(inputMin.min);
    const rawMaxAttr = parseFloat(inputMax.max);
    const minAttr = isNaN(rawMinAttr) ? 0 : rawMinAttr;
    const maxAttr = isNaN(rawMaxAttr) ? 1000 : rawMaxAttr;

    const diff = maxAttr - minAttr;
    const pctMin = (diff <= 0) ? 0 : ((parseFloat(inputMin.value) - minAttr) / diff) * 100;
    const pctMax = (diff <= 0) ? 0 : ((parseFloat(inputMax.value) - minAttr) / diff) * 100;

    if (pctMin >= 95) {
        inputMin.style.zIndex = "5";
    } else {
        inputMin.style.zIndex = "3";
    }

    if (pctMax <= 5) {
        inputMax.style.zIndex = "5";
    } else {
        inputMax.style.zIndex = "4";
    }

    container.style.background = `linear-gradient(
        to right, 
        #e5e7eb ${pctMin}%, 
        var(--primary) ${pctMin}%, 
        var(--primary) ${pctMax}%, 
        #e5e7eb ${pctMax}%
    )`;
}

/**
 * Inizializzazione globale degli eventi al caricamento del DOM.
 * Si occupa di agganciare tutti gli event listener necessari ai controlli di filtro, 
 * implementando pattern di debounce per limitare le richieste AJAX, gestendo la pulizia 
 * dei campi testuali, l'aggiustamento dinamico dei range e la validazione dei campi data.
 */
document.addEventListener('DOMContentLoaded', () => {

    const inputMin = document.getElementById('dimensione_min_slider');
    const inputMax = document.getElementById('dimensione_max_slider');

    if (inputMin && inputMax) {
        inputMin.addEventListener('input', aggiornaDoppioSlider);
        inputMax.addEventListener('input', aggiornaDoppioSlider);
        aggiornaDoppioSlider();
    }

    const filterForm = document.querySelector('#filtro form, .sidebar form');
    if (!filterForm) return;

    let debounceTimer;
    let rangeDebounceTimer; 

    /**
     * Interpella in via asincrona il server per ricalcolare e aggiornare 
     * i limiti operativi dello slider doppio sulla base dei restanti filtri attivi.
     */
    function aggiornaDimensioneRange() {
        if (!inputMin || !inputMax) return;

        const formData = new FormData(filterForm);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (key !== 'dimensione_min' && key !== 'dimensione_max') {
                params.append(key, value);
            }
        }
        params.set('range', '1');

        fetch(window.location.pathname + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) return;

            const newMin = (data.min === null || data.min === undefined) ? 0 : data.min;
            const newMax = (data.max === null || data.max === undefined) ? 0 : data.max;
            const steps  = parseInt(inputMin.dataset.steps, 10) || 1000;

            inputMin.dataset.min = newMin;
            inputMin.dataset.max = newMax;
            inputMax.dataset.min = newMin;
            inputMax.dataset.max = newMax;

            const posMin = (newMin === 0 && newMax === 0)
                ? 0 : valueToLogPosition(newMin, newMin, newMax, steps);
            const posMax = (newMin === 0 && newMax === 0)
                ? 0 : valueToLogPosition(newMax, newMin, newMax, steps);

            inputMin.min = 0; inputMin.max = steps;
            inputMax.min = 0; inputMax.max = steps;
            inputMin.value = posMin;
            inputMax.value = posMax;

            aggiornaDoppioSlider();
        })
        .catch(() => {});
    }

    /**
     * Valida ed eventualizza il clamping di un campo input di tipo 'date', 
     * assicurandosi che il valore fornito ricada in un intervallo logico 
     * compreso tra il 1900 e la data odierna.
     * * @param {HTMLInputElement} input - L'elemento input di tipo data.
     * @param {boolean} [forza=false] - Se true, applica la correzione anche se l'utente sta ancora digitando.
     * @returns {boolean} Ritorna true se il dato è valido o è stato corretto, false altrimenti.
     */
    function validaEAvvicinaData(input, forza = false) {
        if (input && input.type === 'date' && input.value) {
            const dataInserita = input.value;
            const parti = dataInserita.split('-');
            const anno = parseInt(parti[0], 10);

            if (!forza && document.activeElement === input && anno < 1000) {
                return false;
            }

            const oggi = new Date();
            const yyyy = oggi.getFullYear();
            const mm = String(oggi.getMonth() + 1).padStart(2, '0');
            const dd = String(oggi.getDate()).padStart(2, '0');
            const oggiStr = `${yyyy}-${mm}-${dd}`;

            const limiteMin = '1900-01-01';

            if (dataInserita < limiteMin) {
                input.value = limiteMin;
            } else if (dataInserita > oggiStr) {
                input.value = oggiStr;
            }
            return true;
        }
        return false;
    }

    filterForm.querySelectorAll('input[type="date"]').forEach(dateInput => {
        dateInput.addEventListener('blur', function () {
            validaEAvvicinaData(this, true);
            applicaFiltriAJAX();
        });
    });

    /**
     * Raccoglie i dati attualmente immessi nel form e innesca la funzione di caricamento
     * AJAX esterna (se definita) per rinfrescare l'interfaccia utente.
     */
    function applicaFiltriAJAX() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = window.location.pathname + '?' + params.toString();

        if (typeof caricaPaginaAjax === 'function') {
            caricaPaginaAjax(url);
        }
    }

    filterForm.addEventListener('input', function (e) {
        if (e.target.type === 'text' || e.target.tagName === 'TEXTAREA') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applicaFiltriAJAX, 250);
            
            clearTimeout(rangeDebounceTimer);
            rangeDebounceTimer = setTimeout(aggiornaDimensioneRange, 350);
        } else if (e.target.type === 'range') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applicaFiltriAJAX, 150);
        }
    });

    filterForm.addEventListener('change', function (e) {
        if (e.target.type === 'date') {
            validaEAvvicinaData(e.target, false);
            const parti = e.target.value.split('-');
            if (parti[0] && parseInt(parti[0], 10) < 1900) {
                return;
            }
        }

        if (e.target.type !== 'text' && e.target.tagName !== 'TEXTAREA') {
            clearTimeout(debounceTimer);
            applicaFiltriAJAX();
            
            if (e.target.type === 'checkbox') {
                clearTimeout(rangeDebounceTimer);
                rangeDebounceTimer = setTimeout(aggiornaDimensioneRange, 350);
            }
        }
    });

    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        filterForm.querySelectorAll('input[type="date"]').forEach(el => validaEAvvicinaData(el, true));
        clearTimeout(debounceTimer);
        applicaFiltriAJAX();
    });

    filterForm.addEventListener('click', function (e) {
        if (e.target.classList.contains('clear-input-btn')) {
            const wrapper = e.target.closest('.input-clearable-wrapper');
            if (wrapper) {
                const input = wrapper.querySelector('input');
                if (input) {
                    input.value = '';
                    applicaFiltriAJAX();
                    
                    clearTimeout(rangeDebounceTimer);
                    rangeDebounceTimer = setTimeout(aggiornaDimensioneRange, 350);
                }
            }
        }
    });

    const singleRanges = document.querySelectorAll('.js-sync-range');
    singleRanges.forEach(range => {
        range.addEventListener('input', function() {
            const targetId = this.dataset.target;
            const textElement = document.getElementById(targetId);
            if (textElement) {
                textElement.innerText = this.value;
            }
        });
    });

    const resetBtn = document.querySelector('.js-reset-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            const url = this.dataset.resetUrl;
            if (url) {
                window.location.href = url;
            }
        });
    }

});