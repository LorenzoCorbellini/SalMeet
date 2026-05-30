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
                        // Calcoliamo min e max size per i file di questo gruppo specifico
                        $stmtSize = $pdo->prepare("SELECT MIN(fm.dimensione) as min_s, MAX(fm.dimensione) as max_s FROM condivisione_file_gruppo cfg JOIN file_multimediali fm ON cfg.idFile = fm.idFile WHERE cfg.idGruppo = :idGruppo");
                        $stmtSize->execute([':idGruppo' => $idGruppo]);
                        $sizes = $stmtSize->fetch(PDO::FETCH_ASSOC);
                        $minSize = $sizes['min_s'] !== null ? (int)$sizes['min_s'] : 0;
                        $maxSize = $sizes['max_s'] !== null ? (int)$sizes['max_s'] : 100;

                        $filtro_config = getFiltroConfig('file', [
                            'gruppo' => $idGruppo,
                            'tab' => 'file',
                            'min_size' => $minSize,
                            'max_size' => $maxSize
                        ]);
                        include 'filter.php';
                    }
                }
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php
            if (empty($_GET['gruppo'])) {
                // =========================================================
                // SCHERMATA PRINCIPALE: ELENCO DEI GRUPPI
                // =========================================================
                echo '<div class="action-bar">';
                echo "<a onclick='aggiungiGruppo()' class='btn-aggiungi'><img src='images/add.png' alt='Aggiungi' class='btn-img-align'> <strong>Crea un nuovo gruppo</strong></a>";
                echo '</div>';

                $f_porzione = 5;
                $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                if ($np < 1) $np = 1;
                $offset = ($np - 1) * $f_porzione;

                $sql_count = "SELECT COUNT(*) FROM gruppi g JOIN utenti u ON g.idProprietario = u.idUtente WHERE 1=1";
                $sql = "SELECT g.idGruppo, g.nome AS 'Nome Gruppo', u.nickname AS 'Proprietario', g.dataCreazione AS 'Data Creazione'
                        FROM gruppi g
                        JOIN utenti u ON g.idProprietario = u.idUtente
                        WHERE 1=1";
                $params = [];

                if (!empty($_GET['nome'])) {
                    $sql_count .= " AND g.nome LIKE :nome";
                    $sql .= " AND g.nome LIKE :nome";
                    $params[':nome'] = '%' . $_GET['nome'] . '%';
                }
                if (!empty($_GET['proprietario'])) {
                    $ricerca = trim($_GET['proprietario']);
                    $parole = preg_split('/\s+/', $ricerca);
                    foreach ($parole as $index => $parola) {
                        $paramName = ":prop_{$index}";
                        $sql_count .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                        $sql .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                        $params[$paramName] = "%" . $parola . "%";
                    }
                }
                if (!empty($_GET['data'])) {
                    $sql_count .= " AND g.dataCreazione >= :data";
                    $sql .= " AND g.dataCreazione >= :data";
                    $params[':data'] = $_GET['data'];
                }

                $sort_col = $_GET['sort'] ?? 'data';
                $sort_dir = $_GET['dir'] ?? 'DESC';
                $allowed_cols = ['nome' => 'g.nome', 'Proprietario' => 'u.nickname', 'data' => 'g.dataCreazione'];
                $order_by = $allowed_cols[$sort_col] ?? 'g.dataCreazione';
                $direction = (strtoupper($sort_dir) === 'ASC') ? 'ASC' : 'DESC';

                $sql .= " ORDER BY $order_by $direction LIMIT $f_porzione OFFSET $offset";

                $stmt_count = $pdo->prepare($sql_count);
                $stmt_count->execute($params);
                $totale_righe = $stmt_count->fetchColumn();
                $numero_pagine = ceil($totale_righe / $f_porzione);

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($totale_righe > 0) {
                    $datiGruppi = [];
                    foreach ($risultati as $riga) {
                        $gEnc = urlencode($riga['idGruppo']);
                        $htmlNomeGruppo = "<a href='gruppi.php?gruppo={$gEnc}' class='link-bacheca'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";
                        $htmlProprietario = "<a href='utenti.php?utente=" . urlencode($riga['Proprietario']) . "' class='link-utente'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

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
            } else {
                // =========================================================
                // SCHERMATA DETTAGLIO GRUPPO
                // =========================================================
                $idGruppo = (int)$_GET['gruppo'];
                $tab_corrente = $_GET['tab'] ?? 'info';

                $stmtG = $pdo->prepare("SELECT g.*, u.nickname FROM gruppi g JOIN utenti u ON g.idProprietario = u.idUtente WHERE g.idGruppo = :id");
                $stmtG->execute([':id' => $idGruppo]);
                $gruppo = $stmtG->fetch(PDO::FETCH_ASSOC);

                if (!$gruppo) {
                    echo "<p class='errore'>Gruppo non trovato.</p>";
                } else {
                    echo '<h2>' . htmlspecialchars($gruppo['nome']) . '</h2>';
                    echo '<div class="tabs">';
                    echo "<a href='gruppi.php?gruppo={$idGruppo}&tab=info' class='tab-link " . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>";
                    echo "<a href='gruppi.php?gruppo={$idGruppo}&tab=membri' class='tab-link " . ($tab_corrente === 'membri' ? 'active' : '') . "'>Membri</a>";
                    echo "<a href='gruppi.php?gruppo={$idGruppo}&tab=file' class='tab-link " . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>";
                    echo '</div>';

                    if ($tab_corrente === 'info') {
                        echo '<div class="tab-content">';
                        echo '<p><strong>Descrizione:</strong> ' . htmlspecialchars($gruppo['descrizione'] ?? 'Nessuna descrizione') . '</p>';
                        echo '<p><strong>Creato da:</strong> ' . htmlspecialchars($gruppo['nickname']) . ' il ' . formattaData($gruppo['dataCreazione']) . '</p>';
                        echo '</div>';
                    } elseif ($tab_corrente === 'membri') {
                        echo '<div class="tab-content">';
                        $f_porzione = 5;
                        $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                        if ($np < 1) $np = 1;
                        $offset = ($np - 1) * $f_porzione;

                        $sql_count = "SELECT COUNT(*) FROM appartenenza_gruppo ag JOIN utenti u ON ag.idUtente = u.idUtente WHERE ag.idGruppo = :idGruppo";
                        $sql = "SELECT u.nickname, u.nome, u.cognome, ag.dataIscrizione FROM appartenenza_gruppo ag JOIN utenti u ON ag.idUtente = u.idUtente WHERE ag.idGruppo = :idGruppo";
                        $params = [':idGruppo' => $idGruppo];

                        if (!empty($_GET['ricerca_globale'])) {
                            $ricerca = trim($_GET['ricerca_globale']);
                            $parole = preg_split('/\s+/', $ricerca);
                            foreach ($parole as $index => $parola) {
                                $paramName = ":p_{$index}";
                                $sql_count .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                                $sql .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                                $params[$paramName] = "%" . $parola . "%";
                            }
                        }
                        if (!empty($_GET['data_nascita'])) {
                            $sql_count .= " AND u.dataNascita = :data_nascita";
                            $sql .= " AND u.dataNascita = :data_nascita";
                            $params[':data_nascita'] = $_GET['data_nascita'];
                        }

                        $sort_col = $_GET['sort'] ?? 'data';
                        $sort_dir = $_GET['dir'] ?? 'DESC';
                        $allowed_cols = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'ag.dataIscrizione'];
                        $order_by = $allowed_cols[$sort_col] ?? 'ag.dataIscrizione';
                        $direction = (strtoupper($sort_dir) === 'ASC') ? 'ASC' : 'DESC';

                        $sql .= " ORDER BY $order_by $direction LIMIT $f_porzione OFFSET $offset";

                        $stmt_count = $pdo->prepare($sql_count);
                        $stmt_count->execute($params);
                        $totale_righe = $stmt_count->fetchColumn();
                        $numero_pagine = ceil($totale_righe / $f_porzione);

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($totale_righe > 0) {
                            $datiMembri = [];
                            foreach ($risultati as $riga) {
                                $datiMembri[] = [
                                    'Nickname' => htmlspecialchars($riga['nickname']),
                                    'Nome' => htmlspecialchars($riga['nome']),
                                    'Cognome' => htmlspecialchars($riga['cognome']),
                                    'Data Iscrizione' => $riga['dataIscrizione']
                                ];
                            }
                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nickname' => 'nickname',
                                'Nome' => 'nome',
                                'Cognome' => 'cognome',
                                'Data Iscrizione' => 'data'
                            ], $sort_col, $sort_dir);

                            stampaTabella($datiMembri, [], $customHeaders);
                            echo getPagesNav($np, $numero_pagine, 1);
                        } else {
                            echo "<p class='info-risultati'>Nessun membro trovato.</p>";
                        }
                        echo '</div>';
                    } elseif ($tab_corrente === 'file') {
                        echo '<div class="tab-content">';
                        $f_porzione = 5;
                        $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                        if ($np < 1) $np = 1;
                        $offset = ($np - 1) * $f_porzione;

                        $sql_count = "SELECT COUNT(*) FROM condivisione_file_gruppo cfg JOIN file_multimediali fm ON cfg.idFile = fm.idFile JOIN utenti u ON fm.idProprietario = u.idUtente WHERE cfg.idGruppo = :idGruppo";
                        $sql = "SELECT fm.idFile, fm.nome AS 'Nome File', u.nickname AS 'Proprietario', fm.dimensione, fm.tipo, cfg.dataCondivisione AS 'Data Condivisione'
                                FROM condivisione_file_gruppo cfg
                                JOIN file_multimediali fm ON cfg.idFile = fm.idFile
                                JOIN utenti u ON fm.idProprietario = u.idUtente
                                WHERE cfg.idGruppo = :idGruppo";
                        $params = [':idGruppo' => $idGruppo];

                        if (!empty($_GET['file'])) {
                            $sql_count .= " AND fm.nome LIKE :file";
                            $sql .= " AND fm.nome LIKE :file";
                            $params[':file'] = '%' . $_GET['file'] . '%';
                        }
                        if (!empty($_GET['proprietario_file'])) {
                            $ricerca = trim($_GET['proprietario_file']);
                            $parole = preg_split('/\s+/', $ricerca);
                            foreach ($parole as $index => $parola) {
                                $paramName = ":prop_file_{$index}";
                                $sql_count .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                                $sql .= " AND (u.nickname LIKE $paramName OR u.nome LIKE $paramName OR u.cognome LIKE $paramName)";
                                $params[$paramName] = "%" . $parola . "%";
                            }
                        }
                        if (isset($_GET['filetype']) && is_array($_GET['filetype'])) {
                            $tipiValidi = array_intersect($_GET['filetype'], ['immagine', 'audio', 'video']);
                            if (!empty($tipiValidi)) {
                                $inClause = [];
                                foreach ($tipiValidi as $idx => $t) {
                                    $pName = ":t_$idx";
                                    $inClause[] = $pName;
                                    $params[$pName] = $t;
                                }
                                $sql_count .= " AND fm.tipo IN (" . implode(',', $inClause) . ")";
                                $sql .= " AND fm.tipo IN (" . implode(',', $inClause) . ")";
                            }
                        }
                        if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
                            $sql_count .= " AND fm.dimensione >= :dim_min";
                            $sql .= " AND fm.dimensione >= :dim_min";
                            $params[':dim_min'] = (int)$_GET['dimensione_min'];
                        }
                        if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
                            $sql_count .= " AND fm.dimensione <= :dim_max";
                            $sql .= " AND fm.dimensione <= :dim_max";
                            $params[':dim_max'] = (int)$_GET['dimensione_max'];
                        }

                        $sort_col = $_GET['sort'] ?? 'data';
                        $sort_dir = $_GET['dir'] ?? 'DESC';
                        $allowed_cols = ['nome' => 'fm.nome', 'Proprietario' => 'u.nickname', 'data' => 'cfg.dataCondivisione'];
                        $order_by = $allowed_cols[$sort_col] ?? 'cfg.dataCondivisione';
                        $direction = (strtoupper($sort_dir) === 'ASC') ? 'ASC' : 'DESC';

                        $sql .= " ORDER BY $order_by $direction LIMIT $f_porzione OFFSET $offset";

                        $stmt_count = $pdo->prepare($sql_count);
                        $stmt_count->execute($params);
                        $totale_righe = $stmt_count->fetchColumn();
                        $numero_pagine = ceil($totale_righe / $f_porzione);

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if ($totale_righe > 0) {
                            $datiGruppi = [];
                            foreach ($risultati as $riga) {
                                $htmlNomeGruppo = htmlspecialchars($riga['Nome File']);
                                $htmlProprietario = "<a href='utenti.php?utente=" . urlencode($riga['Proprietario']) . "' class='link-utente'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

                                $datiGruppi[] = [
                                    'Nome File'       => $htmlNomeGruppo,
                                    'Proprietario'    => $htmlProprietario,
                                    'Data Creazione'  => $riga['Data Condivisione']
                                ];
                            }

                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nome File'      => 'nome',
                                'Proprietario'   => 'Proprietario',
                                'Data Creazione' => 'data'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($datiGruppi, ['Nome File', 'Proprietario'], $customHeaders);
                            echo '</div>';

                            echo getPagesNav($np, $numero_pagine, 1);
                        } else {
                            echo "<p class='info-risultati'>Nessun gruppo trovato.</p>";
                        }
                        echo '</div>';
                    }
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