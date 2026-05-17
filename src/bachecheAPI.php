<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';


// =========================================================
// CRUD
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['azione']) || empty($input['nome']) || empty($input['owner'])) {
        echo json_encode(['successo' => false, 'messaggio' => 'Parametri mancanti.']);
        exit;
    }

    $azione = $input['azione'];
    $nome   = trim($input['nome']);
    $owner  = (int) $input['owner'];

    // ---------------------------------------------------------
    // AGGIUNGI BACHECA
    // ---------------------------------------------------------
    if ($azione === 'aggiungi') {
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

            $stmt2 = $pdo->prepare("INSERT INTO UtenteAutorizzatoBacheca (nomeBacheca, codUtente, utenteAutorizzato) VALUES (?, ?, ?)");
            $stmt2->execute([$nome, $owner, $owner]);

            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => 'Errore: ' . $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------------------------------------
    // AGGIUNGI UTENTE AUTORIZZATO
    // ---------------------------------------------------------
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
            echo json_encode(['successo' => false, 'messaggio' => 'Utente già autorizzato.']);
            exit;
        }

        try {
            $pdo->prepare("INSERT INTO UtenteAutorizzatoBacheca (nomeBacheca, codUtente, utenteAutorizzato) VALUES (?, ?, ?)")
                ->execute([$nome, $owner, $nuovoUtente]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------------------------------------
    // RIMUOVI UTENTE AUTORIZZATO
    // ---------------------------------------------------------
    if ($azione === 'rimuovi_autorizzato') {
        $target = (int) ($input['utenteDaRimuovere'] ?? 0);

        if ($target === $owner) {
            echo json_encode(['successo' => false, 'messaggio' => 'Non puoi rimuovere il proprietario.']);
            exit;
        }

        try {
            $pdo->prepare("DELETE FROM UtenteAutorizzatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ?")
                ->execute([$nome, $owner, $target]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------------------------------------
    // AGGIUNGI FILE
    // ---------------------------------------------------------
    if ($azione === 'aggiungi_file') {
        $nuovoFile = (int) ($input['nuovoFile'] ?? 0);

        if ($nuovoFile <= 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'ID file non valido.']);
            exit;
        }

        $stFile = $pdo->prepare("SELECT caricatoDa FROM FileMultimediale WHERE numero = ?");
        $stFile->execute([$nuovoFile]);
        $fileData = $stFile->fetch(PDO::FETCH_ASSOC);

        if (!$fileData) {
            echo json_encode(['successo' => false, 'messaggio' => 'Il file non esiste.']);
            exit;
        }

        $creatoreFile = (int) $fileData['caricatoDa'];

        $stAuth = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND utenteAutorizzato = ?");
        $stAuth->execute([$nome, $owner, $creatoreFile]);
        if ($stAuth->fetchColumn() == 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Creatore non autorizzato per questa bacheca.']);
            exit;
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM FilePubblicatoBacheca WHERE nomeBacheca = ? AND codUtente = ? AND file = ?");
        $st->execute([$nome, $owner, $nuovoFile]);
        if ($st->fetchColumn() > 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Il file è già stato pubblicato.']);
            exit;
        }

        try {
            $pdo->prepare("INSERT INTO FilePubblicatoBacheca (nomeBacheca, codUtente, file) VALUES (?, ?, ?)")
                ->execute([$nome, $owner, $nuovoFile]);
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------------------------------------
    // RIMUOVI FILE
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // MODIFICA & ELIMINA
    // ---------------------------------------------------------
    $check = $pdo->prepare("SELECT COUNT(*) FROM Bacheca WHERE nome = :nome AND codiceUtente = :owner");
    $check->execute([':nome' => $nome, ':owner' => $owner]);

    if ($check->fetchColumn() == 0) {
        echo json_encode(['successo' => false, 'messaggio' => 'Bacheca non trovata.']);
        exit;
    }

    if ($azione === 'modifica') {
        $nuovoNome = trim($input['nuovoNome'] ?? '');
        if ($nuovoNome === '') {
            echo json_encode(['successo' => false, 'messaggio' => 'Nome non valido.']);
            exit;
        }

        $checkDup = $pdo->prepare("SELECT COUNT(*) FROM Bacheca WHERE nome = :nuovoNome AND codiceUtente = :owner");
        $checkDup->execute([':nuovoNome' => $nuovoNome, ':owner' => $owner]);
        if ($checkDup->fetchColumn() > 0) {
            echo json_encode(['successo' => false, 'messaggio' => 'Bacheca omonima esistente.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE Bacheca SET nome = :nuovoNome WHERE nome = :nome AND codiceUtente = :owner")
                ->execute([':nuovoNome' => $nuovoNome, ':nome' => $nome, ':owner' => $owner]);
            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
    } elseif ($azione === 'elimina') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM Bacheca WHERE nome = :nome AND codiceUtente = :owner")
                ->execute([':nome' => $nome, ':owner' => $owner]);
            $pdo->commit();
            echo json_encode(['successo' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['successo' => false, 'messaggio' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['successo' => false, 'messaggio' => 'Azione non riconosciuta.']);
    }
    $pdo = null;
    exit;
} else {
    // Opzionale: blocca chi tenta di accedere a questo file aprendolo dal browser normalmente (GET)
    http_response_code(405);
    echo json_encode(['successo' => false, 'messaggio' => 'Metodo non consentito. Usa POST.']);
    exit;
}
