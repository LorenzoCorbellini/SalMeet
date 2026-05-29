<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// Verifica se la richiesta arriva tramite AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA LOGICA DEI FILTRI NELLA SIDEBAR
// =========================================================
function renderFiltroSidebarUtenti($pdo, $idUtente, $tab_corrente) {
    if (empty($idUtente) || !is_numeric($idUtente)) {
        // 1. Filtri per la lista globale degli utenti
        $filtro_config = getFiltroConfig('utenti');
        include 'filter.php';
    } else {
        // 2. Filtri per i tab di un utente specifico
        if ($tab_corrente === 'info') {
            $filtro_config = getFiltroConfig('vuoto');
            if (isset($filtro_config['vuoto']) && $filtro_config['vuoto'] === true) {
                echo '<div id="filtro" class="filter-empty">';
                echo '    <p>' . htmlspecialchars($filtro_config['messaggio']) . '</p>';
                echo '</div>';
            }
        } elseif ($tab_corrente === 'gruppi') {
            $filtro_config = getFiltroConfig('gruppi', [
                'utente' => $idUtente,
                'tab' => 'gruppi'
            ]);
            include 'filter.php';
        } elseif ($tab_corrente === 'bacheche') {
            $filtro_config = getFiltroConfig('bacheche', [
                'utente' => $idUtente,
                'tab' => 'bacheche'
            ]);
            
            // Rimuovo campi in eccesso non presenti nella tabella (Nome e Cognome Proprietario)
            if(isset($filtro_config['campi'])){
                foreach ($filtro_config['campi'] as $k => $c) {
                    if (in_array(($c['name'] ?? ''), ['proprietario_nome', 'proprietario_cognome'])) {
                        unset($filtro_config['campi'][$k]);
                    }
                }
            }
            include 'filter.php';
        } elseif ($tab_corrente === 'file') {
            // Estrazione dinamica dei range di dimensione per gli slider
            $stmtRange = $pdo->prepare("SELECT MIN(dimensione) as min_dim, MAX(dimensione) as max_dim FROM FileMultimediale WHERE caricatoDa = :owner");
            $stmtRange->execute([':owner' => $idUtente]);
            $rangeDati = $stmtRange->fetch(PDO::FETCH_ASSOC);
            
            $minSize = isset($rangeDati['min_dim']) ? floor($rangeDati['min_dim']) : 0;
            $maxSize = isset($rangeDati['max_dim']) ? ceil($rangeDati['max_dim']) : 100;
            if ($minSize == $maxSize) {
                $minSize = 0;
            }

            $filtro_config = getFiltroConfig('file', [
                'utente' => $idUtente,
                'tab' => 'file',
                'min_size' => $minSize,
                'max_size' => $maxSize
            ]);

            // Rimuovo "Nickname Proprietario" perché in questo tab vediamo solo i file dell'utente stesso
            if(isset($filtro_config['campi'])){
                foreach ($filtro_config['campi'] as $k => $c) {
                    if (($c['name'] ?? '') === 'proprietario_file') {
                        unset($filtro_config['campi'][$k]);
                    }
                }
            }
            include 'filter.php';
        }
    }
}

if (!$isAjax):
?>
    <!DOCTYPE html>
    <html lang="it">

    <head>
        <title>SalMeet - Utenti</title>
        <?php include 'head.html'; ?>
        
        <script src="js/FilterHandler.js" defer></script>
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
                // INIZIALIZZAZIONE SIDEBAR
                // =========================================================
                $idUtenteSidebar = (!empty($_GET['utente']) && is_numeric($_GET['utente'])) ? (int)$_GET['utente'] : null;
                $tab_correnteSidebar = $_GET['tab'] ?? 'info';
                
                renderFiltroSidebarUtenti($pdo, $idUtenteSidebar, $tab_correnteSidebar);
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php if (!$isAjax): ?>
                <div id="ajax-results">
                <?php endif; ?>

                <?php
                // =========================================================
                // ROUTING VISTE (CONTENUTO PRINCIPALE)
                // =========================================================
                if (!empty($_GET['utente']) && is_numeric($_GET['utente'])) {
                    $idUtente = (int)$_GET['utente'];
                    $tab_corrente = $_GET['tab'] ?? 'info';

                    // 1. Lettura dati anagrafici dell'utente selezionato
                    $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
                    $stmtUtente->execute([':codice' => $idUtente]);
                    $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

                    if ($infoUtente) {
                        $dataFormattata = !empty($infoUtente['dataNascita']) ? (function_exists('formattaData') ? formattaData($infoUtente['dataNascita']) : date('d/m/Y', strtotime($infoUtente['dataNascita']))) : "";

                        echo "<p><a href='utenti.php'>&larr; Torna all'elenco utenti</a></p>";
                        echo "<h2>". htmlspecialchars($infoUtente['cognome']) . " " . htmlspecialchars($infoUtente['nome']) . "</h2>";

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

                            if (!empty($_GET['nome'])) {
                                $whereSql .= " AND g.nome LIKE :nome";
                                $params[':nome'] = '%' . $_GET['nome'] . '%';
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

                                // Controllo corona
                                $iconaCorona = ((int)$g['id_proprietario'] === $idUtente) ? " <img src='images/crown.png' alt='Owner' class='owner-crown-icon'>" : "";

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

                            if ($numero_records > 0) {
                                echo '<div class="table-container">';
                                stampaTabella($gruppiFormattati, ['Nome Gruppo', 'Proprietario'], $customHeaders);
                                echo '</div>';
                                echo getPagesNav($np, $numero_pagine, 1);
                            } else {
                                echo "<p class='info-risultati'>Nessun gruppo trovato con i filtri correnti.</p>";
                            }

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

                            if (!empty($_GET['titolo'])) {
                                $whereSql .= " AND uab.nomeBacheca LIKE :titolo";
                                $params[':titolo'] = '%' . $_GET['titolo'] . '%';
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
                                $link_bacheca = "bacheche.php?vista=dettaglio&bacheca=" . urlencode($b['Nome Bacheca']) . "&owner=" . urlencode($b['id_proprietario']);
                                $link_proprietario = "utenti.php?utente=" . urlencode($b['id_proprietario']);

                                // Controllo corona
                                $iconaCorona = ((int)$b['id_proprietario'] === $idUtente) ? " <img src='images/crown.png' alt='Owner' class='owner-crown-icon'>" : "";

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

                            if ($numero_records > 0) {
                                echo '<div class="table-container">';
                                stampaTabella($bachecheFormattate, ['Nome Bacheca', 'Proprietario'], $customHeaders);
                                echo '</div>';
                                echo getPagesNav($np, $numero_pagine, 1);
                            } else {
                                echo "<p class='info-risultati'>Nessuna bacheca trovata con i filtri correnti.</p>";
                            }

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

                            if (!empty($_GET['file'])) {
                                $whereSql .= " AND titolo LIKE :file";
                                $params[':file'] = '%' . $_GET['file'] . '%';
                            }
                            
                            // Gestione filtraggio array checkboxes (filetype) identico a media.php
                            $filetypes = [
                                'immagine' => 'Immagini',
                                'audio' => 'Audio',
                                'video' => 'Video'
                            ];

                            if (!empty($_GET['filetype'])) {
                                $selectedTypes = array_filter((array) $_GET['filetype'], function ($type) use ($filetypes) {
                                    return isset($filetypes[$type]);
                                });

                                if (!empty($selectedTypes)) {
                                    $placeholders = [];
                                    foreach (array_values($selectedTypes) as $index => $type) {
                                        $placeholder = ':filetype_' . $index;
                                        $placeholders[] = $placeholder;
                                        $params[$placeholder] = $type;
                                    }
                                    $whereSql .= ' AND tipo IN (' . implode(', ', $placeholders) . ')';
                                }
                            }

                            // Range dinamico file
                            if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
                                $whereSql .= " AND dimensione >= :dimensione_min";
                                $params[':dimensione_min'] = (float)$_GET['dimensione_min'];
                            }
                            if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
                                $whereSql .= " AND dimensione <= :dimensione_max";
                                $params[':dimensione_max'] = (float)$_GET['dimensione_max'];
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

                                $titolo_html = "<a href='{$link_file}' target='_blank' class='file-link'>" .
                                    "<img src='{$icon_path}' alt='Icona' class='icona icona-filetype' style='vertical-align:middle;'>" .
                                    htmlspecialchars($f['File']). "</a>";

                                $filesFormattati[] = [
                                    'File' => $titolo_html,
                                    // Utilizzo function di formattazione nativa per la dimensione file
                                    'Dimensione' => formatFileSizeHtml((int)$f['Dimensione'])
                                ];
                            }

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> file condivisi (<strong>$limit</strong> per pagina)</p></div>";

                            $customHeaders = generaIntestazioniOrdinabili([
                                'File' => 'file',
                                'Dimensione' => 'dimensione'
                            ], $sort_col, $sort_dir);

                            if ($numero_records > 0) {
                                echo '<div class="table-container">';
                                stampaTabella($filesFormattati, ['File', 'Dimensione'], $customHeaders);
                                echo '</div>';
                                echo getPagesNav($np, $numero_pagine, 1);
                            } else {
                                echo "<p class='info-risultati'>Nessun file trovato con i filtri correnti.</p>";
                            }

                        } else {
                            echo "<p class='info-risultati'>Utente non trovato.</p>";
                        }
                    } else {
                        echo "<p class='info-risultati'>Errore: l'utente richiesto non è presente a sistema.</p>";
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

                    if (!empty($_GET['utente'])) {
                        $whereSql .= " AND nickname LIKE :utente";
                        $params[':utente'] = '%' . $_GET['utente'] . '%';
                    }
                    if (!empty($_GET['nome'])) {
                        $whereSql .= " AND nome LIKE :nome";
                        $params[':nome'] = '%' . $_GET['nome'] . '%';
                    }
                    if (!empty($_GET['cognome'])) {
                        $whereSql .= " AND cognome LIKE :cognome";
                        $params[':cognome'] = '%' . $_GET['cognome'] . '%';
                    }
                    if (!empty($_GET['data_nascita'])) {
                        if (isDataValidaRange($_GET['data_nascita'])) {
                            $whereSql .= " AND dataNascita >= :data_nascita";
                            $params[':data_nascita'] = $_GET['data_nascita'];
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