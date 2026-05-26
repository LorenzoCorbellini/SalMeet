<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// Verifica se la richiesta arriva tramite AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if (!$isAjax):
?>
    <!DOCTYPE html>
    <html lang="it">

    <head>
        <title>SalMeet - Utenti</title>
        <?php include 'head.html'; ?>

        <!-- Richiamo lo script AJAX esterno -->
        <script src="js/AJAXHandler.js" defer></script>
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
                            ['tipo'  => 'text', 'name' => 'nickname', 'label' => 'Nickname:'],
                            ['tipo'  => 'text', 'name' => 'nome',     'label' => 'Nome:'],
                            ['tipo'  => 'text', 'name' => 'cognome',  'label' => 'Cognome:'],
                            ['tipo'  => 'date', 'name' => 'data',     'label' => 'Data di Nascita (Da):'],
                        ]
                    ];
                    include 'filter.php';
                }
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php if (!$isAjax): ?>
                <!-- Contenitore bersaglio per le risposte AJAX -->
                <div id="ajax-results">
                <?php endif; ?>

                <?php
                // =========================================================
                // ROUTING VISTE
                // =========================================================
                if (!empty($_GET['utente'])) {
                    $idUtente = (int)$_GET['utente'];

                    // 1. Lettura dati anagrafici dell'utente selezionato
                    $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
                    $stmtUtente->execute([':codice' => $idUtente]);
                    $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

                    if ($infoUtente) {
                        $dataFormattata = !empty($infoUtente['dataNascita']) ? (function_exists('formattaData') ? formattaData($infoUtente['dataNascita']) : date('d/m/Y', strtotime($infoUtente['dataNascita']))) : "";

                        echo "<p><a href='utenti.php'>&larr; Torna all'elenco utenti</a></p>";
                        echo "<h2 class='h2utente'>Profilo di <b><i>" . htmlspecialchars($infoUtente['nickname']) . "</i></b></h2>";
                        echo "<p><strong>Nome:</strong> " . htmlspecialchars($infoUtente['nome']) . "</p>";
                        echo "<p><strong>Cognome:</strong> " . htmlspecialchars($infoUtente['cognome']) . "</p>";
                        echo "<p><strong>Data di Nascita:</strong> " . htmlspecialchars($dataFormattata) . "</p>";

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
                                $linkBacheca = "bacheche.php?vista=dettaglio&bacheca=" . urlencode($bacheca['nomeBacheca']) . "&owner=" . urlencode($bacheca['bachecaOwnerId']);
                                $htmlBacheca = "<a href='{$linkBacheca}'>" . htmlspecialchars($bacheca['nomeBacheca']) . "</a>";

                                $linkOwnerBacheca = "utenti.php?utente=" . urlencode($bacheca['bachecaOwnerId']);
                                $htmlProprietario = "<a href='{$linkOwnerBacheca}'>" . htmlspecialchars($bacheca['proprietarioNickname']) . "</a>";

                                $datiBacheche[] = [
                                    'Nome Bacheca' => $htmlBacheca,
                                    'Proprietario' => $htmlProprietario
                                ];
                            }

                            echo '<div class="table-container">';
                            stampaTabella($datiBacheche, ['Nome Bacheca', 'Proprietario']);
                            echo '</div>';
                        } else {
                            echo "<p>L'utente non partecipa a nessuna bacheca.</p>";
                        }

                        // ---------------------------------------------------------
                        // TABELLA 2: GRUPPI DI APPARTENENZA CON LINK INCROCIATI
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
                                $linkGruppo = "gruppi.php?gruppo=" . urlencode($gruppo['gruppo_id']);
                                $htmlGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($gruppo['nome_gruppo']) . "</a>";

                                $linkOwnerGruppo = "utenti.php?utente=" . urlencode($gruppo['gruppo_owner_id']);
                                $htmlProprietarioGruppo = "<a href='{$linkOwnerGruppo}'>" . htmlspecialchars($gruppo['proprietarioNickname']) . "</a>";

                                $datiGruppi[] = [
                                    'Nome Gruppo'  => $htmlGruppo,
                                    'Proprietario' => $htmlProprietarioGruppo
                                ];
                            }
                            echo '<div class="table-container">';
                            stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario']);
                            echo '</div>';
                        } else {
                            echo "<p>L'utente non è iscritto a nessun gruppo.</p>";
                        }

                        // ---------------------------------------------------------
                        // TABELLA 3: FILE MULTIMEDIALI CARICATI DALL'UTENTE
                        // ---------------------------------------------------------
                        echo "<h3>File multimediali caricati</h3>";

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

                                $title_html = "<div id='file_name'>{$file_icon}<a href='{$file_link}'>{$file_name}</a></div>";
                                $size_html = formatFileSizeHtml((int)$file['dimensione']);

                                $datiFiles[] = [
                                    'File'       => $title_html,
                                    'Dimensione' => $size_html
                                ];
                            }
                            echo '<div class="table-container">';
                            stampaTabella($datiFiles, ['File', 'Dimensione']);
                            echo '</div>';
                        } else {
                            echo "<p>L'utente non ha caricato nessun file multimediale.</p>";
                        }
                    }
                } else {
                    // ---------------------------------------------------------
                    // VISTA PRINCIPALE: Tabella globale con filtri e paginazione
                    // ---------------------------------------------------------
                    $recordsPerPage = 20;
                    list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

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
                    if (!empty($_GET['data'])) {
                        if (isDataValidaRange($_GET['data'])) {
                            $where[] = "DATE(dataNascita) >= :data";
                            $params[':data'] = $_GET['data'];
                        }
                    }

                    // Parametri di Ordinamento Dinamico
                    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
                        'nickname' => 'nickname',
                        'nome'     => 'nome',
                        'cognome'  => 'cognome',
                        'data'     => 'dataNascita'
                    ], 'nickname', 'ASC');

                    // Conteggio e recupero dei risultati
                    $totaleRisultati = getNumberOfRecords($pdo, 'Utente', $where, $params);
                    $numero_pagine = getNumberOfPages($totaleRisultati, $limit);

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
                    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";
                    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;

                    $stmt = $pdo->prepare($sql);
                    foreach ($params as $chiave => $valore) {
                        $stmt->bindValue($chiave, $valore);
                    }
                    $stmt->execute();
                    $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<p class='info-risultati'>Trovati <strong>$totaleRisultati</strong> utenti (<strong>$recordsPerPage</strong> per pagina)</p>";

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
                                'Data di Nascita' => $riga['Data di Nascita']
                            ];
                        }

                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nickname'        => 'nickname',
                            'Nome'            => 'nome',
                            'Cognome'         => 'cognome',
                            'Data di Nascita' => 'data'
                        ], $sort_col, $sort_dir);

                        echo '<div class="table-container">';
                        stampaTabella($datiUtenti, ['Nickname'], $customHeaders);
                        echo '</div>';

                        echo getPagesNav($np, $numero_pagine, 1);
                    } else {
                        echo "<p class='info-risultati'>Nessun utente trovato con i criteri di ricerca selezionati.</p>";
                    }
                }
                ?>

                <?php if (!$isAjax): ?>
                </div> <!-- Fine ajax-results -->
            <?php endif; ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>