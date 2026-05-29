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
                            ['tipo'  => 'date', 'name' => 'data',     'label' => 'Nato dopo:'],
                        ]
                    ];
                    include 'filter.php';
                } else {
                    $tab_corrente = $_GET['tab'] ?? 'info';
                    $idUtente = (int)$_GET['utente'];

                    if ($tab_corrente === 'info') {
                        echo '<div id="filtro" class="filter-empty">';
                        echo '    <p>Non sono presenti filtri per questa sezione.</p>';
                        echo '</div>';
                    } elseif ($tab_corrente === 'gruppi') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'utente', 'value' => $idUtente],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'gruppi'],
                                ['tipo' => 'text', 'name' => 'nome_gruppo', 'label' => 'Nome Gruppo:'],
                                ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Proprietario:'],
                                ['tipo' => 'date', 'name' => 'data', 'label' => 'Data Creazione (Da):']
                            ]
                        ];
                        include 'filter.php';
                    } elseif ($tab_corrente === 'bacheche') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'utente', 'value' => $idUtente],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'bacheche'],
                                ['tipo' => 'text', 'name' => 'nome_bacheca', 'label' => 'Nome Bacheca:'],
                                ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Proprietario:'],
                                ['tipo' => 'date', 'name' => 'data', 'label' => 'Data Creazione (Da):']
                            ]
                        ];
                        include 'filter.php';
                    } elseif ($tab_corrente === 'file') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'utente', 'value' => $idUtente],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'file'],
                                ['tipo' => 'text', 'name' => 'titolo_file', 'label' => 'Nome File:']
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
                // ROUTING VISTE
                // =========================================================
                if (!empty($_GET['utente'])) {
                    $idUtente = (int)$_GET['utente'];
                    $tab_corrente = $_GET['tab'] ?? 'info';

                    // 1. Lettura dati anagrafici dell'utente selezionato
                    $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
                    $stmtUtente->execute([':codice' => $idUtente]);
                    $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

                    if ($infoUtente) {
                        $dataFormattata = !empty($infoUtente['dataNascita']) ? (function_exists('formattaData') ? formattaData($infoUtente['dataNascita']) : date('d/m/Y', strtotime($infoUtente['dataNascita']))) : "";

                        echo "<p><a href='utenti.php'>&larr; Torna all'elenco utenti</a></p>";
                        echo "<h2>Profilo di <b><i>" . htmlspecialchars($infoUtente['cognome']) . " " . htmlspecialchars($infoUtente['nome']) . "</i></b></h2>";

                        // Costruzione dinamica degli URL per le tab
                        $urlInfo     = "?utente=" . urlencode($idUtente) . "&tab=info";
                        $urlGruppi   = "?utente=" . urlencode($idUtente) . "&tab=gruppi";
                        $urlBacheche = "?utente=" . urlencode($idUtente) . "&tab=bacheche";
                        $urlFile     = "?utente=" . urlencode($idUtente) . "&tab=file";

                        echo "<div class='detail-tabs-header'>
                                <div class='bacheca-tabs tabs-reset'>
                                    <a href='{$urlInfo}' class='" . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>
                                    <a href='{$urlGruppi}' class='" . ($tab_corrente === 'gruppi' ? 'active' : '') . "'>Gruppi</a>
                                    <a href='{$urlBacheche}' class='" . ($tab_corrente === 'bacheche' ? 'active' : '') . "'>Bacheche</a>
                                    <a href='{$urlFile}' class='" . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>
                                </div>
                              </div>";

                        if ($tab_corrente === 'info') {
                            $stmtFile = $pdo->prepare("SELECT COUNT(*) FROM FileMultimediale WHERE caricatoDa = :codice");
                            $stmtFile->execute([':codice' => $idUtente]);
                            $numFile = $stmtFile->fetchColumn();

                            $stmtGruppi = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoGruppo WHERE codUtente = :codice");
                            $stmtGruppi->execute([':codice' => $idUtente]);
                            $numGruppi = $stmtGruppi->fetchColumn();

                            $stmtBacheche = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoBacheca WHERE utenteAutorizzato = :codice AND autorizzato = 1");
                            $stmtBacheche->execute([':codice' => $idUtente]);
                            $numBacheche = $stmtBacheche->fetchColumn();

                            echo "<div class='tab-info-card'>
                                    <p class='info-card-text'><strong>Nickname:</strong> " . htmlspecialchars($infoUtente['nickname']) . "</p>
                                    <p class='info-card-text'><strong>Nome:</strong> " . htmlspecialchars($infoUtente['nome']) . "</p>
                                    <p class='info-card-text'><strong>Cognome:</strong> " . htmlspecialchars($infoUtente['cognome']) . "</p>
                                    <p class='info-card-text'><strong>Data di Nascita:</strong> " . htmlspecialchars($dataFormattata) . "</p>
                                    <p class='info-card-text'><strong>Numero di gruppi a cui appartiene:</strong> " . $numGruppi . "</p>
                                    <p class='info-card-text'><strong>Numero di bacheche a cui appartiene:</strong> " . $numBacheche . "</p>
                                    <p class='info-card-text-last'><strong>Numero di file caricati:</strong> " . $numFile . "</p>
                                </div>";
                        } elseif ($tab_corrente === 'gruppi') {
                            $limit = 20;
                            list($limit, $np, $start_from) = getPaginationParams($limit);

                            $allowed_sorts = [
                                'nome' => 'g.nome',
                                'proprietario' => 'u_owner.nickname',
                                'data' => 'g.dataCreazione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'data', 'DESC');

                            $whereSql = " WHERE uag.codUtente = :codice";
                            $params = [':codice' => $idUtente];

                            if (!empty($_GET['nome_gruppo'])) {
                                $whereSql .= " AND g.nome LIKE :nome_gruppo";
                                $params[':nome_gruppo'] = '%' . $_GET['nome_gruppo'] . '%';
                            }
                            if (!empty($_GET['proprietario'])) {
                                $whereSql .= " AND u_owner.nickname LIKE :proprietario";
                                $params[':proprietario'] = '%' . $_GET['proprietario'] . '%';
                            }
                            if (!empty($_GET['data'])) {
                                if (isDataValidaRange($_GET['data'])) {
                                    $whereSql .= " AND g.dataCreazione >= :data";
                                    $params[':data'] = $_GET['data'];
                                }
                            }

                            $countSql = "SELECT COUNT(*) FROM UtenteAutorizzatoGruppo uag 
                                         JOIN Gruppo g ON uag.codGruppo = g.codice 
                                         JOIN Utente u_owner ON g.creatoDa = u_owner.codice " . $whereSql;
                            $stmtCount = $pdo->prepare($countSql);
                            $stmtCount->execute($params);
                            $numero_records = $stmtCount->fetchColumn();
                            $numero_pagine = getNumberOfPages($numero_records, $limit);

                            $sql = "SELECT g.codice AS id_gruppo, g.nome AS `Nome Gruppo`, u_owner.codice AS id_proprietario, u_owner.nickname AS `Proprietario`, g.dataCreazione AS `Data Creazione` 
                                    FROM UtenteAutorizzatoGruppo uag 
                                    JOIN Gruppo g ON uag.codGruppo = g.codice 
                                    JOIN Utente u_owner ON g.creatoDa = u_owner.codice 
                                    $whereSql 
                                    ORDER BY $sql_sort $sort_dir 
                                    LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $gruppi = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $gruppiFormattati = [];
                            foreach ($gruppi as $g) {
                                $link_gruppo = "gruppi.php?codice=" . urlencode($g['id_gruppo']);
                                $link_proprietario = "utenti.php?utente=" . urlencode($g['id_proprietario']);

                                // Controllo corona: se il proprietario del gruppo è l'utente corrente visualizzato
                                $iconaCorona = ((int)$g['id_proprietario'] === $idUtente) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                $gruppiFormattati[] = [
                                    'Nome Gruppo' => "<a href='{$link_gruppo}' class='row-link'>" . htmlspecialchars($g['Nome Gruppo']) . "</a>" . $iconaCorona,
                                    'Proprietario' => "<a href='{$link_proprietario}' class='row-link'>" . htmlspecialchars($g['Proprietario']) . "</a>",
                                    'Data Creazione' => htmlspecialchars($g['Data Creazione'] ?? '')
                                ];
                            }

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> gruppi (<strong>$limit</strong> per pagina)</p></div>";

                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nome Gruppo' => 'nome',
                                'Proprietario' => 'proprietario',
                                'Data Creazione' => 'data'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($gruppiFormattati, ['Nome Gruppo', 'Proprietario'], $customHeaders);
                            echo '</div>';
                            echo getPagesNav($np, $numero_pagine, 1);
                        } elseif ($tab_corrente === 'bacheche') {
                            $limit = 20;
                            list($limit, $np, $start_from) = getPaginationParams($limit);

                            $allowed_sorts = [
                                'nome' => 'uab.nomeBacheca',
                                'proprietario' => 'u_owner.nickname',
                                'data' => 'b.dataCreazione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'data', 'DESC');

                            $whereSql = " WHERE uab.utenteAutorizzato = :codice AND uab.autorizzato = 1";
                            $params = [':codice' => $idUtente];

                            if (!empty($_GET['nome_bacheca'])) {
                                $whereSql .= " AND uab.nomeBacheca LIKE :nome_bacheca";
                                $params[':nome_bacheca'] = '%' . $_GET['nome_bacheca'] . '%';
                            }
                            if (!empty($_GET['proprietario'])) {
                                $whereSql .= " AND u_owner.nickname LIKE :proprietario";
                                $params[':proprietario'] = '%' . $_GET['proprietario'] . '%';
                            }
                            if (!empty($_GET['data'])) {
                                if (isDataValidaRange($_GET['data'])) {
                                    $whereSql .= " AND b.dataCreazione >= :data";
                                    $params[':data'] = $_GET['data'];
                                }
                            }

                            $countSql = "SELECT COUNT(*) FROM UtenteAutorizzatoBacheca uab 
                                         JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente
                                         JOIN Utente u_owner ON b.codiceUtente = u_owner.codice " . $whereSql;
                            $stmtCount = $pdo->prepare($countSql);
                            $stmtCount->execute($params);
                            $numero_records = $stmtCount->fetchColumn();
                            $numero_pagine = getNumberOfPages($numero_records, $limit);

                            $sql = "SELECT b.codiceUtente AS id_proprietario, uab.nomeBacheca AS `Nome Bacheca`, u_owner.nickname AS `Proprietario`, b.dataCreazione AS `Data Creazione` 
                                    FROM UtenteAutorizzatoBacheca uab 
                                    JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente
                                    JOIN Utente u_owner ON b.codiceUtente = u_owner.codice 
                                    $whereSql 
                                    ORDER BY $sql_sort $sort_dir 
                                    LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $bacheche = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $bachecheFormattate = [];
                            foreach ($bacheche as $b) {
                                $link_bacheca = "bacheche.php?bacheca=" . urlencode($b['Nome Bacheca']) . "&owner=" . urlencode($b['id_proprietario']);
                                $link_proprietario = "utenti.php?utente=" . urlencode($b['id_proprietario']);

                                // Controllo corona: se il proprietario della bacheca è l'utente corrente visualizzato
                                $iconaCorona = ((int)$b['id_proprietario'] === $idUtente) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' style='width:16px; height:16px; margin-left:6px; vertical-align:middle;'>" : "";

                                $bachecheFormattate[] = [
                                    'Nome Bacheca' => "<a href='{$link_bacheca}' class='row-link'>" . htmlspecialchars($b['Nome Bacheca']) . "</a>" . $iconaCorona,
                                    'Proprietario' => "<a href='{$link_proprietario}' class='row-link'>" . htmlspecialchars($b['Proprietario']) . "</a>",
                                    'Data Creazione' => htmlspecialchars($b['Data Creazione'] ?? '')
                                ];
                            }

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>$numero_records</strong> bacheche (<strong>$limit</strong> per pagina)</p></div>";

                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nome Bacheca' => 'nome',
                                'Proprietario' => 'proprietario',
                                'Data Creazione' => 'data'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($bachecheFormattate, ['Nome Bacheca', 'Proprietario'], $customHeaders);
                            echo '</div>';
                            echo getPagesNav($np, $numero_pagine, 1);
                        } elseif ($tab_corrente === 'file') {
                            $limit = 20;
                            list($limit, $np, $start_from) = getPaginationParams($limit);

                            $allowed_sorts = [
                                'file' => 'titolo',
                                'dimensione' => 'dimensione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'file', 'ASC');

                            $whereSql = " WHERE caricatoDa = :codice";
                            $params = [':codice' => $idUtente];

                            if (!empty($_GET['titolo_file'])) {
                                $whereSql .= " AND titolo LIKE :titolo_file";
                                $params[':titolo_file'] = '%' . $_GET['titolo_file'] . '%';
                            }

                            $countSql = "SELECT COUNT(*) FROM FileMultimediale" . $whereSql;
                            $stmtCount = $pdo->prepare($countSql);
                            $stmtCount->execute($params);
                            $numero_records = $stmtCount->fetchColumn();
                            $numero_pagine = getNumberOfPages($numero_records, $limit);

                            $sql = "SELECT url, tipo, titolo AS `File`, dimensione AS `Dimensione` 
            FROM FileMultimediale 
            $whereSql 
            ORDER BY $sql_sort $sort_dir 
            LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $icon_types = [
                                'immagine' => 'images/image.png',
                                'video' => 'images/video.png',
                                'audio' => 'images/headphones.png',
                                'default' => 'images/document.png'
                            ];

                            $filesFormattati = [];
                            foreach ($files as $f) {
                                $tipoStr = strtolower($f['tipo']);
                                $icon_path = $icon_types[$tipoStr] ?? $icon_types['default'];

                                $link_file = htmlspecialchars($f['url']);

                                // Uso della classe .file-link definita nel CSS per il colore rosa
                                $titolo_html = "<a href='{$link_file}' target='_blank' class='file-link'>" .
                                    "<img src='{$icon_path}' alt='Icona' style='width:18px; height:18px; margin-right:8px; vertical-align:middle;'>" .
                                    htmlspecialchars($f['File']). "</a>";

                                // Uso della classe .text-right per allineare la dimensione a destra
                                $filesFormattati[] = [
                                    'File' => $titolo_html,
                                    'Dimensione' => "<span class='text-right' style='display:block;'>" . htmlspecialchars($f['Dimensione']) . " MB</span>"
                                ];
                            }

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> file condivisi (<strong>$limit</strong> per pagina)</p></div>";

                            $customHeaders = generaIntestazioniOrdinabili([
                                'File' => 'file',
                                'Dimensione' => 'dimensione'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            // Utilizzo della funzione standard stampaTabella di functions.php
                            stampaTabella($filesFormattati, ['File', 'Dimensione'], $customHeaders);
                            echo '</div>';

                            echo getPagesNav($np, $numero_pagine, 1);
                        } else {
                            echo "<p class='info-risultati'>Utente non trovato.</p>";
                        }
                    }
                } else {
                    // 2. Elenco generale utenti
                    $limit = 20;
                    list($limit, $np, $start_from) = getPaginationParams($limit);

                    $allowed_sorts = [
                        'nickname' => 'nickname',
                        'nome'     => 'nome',
                        'cognome'  => 'cognome',
                        'data'     => 'dataNascita'
                    ];

                    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nickname', 'ASC');

                    $whereSql = "WHERE 1=1";
                    $params = [];

                    if (!empty($_GET['nickname'])) {
                        $whereSql .= " AND nickname LIKE :nickname";
                        $params[':nickname'] = '%' . $_GET['nickname'] . '%';
                    }
                    if (!empty($_GET['nome'])) {
                        $whereSql .= " AND nome LIKE :nome";
                        $params[':nome'] = '%' . $_GET['nome'] . '%';
                    }
                    if (!empty($_GET['cognome'])) {
                        $whereSql .= " AND cognome LIKE :cognome";
                        $params[':cognome'] = '%' . $_GET['cognome'] . '%';
                    }
                    if (!empty($_GET['data'])) {
                        if (isDataValidaRange($_GET['data'])) {
                            $whereSql .= " AND dataNascita >= :data";
                            $params[':data'] = $_GET['data'];
                        }
                    }

                    $countSql = "SELECT COUNT(*) FROM Utente " . $whereSql;
                    $stmtCount = $pdo->prepare($countSql);
                    $stmtCount->execute($params);
                    $numero_records = $stmtCount->fetchColumn();
                    $numero_pagine = getNumberOfPages($numero_records, $limit);

                    $sql = "SELECT codice, nickname, nome AS Nome, cognome AS Cognome, dataNascita AS `Data di Nascita` 
                            FROM Utente 
                            $whereSql 
                            ORDER BY $sql_sort $sort_dir 
                            LIMIT $start_from, $limit";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<div class='table-top-bar'>";
                    echo "<p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> utenti (<strong>$limit</strong> per pagina)</p>";
                    echo "</div>";

                    if (count($utenti) > 0) {
                        $datiUtenti = [];
                        foreach ($utenti as $riga) {
                            $htmlNickname = "<a href='?utente=" . urlencode($riga['codice']) . "' class='row-link' title='Visualizza profilo'>" . htmlspecialchars($riga['nickname']) . "</a>";
                            $datiUtenti[] = [
                                'Nickname'        => $htmlNickname,
                                'Nome'            => htmlspecialchars($riga['Nome']),
                                'Cognome'         => htmlspecialchars($riga['Cognome']),
                                'Data di Nascita' => htmlspecialchars($riga['Data di Nascita'])
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
                </div>
            <?php endif; ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>