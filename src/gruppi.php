<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// Verifica se la richiesta arriva tramite AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if (!$isAjax):
?>
    <!DOCTYPE html>
    <html lang="it">

    <head>
        <title>SalMeet - Gruppi</title>
        <?php include 'head.html'; ?>
        <script src="js/FilterHandler.js" defer></script>
        <script src="js/AJAXHandler.js" defer></script>
    </head>

    <body>
        <header>
            <h1 id="hcod1">Gruppi</h1>
        </header>

        <div class="main-container">
            <aside class="sidebar">
                <?php include 'nav.html'; ?>

                <?php
                // =========================================================
                // CONFIGURAZIONE DINAMICA DEI FILTRI NELLA SIDEBAR
                // =========================================================
                if (empty($_GET['gruppo'])) {
                    // Usa il filtro centralizzato nativo per i gruppi
                    $filtro_config = getFiltroConfig('gruppi');
                    include 'filter.php';
                } else {
                    $tab_corrente = $_GET['tab'] ?? 'info';
                    $idGruppo = (int)$_GET['gruppo'];

                    if ($tab_corrente === 'info') {
                        $filtro_config = getFiltroConfig('vuoto');
                        echo '<div id="filtro" class="filter-empty"><p>' . htmlspecialchars($filtro_config['messaggio'] ?? 'Non sono presenti filtri per questa sezione.') . '</p></div>';
                    } elseif ($tab_corrente === 'membri') {
                        // Usa il filtro centralizzato 'utenti' passandogli i parametri della vista corrente
                        $filtro_config = getFiltroConfig('utenti', ['gruppo' => $idGruppo, 'tab' => 'membri']);
                        include 'filter.php';
                    } elseif ($tab_corrente === 'file') {
                        // Estraiamo dinamicamente il range di dimensioni dei file specifici di questo gruppo
                        $stmtRange = $pdo->prepare("
                            SELECT MIN(f.dimensione) as min_dim, MAX(f.dimensione) as max_dim 
                            FROM FileAssociatoGruppo fag 
                            JOIN FileMultimediale f ON fag.file = f.numero 
                            WHERE fag.codGruppo = :codice
                        ");
                        $stmtRange->execute([':codice' => $idGruppo]);
                        $range = $stmtRange->fetch(PDO::FETCH_ASSOC);

                        $minSize = isset($range['min_dim']) ? floor($range['min_dim']) : 0;
                        $maxSize = isset($range['max_dim']) ? ceil($range['max_dim']) : 100;

                        // Usa il filtro centralizzato 'file', passandogli min e max calcolati
                        $filtro_config = getFiltroConfig('file', [
                            'gruppo' => $idGruppo,
                            'tab' => 'file',
                            'min_size' => $minSize,
                            'max_size' => $maxSize ?: 100
                        ]);
                        include 'filter.php';
                    }
                }
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php if (!$isAjax): ?>
                <div id="ajax-results">
                <?php endif; ?>

                <?php
                // =========================================================
                // ROUTING VISTE: DETTAGLIO GRUPPO E GLOBALE
                // =========================================================

                // Cattura l'URL attuale per navigare indietro correttamente da Utenti
                $current_url = $_SERVER['REQUEST_URI'];

                if (!empty($_GET['gruppo'])) {
                    $idGruppo = (int)$_GET['gruppo'];
                    $tab_corrente = $_GET['tab'] ?? 'info';

                    $stmtGruppo = $pdo->prepare("
                        SELECT g.nome, g.dataCreazione, u.nickname, u.codice as ownerId
                        FROM Gruppo g
                        JOIN Utente u ON g.creatoDa = u.codice
                        WHERE g.codice = :id
                    ");
                    $stmtGruppo->execute([':id' => $idGruppo]);
                    $infoGruppo = $stmtGruppo->fetch(PDO::FETCH_ASSOC);

                    if ($infoGruppo) {
                        echo "<a href='gruppi.php' onclick='history.back(); return false;' class='btn-indietro'>Torna alla pagina precedente</a>";
                        echo "<h2>" . htmlspecialchars($infoGruppo['nome']) . "</h2>";

                        $urlInfo   = "?gruppo=" . urlencode($idGruppo) . "&tab=info";
                        $urlMembri = "?gruppo=" . urlencode($idGruppo) . "&tab=membri";
                        $urlFile   = "?gruppo=" . urlencode($idGruppo) . "&tab=file";

                        echo "<div class='detail-tabs-header'>
                                <div class='bacheca-tabs tabs-reset'>
                                    <a href='{$urlInfo}' class='" . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>
                                    <a href='{$urlMembri}' class='" . ($tab_corrente === 'membri' ? 'active' : '') . "'>Membri del Gruppo</a>
                                    <a href='{$urlFile}' class='" . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>
                                </div>
                              </div>";

                        $ownerId = (int)$infoGruppo['ownerId'];

                        if ($tab_corrente === 'info') {
                            $stmtFile = $pdo->prepare("SELECT COUNT(*) FROM FileAssociatoGruppo WHERE codGruppo = :id");
                            $stmtFile->execute([':id' => $idGruppo]);
                            $numFile = $stmtFile->fetchColumn();

                            $stmtMembri = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoGruppo WHERE codGruppo = :id");
                            $stmtMembri->execute([':id' => $idGruppo]);
                            $numMembri = $stmtMembri->fetchColumn();

                            $linkOwner = "utenti.php?utente=" . urlencode($ownerId) . "&return_to=" . urlencode($current_url);

                            echo "<div class='tab-info-card'>
                                    <p class='info-card-text'><strong>Proprietario:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($infoGruppo['nickname']) . "</a></p>
                                    <p class='info-card-text'><strong>Data Creazione:</strong> " . formattaData($infoGruppo['dataCreazione']) . "</p>
                                    
                                    <p class='info-card-text'><strong>Numero di membri del gruppo:</strong> 
                                        <a href='?gruppo={$idGruppo}&tab=membri'>{$numMembri}</a>
                                    </p>
                                    <p class='info-card-text-last'><strong>Numero di file totali caricati nel gruppo:</strong> 
                                        <a href='?gruppo={$idGruppo}&tab=file'>{$numFile}</a>
                                    </p>
                                </div>";
                        } elseif ($tab_corrente === 'membri') {
                            $recordsPerPage = 20;
                            list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                            $allowed_sorts = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'u.dataNascita'];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nickname', 'ASC');

                            // Utilizzo del filtro dinamico centralizzato
                            $filtri = applicaFiltriDinamici($_GET, 'utenti');
                            $params = array_merge([':id' => $idGruppo], $filtri['parametri']);
                            $whereSql = $filtri['sql'];

                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoGruppo uag JOIN Utente u ON uag.codUtente = u.codice WHERE uag.codGruppo = :id" . $whereSql);
                            $stmtCount->execute($params);
                            $totale = $stmtCount->fetchColumn();

                            $numero_pagine = getNumberOfPages($totale, $limit);

                            $stmtMembri = $pdo->prepare("
                                SELECT u.codice, u.nickname, u.nome, u.cognome, u.dataNascita
                                FROM UtenteAutorizzatoGruppo uag
                                JOIN Utente u ON uag.codUtente = u.codice
                                WHERE uag.codGruppo = :id {$whereSql}
                                ORDER BY {$sql_sort} {$sort_dir}
                                LIMIT {$start_from}, {$limit}
                            ");
                            $stmtMembri->execute($params);
                            $membriRaw = $stmtMembri->fetchAll(PDO::FETCH_ASSOC);

                            $testoPerPagina = ($totale > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                            echo "<div class='table-top-bar'><p class='zero-margin'>Utenti trovati nel gruppo: <strong>{$totale}</strong> {$testoPerPagina}</p></div>";

                            if (!empty($membriRaw)) {
                                $datiMembri = [];
                                foreach ($membriRaw as $membro) {
                                    $linkMembro = "utenti.php?utente=" . urlencode($membro['codice']) . "&return_to=" . urlencode($current_url);

                                    $iconaCorona = ((int)$membro['codice'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                    $htmlMembroNickname = "<a href='{$linkMembro}'>" . $iconaCorona . htmlspecialchars($membro['nickname']) . "</a>";

                                    $datiMembri[] = [
                                        'Nickname' => $htmlMembroNickname,
                                        'Nome' => htmlspecialchars($membro['nome']),
                                        'Cognome' => htmlspecialchars($membro['cognome']),
                                        // Passando la data cruda, stampaTabella le assegnerà la class='data' e la formatterà
                                        'Data di Nascita' => $membro['dataNascita']
                                    ];
                                }

                                $customHeaders = generaIntestazioniOrdinabili([
                                    'Nickname' => 'nickname',
                                    'Nome' => 'nome',
                                    'Cognome' => 'cognome',
                                    'Data di Nascita' => 'data'
                                ], $sort_col, $sort_dir);

                                echo '<div class="table-container">';
                                stampaTabella($datiMembri, ['Nickname'], $customHeaders);
                                echo '</div>';
                                echo "<div class='pagination-spacer'>";
                                echo getPagesNav($np, $numero_pagine, 1);
                                echo "</div>";
                            } else {
                                echo '<div class="table-container table-container-empty">';
                                echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                                echo '</div>';
                                echo "<div class='pagination-spacer'></div>";
                            }
                        } elseif ($tab_corrente === 'file') {
                            $recordsPerPage = 20;
                            list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                            $allowed_sorts = [
                                'file' => 'fm.titolo',
                                'nickname' => 'u.nickname',
                                'dimensione' => 'fm.dimensione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'file', 'ASC');

                            // Utilizzo del filtro dinamico centralizzato
                            $filtri = applicaFiltriDinamici($_GET, 'file');
                            // Riassegnazione fm.nome -> fm.titolo per compatibilità DB / Interfaccia filtro
                            $filtri['sql'] = str_replace('fm.nome', 'fm.titolo', $filtri['sql']);

                            $params = array_merge([':codice' => $idGruppo], $filtri['parametri']);
                            $whereSql = $filtri['sql'];

                            $filetypes = ['immagine' => 'Immagini', 'audio' => 'Audio', 'video' => 'Video'];
                            if (!empty($_GET['filetype']) && is_array($_GET['filetype'])) {
                                $selectedTypes = array_filter((array)$_GET['filetype'], fn($t) => isset($filetypes[$t]));
                                if ($selectedTypes) {
                                    $placeholders = [];
                                    foreach (array_values($selectedTypes) as $i => $type) {
                                        $placeholders[] = ":ft_$i";
                                        $params[":ft_$i"] = $type;
                                    }
                                    $whereSql .= ' AND fm.tipo IN (' . implode(', ', $placeholders) . ')';
                                }
                            }

                            // Gli alias devono allinearsi a fm e u come in filterAPI
                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM FileAssociatoGruppo fag JOIN FileMultimediale fm ON fag.file = fm.numero JOIN Utente u ON u.codice = fm.caricatoDa WHERE fag.codGruppo = :codice" . $whereSql);
                            $stmtCount->execute($params);
                            $totale = $stmtCount->fetchColumn();

                            $numero_pagine = getNumberOfPages($totale, $limit);

                            $stmtFile = $pdo->prepare("
                                SELECT fm.numero, fm.titolo, fm.tipo, u.codice as caricatoDa, u.nickname, fm.dimensione, fm.URL 
                                FROM FileAssociatoGruppo fag 
                                JOIN FileMultimediale fm ON fag.file = fm.numero 
                                JOIN Utente u ON u.codice = fm.caricatoDa 
                                WHERE fag.codGruppo = :codice {$whereSql}
                                ORDER BY {$sql_sort} {$sort_dir}
                                LIMIT {$start_from}, {$limit}
                            ");
                            $stmtFile->execute($params);
                            $filesRaw = $stmtFile->fetchAll(PDO::FETCH_ASSOC);

                            $testoPerPagina = ($totale > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                            echo "<div class='table-top-bar'><p class='zero-margin'>File trovati nel gruppo: <strong>{$totale}</strong>{$testoPerPagina}</p></div>";

                            if (!empty($filesRaw)) {
                                $datiFiles = [];
                                $icon_types = [
                                    'immagine' => 'images/image.png',
                                    'video'    => 'images/video.png',
                                    'audio'    => 'images/headphones.png',
                                    'default'  => 'images/document.png'
                                ];

                                foreach ($filesRaw as $file) {
                                    $tipoStr = strtolower($file['tipo']);
                                    $icon_path = $icon_types[$tipoStr] ?? $icon_types['default'];

                                    $file_link = htmlspecialchars($file['URL']);

                                    $titolo_html = "<a href='{$file_link}' target='_blank' class='file-link'>" .
                                        "<img src='" . htmlspecialchars($icon_path) . "' alt='Icona' style='width:18px; height:18px; margin-right:8px; vertical-align:middle;'>" .
                                        htmlspecialchars($file['titolo']) . "</a>";

                                    $owner_link = "utenti.php?utente=" . urlencode($file['caricatoDa']) . "&return_to=" . urlencode($current_url);

                                    $iconaCorona = ((int)$file['caricatoDa'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                    $htmlOwner = "<a href='" . htmlspecialchars($owner_link) . "'>" . htmlspecialchars($file['nickname']) . "</a>" . $iconaCorona;

                                    $datiFiles[] = [
                                        'File' => $titolo_html,
                                        'Nickname' => $htmlOwner,
                                        'Dimensione' => formatFileSizeHtml((float)$file['dimensione'])
                                    ];
                                }

                                $customHeaders = generaIntestazioniOrdinabili([
                                   'File'       => 'file',
                                    'Proprietario'   => 'nickname',
                                    'Dimensione' => 'dimensione'
                                ], $sort_col, $sort_dir);

                                echo '<div class="table-container">';
                                stampaTabella($datiFiles, ['File', 'Nickname', 'Dimensione'], $customHeaders);
                                echo '</div>';
                                echo "<div class='pagination-spacer'>";
                                echo getPagesNav($np, $numero_pagine, 1);
                                echo "</div>";
                            } else {
                                echo '<div class="table-container table-container-empty">';
                                echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                                echo '</div>';
                                echo "<div class='pagination-spacer'></div>";
                            }
                        }
                    } else {
                        echo "<p class='info-risultati'>Gruppo non trovato.</p>";
                    }
                } else {
                    // =========================================================
                    // ELENCO GLOBALE GRUPPI
                    // =========================================================
                    $recordsPerPage = 20;
                    list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                    $allowed_sorts = [
                        'nome' => 'g.nome',
                        'data' => 'g.dataCreazione',
                        'Proprietario' => 'u.nickname',
                    ];
                    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nome', 'ASC');

                    // Utilizzo del filtro dinamico centralizzato per l'elenco dei Gruppi
                    $filtri = applicaFiltriDinamici($_GET, 'gruppi');
                    $where = [];
                    $params = $filtri['parametri'];

                    if (!empty($filtri['sql'])) {
                        $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
                    }

                    // Alias aggiunti per le tabelle conformi con filterAPI (g, u)
                    $tabella_count = "Gruppo g JOIN Utente u ON g.creatoDa = u.codice";
                    $totaleRisultati = getNumberOfRecords($pdo, $tabella_count, $where, $params);
                    $numero_pagine = getNumberOfPages($totaleRisultati, $limit);

                    $sql = "
                        SELECT g.codice as 'gruppoId', g.nome as 'Nome Gruppo', g.dataCreazione as 'Data Creazione', u.nickname as 'Proprietario', u.codice as 'ownerId'
                        FROM Gruppo g
                        JOIN Utente u ON g.creatoDa = u.codice
                    ";

                    if (!empty($where)) {
                        $sql .= " WHERE " . implode(" AND ", $where);
                    }

                    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";
                    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $datiOriginale = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $testoPerPagina = ($totaleRisultati > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                    echo "<div class='table-top-bar'>";
                    echo "<p class='info-risultati zero-margin'>Trovati <strong>{$totaleRisultati}</strong> gruppi{$testoPerPagina}</p>";
                    echo "</div>";

                    if ($datiOriginale) {
                        $datiGruppi = [];
                        foreach ($datiOriginale as $riga) {
                            $linkGruppo = "gruppi.php?gruppo=" . urlencode($riga['gruppoId']) . "&return_to=" . urlencode($current_url);
                            $htmlNomeGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";

                            $linkOwner = "utenti.php?utente=" . urlencode($riga['ownerId']) . "&return_to=" . urlencode($current_url);
                            $htmlProprietario = "<a href='{$linkOwner}'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

                            $datiGruppi[] = [
                                'Nome Gruppo'    => $htmlNomeGruppo,
                                'Proprietario'   => $htmlProprietario,
                                'Data Creazione' => $riga['Data Creazione']
                            ];
                        }

                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nome Gruppo'    => 'nome',
                            'Proprietario'   => 'Proprietario',
                            'Data Creazione' => 'data'
                        ], $sort_col, $sort_dir);

                        echo '<div class="table-container">';
                        stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders);
                        echo '</div>';

                        echo "<div class='pagination-spacer'>";
                        echo getPagesNav($np, $numero_pagine, 1);
                        echo "</div>";
                    } else {
                        echo '<div class="table-container table-container-empty">';
                        echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                        echo '</div>';
                        echo "<div class='pagination-spacer'></div>";
                    }
                }
                ?>

                <?php if (!$isAjax): ?>
                </div>
            <?php endif; ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>