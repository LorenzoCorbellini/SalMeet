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
    
    const rawMin = element.dataset.min;
    const min = (rawMin === "null" || rawMin === undefined || isNaN(parseFloat(rawMin))) ? 0 : parseFloat(rawMin);
    
    const rawMax = element.dataset.max;
    const max = (rawMax === "null" || rawMax === undefined || isNaN(parseFloat(rawMax))) ? 0 : parseFloat(rawMax);
    
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

    const minConfig = parseSliderScale(inputMin);
    const maxConfig = parseSliderScale(inputMax);

    // Se non ci sono risultati (max reale = 0), blocchiamo le maniglie a 0
    if (maxConfig.max === 0) {
        inputMin.value = 0;
        inputMax.value = 0;
    } else {
        const minGap = Math.max(1, Math.floor(minConfig.steps * 0.02)); 
        
        let posMin = parseInt(inputMin.value, 10);
        let posMax = parseInt(inputMax.value, 10);

        // Controllo incrociato: impediamo che si sovrappongano
        if (this === inputMin) {
            if (posMin > posMax - minGap) {
                inputMin.value = posMax - minGap;
                posMin = inputMin.value; // Aggiorniamo la variabile locale
            }
        } else if (this === inputMax) {
            if (posMax < posMin + minGap) {
                inputMax.value = parseInt(posMin) + minGap;
                posMax = inputMax.value; // Aggiorniamo la variabile locale
            }
        }

        // Assicuriamoci che non superino i limiti estremi se spinti dal gap
        if (inputMin.value > minConfig.steps - minGap) inputMin.value = minConfig.steps - minGap;
        if (inputMax.value < minGap) inputMax.value = minGap;
    }

    // Ora che le posizioni fisiche sono definitive e separate, calcoliamo i byte reali
    const minVal = minConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMin.value), minConfig.min, minConfig.max, minConfig.steps)
        : parseFloat(inputMin.value);
    const maxVal = maxConfig.scale === 'log'
        ? logPositionToValue(parseFloat(inputMax.value), maxConfig.min, maxConfig.max, maxConfig.steps)
        : parseFloat(inputMax.value);

    // Aggiorniamo i testi a schermo e i campi hidden inviati al PHP
    if (txtMin) txtMin.innerText = formatFileSize(minVal);
    if (txtMax) txtMax.innerText = formatFileSize(maxVal);

    if (hiddenMin) hiddenMin.value = minVal;
    if (hiddenMax) hiddenMax.value = maxVal;

    // Recupera la fine del range puramente per i CSS steps
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

    // Coloriamo il segmento centrale della barra
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

    // Funzione per aggiornare min e max dinamicamente via AJAX
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
});