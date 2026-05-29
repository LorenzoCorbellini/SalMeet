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
        <title>SalMeet - Gruppi</title>
        <?php include 'head.html'; ?>

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
                    $filtro_config = [
                        'campi' => [
                            ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Gruppo:'],
                            ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Nickname Proprietario:'],
                            ['tipo' => 'date', 'name' => 'data',         'label' => 'Creata dopo:'],
                        ]
                    ];
                    include 'filter.php';
                } else {
                    $tab_corrente = $_GET['tab'] ?? 'info';
                    $idGruppo = (int)$_GET['gruppo'];

                    if ($tab_corrente === 'info') {
                        echo '<div id="filtro" class="filter-empty">';
                        echo '    <p>Non sono presenti filtri per questa sezione.</p>';
                        echo '</div>';
                    } elseif ($tab_corrente === 'membri') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'gruppo', 'value' => $idGruppo],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'membri'],
                                ['tipo' => 'text', 'name' => 'nickname', 'label' => 'Nickname:'],
                                ['tipo' => 'text', 'name' => 'nome', 'label' => 'Nome:'],
                                ['tipo' => 'text', 'name' => 'cognome', 'label' => 'Cognome:'],
                                ['tipo' => 'date', 'name' => 'data_nascita', 'label' => 'Data di Nascita (Da):']
                            ]
                        ];
                        include 'filter.php';
                    } elseif ($tab_corrente === 'file') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'gruppo', 'value' => $idGruppo],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'file'],
                                ['tipo' => 'text', 'name' => 'titolo_file', 'label' => 'Nome File:'],
                                ['tipo' => 'text', 'name' => 'nickname', 'label' => 'Nickname (Caricato da):']
                            ]
                        ];
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
                        echo "<p><a href='gruppi.php'>&larr; Torna all'elenco gruppi</a></p>";
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
                                    <p class='info-card-text'><strong>Numero di membri del gruppo:</strong> " . $numMembri . "</p>
                                    <p class='info-card-text-last'><strong>Numero di file totali caricati nel gruppo:</strong> " . $numFile . "</p>
                                </div>";

                        } elseif ($tab_corrente === 'membri') {
                            $limit = 20;
                            list($limit, $np, $start_from) = getPaginationParams($limit);

                            $allowed_sorts = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'u.dataNascita'];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nickname', 'ASC');

                            $whereSql = "";
                            $params = [':id' => $idGruppo];

                            if (!empty($_GET['nickname'])) {
                                $whereSql .= " AND u.nickname LIKE :nickname";
                                $params[':nickname'] = '%' . $_GET['nickname'] . '%';
                            }
                            if (!empty($_GET['nome'])) {
                                $whereSql .= " AND u.nome LIKE :nome";
                                $params[':nome'] = '%' . $_GET['nome'] . '%';
                            }
                            if (!empty($_GET['cognome'])) {
                                $whereSql .= " AND u.cognome LIKE :cognome";
                                $params[':cognome'] = '%' . $_GET['cognome'] . '%';
                            }
                            if (!empty($_GET['data_nascita']) && isDataValidaRange($_GET['data_nascita'])) {
                                $whereSql .= " AND u.dataNascita >= :data_nascita";
                                $params[':data_nascita'] = $_GET['data_nascita'];
                            }

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

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>{$totale}</strong> membri (<strong>{$limit}</strong> per pagina)</p></div>";

                            if (!empty($membriRaw)) {
                                $datiMembri = [];
                                foreach ($membriRaw as $membro) {
                                    $linkMembro = "utenti.php?utente=" . urlencode($membro['codice']) . "&return_to=" . urlencode($current_url);
                                    
                                    // Aggiunta icona proprietario
                                    $iconaCorona = ((int)$membro['codice'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                    $htmlMembroNickname = "<a href='{$linkMembro}'>" . htmlspecialchars($membro['nickname']) . "</a>" . $iconaCorona;
                                    $dataFormattata = !empty($membro['dataNascita']) ? formattaData($membro['dataNascita']) : "";

                                    $datiMembri[] = [
                                        'Nickname' => $htmlMembroNickname,
                                        'Nome' => htmlspecialchars($membro['nome']),
                                        'Cognome' => htmlspecialchars($membro['cognome']),
                                        'Data di Nascita' => htmlspecialchars($dataFormattata)
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
                                echo getPagesNav($np, $numero_pagine, 1);
                            } else {
                                echo "<p class='info-risultati'>Nessun membro trovato nel gruppo con i filtri selezionati.</p>";
                            }

                        } elseif ($tab_corrente === 'file') {
                            $limit = 20;
                            list($limit, $np, $start_from) = getPaginationParams($limit);

                            $allowed_sorts = [
                                'file' => 'f.titolo', 
                                'nickname' => 'uProp.nickname', 
                                'cognome' => 'uProp.cognome', 
                                'nome' => 'uProp.nome', 
                                'dimensione' => 'f.dimensione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'file', 'ASC');

                            $whereSql = "";
                            $params = [':codice' => $idGruppo];

                            if (!empty($_GET['titolo_file'])) {
                                $whereSql .= " AND f.titolo LIKE :titolo_file";
                                $params[':titolo_file'] = '%' . $_GET['titolo_file'] . '%';
                            }
                            if (!empty($_GET['nickname'])) {
                                $whereSql .= " AND uProp.nickname LIKE :nickname";
                                $params[':nickname'] = '%' . $_GET['nickname'] . '%';
                            }

                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM FileAssociatoGruppo fag JOIN FileMultimediale f ON fag.file = f.numero JOIN Utente uProp ON uProp.codice=f.caricatoDa WHERE fag.codGruppo = :codice" . $whereSql);
                            $stmtCount->execute($params);
                            $totale = $stmtCount->fetchColumn();

                            $numero_pagine = getNumberOfPages($totale, $limit);

                            $stmtFile = $pdo->prepare("
                                SELECT f.numero, f.titolo, f.tipo, uProp.codice as caricatoDa, uProp.nickname, uProp.cognome, uProp.nome, f.dimensione, f.URL 
                                FROM FileAssociatoGruppo fag 
                                JOIN FileMultimediale f ON fag.file = f.numero 
                                JOIN Utente uProp ON uProp.codice=f.caricatoDa 
                                WHERE fag.codGruppo = :codice {$whereSql}
                                ORDER BY {$sql_sort} {$sort_dir}
                                LIMIT {$start_from}, {$limit}
                            ");
                            $stmtFile->execute($params);
                            $filesRaw = $stmtFile->fetchAll(PDO::FETCH_ASSOC);

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>{$totale}</strong> file condivisi (<strong>{$limit}</strong> per pagina)</p></div>";

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
                                    
                                    // Aggiunta icona proprietario
                                    $iconaCorona = ((int)$file['caricatoDa'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                    $htmlOwner = "<a href='" . htmlspecialchars($owner_link) . "'>" . htmlspecialchars($file['nickname']) . "</a>" . $iconaCorona;

                                    $datiFiles[] = [
                                        'File' => $titolo_html,
                                        'Nickname' => $htmlOwner,
                                        'Cognome' => htmlspecialchars($file['cognome']),
                                        'Nome' => htmlspecialchars($file['nome']),
                                        'Dimensione' => htmlspecialchars($file['dimensione']) . " MB"
                                    ];
                                }

                                $customHeaders = generaIntestazioniOrdinabili([
                                    'File'       => 'file',
                                    'Nickname'   => 'nickname',
                                    'Cognome'    => 'cognome',
                                    'Nome'       => 'nome',
                                    'Dimensione' => 'dimensione'
                                ], $sort_col, $sort_dir);

                                echo '<div class="table-container">';
                                stampaTabella($datiFiles, ['File', 'Nickname'], $customHeaders);
                                echo '</div>';
                                echo getPagesNav($np, $numero_pagine, 1);
                            } else {
                                echo "<p class='info-risultati'>Nessun file condiviso trovato nel gruppo con i filtri selezionati.</p>";
                            }
                        }

                    } else {
                        echo "<p class='info-risultati'>Gruppo non trovato.</p>";
                    }

                } else {
                    // =========================================================
                    // ELENCO GLOBALE GRUPPI
                    // =========================================================
                    $limit = 20;
                    list($limit, $np, $start_from) = getPaginationParams($limit);

                    // Definizione dei campi ordinabili: label in the table => column in the db
                    $allowed_sorts = [
                        'nome' => 'Gruppo.nome',
                        'data' => 'Gruppo.dataCreazione',
                        'Proprietario' => 'Utente.nickname',
                    ];
                    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nome', 'ASC');

                    $where = [];
                    $params = [];

                    if (!empty($_GET['nome'])) {
                        $where[] = "Gruppo.nome LIKE :nome";
                        $params[':nome'] = '%' . $_GET['nome'] . '%';
                    }
                    if (!empty($_GET['proprietario'])) {
                        $where[] = "Utente.nickname LIKE :proprietario";
                        $params[':proprietario'] = '%' . $_GET['proprietario'] . '%';
                    }
                    if (!empty($_GET['data'])) {
                        if (isDataValidaRange($_GET['data'])) {
                            $where[] = "Gruppo.dataCreazione >= :data";
                            $params[':data'] = $_GET['data'];
                        }
                    }

                    $tabella_count = "Gruppo JOIN Utente ON Gruppo.creatoDa = Utente.codice";
                    $totaleRisultati = getNumberOfRecords($pdo, $tabella_count, $where, $params);
                    $numero_pagine = getNumberOfPages($totaleRisultati, $limit);

                    $sql = "
                        SELECT Gruppo.codice as 'gruppoId', Gruppo.nome as 'Nome Gruppo', Gruppo.dataCreazione as 'Data Creazione', Utente.nickname as 'Proprietario', Utente.codice as 'ownerId'
                        FROM Gruppo
                        JOIN Utente ON Gruppo.creatoDa = Utente.codice
                    ";

                    if (!empty($where)) {
                        $sql .= " WHERE " . implode(" AND ", $where);
                    }

                    // Ordinamento e paginazione
                    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";
                    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $datiOriginale = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<div class='table-top-bar'>";
                    echo "<p class='info-risultati zero-margin'>Trovati <strong>{$totaleRisultati}</strong> gruppi (<strong>{$limit}</strong> per pagina)</p>";
                    echo "</div>";

                    if ($datiOriginale) {
                        $datiGruppi = [];
                        // Costruiamo un nuovo array iniettando i link
                        foreach ($datiOriginale as $riga) {
                            $linkGruppo = "gruppi.php?gruppo=" . urlencode($riga['gruppoId']) . "&return_to=" . urlencode($current_url);
                            $htmlNomeGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";

                            $linkOwner = "utenti.php?utente=" . urlencode($riga['ownerId']) . "&return_to=" . urlencode($current_url);
                            $htmlProprietario = "<a href='{$linkOwner}'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

                            $datiGruppi[] = [
                                'Nome Gruppo'    => $htmlNomeGruppo,
                                'Proprietario'   => $htmlProprietario,
                                'Data Creazione' => formattaData($riga['Data Creazione'])
                            ];
                        }

                        // Generazione dinamica dei link per l'ordinamento delle colonne
                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nome Gruppo'    => 'nome',
                            'Proprietario'   => 'Proprietario',
                            'Data Creazione' => 'data'
                        ], $sort_col, $sort_dir);

                        echo '<div class="table-container">';
                        stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders);
                        echo '</div>';

                        // Stampa i link della paginazione
                        echo getPagesNav($np, $numero_pagine, 1);
                    } else {
                        echo "<p class='info-risultati'>Nessun gruppo trovato.</p>";
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