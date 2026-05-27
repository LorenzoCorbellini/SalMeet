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
                                const infoData = u.data_formattata ? ` | Nascita: ${u.data_formattata}` : '';
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

            let cachedServerResults = [];

            const aggiornaColonnaSelezionati = () => {
                countSpan.textContent = utentiSelezionati.size;
                if (utentiSelezionati.size === 0) {
                    selectedDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun utente selezionato.</p>';
                    return;
                }

                selectedDiv.innerHTML = '';
                utentiSelezionati.forEach((u, id) => {
                    const row = document.createElement('div');
                    row.className = 'swal-multi-row swal-multi-row-selected';
                    row.innerHTML = `
                        <div>
                            <strong>${u.nickname}</strong>
                            <div class="swal-multi-row-subtext">${u.nome} ${u.cognome}</div>
                        </div>
                        <button type="button" class="swal-multi-btn-del" data-id="${id}">-</button>
                    `;
                    selectedDiv.appendChild(row);
                });

                selectedDiv.querySelectorAll('.swal-multi-btn-del').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const idDaTogliere = btn.getAttribute('data-id');
                        utentiSelezionati.delete(idDaTogliere);
                        aggiornaColonnaSelezionati();
                        renderizzaRisultatiCentrali(cachedServerResults);
                    });
                });
            };

            const renderizzaRisultatiCentrali = (utenti) => {
                cachedServerResults = utenti;
                resultsDiv.innerHTML = '';

                const filtrati = utenti.filter(u => !utentiSelezionati.has(String(u.codice)));

                if (filtrati.length === 0) {
                    resultsDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun utente disponibile o tutti già selezionati.</p>';
                    return;
                }

                filtrati.forEach(u => {
                    const row = document.createElement('div');
                    row.className = 'swal-multi-row';
                    row.innerHTML = `
                        <div>
                            <strong>${u.nickname}</strong>
                            <div class="swal-multi-row-subtext">${u.nome} ${u.cognome}</div>
                        </div>
                        <button type="button" class="swal-multi-btn-add" data-id="${u.codice}" data-nick="${u.nickname}" data-nome="${u.nome}" data-cognome="${u.cognome}">+</button>
                    `;
                    resultsDiv.appendChild(row);
                });

                resultsDiv.querySelectorAll('.swal-multi-btn-add').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = btn.getAttribute('data-id');
                        utentiSelezionati.set(id, {
                            nickname: btn.getAttribute('data-nick'),
                            nome: btn.getAttribute('data-nome'),
                            cognome: btn.getAttribute('data-cognome')
                        });
                        aggiornaColonnaSelezionati();
                        renderizzaRisultatiCentrali(cachedServerResults);
                    });
                });
            };

            searchBtn.addEventListener('click', async () => {
                const payload = {
                    azione: 'cerca_utente',
                    nickname: nickIn.value.trim(),
                    filtro_nome: nomeIn.value.trim(),
                    cognome: cognomeIn.value.trim(),
                    data_nascita: dateIn.value || null,
                    nomeBacheca: nomeBacheca,
                    owner: owner
                };

                resultsDiv.innerHTML = '<p class="swal-multi-msg-loading">Ricerca in corso...</p>';

                try {
                    const r = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await r.json();

                    if (data.successo && data.utenti) {
                        resultsTitle.textContent = `Risultati (${data.utenti.length})`;
                        renderizzaRisultatiCentrali(data.utenti);
                    } else {
                        resultsTitle.textContent = 'Risultati (0)';
                        resultsDiv.innerHTML = `<p class="swal-multi-msg-error">${data.messaggio || 'Nessun risultato.'}</p>`;
                    }
                } catch (err) {
                    resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Errore server di ricerca.</p>';
                }
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
        }, 'Tutti gli utenti selezionati sono stati abilitati correttamente nella bacheca!');
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
        html: `Vuoi davvero revocare l'accesso a <b class="swal-text-heavy">${nickname}</b>? Tutti i suoi file in questa bacheca verranno rimossi.`,
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
// GESTIONE FILE
// ====================================================================
async function cercaESelezionaFile(nomeBacheca, owner, titoloPopup, returnFullObject = false) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            width: '750px',
            heightAuto: false,
            scrollbarPadding: false,
            html: `
                <div class="swal-dual-grid">
                    <div class="swal-multi-col-left">
                        <h5 class="swal-multi-header swal-multi-header-blue">Ricerca File</h5>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Nome File</label>
                            <input id="swal-search-filename" class="swal2-input swal-multi-input" placeholder="Es. foto panorama">
                        </div>
                        <h5 class="swal-multi-header swal-multi-header-blue" style="margin-top:15px;">Filtra per Autore</h5>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Nickname</label>
                            <input id="swal-search-file-nickname" class="swal2-input swal-multi-input" placeholder="Es. supermario">
                        </div>
                        <div class="swal-multi-input-group">
                            <label class="swal-multi-label">Nome</label>
                            <input id="swal-search-file-nome" class="swal2-input swal-multi-input" placeholder="Es. Mario">
                        </div>
                        <div class="swal-multi-input-group-last">
                            <label class="swal-multi-label">Cognome</label>
                            <input id="swal-search-file-cognome" class="swal2-input swal-multi-input" placeholder="Es. Rossi">
                        </div>
                        <button id="swal-file-search-btn" class="swal2-styled swal2-confirm swal-multi-btn-search">Filtra file</button>
                    </div>
                    
                    <div class="swal-multi-col-right">
                        <h5 id="swal-file-count" class="swal-multi-header swal-multi-header-green">Risultati</h5>
                        <div id="swal-file-search-results" class="swal-multi-list">
                            <p class="swal-multi-msg-loading">Caricamento file disponibili...</p>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Seleziona',
            cancelButtonText: 'Annulla',
            reverseButtons: true,
            didOpen: () => {
                const searchBtn = document.getElementById('swal-file-search-btn');
                const filenameInput = document.getElementById('swal-search-filename');
                const nicknameInput = document.getElementById('swal-search-file-nickname');
                const nomeInput = document.getElementById('swal-search-file-nome');
                const cognomeInput = document.getElementById('swal-search-file-cognome');
                const resultsDiv = document.getElementById('swal-file-search-results');
                const countSpan = document.getElementById('swal-file-count');

                const eseguiCercaFile = async () => {
                    resultsDiv.innerHTML = '<p class="swal-multi-msg-loading">Ricerca in corso...</p>';
                    countSpan.innerHTML = 'Ricerca in corso...';

                    const tFile = filenameInput.value.trim();
                    const tNick = nicknameInput.value.trim();
                    const tNome = nomeInput.value.trim();
                    const tCogn = cognomeInput.value.trim();

                    const payloadDati = {
                        azione: 'cerca_file_bacheca',
                        nome: nomeBacheca,
                        owner: owner
                    };
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

                        if (data.successo && data.files.length > 0) {
                            const limitFile = 30;
                            const hasMore = data.files.length >= limitFile;

                            if (hasMore) {
                                countSpan.innerHTML = `Risultati (Primi ${limitFile})`;
                            } else {
                                countSpan.innerHTML = `Risultati (${data.files.length})`;
                            }

                            // Generazione righe iniettate direttamente nel contenitore flessibile
                            let html = '';
                            data.files.forEach(f => {
                                html += `
                                    <label class="swal-multi-row" style="cursor: pointer; justify-content: flex-start; gap: 10px;">
                                        <input type="radio" name="swal-file-radio" value="${f.numero}" data-nome="${f.nome_file}" data-owner="${f.nickname}" style="cursor: pointer; margin:0;">
                                        <div>
                                            <strong>${f.nome_file}</strong> 
                                            <div class="swal-multi-row-subtext">Caricato da: <b>@${f.nickname}</b> (${f.utente_nome} ${f.utente_cognome})</div>
                                        </div>
                                    </label>
                                `;
                            });
                            resultsDiv.innerHTML = html;
                        } else {
                            countSpan.innerHTML = `Risultati (0)`;
                            resultsDiv.innerHTML = '<p class="swal-multi-msg-empty">Nessun file disponibile o trovato per i criteri inseriti.</p>';
                        }
                    } catch (err) {
                        countSpan.innerHTML = 'Risultati (0)';
                        resultsDiv.innerHTML = '<p class="swal-multi-msg-error">Errore di comunicazione col server.</p>';
                    }
                };

                [filenameInput, nicknameInput, nomeInput, cognomeInput].forEach(input => {
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            eseguiCercaFile();
                        }
                    });
                });

                searchBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    eseguiCercaFile();
                });

                eseguiCercaFile();
            },
            preConfirm: () => {
                const selected = document.querySelector('input[name="swal-file-radio"]:checked');
                if (!selected) {
                    Swal.showValidationMessage('Seleziona un file dalla lista a destra prima di proseguire.');
                    return false;
                }
                return {
                    id: selected.value,
                    nome: selected.getAttribute('data-nome'),
                    proprietario: selected.getAttribute('data-owner')
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

async function aggiungiFile(nomeBacheca, owner) {
    const fileScelto = await cercaESelezionaFile(nomeBacheca, owner, 'Seleziona un File da Pubblicare', true);

    if (fileScelto) {
        const messaggioConferma = `File <b class="swal-text-bold">${fileScelto.nome}</b> di <b class="swal-text-bold">${fileScelto.proprietario}</b> aggiunto con successo alla bacheca.`;

        eseguiRichiesta({
            azione: 'aggiungi_file',
            nome: nomeBacheca,
            owner: owner,
            nuovoFile: parseInt(fileScelto.id, 10)
        }, messaggioConferma);
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