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
                // Popup di successo
                Swal.fire({
                    icon: 'success',
                    title: 'Operazione completata',
                    text: messaggioSuccesso,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                location.reload();
            }
        } else {
            // Popup di errore logico
            Swal.fire({
                icon: 'error',
                title: 'Errore',
                text: data.messaggio
            });
        }
    })
    .catch((error) => {
        console.error(error);
        // Popup di errore critico/server
        Swal.fire({
            icon: 'error',
            title: 'Errore di sistema',
            text: 'Impossibile comunicare con il server.'
        });
    });
}

// =========================================================
// GESTIONE BACHECHE
// =========================================================

async function aggiungiBacheca() {
    // Popup con form multiplo (Nome e Proprietario)
    const { value: formValues } = await Swal.fire({
        title: 'Nuova Bacheca',
        html:
            '<input id="swal-nome" class="swal2-input" placeholder="Nome della Bacheca">' +
            '<input id="swal-owner" type="number" class="swal2-input" placeholder="Codice Proprietario">',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Crea Bacheca',
        cancelButtonText: 'Annulla',
        preConfirm: () => {
            const nome = document.getElementById('swal-nome').value.trim();
            const owner = document.getElementById('swal-owner').value;
            
            if (!nome || !owner) {
                Swal.showValidationMessage('Per favore compila entrambi i campi.');
                return false;
            }
            
            const ownerInt = parseInt(owner, 10);
            if (isNaN(ownerInt) || ownerInt <= 0) {
                Swal.showValidationMessage('Il codice utente deve essere un numero valido.');
                return false;
            }
            
            return { nome: nome, owner: ownerInt };
        }
    });

    if (formValues) {
        eseguiRichiesta({
            azione: 'aggiungi',
            nome: formValues.nome,
            owner: formValues.owner
        }, 'Bacheca creata con successo.');
    }
}

async function modificaBacheca(nomeBacheca, owner) {
    const { value: nuovoNome } = await Swal.fire({
        title: 'Modifica Bacheca',
        input: 'text',
        inputLabel: 'Inserisci il nuovo nome:',
        inputValue: nomeBacheca,
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
            azione: 'modifica',
            nome: nomeBacheca,
            owner: owner,
            nuovoNome: nuovoNome.trim()
        }, 'Bacheca modificata con successo.');
    }
}

function eliminaBacheca(nomeBacheca, owner) {
    Swal.fire({
        title: 'Sei sicuro?',
        text: "Vuoi davvero eliminare questa bacheca e tutto il suo contenuto? L'azione è irreversibile!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sì, elimina!',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            eseguiRichiesta({
                azione: 'elimina',
                nome: nomeBacheca,
                owner: owner
            }, 'Bacheca eliminata con successo.');
        }
    });
}

// =========================================================
// GESTIONE UTENTI NELLA BACHECA
// =========================================================

async function aggiungiAutorizzato(nomeBacheca, owner) {
    const { value: userInput } = await Swal.fire({
        title: 'Autorizza Utente',
        input: 'number',
        inputLabel: "Inserisci il codice dell'utente da autorizzare:",
        showCancelButton: true,
        confirmButtonText: 'Autorizza',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            const val = parseInt(value, 10);
            if (isNaN(val) || val <= 0) {
                return 'ID utente non valido.';
            }
        }
    });

    if (userInput) {
        eseguiRichiesta({
            azione: 'aggiungi_autorizzato',
            nome: nomeBacheca,
            owner: owner,
            nuovoUtente: parseInt(userInput, 10)
        }, 'Utente autorizzato con successo.');
    }
}

function rimuoviAutorizzato(nomeBacheca, owner, utenteDaRimuovere) {
    Swal.fire({
        title: 'Rimuovi Utente',
        text: "Vuoi revocare l'accesso a questo utente per questa bacheca?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
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

async function aggiungiFile(nomeBacheca, owner) {
    const { value: fileInput } = await Swal.fire({
        title: 'Aggiungi File',
        input: 'number',
        inputLabel: "Inserisci l'ID del file da pubblicare:",
        showCancelButton: true,
        confirmButtonText: 'Aggiungi File',
        cancelButtonText: 'Annulla',
        inputValidator: (value) => {
            const val = parseInt(value, 10);
            if (isNaN(val) || val <= 0) {
                return 'ID file non valido.';
            }
        }
    });

    if (fileInput) {
        eseguiRichiesta({
            azione: 'aggiungi_file',
            nome: nomeBacheca,
            owner: owner,
            nuovoFile: parseInt(fileInput, 10)
        }, 'File aggiunto con successo alla bacheca.');
    }
}

function rimuoviFile(nomeBacheca, owner, fileDaRimuovere) {
    Swal.fire({
        title: 'Rimuovi File',
        text: "Sei sicuro di voler togliere questo file dalla bacheca?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
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