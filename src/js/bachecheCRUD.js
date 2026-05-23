const API_URL = 'bachecheAPI.php';

// Funzione helper per le chiamate fetch standardizzate
function eseguiRichiesta(bodyData, messaggioSuccesso) {
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
                        text: messaggioSuccesso,
                        timer: 2000,
                        showConfirmButton: false,
                        heightAuto: false,
                        scrollbarPadding: false
                    }).then(() => location.reload());
                } else {
                    location.reload();
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
// ASTRAZIONE: FUNZIONE DI RICERCA UTENTE (Campi Separati)
// =========================================================
async function cercaESelezionaUtente(titoloPopup) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            heightAuto: false,
            scrollbarPadding: false,
            html: `
                <div style="text-align: left; margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nickname:</label>
                        <input id="swal-search-nickname" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. supermario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nome:</label>
                        <input id="swal-search-nome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Mario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Cognome:</label>
                        <input id="swal-search-cognome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Rossi">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Data di Nascita:</label>
                        <input id="swal-search-date" type="date" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;">
                    </div>
                    <button id="swal-search-btn" class="swal2-styled swal2-confirm" style="margin: 10px 0 0 0; width: 100%; height: 40px; font-size: 0.95rem !important;">Cerca</button>
                </div>
                <div id="swal-search-results" style="max-height: 180px; overflow-y: auto; text-align: left; margin-top: 15px; border-top: 1px solid var(--border-soft); padding-top: 12px;">
                    <p style="color: var(--text-muted); text-align: center; margin-top: 5px; font-size: 0.9rem;">Compila almeno un campo per avviare la ricerca.</p>
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
                            let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                            data.utenti.forEach(u => {
                                const infoData = u.data_formattata ? ` | Nascita: ${u.data_formattata}` : '';
                                html += `
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; border:1px solid var(--border-soft); border-radius:8px; transition: background 0.2s;">
                                        <input type="radio" name="swal-user-radio" value="${u.codice}" style="margin:0; width: 18px; height: 18px; accent-color: var(--primary);">
                                        <span style="color: var(--text-dark); font-size: 0.95rem;">
                                            <strong>${u.nickname}</strong> 
                                            <span style="color: var(--text-muted); font-size: 0.85em;">(${u.nome} ${u.cognome}${infoData})</span>
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
                    Swal.showValidationMessage('Seleziona un utente dalla lista prima di proseguire.');
                    return false;
                }
                return selected.value;
            }
        }).then((result) => {
            if (result.isConfirmed) resolve(result.value);
            else resolve(null);
        });
    });
}

// ====================================================================
// ASTRAZIONE: FUNZIONE DI RICERCA FILE DEGLI UTENTI AUTORIZZATI
// ====================================================================
async function cercaESelezionaFile(nomeBacheca, owner, titoloPopup) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            heightAuto: false,
            scrollbarPadding: false,
            html: `
                <div style="text-align: left; margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nome File:</label>
                        <input id="swal-search-filename" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. foto.jpg">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nickname Utente:</label>
                        <input id="swal-search-file-nickname" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. supermario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nome Utente:</label>
                        <input id="swal-search-file-nome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Mario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Cognome Utente:</label>
                        <input id="swal-search-file-cognome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Rossi">
                    </div>
                    <button id="swal-file-search-btn" class="swal2-styled swal2-confirm" style="margin: 10px 0 0 0; width: 100%; height: 40px; font-size: 0.95rem !important;">Cerca</button>
                </div>
                <div id="swal-file-search-results" style="max-height: 180px; overflow-y: auto; text-align: left; margin-top: 15px; border-top: 1px solid var(--border-soft); padding-top: 12px;">
                    <p style="color: var(--text-muted); text-align: center; margin-top: 5px; font-size: 0.9rem;">Caricamento file disponibili...</p>
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
                            let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                            data.files.forEach(f => {
                                html += `
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; border:1px solid var(--border-soft); border-radius:8px; transition: background 0.2s;">
                                        <input type="radio" name="swal-file-radio" value="${f.numero}" style="margin:0; width: 18px; height: 18px; accent-color: var(--primary);">
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

                // Consente l'invio rapido premendo Invio sui campi di testo
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

                // Avvio iniziale immediato all'apertura del popup per mostrare tutti i file autorizzati
                eseguiCercaFile();
            },
            preConfirm: () => {
                const selected = document.querySelector('input[name="swal-file-radio"]:checked');
                if (!selected) {
                    Swal.showValidationMessage('Seleziona un file dalla lista prima di proseguire.');
                    return false;
                }
                return selected.value;
            }
        }).then((result) => {
            if (result.isConfirmed) resolve(result.value);
            else resolve(null);
        });
    });
}

// =========================================================
// GESTIONE BACHECHE
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

    if (!nomeBacheca) return;

    const ownerId = await cercaESelezionaUtente('Assegna un Proprietario');

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
            if (!value || value.trim() === '') {
                return 'Il nome non può essere vuoto!';
            }
        }
    });

    if (nuovoNome && nuovoNome.trim() !== nomeBacheca) {
        eseguiRichiesta({
            azione: 'rinomina',
            nome: nomeBacheca,
            owner: owner,
            nuovoNome: nuovoNome.trim()
        }, 'Bacheca rinominata con successo.');
    }
}

function eliminaBacheca(nomeBacheca, owner, ownerNickname) {
    Swal.fire({
        title: 'Sei sicuro?',
        text: `Vuoi davvero eliminare la bacheca "${nomeBacheca}" dell'utente "${ownerNickname}"? L'azione è irreversibile!`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina!',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'elimina',
                nome: nomeBacheca,
                owner: owner
            }, 'Bacheca Basket eliminata con successo.');
        }
    });
}

// =========================================================
// GESTIONE UTENTI NELLA BACHECA
// =========================================================

async function aggiungiAutorizzato(nomeBacheca, owner) {
    const utenteId = await cercaESelezionaUtente('Cerca Utente da Autorizzare');

    if (utenteId) {
        eseguiRichiesta({
            azione: 'aggiungi_autorizzato',
            nome: nomeBacheca,
            owner: owner,
            nuovoUtente: parseInt(utenteId, 10)
        }, 'Utente autorizzato con successo.');
    }
}

function rimuoviAutorizzato(nomeBacheca, owner, utenteDaRimuovere, nickname) {
    Swal.fire({
        title: 'Rimuovi Utente',
        text: `Vuoi davvero revocare l'accesso all'utente "${nickname}" per questa bacheca? Verranno rimossi anche i suoi file pubblicati.`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, rimuovi',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'rimuovi_autorizzato',
                nome: nomeBacheca,
                owner: owner,
                utenteDaRimuovere: utenteDaRimuovere
            }, 'Utente rimosso con successo dalla bacheca.');
        }
    });
}

// =========================================================
// GESTIONE FILE NELLA BACHECA
// =========================================================

// ====================================================================
// ASTRAZIONE: FUNZIONE DI RICERCA FILE DEGLI UTENTI AUTORIZZATI
// ====================================================================
async function cercaESelezionaFile(nomeBacheca, owner, titoloPopup) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            heightAuto: false,
            scrollbarPadding: false,
            html: `
                <div style="text-align: left; margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nome File:</label>
                        <input id="swal-search-filename" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. foto.png">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nickname Utente:</label>
                        <input id="swal-search-file-nickname" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. supermario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Nome Utente:</label>
                        <input id="swal-search-file-nome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Mario">
                    </div>
                    <div>
                        <label class="swal2-input-label" style="display:block; margin-bottom: 2px; font-weight: normal; font-size: 0.9rem;">Cognome Utente:</label>
                        <input id="swal-search-file-cognome" class="swal2-input" style="margin: 0; width: 100%; box-sizing: border-box; height: 38px !important;" placeholder="Es. Rossi">
                    </div>
                    <button id="swal-file-search-btn" class="swal2-styled swal2-confirm" style="margin: 10px 0 0 0; width: 100%; height: 40px; font-size: 0.95rem !important;">Filtra Risultati</button>
                </div>
                <div id="swal-file-search-results" style="max-height: 180px; overflow-y: auto; text-align: left; margin-top: 15px; border-top: 1px solid #ddd; padding-top: 12px;">
                    <p style="color: #666; text-align: center; margin-top: 5px; font-size: 0.9rem;">Caricamento iniziale dei file...</p>
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
                    resultsDiv.innerHTML = '<p style="color:#666; text-align:center; margin:15px 0;"><i>Ricerca in corso...</i></p>';
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
                            let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                            data.files.forEach(f => {
                                html += `
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; border:1px solid #ddd; border-radius:8px; transition: background 0.2s;">
                                        <input type="radio" name="swal-file-radio" value="${f.numero}" style="margin:0; width: 18px; height: 18px;">
                                        <span style="color: #333; font-size: 0.95rem;">
                                            <strong>${f.nome_file}</strong> 
                                            <span style="color: #666; font-size: 0.85em; display:block; margin-top:2px;">Caricato da: <b>@${f.nickname}</b> (${f.utente_nome} ${f.utente_cognome})</span>
                                        </span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<p style="color:#666; text-align:center; margin:15px 0;">Nessun file disponibile o trovato per i criteri inseriti.</p>';
                        }
                    } catch (err) {
                        resultsDiv.innerHTML = '<p style="color:red; text-align:center; margin:15px 0;">Errore di comunicazione col server.</p>';
                    }
                };

                // Consente l'invio rapido della ricerca premendo Invio sui campi di testo
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

                // AVVIO IMMEDIATO ALL'APERTURA: Carica subito tutti i file degli utenti autorizzati
                eseguiCercaFile();
            },
            preConfirm: () => {
                const selected = document.querySelector('input[name="swal-file-radio"]:checked');
                if (!selected) {
                    Swal.showValidationMessage('Seleziona un file dalla lista prima di proseguire.');
                    return false;
                }
                return selected.value;
            }
        }).then((result) => {
            if (result.isConfirmed) resolve(result.value);
            else resolve(null);
        });
    });
}

// Aggiornamento della funzione standard di pubblicazione file
async function aggiungiFile(nomeBacheca, owner) {
    const fileId = await cercaESelezionaFile(nomeBacheca, owner, 'Seleziona un File da Pubblicare');

    if (fileId) {
        eseguiRichiesta({
            azione: 'aggiungi_file',
            nome: nomeBacheca,
            owner: owner,
            nuovoFile: parseInt(fileId, 10)
        }, 'File aggiunto con successo alla bacheca.');
    }
}

function rimuoviFile(nomeBacheca, owner, fileDaRimuovere, nomeFile, caricatoDa) {
    Swal.fire({
        title: 'Rimuovi File',
        text: `Vuoi davvero rimuovere il file "${nomeFile}" caricato da "${caricatoDa}" dalla bacheca?`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, rimuovi',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'rimuovi_file',
                nome: nomeBacheca,
                owner: owner,
                fileDaRimuovere: fileDaRimuovere
            }, 'File rimosso con successo dalla bacheca.');
        }
    });
}