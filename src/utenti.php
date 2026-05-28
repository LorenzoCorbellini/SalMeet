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
                            ['tipo'  => 'date', 'name' => 'data',     'label' => 'Data di Nascita (Da):'],
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
                                ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Proprietario:']
                            ]
                        ];
                        include 'filter.php';
                    } elseif ($tab_corrente === 'bacheche') {
                        $filtro_config = [
                            'campi' => [
                                ['tipo' => 'hidden', 'name' => 'utente', 'value' => $idUtente],
                                ['tipo' => 'hidden', 'name' => 'tab', 'value' => 'bacheche'],
                                ['tipo' => 'text', 'name' => 'nome_bacheca', 'label' => 'Nome Bacheca:'],
                                ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Proprietario:']
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
                        echo "<h2 class='h2utente'>Profilo di <b><i>" . htmlspecialchars($infoUtente['nickname']) . "</i></b></h2>";

                        echo '<div class="tabs">';
                        echo '<a href="?utente=' . $idUtente . '&tab=info" class="tab ' . ($tab_corrente === 'info' ? 'active' : '') . '">Informazioni</a>';
                        echo '<a href="?utente=' . $idUtente . '&tab=gruppi" class="tab ' . ($tab_corrente === 'gruppi' ? 'active' : '') . '">Gruppi</a>';
                        echo '<a href="?utente=' . $idUtente . '&tab=bacheche" class="tab ' . ($tab_corrente === 'bacheche' ? 'active' : '') . '">Bacheche</a>';
                        echo '<a href="?utente=' . $idUtente . '&tab=file" class="tab ' . ($tab_corrente === 'file' ? 'active' : '') . '">File Condivisi</a>';
                        echo '</div>';

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

                            echo "<div class='info-dettaglio' style='margin-top: 20px;'>";
                            echo "<p><strong>Nome:</strong> " . htmlspecialchars($infoUtente['nome']) . "</p>";
                            echo "<p><strong>Cognome:</strong> " . htmlspecialchars($infoUtente['cognome']) . "</p>";
                            echo "<p><strong>Data di Nascita:</strong> " . htmlspecialchars($dataFormattata) . "</p>";
                            echo "<p><strong>Numero di file caricati:</strong> " . $numFile . "</p>";
                            echo "<p><strong>Numero di gruppi a cui appartiene:</strong> " . $numGruppi . "</p>";
                            echo "<p><strong>Numero di bacheche a cui appartiene:</strong> " . $numBacheche . "</p>";
                            echo "</div>";

                        } elseif ($tab_corrente === 'gruppi') {
                            $limit = 10;
                            $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                            if ($np < 1) $np = 1;
                            $start_from = ($np - 1) * $limit;

                            $allowed_sorts = [
                                'nome' => 'g.nome',
                                'proprietario' => 'u_owner.nickname',
                                'data' => 'g.dataCreazione'
                            ];
                            $sort_col = $_GET['sort'] ?? 'data';
                            $sort_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
                            $sql_sort = $allowed_sorts[$sort_col] ?? 'g.dataCreazione';

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

                            $countSql = "SELECT COUNT(*) FROM UtenteAutorizzatoGruppo uag 
                                         JOIN Gruppo g ON uag.codGruppo = g.codice 
                                         JOIN Utente u_owner ON g.creatoDa = u_owner.codice " . $whereSql;
                            $stmtCount = $pdo->prepare($countSql);
                            $stmtCount->execute($params);
                            $numero_records = $stmtCount->fetchColumn();
                            $numero_pagine = ceil($numero_records / $limit);

                            $sql = "SELECT g.nome AS `Nome Gruppo`, u_owner.nickname AS `Proprietario`, g.dataCreazione AS `Data Creazione` 
                                    FROM UtenteAutorizzatoGruppo uag 
                                    JOIN Gruppo g ON uag.codGruppo = g.codice 
                                    JOIN Utente u_owner ON g.creatoDa = u_owner.codice 
                                    $whereSql 
                                    ORDER BY $sql_sort $sort_dir 
                                    LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $gruppi = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> gruppi.</p></div>";
                            
                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nome Gruppo' => 'nome',
                                'Proprietario' => 'proprietario',
                                'Data Creazione' => 'data'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($gruppi, [], $customHeaders);
                            echo '</div>';
                            echo getPagesNav($np, $numero_pagine, 1);

                        } elseif ($tab_corrente === 'bacheche') {
                            $limit = 10;
                            $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                            if ($np < 1) $np = 1;
                            $start_from = ($np - 1) * $limit;

                            $allowed_sorts = [
                                'nome' => 'uab.nomeBacheca',
                                'proprietario' => 'u_owner.nickname',
                                'data' => 'b.dataCreazione'
                            ];
                            $sort_col = $_GET['sort'] ?? 'data';
                            $sort_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
                            $sql_sort = $allowed_sorts[$sort_col] ?? 'b.dataCreazione';

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

                            $countSql = "SELECT COUNT(*) FROM UtenteAutorizzatoBacheca uab 
                                         JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente
                                         JOIN Utente u_owner ON b.codiceUtente = u_owner.codice " . $whereSql;
                            $stmtCount = $pdo->prepare($countSql);
                            $stmtCount->execute($params);
                            $numero_records = $stmtCount->fetchColumn();
                            $numero_pagine = ceil($numero_records / $limit);

                            $sql = "SELECT uab.nomeBacheca AS `Nome Bacheca`, u_owner.nickname AS `Proprietario`, b.dataCreazione AS `Data Creazione` 
                                    FROM UtenteAutorizzatoBacheca uab 
                                    JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente
                                    JOIN Utente u_owner ON b.codiceUtente = u_owner.codice 
                                    $whereSql 
                                    ORDER BY $sql_sort $sort_dir 
                                    LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $bacheche = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>$numero_records</strong> bacheche.</p></div>";
                            
                            $customHeaders = generaIntestazioniOrdinabili([
                                'Nome Bacheca' => 'nome',
                                'Proprietario' => 'proprietario',
                                'Data Creazione' => 'data'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($bacheche, [], $customHeaders);
                            echo '</div>';
                            echo getPagesNav($np, $numero_pagine, 1);

                        } elseif ($tab_corrente === 'file') {
                            $limit = 10;
                            $np = isset($_GET['np']) ? (int)$_GET['np'] : 1;
                            if ($np < 1) $np = 1;
                            $start_from = ($np - 1) * $limit;

                            $allowed_sorts = [
                                'file' => 'titolo',
                                'dimensione' => 'dimensione'
                            ];
                            $sort_col = $_GET['sort'] ?? 'file';
                            $sort_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
                            $sql_sort = $allowed_sorts[$sort_col] ?? 'titolo';

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
                            $numero_pagine = ceil($numero_records / $limit);

                            $sql = "SELECT titolo AS `File`, dimensione AS `Dimensione` 
                                    FROM FileMultimediale 
                                    $whereSql 
                                    ORDER BY $sql_sort $sort_dir 
                                    LIMIT $start_from, $limit";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($files as &$fileRow) {
                                $fileRow['Dimensione'] .= " MB";
                            }

                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> file condivisi.</p></div>";
                            
                            $customHeaders = generaIntestazioniOrdinabili([
                                'File' => 'file',
                                'Dimensione' => 'dimensione'
                            ], $sort_col, $sort_dir);

                            echo '<div class="table-container">';
                            stampaTabella($files, [], $customHeaders);
                            echo '</div>';
                            echo getPagesNav($np, $numero_pagine, 1);
                        }

                    } else {
                        echo "<p class='info-risultati'>Utente non trovato.</p>";
                    }
                } else {
                    // 2. Elenco generale utenti
                    $limit = 15;
                    $np = isset($_GET['np']) ? (int) $_GET['np'] : 1;
                    if ($np < 1) $np = 1;
                    $start_from = ($np - 1) * $limit;

                    // Gestione Ordinamento
                    $allowed_sorts = [
                        'nickname' => 'nickname',
                        'nome'     => 'nome',
                        'cognome'  => 'cognome',
                        'data'     => 'dataNascita'
                    ];

                    $sort_col = $_GET['sort'] ?? 'nickname';
                    $sort_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC' ? 'DESC' : 'ASC';
                    $sql_sort = $allowed_sorts[$sort_col] ?? 'nickname';

                    // Costruzione clausola WHERE
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

                    // Query per il numero totale di record
                    $countSql = "SELECT COUNT(*) FROM Utente " . $whereSql;
                    $stmtCount = $pdo->prepare($countSql);
                    $stmtCount->execute($params);
                    $numero_records = $stmtCount->fetchColumn();
                    $numero_pagine = ceil($numero_records / $limit);

                    // Query per i dati
                    $sql = "SELECT codice, nickname, nome AS Nome, cognome AS Cognome, dataNascita AS `Data di Nascita` 
                            FROM Utente 
                            $whereSql 
                            ORDER BY $sql_sort $sort_dir 
                            LIMIT $start_from, $limit";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo "<div class='table-top-bar'>";
                    echo "<p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> utenti.</p>";
                    echo "</div>";

                    if (count($utenti) > 0) {
                        $datiUtenti = [];
                        foreach ($utenti as $riga) {
                            $htmlNickname = "<a href='?utente=" . urlencode($riga['codice']) . "' class='row-link' title='Visualizza profilo'>" . htmlspecialchars($riga['nickname']) . "</a>";
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
                </div> <?php endif; ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>