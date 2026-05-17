
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
            if (messaggioSuccesso) alert(messaggioSuccesso);
            location.reload();
        } else {
            alert('Errore: ' + data.messaggio);
        }
    })
    .catch((error) => {
        console.error(error);
        alert('Errore di comunicazione con il server.');
    });
}

// =========================================================
// GESTIONE BACHECHE
// =========================================================

function aggiungiBacheca() {
    const nome = prompt("Inserisci il nome per la nuova bacheca:");
    if (nome === null) return; 

    const nomeTrim = nome.trim();
    if (nomeTrim === '') {
        alert('Il nome non può essere vuoto.');
        return;
    }

    const ownerInput = prompt("Inserisci il codice utente del proprietario:");
    if (ownerInput === null) return;
    
    const owner = parseInt(ownerInput, 10);
    if (isNaN(owner) || owner <= 0) {
        alert('Codice utente non valido.');
        return;
    }

    eseguiRichiesta({
        azione: 'aggiungi',
        nome: nomeTrim,
        owner: owner
    }, 'Bacheca creata con successo.');
}

function modificaBacheca(nomeAttuale, owner) {
    const nuovoNome = prompt(`Nuovo nome per la bacheca:\n"${nomeAttuale}"`, nomeAttuale);
    if (nuovoNome === null) return; 

    const nuovoNomeTrim = nuovoNome.trim();
    if (nuovoNomeTrim === '') {
        alert('Il nome non può essere vuoto.');
        return;
    }

    if (nuovoNomeTrim === nomeAttuale) {
        alert('Il nome è uguale a quello attuale.');
        return;
    }

    const conferma = confirm(`Rinominare la bacheca da:\n"${nomeAttuale}"\na:\n"${nuovoNomeTrim}"?`);
    if (!conferma) return;

    eseguiRichiesta({
        azione:      'modifica',
        nome:        nomeAttuale,
        owner:       owner,
        nuovoNome:   nuovoNomeTrim
    }, 'Bacheca rinominata con successo.');
}

function eliminaBacheca(nome, owner) {
    if (!confirm(`Sei sicuro di voler eliminare definitivamente la bacheca "${nome}"?`)) return;

    eseguiRichiesta({
        azione: 'elimina',
        nome: nome,
        owner: owner
    }, 'Bacheca eliminata con successo.');
}

// =========================================================
// GESTIONE UTENTI AUTORIZZATI
// =========================================================

function aggiungiAutorizzato(nomeBacheca, owner) {
    const utenteInput = prompt("Inserisci il codice dell'utente da autorizzare:");
    if (utenteInput === null) return;

    const idUtente = parseInt(utenteInput, 10);
    if (isNaN(idUtente) || idUtente <= 0) {
        alert('Codice utente non valido.');
        return;
    }

    eseguiRichiesta({
        azione: 'aggiungi_autorizzato',
        nome: nomeBacheca,
        owner: owner,
        utenteDaAutorizzare: idUtente
    }, 'Utente autorizzato con successo.');
}

function rimuoviAutorizzato(nomeBacheca, owner, utenteDaRimuovere) {
    if (!confirm("Sei sicuro di voler rimuovere l'autorizzazione a questo utente?")) return;

    eseguiRichiesta({
        azione: 'rimuovi_autorizzato',
        nome: nomeBacheca,
        owner: owner,
        utenteDaRimuovere: utenteDaRimuovere
    }, 'Utente rimosso con successo dalla bacheca.'); 
}

// =========================================================
// GESTIONE FILE NELLA BACHECA
// =========================================================

function aggiungiFile(nomeBacheca, owner) {
    const fileInput = prompt("Inserisci l'ID del file da aggiungere alla bacheca:");
    if (fileInput === null) return;
    
    const nuovoFile = parseInt(fileInput, 10);
    if (isNaN(nuovoFile) || nuovoFile <= 0) {
        alert('ID file non valido.');
        return;
    }

    eseguiRichiesta({
        azione: 'aggiungi_file',
        nome: nomeBacheca,
        owner: owner,
        nuovoFile: nuovoFile
    }, 'File aggiunto con successo alla bacheca.');
}

function rimuoviFile(nomeBacheca, owner, fileDaRimuovere) {
    if (!confirm("Rimuovere questo file dalla bacheca?")) return;

    eseguiRichiesta({
        azione: 'rimuovi_file',
        nome: nomeBacheca,
        owner: owner,
        fileDaRimuovere: fileDaRimuovere
    }, 'File rimosso con successo dalla bacheca.');
}