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
                        echo '<div id="filtro" class="filter-empty"><p>' . htmlspecialchars($filtro_config['messaggio']) . '</p></div>';
                    } elseif ($tab_corrente === 'membri') {
                        $filtro_config = getFiltroConfig('utenti', ['gruppo' => $idGruppo, 'tab' => 'membri']);
                        include 'filter.php';
                    } elseif ($tab_corrente === 'file') {
                        // Passiamo i parametri strutturali necessari a mantenere il tab e il contesto
                        $filtro_config = getFiltroConfig('file', ['gruppo' => $idGruppo, 'tab' => 'file']);
                        include 'filter.php';
                    }
                }
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php
            // =========================================================
            // CASO A: ELENCO GENERALE DEI GRUPPI
            // =========================================================
            if (empty($_GET['gruppo'])) {
                echo "<h2>Elenco Gruppi Creati</h2>";

                $condizioni = [];
                $params = [];

                if (!empty($_GET['nome'])) {
                    $condizioni[] = "g.nome LIKE :nome";
                    $params[':nome'] = '%' . $_GET['nome'] . '%';
                }

                // Sostituita la ricerca singola del proprietario con la ricerca rapida/globale (multi-parola e multi-colonna)
                if (!empty($_GET['proprietario'])) {
                    $search = trim($_GET['proprietario']);
                    $parole = preg_split('/\s+/', $search);
                    $subCondizioniAND = [];
                    foreach ($parole as $index => $parola) {
                        $paramName = ":p_prop_" . $index;
                        $subCondizioniAND[] = "(u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                        $params[$paramName] = '%' . $parola . '%';
                    }
                    if (!empty($subCondizioniAND)) {
                        $condizioni[] = "(" . implode(" AND ", $subCondizioniAND) . ")";
                    }
                }

                if (!empty($_GET['data'])) {
                    $condizioni[] = "g.dataCreazione >= :data";
                    $params[':data'] = $_GET['data'];
                }

                $whereSql = !empty($condizioni) ? "WHERE " . implode(" AND ", $condizioni) : "";

                // Paginazione ordinamento
                $sort_col = $_GET['sort'] ?? 'nome';
                $sort_dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

                $colonne_permesse = [
                    'nome' => 'g.nome',
                    'Proprietario' => 'u.nickname',
                    'data' => 'g.dataCreazione'
                ];
                $orderBy = $colonne_permesse[$sort_col] ?? 'g.nome';

                // Query per conteggio totale
                $sql_count = "SELECT COUNT(*) FROM gruppi g JOIN utenti u ON g.idCreatore = u.idUtente $whereSql";
                $stmt_count = $pdo->prepare($sql_count);
                $stmt_count->execute($params);
                $totale_righe = $stmt_count->fetchColumn();

                $righe_per_pagina = 7;
                $numero_pagine = ceil($totale_righe / $righe_per_pagina);
                $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                if ($np < 1) $np = 1;
                if ($np > $numero_pagine && $numero_pagine > 0) $np = $numero_pagine;
                $offset = ($np - 1) * $righe_per_pagina;

                $sql = "SELECT g.idGruppo, g.nome AS 'Nome Gruppo', u.idUtente, u.nickname AS 'Proprietario', g.dataCreazione AS 'Data Creazione'
                        FROM gruppi g
                        JOIN utenti u ON g.idCreatore = u.idUtente
                        $whereSql
                        ORDER BY $orderBy $sort_dir
                        LIMIT $righe_per_pagina OFFSET $offset";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($risultati)) {
                    $datiGruppi = [];
                    foreach ($risultati as $riga) {
                        $gEnc = urlencode($riga['idGruppo']);
                        $uEnc = urlencode($riga['idUtente']);
                        
                        $htmlNomeGruppo = "<a href='gruppi.php?gruppo={$gEnc}&tab=info' class='async-link'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";
                        $htmlProprietario = "<a href='utenti.php?utente={$uEnc}&tab=info' class='async-link'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

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

                    echo getPagesNav($np, $numero_pagine, 1);
                } else {
                    echo "<p class='info-risultati'>Nessun gruppo trovato.</p>";
                }
            }
            // =========================================================
            // CASO B: DETTAGLIO DI UN SINGOLO GRUPPO (GESTIONE TAB)
            // =========================================================
            else {
                $idGruppo = (int)$_GET['gruppo'];
                $tab_corrente = $_GET['tab'] ?? 'info';

                // Recupera info sul gruppo corrente
                $sqlGruppo = "SELECT g.*, u.nickname AS creatore_nick, u.idUtente AS id_creatore 
                              FROM gruppi g 
                              JOIN utenti u ON g.idCreatore = u.idUtente 
                              WHERE g.idGruppo = :id";
                $stmtG = $pdo->prepare($sqlGruppo);
                $stmtG->execute([':id' => $idGruppo]);
                $infoGruppo = $stmtG->fetch(PDO::FETCH_ASSOC);

                if (!$infoGruppo) {
                    echo "<p class='error'>Gruppo non trovato.</p>";
                    if (!$isAjax) echo "</div></div></body></html>";
                    exit;
                }

                echo "<h2>Gruppo: " . htmlspecialchars($infoGruppo['nome']) . "</h2>";

                // Gestione dei tab tramite blocco HTML nativo coerente con il resto dei file
                $tabs = ['info' => 'Informazioni', 'membri' => 'Membri', 'file' => 'File Condivisi'];
                echo '<div class="tabs">';
                foreach ($tabs as $chiaveTab => $etichettaTab) {
                    $attivo = ($tab_corrente === $chiaveTab) ? 'active' : '';
                    $gEnc = urlencode($idGruppo);
                    echo "<a href='gruppi.php?gruppo={$gEnc}&tab={$chiaveTab}' class='tab-button {$attivo} async-link'>{$etichettaTab}</a>";
                }
                echo '</div>';

                // ---------------------------------------------------------
                // TAB 1: INFORMAZIONI GENERALI
                // ---------------------------------------------------------
                if ($tab_corrente === 'info') {
                    echo "<div class='tab-content active'>";
                    echo "<h3>Informazioni Generali</h3>";
                    $datiInfo = [
                        ['Proprietà' => '<strong>Nome Gruppo</strong>', 'Valore' => htmlspecialchars($infoGruppo['nome'])],
                        ['Proprietà' => '<strong>Creatore / Amministratore</strong>', 'Valore' => "<a href='utenti.php?utente=" . urlencode($infoGruppo['id_creatore']) . "&tab=info' class='async-link'>" . htmlspecialchars($infoGruppo['creatore_nick']) . "</a>"],
                        ['Proprietà' => '<strong>Data di Fondazione</strong>', 'Valore' => formattaData($infoGruppo['dataCreazione'])],
                        ['Proprietà' => '<strong>Descrizione</strong>', 'Valore' => !empty($infoGruppo['descrizione']) ? htmlspecialchars($infoGruppo['descrizione']) : '<i>Nessuna descrizione inserita.</i>']
                    ];
                    stampaTabella($datiInfo, [], []);
                    echo "</div>";
                }
                // ---------------------------------------------------------
                // TAB 2: MEMBRI DEL GRUPPO
                // ---------------------------------------------------------
                elseif ($tab_corrente === 'membri') {
                    echo "<div class='tab-content active'>";
                    echo "<h3>Membri Iscritti</h3>";

                    $condizioni = ["pa.idGruppo = :idGruppo"];
                    $params = [':idGruppo' => $idGruppo];

                    if (!empty($_GET['ricerca_globale'])) {
                        $search = trim($_GET['ricerca_globale']);
                        $parole = preg_split('/\s+/', $search);
                        $subCondizioniAND = [];
                        foreach ($parole as $index => $parola) {
                            $paramName = ":p_memb_" . $index;
                            $subCondizioniAND[] = "(u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                            $params[$paramName] = '%' . $parola . '%';
                        }
                        if (!empty($subCondizioniAND)) {
                            $condizioni[] = "(" . implode(" AND ", $subCondizioniAND) . ")";
                        }
                    }

                    if (!empty($_GET['data_nascita'])) {
                        $condizioni[] = "u.dataNascita = :data_nascita";
                        $params[':data_nascita'] = $_GET['data_nascita'];
                    }

                    $whereSql = "WHERE " . implode(" AND ", $condizioni);

                    $sort_col = $_GET['sort'] ?? 'Nickname';
                    $sort_dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

                    $colonne_permesse = [
                        'Nickname' => 'u.nickname',
                        'Nome' => 'u.nome',
                        'Cognome' => 'u.cognome',
                        'data' => 'pa.dataIscrizione'
                    ];
                    $orderBy = $colonne_permesse[$sort_col] ?? 'u.nickname';

                    $sql_count = "SELECT COUNT(*) FROM partecipazioni pa JOIN utenti u ON pa.idUtente = u.idUtente $whereSql";
                    $stmt_count = $pdo->prepare($sql_count);
                    $stmt_count->execute($params);
                    $totale_righe = $stmt_count->fetchColumn();

                    $righe_per_pagina = 5;
                    $numero_pagine = ceil($totale_righe / $righe_per_pagina);
                    $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                    if ($np < 1) $np = 1;
                    if ($np > $numero_pagine && $numero_pagine > 0) $np = $numero_pagine;
                    $offset = ($np - 1) * $righe_per_pagina;

                    $sqlMembri = "SELECT u.idUtente, u.nickname AS 'Nickname', u.nome AS 'Nome', u.cognome AS 'Cognome', pa.dataIscrizione AS 'Data Iscrizione'
                                  FROM partecipazioni pa
                                  JOIN utenti u ON pa.idUtente = u.idUtente
                                  $whereSql
                                  ORDER BY $orderBy $sort_dir
                                  LIMIT $righe_per_pagina OFFSET $offset";

                    $stmtM = $pdo->prepare($sqlMembri);
                    $stmtM->execute($params);
                    $membri = $stmtM->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($membri)) {
                        $datiMembri = [];
                        foreach ($membri as $membro) {
                            $uEnc = urlencode($membro['idUtente']);
                            $htmlNick = "<a href='utenti.php?utente={$uEnc}&tab=info' class='async-link'>" . htmlspecialchars($membro['Nickname']) . "</a>";

                            $datiMembri[] = [
                                'Nickname'         => $htmlNick,
                                'Nome'             => htmlspecialchars($membro['Nome']),
                                'Cognome'          => htmlspecialchars($membro['Cognome']),
                                'Data Iscrizione'  => $membro['Data Iscrizione']
                            ];
                        }

                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nickname'        => 'Nickname',
                            'Nome'            => 'Nome',
                            'Cognome'         => 'Cognome',
                            'Data Iscrizione' => 'data'
                        ], $sort_col, $sort_dir);

                        echo '<div class="table-container">';
                        stampaTabella($datiMembri, ['Nickname'], $customHeaders);
                        echo '</div>';

                        echo getPagesNav($np, $numero_pagine, 1);
                    } else {
                        echo "<p class='info-risultati'>Nessun membro corrisponde ai criteri di ricerca.</p>";
                    }
                    echo "</div>";
                }
                // ---------------------------------------------------------
                // TAB 3: FILE CONDIVISI NEL GRUPPO
                // ---------------------------------------------------------
                elseif ($tab_corrente === 'file') {
                    echo "<div class='tab-content active'>";
                    echo "<h3>Archivio Multimediale del Gruppo</h3>";

                    $condizioni = ["fm.idGruppo = :idGruppo"];
                    $params = [':idGruppo' => $idGruppo];

                    if (!empty($_GET['file'])) {
                        $condizioni[] = "fm.nome LIKE :file";
                        $params[':file'] = '%' . $_GET['file'] . '%';
                    }

                    // Sostituita la ricerca del singolo nickname proprietario_file con la ricerca rapida/globale multi-parola
                    if (!empty($_GET['proprietario_file'])) {
                        $search = trim($_GET['proprietario_file']);
                        $parole = preg_split('/\s+/', $search);
                        $subCondizioniAND = [];
                        foreach ($parole as $index => $parola) {
                            $paramName = ":p_file_owner_" . $index;
                            $subCondizioniAND[] = "(u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                            $params[$paramName] = '%' . $parola . '%';
                        }
                        if (!empty($subCondizioniAND)) {
                            $condizioni[] = "(" . implode(" AND ", $subCondizioniAND) . ")";
                        }
                    }

                    if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
                        $condizioni[] = "fm.dimensione >= :dim_min";
                        $params[':dim_min'] = (int)$_GET['dimensione_min'];
                    }
                    if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
                        $condizioni[] = "fm.dimensione <= :dim_max";
                        $params[':dim_max'] = (int)$_GET['dimensione_max'];
                    }

                    // Filtro checkbox-group sul tipo di file (Immagini, Audio, Video)
                    if (!empty($_GET['filetype']) && is_array($_GET['filetype'])) {
                        $tipiSelezionati = $_GET['filetype'];
                        $subOR = [];
                        foreach ($tipiSelezionati as $index => $tipo) {
                            $paramName = ":type_" . $index;
                            $subOR[] = "fm.tipo = $paramName";
                            $params[$paramName] = $tipo;
                        }
                        if (!empty($subOR)) {
                            $condizioni[] = "(" . implode(" OR ", $subOR) . ")";
                        }
                    }

                    $whereSql = "WHERE " . implode(" AND ", $condizioni);

                    $sort_col = $_GET['sort'] ?? 'Nome';
                    $sort_dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

                    $colonne_permesse = [
                        'Nome' => 'fm.nome',
                        'Tipo' => 'fm.tipo',
                        'Dimensione' => 'fm.dimensione',
                        'Proprietario' => 'u.nickname'
                    ];
                    $orderBy = $colonne_permesse[$sort_col] ?? 'fm.nome';

                    $sql_count = "SELECT COUNT(*) FROM file_multimediali fm JOIN utenti u ON fm.idCaricatore = u.idUtente $whereSql";
                    $stmt_count = $pdo->prepare($sql_count);
                    $stmt_count->execute($params);
                    $totale_righe = $stmt_count->fetchColumn();

                    $righe_per_pagina = 5;
                    $numero_pagine = ceil($totale_righe / $righe_per_pagina);
                    $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                    if ($np < 1) $np = 1;
                    if ($np > $numero_pagine && $numero_pagine > 0) $np = $numero_pagine;
                    $offset = ($np - 1) * $righe_per_pagina;

                    $sqlFile = "SELECT fm.idFile, fm.nome AS 'Nome', fm.tipo AS 'Tipo', fm.dimensione AS 'Dimensione', 
                                       u.idUtente, u.nickname AS 'Nickname'
                                FROM file_multimediali fm
                                JOIN utenti u ON fm.idCaricatore = u.idUtente
                                $whereSql
                                ORDER BY $orderBy $sort_dir
                                LIMIT $righe_per_pagina OFFSET $offset";

                    $stmtF = $pdo->prepare($sqlFile);
                    $stmtF->execute($params);
                    $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($files)) {
                        $datiFile = [];
                        foreach ($files as $file) {
                            $uEnc = urlencode($file['idUtente']);
                            
                            // Rimossi Nome e Cognome, mantenendo soltanto il Nickname come richiesto
                            $htmlNick = "<a href='utenti.php?utente={$uEnc}&tab=info' class='async-link'>" . htmlspecialchars($file['Nickname']) . "</a>";

                            $datiFile[] = [
                                'Nome'         => htmlspecialchars($file['Nome']),
                                'Tipo'         => htmlspecialchars($file['Tipo']),
                                'Dimensione'   => (int)$file['Dimensione'], 
                                'Proprietario' => $htmlNick
                            ];
                        }

                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nome'         => 'Nome',
                            'Tipo'         => 'Tipo',
                            'Dimensione'   => 'Dimensione',
                            'Proprietario' => 'Proprietario'
                        ], $sort_col, $sort_dir);

                        echo '<div class="table-container">';
                        stampaTabella($datiFile, ['Nome'], $customHeaders);
                        echo '</div>';

                        echo getPagesNav($np, $numero_pagine, 1);
                    } else {
                        echo "<p class='info-risultati'>Nessun file multimediale trovato nel gruppo per i criteri indicati.</p>";
                    }
                    echo "</div>";
                }
            }
            ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>