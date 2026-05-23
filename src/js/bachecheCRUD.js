const API_URL = 'bachecheAPI.php';

// Funzione helper per le chiamate fetch standardizzate con supporto al redirect dinamico
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
                        text: messaggioSuccesso,
                        timer: 1500,
                        showConfirmButton: false,
                        heightAuto: false,
                        scrollbarPadding: false
                    }).then(() => {
                        if (urlRedirect) {
                            window.location.href = urlRedirect;
                        } else {
                            location.reload();
                        }
                    });
                } else {
                    if (urlRedirect) {
                        window.location.href = urlRedirect;
                    } else {
                        location.reload();
                    }
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

function aggiungiBacheca() {
    Swal.fire({
        title: 'Nuova Bacheca',
        input: 'text',
        inputLabel: 'Nome della bacheca:',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Crea',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'Il nome non può essere vuoto!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const nomeBacheca = result.value.trim();
            // Ricaviamo l'owner corrente dalla sessione/URL se necessario, altrimenti impostiamo un default temporaneo
            const urlParams = new URLSearchParams(window.location.search);
            const currentOwner = urlParams.get('owner') || 1; // Fallback di sicurezza se non specificato

            eseguiRichiesta({
                azione: 'aggiungi',
                nome: nomeBacheca,
                owner: parseInt(currentOwner, 10)
            }, 'Bacheca creata con successo!');
        }
    });
}

function rinominaBacheca(vecchioNome, owner) {
    Swal.fire({
        title: `Rinomina "${vecchioNome}"`,
        input: 'text',
        inputValue: vecchioNome,
        inputLabel: 'Nuovo nome della bacheca:',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Salva',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'Il nome non può essere vuoto!';
            }
            if (value.trim() === vecchioNome) {
                return 'Il nome è identico a quello precedente!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const nuovoNome = result.value.trim();
            
            // Costruiamo il nuovo URL mantenendo tutti i parametri correnti ma aggiornando il nome bacheca
            const url = new URL(window.location.href);
            url.searchParams.set('bacheca', nuovoNome);
            url.searchParams.set('owner', owner);
            
            eseguiRichiesta({
                azione: 'rinomina',
                nome: vecchioNome,
                owner: parseInt(owner, 10),
                nuovoNome: nuovoNome
            }, 'Bacheca rinominata con successo!', url.toString());
        }
    });
}

function eliminaBacheca(nomeBacheca, owner, ownerNickname) {
    Swal.fire({
        title: 'Elimina Bacheca',
        text: `Vuoi davvero eliminare la bacheca "${nomeBacheca}" di ${ownerNickname}? L'azione è irreversibile!`,
        icon: 'warning',
        heightAuto: false,
        scrollbarPadding: false,
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            // Dopo l'eliminazione torniamo alla lista pulita delle bacheche
            const urlRedirect = 'bacheche.php';

            eseguiRichiesta({
                azione: 'elimina',
                nome: nomeBacheca,
                owner: parseInt(owner, 10)
            }, 'Bacheca eliminata con successo!', urlRedirect);
        }
    });
}

// -------------------------------------------------------------------------
// FUNZIONI COMPLEMENTARI (AUTORIZZAZIONI E FILE)
// -------------------------------------------------------------------------

function cercaESelezionaUtente(nomeBacheca, owner) {
    return new Promise((resolve) => {
        Swal.fire({
            title: 'Cerca Utente da Autorizzare',
            html: `
                <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
                    <label>Nickname:</label><input id="swal-nick" class="swal2-input" style="margin:0;">
                    <label>Nome:</label><input id="swal-nome" class="swal2-input" style="margin:0;">
                    <label>Cognome:</label><input id="swal-cognome" class="swal2-input" style="margin:0;">
                </div>
                <button id="swal-btn-cerca" class="swal2-confirm swal2-styled" style="margin-top:15px;">Cerca</button>
                <div id="swal-risultati" style="margin-top:15px; max-height:150px; overflow-y:auto; border:1px solid #ccc; display:none; text-align:left; padding:5px;"></div>
            `,
            showCancelButton: true,
            cancelButtonText: 'Chiudi',
            showConfirmButton: true,
            confirmButtonText: 'Autorizza Selezionato',
            heightAuto: false,
            scrollbarPadding: false,
            didOpen: () => {
                const btnCerca = document.getElementById('swal-btn-cerca');
                btnCerca.addEventListener('click', () => {
                    const nick = document.getElementById('swal-nick').value.trim();
                    const nome = document.getElementById('swal-nome').value.trim();
                    const cognome = document.getElementById('swal-cognome').value.trim();

                    fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            azione: 'cerca_utente',
                            nickname: nick,
                            filtro_nome: nome,
                            cognome: cognome
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const box = document.getElementById('swal-risultati');
                        box.innerHTML = '';
                        box.style.display = 'block';

                        if (data.successo && data.utenti.length > 0) {
                            data.utenti.forEach(u => {
                                const div = document.createElement('div');
                                div.style.padding = '5px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.innerHTML = `<strong>${u.nickname}</strong> - ${u.nome} ${u.cognome} (Nato il: ${u.data_formattata || u.dataNascita})`;
                                div.addEventListener('click', () => {
                                    // Deseleziona precedenti
                                    Array.from(box.children).forEach(c => c.style.background = 'none');
                                    div.style.background = '#d3e2ff';
                                    div.setAttribute('data-selezionato', u.codice);
                                });
                                box.appendChild(div);
                            });
                        } else {
                            box.innerHTML = '<span style="color:red; padding:5px; display:block;">Nessun utente trovato con questi filtri.</span>';
                        }
                    });
                });
            },
            preConfirm: () => {
                const box = document.getElementById('swal-risultati');
                const selezionato = box.querySelector('[data-selezionato]');
                if (!selezionato) {
                    Swal.showValidationMessage('Devi prima cercare e cliccare su un utente dalla lista!');
                    return false;
                }
                return selezionato.getAttribute('data-selezionato');
            }
        }).then((result) => {
            if (result.isConfirmed) resolve(result.value);
            else resolve(null);
        });
    });
}

async function aggiungiAutorizzato(nomeBacheca, owner) {
    const utenteId = await cercaESelezionaUtente(nomeBacheca, owner);
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
        title: 'Rimuovi Autorizzazione',
        text: `Vuoi davvero revocare l'accesso a "${nickname}"? Tutti i suoi file pubblicati in questa bacheca verranno rimossi di conseguenza.`,
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
            }, 'Autorizzazione revocata e file associati rimossi.');
        }
    });
}

function cercaESelezionaFile(nomeBacheca, owner, titoloPopup) {
    return new Promise((resolve) => {
        Swal.fire({
            title: titoloPopup,
            html: `
                <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
                    <label>Filtra per Titolo File:</label><input id="swal-file-titolo" class="swal2-input" style="margin:0;">
                    <hr style="margin: 10px 0; border:0; border-top:1px solid #eee;">
                    <span style="font-size:12px; color:#666;">Filtra per Utente Caricatore (Opzionale):</span>
                    <label>Nickname:</label><input id="swal-file-nick" class="swal2-input" style="margin:0;">
                    <label>Nome:</label><input id="swal-file-nome" class="swal2-input" style="margin:0;">
                    <label>Cognome:</label><input id="swal-file-cognome" class="swal2-input" style="margin:0;">
                </div>
                <button id="swal-btn-cerca-file" class="swal2-confirm swal2-styled" style="margin-top:15px;">Cerca File</button>
                <div id="swal-file-risultati" style="margin-top:15px; max-height:150px; overflow-y:auto; border:1px solid #ccc; display:none; text-align:left; padding:5px;"></div>
            `,
            showCancelButton: true,
            cancelButtonText: 'Chiudi',
            showConfirmButton: true,
            confirmButtonText: 'Pubblica Selezionato',
            heightAuto: false,
            scrollbarPadding: false,
            didOpen: () => {
                const btnCerca = document.getElementById('swal-btn-cerca-file');
                btnCerca.addEventListener('click', () => {
                    const termine = document.getElementById('swal-file-titolo').value.trim();
                    const nick = document.getElementById('swal-file-nick').value.trim();
                    const nome = document.getElementById('swal-file-nome').value.trim();
                    const cognome = document.getElementById('swal-file-cognome').value.trim();

                    fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            azione: 'cerca_file_bacheca',
                            nome: nomeBacheca,
                            owner: owner,
                            termine_file: termine,
                            nickname: nick,
                            filtro_nome: nome,
                            cognome: cognome
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const box = document.getElementById('swal-file-risultati');
                        box.innerHTML = '';
                        box.style.display = 'block';

                        if (data.successo && data.files.length > 0) {
                            data.files.forEach(f => {
                                const div = document.createElement('div');
                                div.style.padding = '5px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.innerHTML = `[ID: ${f.numero}] <strong>${f.nome_file}</strong> <br><span style="font-size:11px; color:#555;">Caricato da: ${f.nickname} (${f.utente_nome} ${f.utente_cognome})</span>`;
                                div.addEventListener('click', () => {
                                    Array.from(box.children).forEach(c => c.style.background = 'none');
                                    div.style.background = '#d3e2ff';
                                    div.setAttribute('data-selezionato', f.numero);
                                });
                                box.appendChild(div);
                            });
                        } else {
                            box.innerHTML = '<span style="color:red; padding:5px; display:block;">Nessun file disponibile o trovato con questi filtri.</span>';
                        }
                    });
                });
            },
            preConfirm: () => {
                const box = document.getElementById('swal-file-risultati');
                const selected = box.querySelector('[data-selezionato]');
                if (!selected) {
                    Swal.showValidationMessage('Devi prima cercare e selezionare un file!');
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
                fileDaRimuovere: parseInt(fileDaRimuovere, 10)
            }, 'File rimosso dalla bacheca.');
        }
    });
}