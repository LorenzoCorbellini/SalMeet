<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <title>SalMeet - Utenti</title>
    <?php include 'head.html'; ?>
</head>

<body>
    <header>
        <h1 id="hcod1">Utenti</h1>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <?php include 'nav.html'; ?>

            <?php
            // =========================================================
            // CONFIGURAZIONE DINAMICA DEI FILTRI NELLA SIDEBAR
            // =========================================================
            if (empty($_GET['utente'])) {
                // Filtri per la lista globale degli utenti
                $filtro_config = [
                    'campi' => [
                        ['tipo'  => 'text', 'name' => 'nickname', 'label' => 'Nickname'],
                        ['tipo'  => 'text', 'name' => 'nome',     'label' => 'Nome'],
                        ['tipo'  => 'text', 'name' => 'cognome',  'label' => 'Cognome'],
                        ['tipo'  => 'date', 'name' => 'data',     'label' => 'Data di Nascita (Da)'],
                    ]
                ];
                include 'filter.php';
            }
            ?>
        </aside>

        <div id="content">
            <?php
            // =========================================================
            // ROUTING VISTE
            // =========================================================
            if (!empty($_GET['utente'])) {
                $idUtente = (int)$_GET['utente'];

                // 1. Lettura dati anagrafici dell'utente selezionato (Modificato 'utente' in 'Utente')
                $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
                $stmtUtente->execute([':codice' => $idUtente]);
                $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

                if ($infoUtente) {
                    echo "<p><a href='utenti.php'>&larr; Torna all'elenco utenti</a></p>";
                    echo "<h2 class='h2utente'>Profilo di <b><i>" . htmlspecialchars($infoUtente['nickname']) . "</i></b></h2>";
                    echo "<p><strong>Nome:</strong> " . htmlspecialchars($infoUtente['nome']) . "</p>";
                    echo "<p><strong>Cognome:</strong> " . htmlspecialchars($infoUtente['cognome']) . "</p>";
                    echo "<p><strong>Data di Nascita:</strong> " . formattaData($infoUtente['dataNascita']) . "</p>";

                    // ---------------------------------------------------------
                    // TABELLA 1: BACHECHE ASSOCIATE CON LINK INCROCIATI
                    // ---------------------------------------------------------
                    echo "<h3>Bacheche associate</h3>";

                    $stmtBacheche = $pdo->prepare("
                        SELECT 
                            b.nome AS 'nomeBacheca',
                            b.codiceUtente AS 'bachecaOwnerId',
                            uProp.nickname AS 'proprietarioNickname'
                        FROM UtenteAutorizzatoBacheca uab
                        JOIN Bacheca b ON uab.codUtente = b.codiceUtente AND uab.nomeBacheca = b.nome
                        JOIN Utente uProp ON b.codiceUtente = uProp.codice
                        WHERE uab.utenteAutorizzato = :codice
                        ORDER BY b.nome ASC
                    ");
                    $stmtBacheche->execute([':codice' => $idUtente]);
                    $bachecheRaw = $stmtBacheche->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($bachecheRaw)) {
                        $datiBacheche = [];
                        foreach ($bachecheRaw as $bacheca) {
                            // Link che porta alla pagina bacheche.php passando vista, nome bacheca e proprietario
                            $linkBacheca = "bacheche.php?vista=dettaglio&bacheca=" . urlencode($bacheca['nomeBacheca']) . "&owner=" . urlencode($bacheca['bachecaOwnerId']);
                            $htmlBacheca = "<a href='{$linkBacheca}'>" . htmlspecialchars($bacheca['nomeBacheca']) . "</a>";

                            // Link che porta alla pagina utenti.php per il profilo del proprietario
                            $linkOwnerBacheca = "utenti.php?utente=" . urlencode($bacheca['bachecaOwnerId']);
                            $htmlProprietario = "<a href='{$linkOwnerBacheca}'>" . htmlspecialchars($bacheca['proprietarioNickname']) . "</a>";

                            $datiBacheche[] = [
                                'Nome Bacheca' => $htmlBacheca,
                                'Proprietario' => $htmlProprietario
                            ];
                        }
                        // Consentiamo il rendering HTML dei link indicandoli nel secondo parametro
                        stampaTabella($datiBacheche, ['Nome Bacheca', 'Proprietario']);
                    } else {
                        echo "<p>L'utente non partecipa a nessuna bacheca.</p>";
                    }

                    // ---------------------------------------------------------
                    // TABELLA 2: GRUPPI DI APPARTENENZA CON LINK INCROCIATI (Modificati Alias in snake_case)
                    // ---------------------------------------------------------
                    echo "<h3>Gruppi di appartenenza</h3>";

                    $stmtGruppi = $pdo->prepare("
                        SELECT 
                            g.codice AS 'gruppo_id',
                            g.nome AS 'nome_gruppo',
                            g.creatoDa AS 'gruppo_owner_id',
                            uProp.nickname AS 'proprietarioNickname'
                        FROM UtenteAutorizzatoGruppo uag
                        JOIN Gruppo g ON uag.codGruppo = g.codice
                        JOIN Utente uProp ON g.creatoDa = uProp.codice
                        WHERE uag.codUtente = :codice
                        ORDER BY g.nome ASC
                    ");
                    $stmtGruppi->execute([':codice' => $idUtente]);
                    $gruppiRaw = $stmtGruppi->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($gruppiRaw)) {
                        $datiGruppi = [];
                        foreach ($gruppiRaw as $gruppo) {
                            // Link che porta alla pagina gruppi.php passando l'ID univoco del gruppo
                            $linkGruppo = "gruppi.php?gruppo=" . urlencode($gruppo['gruppo_id']);
                            $htmlGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($gruppo['nome_gruppo']) . "</a>";

                            // Link che porta alla pagina utenti.php per il profilo del creatore del gruppo
                            $linkOwnerGruppo = "utenti.php?utente=" . urlencode($gruppo['gruppo_owner_id']);
                            $htmlProprietarioGruppo = "<a href='{$linkOwnerGruppo}'>" . htmlspecialchars($gruppo['proprietarioNickname']) . "</a>";

                            $datiGruppi[] = [
                                'Nome Gruppo'  => $htmlGruppo,
                                'Proprietario' => $htmlProprietarioGruppo
                            ];
                        }
                        // Consentiamo il rendering HTML dei link indicandoli nel secondo parametro
                        stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario']);
                    } else {
                        echo "<p>L'utente non è iscritto a nessun gruppo.</p>";
                    }

                    // ---------------------------------------------------------
                    // TABELLA 3: FILE MULTIMEDIALI CARICATI DALL'UTENTE (MODIFICATA)
                    // ---------------------------------------------------------
                    echo "<h3>File multimediali caricati</h3>";

                    // Aggiunto f.URL alla SELECT per poter creare l'indirizzo di destinazione ipertestuale
                    $stmtFiles = $pdo->prepare("
                        SELECT f.titolo, f.tipo, f.dimensione, f.URL
                        FROM FileMultimediale f
                        WHERE f.caricatoDa = :codice
                        ORDER BY f.titolo ASC
                    ");
                    $stmtFiles->execute([':codice' => $idUtente]);
                    $filesRaw = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($filesRaw)) {
                        $datiFiles = [];
                        
                        // Mappatura delle icone ereditata direttamente da media.php
                        $icon_types = [
                            'immagine' => 'images/image.png',
                            'video'    => 'images/video.png',
                            'audio'    => 'images/headphones.png',
                            'default'  => 'images/document.png'
                        ];

                        foreach ($filesRaw as $file) {
                            $icon_path = $icon_types[$file['tipo']] ?? $icon_types['default'];
                            
                            $file_icon = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($file['tipo']) . "'>";
                            $file_name = htmlspecialchars($file['titolo']);
                            $file_link = htmlspecialchars($file['URL']);
                            
                            // Struttura HTML identica a media.php per ereditare l'icona e lo stile del link (colore rosa da CSS globale)
                            $title_html = "<div id='file_name'>{$file_icon}<a href='{$file_link}'>{$file_name}</a></div>";

                            $datiFiles[] = [
                                'File'       => $title_html,
                                'Dimensione' => htmlspecialchars($file['dimensione']) . " KB",
                            ];
                        }
                        // ABILITATO RENDERING HTML: Inserito 'File' nell'array delle colonne HTML consentite (secondo parametro)
                        stampaTabella($datiFiles, ['File']);
                    } else {
                        echo "<p>L'utente non ha caricato nessun file multimediale.</p>";
                    }
                }
            } else {
                // ---------------------------------------------------------
                // VISTA PRINCIPALE: Tabella globale con filtri e paginazione
                // ---------------------------------------------------------
                $where = [];
                $params = [];

                if (!empty($_GET['nickname'])) {
                    $where[] = "nickname LIKE :nickname";
                    $params[':nickname'] = '%' . $_GET['nickname'] . '%';
                }
                if (!empty($_GET['nome'])) {
                    $where[] = "nome LIKE :nome";
                    $params[':nome'] = '%' . $_GET['nome'] . '%';
                }
                if (!empty($_GET['cognome'])) {
                    $where[] = "cognome LIKE :cognome";
                    $params[':cognome'] = '%' . $_GET['cognome'] . '%';
                }

                // Filtro data modificato: tipo 'date' nativo (aaaa-mm-gg) come in bacheche.php
                if (!empty($_GET['data'])) {
                    $where[] = "DATE(dataNascita) >= :data";
                    $params[':data'] = $_GET['data'];
                }

                // Parametri di Paginazione (es. 50 record per pagina)
                list($pagina, $limit, $offset) = getParametriPaginazione(50);

                // Parametri di Ordinamento Dinamico
                list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
                    'nickname' => 'nickname',
                    'nome'     => 'nome',
                    'cognome'  => 'cognome',
                    'data'     => 'dataNascita'
                ], 'nickname', 'ASC');

                // Conteggio totale dei risultati per la paginazione (Modificato 'utente' in 'Utente')
                $sqlContatore = "SELECT COUNT(*) FROM Utente";
                if ($where) {
                    $sqlContatore .= " WHERE " . implode(" AND ", $where);
                }
                $stmtContatore = $pdo->prepare($sqlContatore);
                $stmtContatore->execute($params);
                $totaleRisultati = $stmtContatore->fetchColumn();

                // Query principale con filtri, ordinamento e limiti (Modificato 'utente' in 'Utente')
                $sql = "
                    SELECT codice as 'owner',
                           nickname as 'Nickname',
                           nome as 'Nome',
                           cognome as 'Cognome',
                           dataNascita as 'Data di Nascita'
                    FROM Utente
                ";
                if ($where) {
                    $sql .= " WHERE " . implode(" AND ", $where);
                }
                $sql .= " ORDER BY {$sql_sort} {$sort_dir} LIMIT :limit OFFSET :offset";

                $stmt = $pdo->prepare($sql);
                foreach ($params as $chiave => $valore) {
                    $stmt->bindValue($chiave, $valore);
                }
                $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<p class='info-risultati'>Trovati <strong>$totaleRisultati</strong> utenti ($limit per pagina).</p>";

                if (!empty($righe)) {
                    $datiUtenti = [];
                    $current_url = $_SERVER['REQUEST_URI'];

                    foreach ($righe as $riga) {
                        $linkDettaglio = "utenti.php?utente=" . urlencode($riga['owner']) . "&return_to=" . urlencode($current_url);
                        $htmlNickname = "<a href='{$linkDettaglio}'>" . htmlspecialchars($riga['Nickname']) . "</a>";

                        $datiUtenti[] = [
                            'Nickname'        => $htmlNickname,
                            'Nome'            => $riga['Nome'],
                            'Cognome'         => $riga['Cognome'],
                            'Data di Nascita' => formattaData($riga['Data di Nascita'])
                        ];
                    }

                    // Generazione dinamica dei link per l'ordinamento delle colonne
                    $customHeaders = generaIntestazioniOrdinabili([
                        'Nickname'        => 'nickname',
                        'Nome'            => 'nome',
                        'Cognome'         => 'cognome',
                        'Data di Nascita' => 'data'
                    ], $sort_col, $sort_dir);

                    // Stampiamo la tabella passando 'Nickname' nelle colonne HTML consentite per preservare il link <a>
                    stampaTabella($datiUtenti, ['Nickname'], $customHeaders);

                    // Barra di navigazione per la paginazione
                    stampaPaginazione($pagina, $totaleRisultati, $limit);
                } else {
                    echo "<p class='info-risultati'>Nessun utente trovato con i criteri di ricerca selezionati.</p>";
                }
            }
            ?>
        </div>
    </div>

    <?php include 'footer.html'; ?>
</body>

</html>