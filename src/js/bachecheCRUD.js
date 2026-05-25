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
        confirmButtonText: 'Avanti &rarr;',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            if (!value || value.trim() === '') return 'Il nome della bacheca è obbligatorio.';
        }
    });

    // Se l'utente clicca Annulla o chiude il popup, fermiamo tutto
    if (!nomeBacheca) return;

    // Richiamiamo il popup di ricerca utente (Layout Split a due colonne)
    const ownerId = await cercaESelezionaUtente('Assegna un Proprietario');

    // Se un utente è stato selezionato, inviamo la richiesta al server
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
        inputValidator: (value) => {
            if (!value || value.trim() === '') return 'Il nome non può essere vuoto!';
        }
    });

    if (nuovoNome && nuovoNome.trim() !== nomeBacheca) {
        const nomePulito = nuovoNome.trim();
        const urlAttuale = new URL(window.location.href);

        // Sostituiamo il vecchio nome con il nuovo nell'URL
        if (urlAttuale.searchParams.has('bacheca')) {
            urlAttuale.searchParams.set('bacheca', nomePulito);
        }

        const messaggioConferma = `<b style="font-weight: bold !important;">${nomeBacheca}</b> rinominata con successo in <b style="font-weight: bold !important;">${nomePulito}</b>`;

        eseguiRichiesta({
            azione: 'rinomina',
            nome: nomeBacheca,
            owner: owner,
            nuovoNome: nomePulito
        }, messaggioConferma, urlAttuale.toString());
    }
}

function eliminaBacheca(nomeBacheca, owner) {
    Swal.fire({
        title: 'Elimina Bacheca',
        html: `Vuoi davvero eliminare la bacheca <b style="font-weight: bold !important;">${nomeBacheca}</b>? L'azione è irreversibile.`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'elimina',
                nome: nomeBacheca,
                owner: owner
            }, `Bacheca <b style="font-weight: bold !important;">${nomeBacheca}</b> eliminata con successo.`, 'bacheche.php');
        }
    });
}

// =========================================================
// GESTIONE UTENTI (Layout Split a due colonne)
// =========================================================
async function cercaESelezionaUtente(titoloPopup, returnFullObject = false) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            heightAuto: false,
            scrollbarPadding: false,
            customClass: { popup: 'swal-wide-split' },
            html: `
                <div style="display: flex; gap: 20px; text-align: left; align-items: stretch; min-height: 250px; margin-top: 15px;">
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 10px; border-right: 1px solid var(--border-soft); padding-right: 15px;">
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Nickname</label>
                            <input id="swal-search-nickname" class="swal2-input" placeholder="Es. supermario" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Nome</label>
                            <input id="swal-search-nome" class="swal2-input" placeholder="Es. Mario" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Cognome</label>
                            <input id="swal-search-cognome" class="swal2-input" placeholder="Es. Rossi" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Data di Nascita</label>
                            <input id="swal-search-date" type="date" class="swal2-input" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <button id="swal-search-btn" class="swal2-styled swal2-confirm" style="margin: 10px 0 0 0; width: 100%; height: 40px; font-size: 0.95rem !important; padding: 0;">Cerca Utente</button>
                    </div>
                    
                    <div style="flex: 1.5; display: flex; flex-direction: column;">
                        <span class="swal-filter-label" style="margin-bottom: 10px; color: var(--primary-dark); font-weight: bold !important;">Risultati della ricerca:</span>
                        <div id="swal-search-results" style="flex: 1; overflow-y: auto; max-height: 280px; padding-right: 5px;">
                            <p style="color: var(--text-muted); text-align: center; margin-top: 15px; font-size: 0.9rem;">Compila almeno un campo a sinistra per avviare la ricerca.</p>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Seleziona',
            cancelButtonText: 'Annulla',
            didOpen: () => {
                const searchBtn = document.getElementById('swal-search-btn');
                const nickInput = document.getElementById('swal-search-nickname');
                const nomeInput = document.getElementById('swal-search-nome');
                const cognomeInput = document.getElementById('swal-search-cognome');
                const dateInput = document.getElementById('swal-search-date');
                const resultsDiv = document.getElementById('swal-search-results');

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
                    const dateTerm = dateInput.value;

                    if (nicknameTerm.length === 0 && nomeTerm.length === 0 && cognomeTerm.length === 0 && !dateTerm) {
                        resultsDiv.innerHTML = '<p style="color:var(--accent); font-size:0.95em; margin:10px 0; text-align:center;">Inserisci almeno un criterio per la ricerca.</p>';
                        return;
                    }

                    resultsDiv.innerHTML = '<p style="color:var(--text-muted); text-align:center; margin:15px 0;"><i>Ricerca in corso...</i></p>';

                    try {
                        const response = await fetch(API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                azione: 'cerca_utente',
                                nickname: nicknameTerm,
                                filtro_nome: nomeTerm,
                                cognome: cognomeTerm,
                                data_nascita: dateTerm
                            })
                        });
                        const data = await response.json();

                        if (data.successo && data.utenti.length > 0) {
                            let html = '<div style="display:flex; flex-direction:column; gap:6px;">';
                            data.utenti.forEach(u => {
                                const infoData = u.data_formattata ? ` | Nascita: ${u.data_formattata}` : '';
                                html += `
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:8px 12px; border:1px solid var(--border-soft); border-radius:8px; transition: background 0.2s;">
                                        <input type="radio" name="swal-user-radio" value="${u.codice}" data-nickname="${u.nickname}" style="margin:0; width: 16px; height: 16px; accent-color: var(--primary);">
                                        <span style="color: var(--text-dark); font-size: 0.95rem;">
                                            <strong>${u.nickname}</strong> 
                                            <span style="color: var(--text-muted); font-size: 0.85em; display:block; margin-top:2px;">${u.nome} ${u.cognome}${infoData}</span>
                                        </span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<p style="color:var(--text-muted); text-align:center; margin:15px 0;">Nessun utente trovato con questi criteri.</p>';
                        }
                    } catch (err) {
                        resultsDiv.innerHTML = '<p style="color:var(--accent); text-align:center; margin:15px 0;">Errore di comunicazione col server.</p>';
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

async function aggiungiAutorizzato(nomeBacheca, owner) {
    const utenteScelto = await cercaESelezionaUtente('Cerca Utente da Autorizzare', true);

    if (utenteScelto) {
        eseguiRichiesta({
            azione: 'aggiungi_autorizzato',
            nome: nomeBacheca,
            owner: owner,
            nuovoUtente: parseInt(utenteScelto.id, 10)
        }, `Utente <b style="font-weight: bold !important;">${utenteScelto.nickname}</b> autorizzato con successo.`);
    }
}

function rimuoviAutorizzato(nomeBacheca, owner, utenteDaRimuovere, nickname) {
    Swal.fire({
        title: 'Rimuovi Autorizzazione',
        html: `Vuoi davvero revocare l'accesso a <b style="font-weight: 900 !important;">${nickname}</b>? Tutti i suoi file in questa bacheca verranno rimossi.`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, revoca',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'rimuovi_autorizzato',
                nome: nomeBacheca,
                owner: owner,
                utenteDaRimuovere: parseInt(utenteDaRimuovere, 10)
            }, `Autorizzazione revocata a <b style="font-weight: bold !important;">${nickname}</b> e file rimossi.`);
        }
    });
}

// ====================================================================
// GESTIONE FILE (Layout Split a due colonne)
// ====================================================================
async function cercaESelezionaFile(nomeBacheca, owner, titoloPopup, returnFullObject = false) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            heightAuto: false,
            scrollbarPadding: false,
            customClass: { popup: 'swal-wide-split' },
            html: `
                <div style="display: flex; gap: 20px; text-align: left; align-items: stretch; min-height: 280px; margin-top: 15px;">
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 10px; border-right: 1px solid var(--border-soft); padding-right: 15px;">
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Nome File</label>
                            <input id="swal-search-filename" class="swal2-input" placeholder="Es. foto panorama" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <hr style="margin: 4px 0; border:0; border-top:1px dashed var(--border-soft); width: 100%;">
                        <span style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight: bold !important;">Filtra per Autore</span>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Nickname</label>
                            <input id="swal-search-file-nickname" class="swal2-input" placeholder="Es. supermario" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Nome</label>
                            <input id="swal-search-file-nome" class="swal2-input" placeholder="Es. Mario" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <div style="width: 100%;">
                            <label class="swal-filter-label" style="font-weight: bold !important; display: block; margin-bottom: 4px;">Cognome</label>
                            <input id="swal-search-file-cognome" class="swal2-input" placeholder="Es. Rossi" style="width: 100%; max-width: 100%; margin: 0; box-sizing: border-box; height: 38px; font-size: 0.95rem;">
                        </div>
                        <button id="swal-file-search-btn" class="swal2-styled swal2-confirm" style="margin: 10px 0 0 0; width: 100%; height: 40px; font-size: 0.95rem !important; padding: 0;">Filtra file</button>
                    </div>
                    
                    <div style="flex: 1.5; display: flex; flex-direction: column;">
                        <span class="swal-filter-label" style="margin-bottom: 10px; color: var(--primary-dark); font-weight: bold !important;">File degli utenti autorizzati:</span>
                        <div id="swal-file-search-results" style="flex: 1; overflow-y: auto; max-height: 310px; padding-right: 5px;">
                            <p style="color: var(--text-muted); text-align: center; margin-top: 15px; font-size: 0.9rem;">Caricamento file disponibili...</p>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Seleziona',
            cancelButtonText: 'Annulla',
            didOpen: () => {
                const searchBtn = document.getElementById('swal-file-search-btn');
                const filenameInput = document.getElementById('swal-search-filename');
                const nicknameInput = document.getElementById('swal-search-file-nickname');
                const nomeInput = document.getElementById('swal-search-file-nome');
                const cognomeInput = document.getElementById('swal-search-file-cognome');
                const resultsDiv = document.getElementById('swal-file-search-results');

                const eseguiCercaFile = async () => {
                    resultsDiv.innerHTML = '<p style="color:var(--text-muted); text-align:center; margin:15px 0;"><i>Ricerca in corso...</i></p>';
                    try {
                        const response = await fetch(API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                azione: 'cerca_file_bacheca',
                                nome: nomeBacheca,
                                owner: owner,
                                termine_file: filenameInput.value.trim(),
                                nickname: nicknameInput.value.trim(),
                                filtro_nome: nomeInput.value.trim(),
                                cognome: cognomeInput.value.trim()
                            })
                        });
                        const data = await response.json();

                        if (data.successo && data.files.length > 0) {
                            let html = '<div style="display:flex; flex-direction:column; gap:6px;">';
                            data.files.forEach(f => {
                                html += `
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:8px 12px; border:1px solid var(--border-soft); border-radius:8px; transition: background 0.2s;">
                                        <input type="radio" name="swal-file-radio" value="${f.numero}" data-nome="${f.nome_file}" data-owner="${f.nickname}" style="margin:0; width: 16px; height: 16px; accent-color: var(--primary);">
                                        <span style="color: var(--text-dark); font-size: 0.95rem;">
                                            <strong>${f.nome_file}</strong> 
                                            <span style="color: var(--text-muted); font-size: 0.85em; display:block; margin-top:2px;">Caricato da: <b>@${f.nickname}</b> (${f.utente_nome} ${f.utente_cognome})</span>
                                        </span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<p style="color:var(--text-muted); text-align:center; margin:15px 0;">Nessun file disponibile o trovato per i criteri inseriti.</p>';
                        }
                    } catch (err) {
                        resultsDiv.innerHTML = '<p style="color:var(--accent); text-align:center; margin:15px 0;">Errore di comunicazione col server.</p>';
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
        const messaggioConferma = `File <b style="font-weight: bold !important;">${fileScelto.nome}</b> di <b style="font-weight: bold !important;">${fileScelto.proprietario}</b> aggiunto con successo alla bacheca.`;

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
        html: `Vuoi davvero rimuovere il file <b style="font-weight: bold !important;">${nomeFile}</b> caricato da <b style="font-weight: bold !important;">${caricatoDa}</b> dalla bacheca?`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, rimuovi',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            const messaggioSuccesso = `File <b style="font-weight: bold !important;">${nomeFile}</b> di <b style="font-weight: bold !important;">${caricatoDa}</b> rimosso con successo.`;

            eseguiRichiesta({
                azione: 'rimuovi_file',
                nome: nomeBacheca,
                owner: owner,
                fileDaRimuovere: parseInt(fileDaRimuovere, 10)
            }, messaggioSuccesso);
        }
    });
}