const API_URL = 'bachecheAPI.php';

function eseguiRichiesta(bodyData, messaggioSuccesso, urlRedirect = null) {
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bodyData)
    })
        .then(r => r.json())
        .then(data => {
            if (data.successo) {
                if (messaggioSuccesso) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Operazione completata',
                        html: messaggioSuccesso,
                        timer: 3000,
                        showConfirmButton: false,
                        heightAuto: false,
                        scrollbarPadding: false
                    }).then(() => {
                        if (urlRedirect) window.location.href = urlRedirect;
                        else location.reload();
                    });
                } else {
                    if (urlRedirect) window.location.href = urlRedirect;
                    else location.reload();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Errore',
                    text: data.messaggio,
                    heightAuto: false,
                    scrollbarPadding: false
                });
            }
        })
        .catch((error) => {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Errore di sistema',
                text: 'Impossibile comunicare con il server.',
                heightAuto: false,
                scrollbarPadding: false
            });
        });
}

// =========================================================
// BACHECHE: AGGIUNGI, RINOMINA, ELIMINA
// =========================================================
async function aggiungiBacheca() {
    const { value: nomeBacheca } = await Swal.fire({
        title: 'Nuova Bacheca',
        input: 'text',
        inputLabel: 'Inserisci il nome della nuova bacheca:',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Avanti &rarr;',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            if (!value || value.trim() === '') return 'Il nome della bacheca è obbligatorio.';
        }
    });

    if (!nomeBacheca) return;

    const ownerId = await cercaESelezionaUtente('Assegna un proprietario');

    if (ownerId) {
        eseguiRichiesta({
            azione: 'aggiungi',
            nome: nomeBacheca.trim(),
            owner: parseInt(ownerId, 10)
        }, 'Bacheca creata con successo.');
    }
}

async function rinominaBacheca(nomeBacheca, owner) {
    const { value: nuovoNome } = await Swal.fire({
        title: 'Rinomina Bacheca',
        input: 'text',
        inputLabel: 'Inserisci il nuovo nome:',
        inputValue: nomeBacheca,
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Salva',
        cancelButtonText: 'Annulla',
        reverseButtons: true,
        inputValidator: (value) => {
            if (!value || value.trim() === '') return 'Il nome non può essere vuoto!';
        }
    });

    if (nuovoNome && nuovoNome.trim() !== nomeBacheca) {
        const nomePulito = nuovoNome.trim();
        const urlAttuale = new URL(window.location.href);

        if (urlAttuale.searchParams.has('bacheca')) {
            urlAttuale.searchParams.set('bacheca', nomePulito);
        }

        const messaggioConferma = `<b class="swal-text-bold">${nomeBacheca}</b> rinominata con successo in <b class="swal-text-bold">${nomePulito}</b>`;

        eseguiRichiesta({
            azione: 'rinomina',
            nome: nomeBacheca,
            owner: owner,
            nuovoNome: nomePulito
        }, messaggioConferma, urlAttuale.toString());
    }
}

function eliminaBacheca(nomeBacheca, idOwner, nicknameOwner) {
    Swal.fire({
        title: 'Elimina Bacheca',
        html: `Vuoi davvero eliminare la bacheca <b class="swal-text-bold">${nomeBacheca}</b> di <b class="swal-text-bold">${nicknameOwner}</b>? L'azione è irreversibile.`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'elimina',
                nome: nomeBacheca,
                owner: idOwner
            }, `Bacheca eliminata con successo.`, 'bacheche.php');
        }
    });
}

// =========================================================
// GESTIONE RICHIESTE PENDENTI (Accetta / Rifiuta)
// =========================================================

function accettaRichiesta(nomeBacheca, owner, utenteTarget, nickname) {
    eseguiRichiesta({
        azione: 'accetta_richiesta',
        nome: nomeBacheca,
        owner: owner,
        utenteTarget: parseInt(utenteTarget, 10)
    }, `La richiesta di accesso di <b class="swal-text-bold">${nickname}</b> è stata accettata.`);
}

function rifiutaRichiesta(nomeBacheca, owner, utenteTarget, nickname) {
    Swal.fire({
        title: 'Rifiuta Richiesta',
        html: `Vuoi rifiutare e cancellare la richiesta di accesso di <b class="swal-text-bold">${nickname}</b>?`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, rifiuta',
        cancelButtonText: 'Annulla',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'rifiuta_richiesta',
                nome: nomeBacheca,
                owner: owner,
                utenteTarget: parseInt(utenteTarget, 10)
            }, `Richiesta di <b class="swal-text-bold">${nickname}</b> rifiutata.`);
        }
    });
}

// =========================================================
// GESTIONE UTENTE PROPRIETARIO
// =========================================================
async function cercaESelezionaUtente(titoloPopup, returnFullObject = false, nomeBacheca = null, owner = null) {
    const oggi = new Date();
    const yyyy = oggi.getFullYear();
    const mm = String(oggi.getMonth() + 1).padStart(2, '0');
    const dd = String(oggi.getDate()).padStart(2, '0');
    const oggiStringa = `${yyyy}-${mm}-${dd}`;

    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            width: '750px',
            heightAuto: false,
            scrollbarPadding: false,
            html: `
                <div class="swal-dual-grid">
                    
                    <div class="swal-multi-col-left">
                        <h5 class="swal-multi-header swal-multi-header-blue">Filtri Cerca</h5>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Nickname</label>
                            <input id="swal-search-nickname" class="swal2-input swal-multi-input" placeholder="Es. supermario">
                        </div>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Nome</label>
                            <input id="swal-search-nome" class="swal2-input swal-multi-input" placeholder="Es. Mario">
                        </div>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Cognome</label>
                            <input id="swal-search-cognome" class="swal2-input swal-multi-input" placeholder="Es. Rossi">
                        </div>
                        <div class="swal-multi-input-group-last">
                            <label class="swal-multi-label">Data Nascita</label>
                            <input id="swal-search-date" type="date" class="swal2-input swal-multi-input" min="1900-01-01" max="${oggiStringa}">
                        </div>
                        <button id="swal-search-btn" class="swal2-styled swal2-confirm swal-multi-btn-search">Avvia Ricerca</button>
                    </div>
                    
                    <div class="swal-multi-col-right">
                        <h5 id="swal-user-count" class="swal-multi-header swal-multi-header-green">Risultati</h5>
                        <div id="swal-search-results" class="swal-multi-list">
                            <p class="swal-multi-msg-empty">Compila almeno un campo a sinistra per avviare la ricerca.</p>
                        </div>
                    </div>
                    
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Seleziona proprietario',
            cancelButtonText: 'Annulla',
            reverseButtons: true,
            didOpen: () => {
                const searchBtn = document.getElementById('swal-search-btn');
                const nickInput = document.getElementById('swal-search-nickname');
                const nomeInput = document.getElementById('swal-search-nome');
                const cognomeInput = document.getElementById('swal-search-cognome');
                const dateInput = document.getElementById('swal-search-date');
                const resultsDiv = document.getElementById('swal-search-results');
                const countSpan = document.getElementById('swal-user-count');

                [nickInput, nomeInput, cognomeInput, dateInput].forEach(input => {
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            searchBtn.click();
                        }
                    });
                });

                searchBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const nicknameTerm = nickInput.value.trim();
                    const nomeTerm = nomeInput.value.trim();
                    const cognomeTerm = cognomeInput.value.trim();
                    let dateTerm = dateInput.value;

                    if (dateTerm) {
                        const limiteMin = '1900-01-01';
                        if (dateTerm < limiteMin) {
                            dateTerm = limiteMin;
                            dateInput.value = limiteMin;
                        } else if (dateTerm > oggiStringa) {
                            dateTerm = oggiStringa;
                            dateInput.value = oggiStringa;
                        }
                    }

                    if (nicknameTerm.length === 0 && nomeTerm.length === 0 && cognomeTerm.length === 0 && !dateTerm) {
                        resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Inserisci almeno un criterio per la ricerca.</p>';
                        countSpan.innerHTML = 'Risultati';
                        return;
                    }

                    resultsDiv.innerHTML = '<p class="swal-multi-msg-loading">Ricerca in corso...</p>';
                    countSpan.innerHTML = 'Ricerca in corso...';

                    const payloadDati = { azione: 'cerca_utente' };
                    if (nicknameTerm) payloadDati.nickname = nicknameTerm;
                    if (nomeTerm) payloadDati.filtro_nome = nomeTerm;
                    if (cognomeTerm) payloadDati.cognome = cognomeTerm;
                    if (dateTerm) payloadDati.data_nascita = dateTerm;

                    if (nomeBacheca) payloadDati.nomeBacheca = nomeBacheca;
                    if (owner) payloadDati.owner = owner;

                    try {
                        const response = await fetch(API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payloadDati)
                        });
                        const data = await response.json();

                        if (data.successo && data.utenti.length > 0) {
                            const limitUtenti = 50;
                            const hasMore = data.utenti.length >= limitUtenti;

                            if (hasMore) {
                                countSpan.innerHTML = `Risultati (Primi ${limitUtenti})`;
                            } else {
                                countSpan.innerHTML = `Risultati (${data.utenti.length})`;
                            }

                            // Generazione righe iniettate direttamente nel contenitore flessibile
                            let html = '';
                            data.utenti.forEach(u => {
                                const infoData = u.data_formattata ? ` ${u.data_formattata}` : '';
                                html += `
                                    <label class="swal-multi-row" style="cursor: pointer; justify-content: flex-start; gap: 10px;">
                                        <input type="radio" name="swal-user-radio" value="${u.codice}" data-nickname="${u.nickname}" style="cursor: pointer; margin:0;">
                                        <div>
                                            <strong>${u.nickname}</strong> 
                                            <div class="swal-multi-row-subtext">${u.nome} ${u.cognome}${infoData}</div>
                                        </div>
                                    </label>
                                `;
                            });
                            resultsDiv.innerHTML = html;
                        } else {
                            countSpan.innerHTML = `Risultati (0)`;
                            resultsDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun utente trovato con questi criteri.</p>';
                        }
                    } catch (err) {
                        countSpan.innerHTML = 'Risultati (0)';
                        resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Errore di comunicazione col server.</p>';
                    }
                });
            },
            preConfirm: () => {
                const selected = document.querySelector('input[name="swal-user-radio"]:checked');
                if (!selected) {
                    Swal.showValidationMessage('Seleziona un utente dalla lista a destra prima di proseguire.');
                    return false;
                }
                return {
                    id: selected.value,
                    nickname: selected.getAttribute('data-nickname')
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (returnFullObject) {
                    resolve(result.value);
                } else {
                    resolve(result.value.id);
                }
            } else {
                resolve(null);
            }
        });
    });
}

// =========================================================
// AGGIUNGI PIÙ UTENTI ALLA VOLTA
// =========================================================
async function aggiungiUtentiMultipli(nomeBacheca, owner) {
    const oggi = new Date();
    const yyyy = oggi.getFullYear();
    const mm = String(oggi.getMonth() + 1).padStart(2, '0');
    const dd = String(oggi.getDate()).padStart(2, '0');
    const oggiStringa = `${yyyy}-${mm}-${dd}`;

    let utentiSelezionati = new Map();

    const { value: arrayIdUtenti } = await Swal.fire({
        title: `Seleziona utenti da autorizzare`,
        width: '1000px',
        heightAuto: false,
        scrollbarPadding: false,
        html: `
            <div class="swal-multi-grid">
                
                <div class="swal-multi-col-left">
                    <h5 class="swal-multi-header swal-multi-header-blue">Filtri Cerca</h5>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Nickname</label>
                        <input id="swal-m-nickname" class="swal2-input swal-multi-input" placeholder="Es. joker">
                    </div>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Nome</label>
                        <input id="swal-m-nome" class="swal2-input swal-multi-input" placeholder="Es. Luca">
                    </div>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Cognome</label>
                        <input id="swal-m-cognome" class="swal2-input swal-multi-input" placeholder="Es. Verdi">
                    </div>
                    <div class="swal-multi-input-group-last">
                        <label class="swal-multi-label">Data Nascita</label>
                        <input id="swal-m-date" type="date" class="swal2-input swal-multi-input" max="${oggiStringa}">
                    </div>
                    <button id="swal-m-search-btn" class="swal2-styled swal2-confirm swal-multi-btn-search">Avvia Ricerca</button>
                </div>

                <div class="swal-multi-col-center">
                    <h5 id="swal-m-results-title" class="swal-multi-header swal-multi-header-green">Risultati</h5>
                    <div id="swal-m-results" class="swal-multi-list">
                        <p class="swal-multi-msg-empty">Esegui una ricerca per visualizzare gli utenti.</p>
                    </div>
                </div>

                <div class="swal-multi-col-right">
                    <h5 class="swal-multi-header swal-multi-header-orange">Selezionati (<span id="swal-m-count">0</span>)</h5>
                    <div id="swal-m-selected" class="swal-multi-list">
                        <p class="swal-multi-msg-empty">Nessun utente selezionato.</p>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Autorizza utenti',
        cancelButtonText: 'Annulla',
        reverseButtons: true,
        preConfirm: () => {
            if (utentiSelezionati.size === 0) {
                Swal.showValidationMessage('Seleziona almeno un utente dalla colonna centrale prima di salvare.');
                return false;
            }
            return Array.from(utentiSelezionati.keys());
        },
        didOpen: () => {
            const searchBtn = document.getElementById('swal-m-search-btn');
            const nickIn = document.getElementById('swal-m-nickname');
            const nomeIn = document.getElementById('swal-m-nome');
            const cognomeIn = document.getElementById('swal-m-cognome');
            const dateIn = document.getElementById('swal-m-date');

            const resultsDiv = document.getElementById('swal-m-results');
            const selectedDiv = document.getElementById('swal-m-selected');
            const countSpan = document.getElementById('swal-m-count');
            const resultsTitle = document.getElementById('swal-m-results-title');

            async function eseguiCercaUtente() {
                resultsDiv.innerHTML = '<p class="swal-multi-msg-loading">Ricerca in corso...</p>';
                resultsTitle.textContent = 'Ricerca in corso...';

                const payload = {
                    azione: 'cerca_utente',
                    nickname: nickIn.value.trim(),
                    filtro_nome: nomeIn.value.trim(),
                    cognome: cognomeIn.value.trim(),
                    data_nascita: dateIn.value || null,
                    nomeBacheca: nomeBacheca,
                    owner: owner,
                    utenti_esclusi: Array.from(utentiSelezionati.keys())
                };

                try {
                    const r = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await r.json();

                    if (data.successo && data.utenti && data.utenti.length > 0) {
                        renderizzaRisultatiCentrali(data.utenti);
                    } else {
                        resultsTitle.textContent = 'Risultati (0)';
                        resultsDiv.innerHTML = `<p class="swal-multi-msg-error">${data.messaggio || 'Nessun risultato trovato.'}</p>`;
                    }
                } catch (err) {
                    resultsTitle.textContent = 'Errore';
                    resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Errore server di ricerca.</p>';
                }
            }

            function renderizzaRisultatiCentrali(utenti) {
                resultsDiv.innerHTML = '';

                const limitUtenti = 50;
                if (utenti.length >= limitUtenti) {
                    resultsTitle.textContent = `Risultati (Primi ${limitUtenti})`;
                } else {
                    resultsTitle.textContent = `Risultati (${utenti.length})`;
                }

                utenti.forEach(u => {
                    const row = document.createElement('div');
                    const infoData = u.data_formattata ? ` ${u.data_formattata}` : '';
                    row.className = 'swal-multi-row';
                    row.innerHTML = `
                        <div>
                            <strong>${u.nickname}</strong>
                            <div class="swal-multi-row-subtext">${u.nome} ${u.cognome} ${infoData}</div>
                        </div>
                        <button type="button" class="swal-multi-btn-add">+</button>
                    `;

                    // L'evento ora è chiuso in una "bolla" protetta, la variabile 'u' non la perde mai!
                    row.querySelector('.swal-multi-btn-add').addEventListener('click', () => {
                        utentiSelezionati.set(String(u.codice), {
                            nickname: u.nickname,
                            nome: u.nome,
                            cognome: u.cognome,
                            data_formattata: u.data_formattata || ''
                        });
                        aggiornaColonnaSelezionati();
                        eseguiCercaUtente();
                    });

                    resultsDiv.appendChild(row);
                });
            }

            function aggiornaColonnaSelezionati() {
                countSpan.textContent = utentiSelezionati.size;
                if (utentiSelezionati.size === 0) {
                    selectedDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun utente selezionato.</p>';
                    return;
                }

                selectedDiv.innerHTML = '';
                utentiSelezionati.forEach((u, id) => {
                    const row = document.createElement('div');
                    const infoData = u.data_formattata ? ` ${u.data_formattata}` : '';
                    row.className = 'swal-multi-row swal-multi-row-selected';
                    row.innerHTML = `
                        <div>
                            <strong>${u.nickname}</strong>
                            <div class="swal-multi-row-subtext">${u.nome} ${u.cognome} ${infoData}</div>
                        </div>
                        <button type="button" class="swal-multi-btn-del">-</button>
                    `;

                    // Idem per la rimozione, evento agganciato direttamente
                    row.querySelector('.swal-multi-btn-del').addEventListener('click', () => {
                        utentiSelezionati.delete(id);
                        aggiornaColonnaSelezionati();
                        eseguiCercaUtente();
                    });

                    selectedDiv.appendChild(row);
                });
            }

            searchBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                eseguiCercaUtente();
            });

            [nickIn, nomeIn, cognomeIn, dateIn].forEach(input => {
                input.addEventListener('keyup', (e) => { if (e.key === 'Enter') searchBtn.click(); });
            });
        }
    });

    if (arrayIdUtenti) {
        eseguiRichiesta({
            azione: 'inserisci_utenti_multipli',
            nomeBacheca: nomeBacheca,
            owner: owner,
            listaUtenti: arrayIdUtenti
        }, 'Tutti gli utenti selezionati sono stati autorizzati con successo alla bacheca!');
    }
}

async function aggiungiAutorizzato(nomeBacheca, owner) {
    const utenteScelto = await cercaESelezionaUtente('Cerca Utente da Autorizzare', true, nomeBacheca, owner);

    if (utenteScelto) {
        eseguiRichiesta({
            azione: 'aggiungi_autorizzato',
            nome: nomeBacheca,
            owner: owner,
            nuovoUtente: parseInt(utenteScelto.id, 10)
        }, `Utente <b class="swal-text-bold">${utenteScelto.nickname}</b> autorizzato con successo.`);
    }
}

function rimuoviAutorizzato(nomeBacheca, owner, utenteDaRimuovere, nickname) {
    Swal.fire({
        title: 'Rimuovi Autorizzazione',
        html: `Vuoi davvero revocare l'accesso a <b class="swal-text-heavy">${nickname}</b> da <b class="swal-text-heavy">${nomeBacheca}</b>? Tutti i suoi file verranno rimossi da questa bacheca.`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, revoca',
        cancelButtonText: 'Annulla',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'rimuovi_autorizzato',
                nome: nomeBacheca,
                owner: owner,
                utenteDaRimuovere: parseInt(utenteDaRimuovere, 10)
            }, `Autorizzazione revocata e file rimossi.`);
        }
    });
}


// ====================================================================
// GESTIONE FILE MULTIPLI
// ====================================================================
async function aggiungiFileMultipli(nomeBacheca, owner) {
    let fileSelezionati = new Map(); // key: idFile, value: { nomeFile, nickname }

    const { value: arrayIdFiles } = await Swal.fire({
        title: `Seleziona file da pubblicare`,
        width: '1000px',
        heightAuto: false,
        scrollbarPadding: false,
        html: `
            <div class="swal-multi-grid">
                
                <div class="swal-multi-col-left">
                    <h5 class="swal-multi-header swal-multi-header-blue">Filtri Cerca</h5>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Nome File</label>
                        <input id="swal-f-filename" class="swal2-input swal-multi-input" placeholder="Es. foto panorama">
                    </div>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Autore (Nickname)</label>
                        <input id="swal-f-nickname" class="swal2-input swal-multi-input" placeholder="Es. supermario">
                    </div>
                    <div class="swal-multi-input-group">
                        <label class="swal-multi-label">Autore (Nome)</label>
                        <input id="swal-f-nome" class="swal2-input swal-multi-input" placeholder="Es. Mario">
                    </div>
                    <div class="swal-multi-input-group-last">
                        <label class="swal-multi-label">Autore (Cognome)</label>
                        <input id="swal-f-cognome" class="swal2-input swal-multi-input" placeholder="Es. Rossi">
                    </div>
                    <button id="swal-f-search-btn" class="swal2-styled swal2-confirm swal-multi-btn-search">Filtra File</button>
                </div>

                <div class="swal-multi-col-center">
                    <h5 id="swal-f-results-title" class="swal-multi-header swal-multi-header-green">File Disponibili</h5>
                    <div id="swal-f-results" class="swal-multi-list">
                        <p class="swal-multi-msg-loading">Caricamento file disponibili...</p>
                    </div>
                </div>

                <div class="swal-multi-col-right">
                    <h5 class="swal-multi-header swal-multi-header-orange">Da pubblicare (<span id="swal-f-count">0</span>)</h5>
                    <div id="swal-f-selected" class="swal-multi-list">
                        <p class="swal-multi-msg-empty">Nessun file selezionato.</p>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Pubblica file',
        cancelButtonText: 'Annulla',
        reverseButtons: true,
        preConfirm: () => {
            if (fileSelezionati.size === 0) {
                Swal.showValidationMessage('Seleziona almeno un file dalla colonna centrale prima di pubblicare.');
                return false;
            }
            return Array.from(fileSelezionati.keys());
        },
        didOpen: () => {
            const searchBtn = document.getElementById('swal-f-search-btn');
            const fileIn = document.getElementById('swal-f-filename');
            const nickIn = document.getElementById('swal-f-nickname');
            const nomeIn = document.getElementById('swal-f-nome');
            const cognomeIn = document.getElementById('swal-f-cognome');

            const resultsDiv = document.getElementById('swal-f-results');
            const selectedDiv = document.getElementById('swal-f-selected');
            const countSpan = document.getElementById('swal-f-count');
            const resultsTitle = document.getElementById('swal-f-results-title');

            async function eseguiCercaFile() {
                resultsDiv.innerHTML = '<p class="swal-multi-msg-loading">Ricerca in corso...</p>';
                resultsTitle.textContent = 'Ricerca in corso...';

                const payloadDati = {
                    azione: 'cerca_file_bacheca',
                    nome: nomeBacheca,
                    owner: owner,
                    file_esclusi: Array.from(fileSelezionati.keys())
                };

                const tFile = fileIn.value.trim();
                const tNick = nickIn.value.trim();
                const tNome = nomeIn.value.trim();
                const tCogn = cognomeIn.value.trim();

                if (tFile) payloadDati.termine_file = tFile;
                if (tNick) payloadDati.nickname = tNick;
                if (tNome) payloadDati.filtro_nome = tNome;
                if (tCogn) payloadDati.cognome = tCogn;

                try {
                    const response = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payloadDati)
                    });
                    const data = await response.json();

                    if (data.successo && data.files && data.files.length > 0) {
                        renderizzaRisultatiCentrali(data.files);
                    } else {
                        resultsTitle.textContent = 'Disponibili (0)';
                        resultsDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun file disponibile per questa ricerca.</p>';
                    }
                } catch (err) {
                    resultsTitle.textContent = 'Errore';
                    resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Errore di comunicazione col server.</p>';
                }
            }

            function renderizzaRisultatiCentrali(files) {
                resultsDiv.innerHTML = '';

                const limitFile = 30;
                if (files.length >= limitFile) {
                    resultsTitle.textContent = `Disponibili (Primi ${limitFile})`;
                } else {
                    resultsTitle.textContent = `Disponibili (${files.length})`;
                }

                files.forEach(f => {
                    const row = document.createElement('div');
                    row.className = 'swal-multi-row';
                    row.innerHTML = `
                        <div>
                            <strong>${f.nome_file}</strong>
                            <div class="swal-multi-row-subtext">@${f.nickname} (${f.utente_nome} ${f.utente_cognome})</div>
                        </div>
                        <button type="button" class="swal-multi-btn-add">+</button>
                    `;

                    // Aggiungiamo l'evento in modo sicuro e diretto
                    row.querySelector('.swal-multi-btn-add').addEventListener('click', () => {
                        fileSelezionati.set(String(f.numero), {
                            nomeFile: f.nome_file,
                            nickname: f.nickname
                        });
                        aggiornaColonnaSelezionati();
                        eseguiCercaFile();
                    });

                    resultsDiv.appendChild(row);
                });
            }

            function aggiornaColonnaSelezionati() {
                countSpan.textContent = fileSelezionati.size;
                if (fileSelezionati.size === 0) {
                    selectedDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun file selezionato.</p>';
                    return;
                }

                selectedDiv.innerHTML = '';
                fileSelezionati.forEach((f, id) => {
                    const row = document.createElement('div');
                    row.className = 'swal-multi-row swal-multi-row-selected';
                    row.innerHTML = `
                        <div>
                            <strong>${f.nomeFile}</strong>
                            <div class="swal-multi-row-subtext">@${f.nickname}</div>
                        </div>
                        <button type="button" class="swal-multi-btn-del">-</button>
                    `;

                    // Aggiungiamo l'evento di rimozione in modo sicuro
                    row.querySelector('.swal-multi-btn-del').addEventListener('click', () => {
                        fileSelezionati.delete(id);
                        aggiornaColonnaSelezionati();
                        eseguiCercaFile();
                    });

                    selectedDiv.appendChild(row);
                });
            }

            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                eseguiCercaFile();
            });

            [fileIn, nickIn, nomeIn, cognomeIn].forEach(input => {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        eseguiCercaFile();
                    }
                });
            });

            eseguiCercaFile();
        }
    });

    if (arrayIdFiles) {
        eseguiRichiesta({
            azione: 'inserisci_file_multipli',
            nomeBacheca: nomeBacheca,
            owner: owner,
            listaFiles: arrayIdFiles
        }, 'Tutti i file selezionati sono stati aggiunti correttamente alla bacheca!');
    }
}

function rimuoviFile(nomeBacheca, owner, fileDaRimuovere, nomeFile, caricatoDa) {
    Swal.fire({
        title: 'Rimuovi File',
        html: `Vuoi davvero rimuovere il file <b class="swal-text-bold">${nomeFile}</b> caricato da <b class="swal-text-bold">${caricatoDa}</b> dalla bacheca?`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, rimuovi',
        cancelButtonText: 'Annulla',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const messaggioSuccesso = `File rimosso con successo.`;

            eseguiRichiesta({
                azione: 'rimuovi_file',
                nome: nomeBacheca,
                owner: owner,
                fileDaRimuovere: parseInt(fileDaRimuovere, 10)
            }, messaggioSuccesso);
        }
    });
}