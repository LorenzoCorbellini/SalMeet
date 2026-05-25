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

function eliminaBacheca(nomeBacheca, owner) {
    Swal.fire({
        title: 'Elimina Bacheca',
        html: `Vuoi davvero eliminare la bacheca <b class="swal-text-bold">${nomeBacheca}</b>? L'azione è irreversibile.`,
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
                owner: owner
            }, `Bacheca <b class="swal-text-bold">${nomeBacheca}</b> eliminata con successo.`, 'bacheche.php');
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
                <div class="swal-split-container swal-user-split">
                    <div class="swal-split-sidebar">
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Nickname</label>
                            <input id="swal-search-nickname" class="swal2-input swal-custom-input" placeholder="Es. supermario">
                        </div>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Nome</label>
                            <input id="swal-search-nome" class="swal2-input swal-custom-input" placeholder="Es. Mario">
                        </div>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Cognome</label>
                            <input id="swal-search-cognome" class="swal2-input swal-custom-input" placeholder="Es. Rossi">
                        </div>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Data di Nascita</label>
                            <input id="swal-search-date" type="date" class="swal2-input swal-custom-input">
                        </div>
                        <button id="swal-search-btn" class="swal2-styled swal2-confirm swal-search-btn">Cerca Utente</button>
                    </div>
                    
                    <div class="swal-split-main">
                        <span class="swal-filter-label swal-results-title">Risultati della ricerca:</span>
                        <div id="swal-search-results" class="swal-results-container swal-results-user">
                            <p class="swal-text-placeholder">Compila almeno un campo a sinistra per avviare la ricerca.</p>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Seleziona',
            cancelButtonText: 'Annulla',
            reverseButtons: true, 
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
                        resultsDiv.innerHTML = '<p class="swal-text-error">Inserisci almeno un criterio per la ricerca.</p>';
                        return;
                    }

                    resultsDiv.innerHTML = '<p class="swal-text-info"><i>Ricerca in corso...</i></p>';

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
                            let html = '<div class="swal-radio-group">';
                            data.utenti.forEach(u => {
                                const infoData = u.data_formattata ? ` | Nascita: ${u.data_formattata}` : '';
                                html += `
                                    <label class="swal-radio-label">
                                        <input type="radio" name="swal-user-radio" value="${u.codice}" data-nickname="${u.nickname}" class="swal-radio-input">
                                        <span class="swal-user-info">
                                            <strong>${u.nickname}</strong> 
                                            <span class="swal-user-subinfo">${u.nome} ${u.cognome}${infoData}</span>
                                        </span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<p class="swal-text-info">Nessun utente trovato con questi criteri.</p>';
                        }
                    } catch (err) {
                        resultsDiv.innerHTML = '<p class="swal-text-error-center">Errore di comunicazione col server.</p>';
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
            }, `Autorizzazione revocata a <b class="swal-text-bold">${nickname}</b> e file rimossi.`);
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
                <div class="swal-split-container swal-file-split">
                    <div class="swal-split-sidebar">
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Nome File</label>
                            <input id="swal-search-filename" class="swal2-input swal-custom-input" placeholder="Es. foto panorama">
                        </div>
                        <hr class="swal-divider">
                        <span class="swal-section-header">Filtra per Autore</span>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Nickname</label>
                            <input id="swal-search-file-nickname" class="swal2-input swal-custom-input" placeholder="Es. supermario">
                        </div>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Nome</label>
                            <input id="swal-search-file-nome" class="swal2-input swal-custom-input" placeholder="Es. Mario">
                        </div>
                        <div class="swal-input-wrapper">
                            <label class="swal-filter-label swal-input-label">Cognome</label>
                            <input id="swal-search-file-cognome" class="swal2-input swal-custom-input" placeholder="Es. Rossi">
                        </div>
                        <button id="swal-file-search-btn" class="swal2-styled swal2-confirm swal-search-btn">Filtra file</button>
                    </div>
                    
                    <div class="swal-split-main">
                        <span class="swal-filter-label swal-results-title">File degli utenti autorizzati:</span>
                        <div id="swal-file-search-results" class="swal-results-container swal-results-file">
                            <p class="swal-text-placeholder">Caricamento file disponibili...</p>
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

                const eseguiCercaFile = async () => {
                    resultsDiv.innerHTML = '<p class="swal-text-info"><i>Ricerca in corso...</i></p>';
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
                            let html = '<div class="swal-radio-group">';
                            data.files.forEach(f => {
                                html += `
                                    <label class="swal-radio-label">
                                        <input type="radio" name="swal-file-radio" value="${f.numero}" data-nome="${f.nome_file}" data-owner="${f.nickname}" class="swal-radio-input">
                                        <span class="swal-user-info">
                                            <strong>${f.nome_file}</strong> 
                                            <span class="swal-user-subinfo">Caricato da: <b>@${f.nickname}</b> (${f.utente_nome} ${f.utente_cognome})</span>
                                        </span>
                                    </label>
                                `;
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<p class="swal-text-info">Nessun file disponibile o trovato per i criteri inseriti.</p>';
                        }
                    } catch (err) {
                        resultsDiv.innerHTML = '<p class="swal-text-error-center">Errore di comunicazione col server.</p>';
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
            const messaggioSuccesso = `File <b class="swal-text-bold">${nomeFile}</b> di <b class="swal-text-bold">${caricatoDa}</b> rimosso con successo.`;

            eseguiRichiesta({
                azione: 'rimuovi_file',
                nome: nomeBacheca,
                owner: owner,
                fileDaRimuovere: parseInt(fileDaRimuovere, 10)
            }, messaggioSuccesso);
        }
    });
}