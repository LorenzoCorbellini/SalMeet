<?php

/**
 * @file bachecheAPI.php
 * @description Endpoint API per la gestione asincrona (AJAX) delle operazioni CRUD 
 * e delle ricerche dinamiche relative all'entità "Bacheca".
 * Riceve richieste POST contenenti un payload JSON e restituisce risposte nel medesimo formato.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['azione'])) {
        echo json_encode(['successo' => false, 'messaggio' => 'Azione mancante.']);
        exit;
    }

    $azione = $input['azione'];

    /**
     * AZIONE: cerca_utente
     * Esegue una ricerca dinamica degli utenti a sistema basata su criteri multipli 
     * (nickname, nome, cognome, data di nascita). Esclude automaticamente gli utenti 
     * che sono già autorizzati alla bacheca in esame e quelli già selezionati temporaneamente nel frontend.
     */
    if ($azione === 'cerca_utente') {
        $nickname     = !empty($input['nickname']) ? trim($input['nickname']) : '';
        $filtro_nome  = !empty($input['filtro_nome']) ? trim($input['filtro_nome']) : '';
        $cognome      = !empty($input['cognome']) ? trim($input['cognome']) : '';
        $data_nascita = !empty($input['data_nascita']) ? trim($input['data_nascita']) : null;

        $nomeBachecaEsclusione = !empty($input['nomeBacheca']) ? trim($input['nomeBacheca']) : '';
        $ownerEsclusione       = isset($input['owner']) ? (int)$input['owner'] : 0;

        try {
            $sql = "SELECT codice, nickname, nome, cognome, dataNascita FROM Utente WHERE 1=1";
            $params = [];

            if ($nickname !== '') {
                $sql .= " AND nickname LIKE :nickname";
                $params[':nickname'] = '%' . $nickname . '%';
            }

            if ($filtro_nome !== '') {
                $sql .= " AND nome LIKE :filtro_nome";
                $params[':filtro_nome'] = '%' . $filtro_nome . '%';
            }

            if ($cognome !== '') {
                $sql .= " AND cognome LIKE :cognome";
                $params[':cognome'] = '%' . $cognome . '%';
            }

            if ($data_nascita !== null) {
                $sql .= " AND dataNascita >= :data_nascita";
                $params[':data_nascita'] = $data_nascita;
            }

            if (empty($params)) {
                echo json_encode(['successo' => true, 'utenti' => []]);
                exit;
            }

            if ($nomeBachecaEsclusione !== '' && $ownerEsclusione > 0) {
                $sql .= " AND codice NOT IN (
                    SELECT utenteAutorizzato 
                    FROM UtenteAutorizzatoBacheca 
                    WHERE nomeBacheca = :nomeBachecaEx 
                      AND codUtente = :ownerEx
                )";
                $params[':nomeBachecaEx'] = $nomeBachecaEsclusione;
                $params[':ownerEx']       = $ownerEsclusione;
            }

            $utenti_esclusi = !empty($input['utenti_esclusi']) && is_array($input['utenti_esclusi']) ? $input['utenti_esclusi'] : [];
            if (!empty($utenti_esclusi)) {
                $inParams = [];
                foreach ($utenti_esclusi as $i => $id) {
                    $pName = ':ex_u_' . $i;
                    $inParams[] = $pName;
                    $params[$pName] = (int)$id;
                }
                $sql .= " AND codice NOT IN (" . implode(',', $inParams) . ")";
            }

            $sql .= " ORDER BY nickname ASC LIMIT 50";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($utenti as &$u) {
                if (!empty($u['dataNascita'])) {
                    if (function_exists('formattaData')) {
                        $u['data_formattata'] = formattaData($u['dataNascita']);
                    } else {
                        $u['data_formattata'] = date('d/m/Y', strtotime($u['dataNascita']));
                    }
                }
            }

            echo json_encode(['successo' => true, 'utenti' => $utenti]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => 'Errore nel server durante la ricerca.']);
        }
        exit;
    }

    /**
     * AZIONE: inserisci_utenti_multipli
     * Associa massivamente un elenco di utenti (forniti via array di ID) a una specifica bacheca.
     * Sfrutta una transazione SQL e la clausola ON DUPLICATE KEY UPDATE per gestire 
     * eventuali conflitti o ri-autorizzazioni.
     */
    if ($azione === 'inserisci_utenti_multipli') {
        $nBachecaUtenti = isset($input['nomeBacheca']) ? trim($input['nomeBacheca']) : (isset($input['nome']) ? trim($input['nome']) : '');
        $idOwnerUtenti  = isset($input['owner']) ? (int)$input['owner'] : 0;
        $listaUtenti    = !empty($input['listaUtenti']) && is_array($input['listaUtenti']) ? $input['listaUtenti'] : [];

        if ($nBachecaUtenti === '' || $idOwnerUtenti <= 0 || empty($listaUtenti)) {
            echo json_encode(['successo' => false, 'messaggio' => 'Parametri incompleti per il salvataggio utenti.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO UtenteAutorizzatoBacheca (nomeBacheca, codUtente, utenteAutorizzato, autorizzato) 
                                   VALUES (:nb, :ow, :ua, 1)
                                   ON DUPLICATE KEY UPDATE autorizzato = 1");

            foreach ($listaUtenti as $idU) {
                $stmt->execute([
                    ':nb' => $nBachecaUtenti,
                    ':ow' => $idOwnerUtenti,
                    ':ua' => (int)$idU
                ]);
            }

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => 'Errore durante il salvataggio utenti: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: inserisci_file_multipli
     * Pubblica massivamente un elenco di file multimediali in una bacheca.
     * Implementa una transazione SQL sicura e ignora i duplicati già esistenti per prevenire errori.
     */
    if ($azione === 'inserisci_file_multipli') {
        $nBachecaFiles = isset($input['nomeBacheca']) ? trim($input['nomeBacheca']) : (isset($input['nome']) ? trim($input['nome']) : '');
        $idOwnerFiles  = isset($input['owner']) ? $input['owner'] : null;
        $listaFiles    = !empty($input['listaFiles']) && is_array($input['listaFiles']) ? $input['listaFiles'] : [];

        if ($nBachecaFiles === '' || $idOwnerFiles === null || count($listaFiles) === 0) {
            $c = count($listaFiles);
            $o = $idOwnerFiles !== null ? $idOwnerFiles : 'MANCANTE';
            echo json_encode(['successo' => false, 'messaggio' => "Errore Parametri - Nome: '$nBachecaFiles', Owner: $o, File Ricevuti: $c"]);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO FilePubblicatoBacheca (nomeBacheca, codUtente, file) 
                                   VALUES (:nb, :ow, :fi)
                                   ON DUPLICATE KEY UPDATE file = file");

            foreach ($listaFiles as $idF) {
                $stmt->execute([
                    ':nb' => $nBachecaFiles,
                    ':ow' => (int)$idOwnerFiles,
                    ':fi' => (int)$idF
                ]);
            }

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => 'Errore pubblicazione file: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * VALIDAZIONE GLOBALE PARAMETRI STANDARD
     * Verifica la presenza delle chiavi minime 'nome' (bacheca) e 'owner' (proprietario)
     * richieste per l'esecuzione di tutte le successive azioni CRUD singole.
     */
    if (empty($input['nome']) || !isset($input['owner'])) {
        echo json_encode(['successo' => false, 'messaggio' => 'Parametri mancanti per azione standard.']);
        exit;
    }

    $nome  = trim($input['nome']);
    $owner = (int) $input['owner'];

    /**
     * AZIONE: cerca_file_bacheca
     * Estrae i file appartenenti agli utenti che sono già autorizzati in una determinata bacheca,
     * consentendone la ricerca puntuale e ignorando quelli che vi sono già stati pubblicati.
     */
    if ($azione === 'cerca_file_bacheca') {
        $termine_file = isset($input['termine_file']) ? trim($input['termine_file']) : '';
        $nickname     = isset($input['nickname']) ? trim($input['nickname']) : '';
        $filtro_nome  = isset($input['filtro_nome']) ? trim($input['filtro_nome']) : '';
        $cognome      = isset($input['cognome']) ? trim($input['cognome']) : '';

        try {
            $sql = "SELECT f.numero, f.titolo AS nome_file, u.nickname, u.nome AS utente_nome, u.cognome AS utente_cognome 
                    FROM FileMultimediale f
                    JOIN Utente u ON f.caricatoDa = u.codice
                    WHERE f.caricatoDa IN (
                        SELECT utenteAutorizzato 
                        FROM UtenteAutorizzatoBacheca 
                        WHERE nomeBacheca = :nomeBacheca AND codUtente = :ownerBacheca AND autorizzato = 1
                    )";

            $params = [
                ':nomeBacheca'  => $nome,
                ':ownerBacheca' => $owner
            ];

            $sql .= " AND f.numero NOT IN (
                SELECT file 
                FROM FilePubblicatoBacheca 
                WHERE nomeBacheca = :nomeBacheca AND codUtente = :ownerBacheca
            )";

            if ($termine_file !== '') {
                $sql .= " AND f.titolo LIKE :termine_file";
                $params[':termine_file'] = '%' . $termine_file . '%';
            }
            if ($nickname !== '') {
                $sql .= " AND u.nickname LIKE :nickname";
                $params[':nickname'] = '%' . $nickname . '%';
            }
            if ($filtro_nome !== '') {
                $sql .= " AND u.nome LIKE :filtro_nome";
                $params[':filtro_nome'] = '%' . $filtro_nome . '%';
            }
            if ($cognome !== '') {
                $sql .= " AND u.cognome LIKE :cognome";
                $params[':cognome'] = '%' . $cognome . '%';
            }

            $file_esclusi = !empty($input['file_esclusi']) && is_array($input['file_esclusi']) ? $input['file_esclusi'] : [];
            if (!empty($file_esclusi)) {
                $inParams = [];
                foreach ($file_esclusi as $i => $id) {
                    $pName = ':ex_f_' . $i;
                    $inParams[] = $pName;
                    $params[$pName] = (int)$id;
                }
                $sql .= " AND f.numero NOT IN (" . implode(',', $inParams) . ")";
            }

            $sql .= " ORDER BY f.numero DESC LIMIT 30";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['successo' => true, 'files' => $files]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => 'Errore nel server durante la ricerca dei file.']);
        }
        exit;
    }

    /**
     * AZIONE: aggiungi
     * Crea una nuova bacheca. Effettua controlli sulla lunghezza del nome, sull'esistenza
     * del proprietario e previene la duplicazione della bacheca.
     */
    if ($azione === 'aggiungi') {
        $lunghezzaNome = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
        if ($lunghezzaNome > 45) {
            echo json_encode(['successo' => false, 'messaggio' => 'Il nome della bacheca non può superare i 45 caratteri.']);
            exit;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM Utente WHERE codice = ?");
        $st->execute([$owner]);
        if ($st->fetchColumn() == 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Utente non esistente.']);
            exit;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM Bacheca WHERE nome = ? AND codiceUtente = ?");
        $st->execute([$nome, $owner]);
        if ($st->fetchColumn() > 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Bacheca già esistente per questo utente.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $dataOggi = date('Y-m-d');
            $stmt1 = $pdo->prepare("INSERT INTO Bacheca (nome, codiceUtente, dataCreazione) VALUES (?, ?, ?)");
            $stmt1->execute([$nome, $owner, $dataOggi]);

            $stmt2 = $pdo->prepare("INSERT INTO UtenteAutorizzatoBacheca (nomeBacheca, codUtente, utenteAutorizzato, autorizzato) VALUES (?, ?, ?, 1)");
            $stmt2->execute([$nome, $owner, $owner]);

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => 'Errore: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: aggiungi_autorizzato
     * Concede l'autorizzazione diretta a un singolo utente per l'accesso a una bacheca.
     */
    if ($azione === 'aggiungi_autorizzato') {
        $nuovoUtente = (int) ($input['nuovoUtente'] ?? 0);

        if ($nuovoUtente <= 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Codice utente non valido.']);
            exit;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM Utente WHERE codice = ?");
        $st->execute([$nuovoUtente]);
        if ($st->fetchColumn() == 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'L\'utente non esiste.']);
            exit;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ?");
        $st->execute([$nome, $owner, $nuovoUtente]);
        if ($st->fetchColumn() > 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Utente già autorizzato o in attesa.']);
            exit;
        }

        try {
            $pdo->prepare("INSERT INTO UtenteAutorizzatoBacheca (nomeBacheca, codUtente, utenteAutorizzato, autorizzato) VALUES (?, ?, ?, 1)")
                ->execute([$nome, $owner, $nuovoUtente]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: accetta_richiesta
     * Aggiorna lo stato di una richiesta pendente, concedendo l'autorizzazione di accesso.
     */
    if ($azione === 'accetta_richiesta') {
        $target = (int) ($input['utenteTarget'] ?? 0);
        try {
            $pdo->prepare("UPDATE UtenteAutorizzatoBacheca SET autorizzato = 1 WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ?")
                ->execute([$nome, $owner, $target]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: rifiuta_richiesta
     * Elimina definitivamente una richiesta di accesso pendente (non autorizzata).
     */
    if ($azione === 'rifiuta_richiesta') {
        $target = (int) ($input['utenteTarget'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM UtenteAutorizzatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ? AND autorizzato = 0")
                ->execute([$nome, $owner, $target]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: rimuovi_autorizzato
     * Revoca l'accesso alla bacheca per un determinato utente e, contestualmente, rimuove 
     * tutti i file che quest'ultimo vi aveva precedentemente pubblicato.
     */
    if ($azione === 'rimuovi_autorizzato') {
        $target = (int) ($input['utenteDaRimuovere'] ?? 0);

        if ($target === $owner) {
            echo json_encode(['successo' => false, 'messaggio' => 'Non puoi rimuovere il proprietario.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stDeleteFiles = $pdo->prepare("
                DELETE FROM FilePubblicatoBacheca 
                WHERE nomeBacheca = ? AND codUtente = ? AND file IN (
                    SELECT numero FROM FileMultimediale WHERE caricatoDa = ?
                )
            ");
            $stDeleteFiles->execute([$nome, $owner, $target]);

            $stDeleteAuth = $pdo->prepare("
                DELETE FROM UtenteAutorizzatoBacheca 
                WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ?
            ");
            $stDeleteAuth->execute([$nome, $owner, $target]);

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['successo' => false, 'messaggio' => 'Errore durante la rimozione: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * AZIONE: rimuovi_file
     * Dissocia e rimuove un singolo file pubblicato dalla bacheca specificata.
     */
    if ($azione === 'rimuovi_file') {
        $targetFile = (int) ($input['fileDaRimuovere'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM FilePubblicatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND file = ?")
                ->execute([$nome, $owner, $targetFile]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * VERIFICA PRELIMINARE MODIFICA/ELIMINAZIONE
     * Controlla l'effettiva esistenza della bacheca prima di consentire 
     * le operazioni distruttive o di ridenominazione.
     */
    $check = $pdo->prepare("SELECT COUNT(*) FROM Bacheca WHERE nome = :nome AND codiceUtente = :owner");
    $check->execute([':nome' => $nome, ':owner' => $owner]);

    if ($check->fetchColumn() == 0) {
        echo json_encode(['successo' => false, 'messaggio' => 'Bacheca non trovata.']);
        exit;
    }

    /**
     * AZIONE: rinomina
     * Modifica il nome di una bacheca esistente. Implementa un aggiornamento esplicito delle 
     * tabelle relazionali e disattiva temporaneamente il controllo delle chiavi esterne per 
     * forzare l'aggiornamento a cascata senza blocchi.
     */
    if ($azione === 'rinomina') {
        $nuovoNome = trim($input['nuovoNome'] ?? '');
        if ($nuovoNome === '') {
            echo json_encode(['successo' => false, 'messaggio' => 'Nome non valido.']);
            exit;
        }

        $lunghezzaNuovoNome = function_exists('mb_strlen') ? mb_strlen($nuovoNome, 'UTF-8') : strlen($nuovoNome);
        if ($lunghezzaNuovoNome > 45) {
            echo json_encode(['successo' => false, 'messaggio' => 'Il nuovo nome della bacheca non può superare i 45 caratteri.']);
            exit;
        }

        $checkDup = $pdo->prepare("SELECT COUNT(*) FROM Bacheca WHERE nome = :nuovoNome AND codiceUtente = :owner AND nome != :vecchioNome");
        $checkDup->execute([
            ':nuovoNome'   => $nuovoNome,
            ':owner'       => $owner,
            ':vecchioNome' => $nome
        ]);

        if ($checkDup->fetchColumn() > 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Bacheca omonima esistente.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            $pdo->prepare("UPDATE UtenteAutorizzatoBacheca SET nomeBacheca = :nuovoNome WHERE nomeBacheca = :vecchioNome AND codUtente = :owner")
                ->execute([':nuovoNome' => $nuovoNome, ':vecchioNome' => $nome, ':owner' => $owner]);

            $pdo->prepare("UPDATE FilePubblicatoBacheca SET nomeBacheca = :nuovoNome WHERE nomeBacheca = :vecchioNome AND codUtente = :owner")
                ->execute([':nuovoNome' => $nuovoNome, ':vecchioNome' => $nome, ':owner' => $owner]);

            $pdo->prepare("UPDATE Bacheca SET nome = :nuovoNome WHERE nome = :vecchioNome AND codiceUtente = :owner")
                ->execute([':nuovoNome' => $nuovoNome, ':vecchioNome' => $nome, ':owner' => $owner]);

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                $pdo->rollBack();
            }
            echo json_encode(['successo' => false, 'messaggio' => 'Errore durante la ridenominazione: ' . $e->getMessage()]);
        }
    }

    /**
     * AZIONE: elimina
     * Cancella in via definitiva una bacheca e tutte le sue associazioni dal database.
     */
    elseif ($azione === 'elimina') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM Bacheca WHERE nome = :nome AND codiceUtente = :owner")
                ->execute([':nome' => $nome, ':owner' => $owner]);
            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['successo' => false, 'messaggio' => 'Azione non riconosciuta.']);
    }

    $pdo = null;
    exit;
} else {
    http_response_code(405);
    echo json_encode(['successo' => false, 'messaggio' => 'Metodo non consentito. Usa POST.']);
    exit;
}
