<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// =========================================================
//  ASTRAZIONE BOTTONE "Aggiungi nuova bacheca" 
// =========================================================
function getBottoneNuovaBacheca(): string
{
    return "<a onclick='aggiungiBacheca()' class='btn-aggiungi' style='cursor: pointer;'>
        <img src='images/add.png' alt='Aggiungi' style='vertical-align: middle;'> <strong>Aggiungi una nuova bacheca</strong>
    </a>";
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA LOGICA DEI FILTRI NELLA SIDEBAR
// =========================================================
function renderFiltroSidebar($pdo, $vista_corrente, $tab_corrente, $bacheca, $owner)
{
    if ($vista_corrente === 'dettaglio') {
        // Campi nascosti comuni a tutti i tab di dettaglio per non perdere il contesto
        $campi_base = [
            ['tipo' => 'hidden', 'name' => 'vista',   'value' => 'dettaglio', 'label' => ''],
            ['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $bacheca, 'label' => ''],
            ['tipo' => 'hidden', 'name' => 'owner',   'value' => $owner, 'label' => ''],
            ['tipo' => 'hidden', 'name' => 'tab',     'value' => $tab_corrente, 'label' => ''],
        ];

        // 1. FILTRO TAB UTENTI
        if ($tab_corrente === 'utenti') {
            $filtro_config = [
                'campi' => array_merge($campi_base, [
                    ['tipo' => 'text',   'name' => 'utente',  'label' => 'Nickname'],
                    ['tipo' => 'text',   'name' => 'nome',    'label' => 'Nome'],
                    ['tipo' => 'text',   'name' => 'cognome', 'label' => 'Cognome'],
                    ['tipo' => 'date',   'name' => 'data_nascita', 'label' => 'Nati dal'],
                ])
            ];
            include 'filter.php';

            // 2. FILTRO TAB FILE
        } elseif ($tab_corrente === 'file') {
            // Interrogiamo il DB per trovare il range esatto di dimensioni di questa bacheca
            $stmtRange = $pdo->prepare("
                SELECT MIN(fm.dimensione) as min_dim, MAX(fm.dimensione) as max_dim 
                FROM FilePubblicatoBacheca fb
                JOIN FileMultimediale fm ON fm.numero = fb.file
                WHERE fb.nomeBacheca = :bacheca AND fb.codUtente = :owner
            ");
            $stmtRange->execute([':bacheca' => $bacheca, ':owner' => $owner]);
            $rangeDati = $stmtRange->fetch(PDO::FETCH_ASSOC);

            // Calcoliamo il minimo e il massimo arrotondando per eccesso/difetto
            $minSize = isset($rangeDati['min_dim']) ? floor($rangeDati['min_dim']) : 0;
            $maxSize = isset($rangeDati['max_dim']) ? ceil($rangeDati['max_dim']) : 100;
            if ($minSize == $maxSize) $minSize = 0; // Protezione se c'è un solo file

            // Recuperiamo i valori attualmente selezionati dall'URL, altrimenti usiamo i default
            $currentMin = (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') ? (int)$_GET['dimensione_min'] : $minSize;
            $currentMax = (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') ? (int)$_GET['dimensione_max'] : $maxSize;

            $filtro_config = [
                'campi' => array_merge($campi_base, [
                    ['tipo' => 'text',   'name' => 'file',              'label' => 'Nome'],
                    ['tipo' => 'text',   'name' => 'proprietario_file', 'label' => 'Nickname Proprietario'],
                    [
                        'tipo' => 'multi-range',
                        'name_min' => 'dimensione_min',
                        'name_max' => 'dimensione_max',
                        'label' => 'Dimensione',
                        'min' => $minSize,
                        'max' => $maxSize,
                        'value_min' => $currentMin,
                        'value_max' => $currentMax
                    ],
                ])
            ];
            include 'filter.php';

            // 3. MESSAGGIO PER TAB INFO (Nessun filtro disponibile)
        } else {
            echo '<div id="filtro" class="filter-empty">';
            echo '    <p>Nessun filtro disponibile per questa sezione</p>';
            echo '</div>';
        }
    } else {
        // 4. VISTA NORMALE (Elenco Principale Bacheche)
        $filtro_config = [
            'campi' => [
                ['tipo' => 'text', 'name' => 'titolo',       'label' => 'Nome'],
                ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Nickname Proprietario'],
                ['tipo' => 'date', 'name' => 'data',         'label' => 'Creata dal'],
            ]
        ];
        include 'filter.php';
    }
}

// =========================================================
//  FUNZIONE PER RECUPERARE UTENTI (AGGIORNATA CON PAGINAZIONE E CORONA)
// =========================================================
function getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort = 'u.nickname', $sort_dir = 'ASC', $limit = 20, $start_from = 0)
{
    $baseSql = "
        FROM UtenteAutorizzatoBacheca uab
        JOIN Utente u ON u.codice = uab.utenteAutorizzato
        WHERE uab.nomeBacheca = :bacheca AND uab.codUtente = :owner
    ";

    $params = [
        ':bacheca' => $bacheca,
        ':owner' => $owner
    ];

    $whereSql = "";
    if (!empty($_GET['utente'])) {
        $whereSql .= " AND u.nickname LIKE :utente";
        $params[':utente'] = '%' . $_GET['utente'] . '%';
    }
    if (!empty($_GET['nome'])) {
        $whereSql .= " AND u.nome LIKE :nome";
        $params[':nome'] = '%' . $_GET['nome'] . '%';
    }
    if (!empty($_GET['cognome'])) {
        $whereSql .= " AND u.cognome LIKE :cognome";
        $params[':cognome'] = '%' . $_GET['cognome'] . '%';
    }
    if (!empty($_GET['data_nascita'])) {
        $whereSql .= " AND u.dataNascita >= :data_nascita";
        $params[':data_nascita'] = $_GET['data_nascita'];
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) " . $baseSql . $whereSql);
    $stmtCount->execute($params);
    $totale = $stmtCount->fetchColumn();

    $sql = "SELECT u.codice, u.nickname, u.nome, u.cognome, u.dataNascita " . $baseSql . $whereSql;
    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $datiUtenti = [];
    foreach ($utenti as $u) {
        $isOwner = ((int)$u['codice'] === (int)$owner);

        $azioni = !$isOwner
            ? "<div style='text-align:center;'>
                <span title='Elimina' class='btn-azione' onclick=\"rimuoviAutorizzato('{$bEnc}', {$owner}, {$u['codice']})\">
                    <img src='images/trash.png' alt='Elimina'>
                </span>
               </div>"
            : "<div style='text-align:center;'><small style='color:gray;'>Proprietario</small></div>";

        $user_link = "utenti.php?utente=" . urlencode($u['codice']);
        $htmlNickname = "<a href='" . htmlspecialchars($user_link) .  "'>" . htmlspecialchars($u['nickname']) . "</a>";

        // --- SE L'UTENTE È IL PROPRIETARIO, AGGIUNGIAMO LA CORONA ALLA SINISTRA ---
        if ($isOwner) {
            $htmlNickname = "<img src='images/crown.png' alt='Owner' style='width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;'>" . $htmlNickname;
        }

        $datiUtenti[] = [
            'Nickname' => $htmlNickname,
            'Nome' => $u['nome'],
            'Cognome' => $u['cognome'],
            'Data Nascita' => $u['dataNascita'],
            'Azioni' => $azioni
        ];
    }
    return [$datiUtenti, $totale];
}

// =========================================================
//  FUNZIONE PER RECUPERARE FILE (AGGIORNATA CON PAGINAZIONE)
// =========================================================
function getFileBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort = 'fm.titolo', $sort_dir = 'ASC', $limit = 20, $start_from = 0)
{
    $baseSql = "
        FROM FilePubblicatoBacheca fb
        JOIN FileMultimediale fm ON fm.numero = fb.file
        JOIN Utente u ON u.codice = fm.caricatoDa
        WHERE fb.nomeBacheca = :bacheca AND fb.codUtente = :owner
    ";

    $params = [
        ':bacheca' => $bacheca,
        ':owner' => $owner
    ];

    $whereSql = "";
    if (!empty($_GET['file'])) {
        $whereSql .= " AND fm.titolo LIKE :file";
        $params[':file'] = '%' . $_GET['file'] . '%';
    }
    if (!empty($_GET['proprietario_file'])) {
        $whereSql .= " AND u.nickname LIKE :proprietario_file";
        $params[':proprietario_file'] = '%' . $_GET['proprietario_file'] . '%';
    }
    if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
        $whereSql .= " AND fm.dimensione >= :dimensione_min";
        $params[':dimensione_min'] = (float)$_GET['dimensione_min'];
    }
    if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
        $whereSql .= " AND fm.dimensione <= :dimensione_max";
        $params[':dimensione_max'] = (float)$_GET['dimensione_max'];
    }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) " . $baseSql . $whereSql);
    $stmtCount->execute($params);
    $totale = $stmtCount->fetchColumn();

    $sql = "SELECT fm.numero, fm.titolo, u.codice as caricatoDa, u.nickname, fm.dimensione, fm.URL, fm.tipo " . $baseSql . $whereSql;
    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $file = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $icon_types = [
        'immagine' => 'images/image.png',
        'video' => 'images/video.png',
        'audio' => 'images/headphones.png',
        'default' => 'images/document.png'
    ];

    $datiFile = [];
    foreach ($file as $f) {
        $tipoStr = strtolower($f['tipo']);
        $icon_path = $icon_types[$tipoStr] ?? $icon_types['default'];
        $title = preg_replace('/\d{3}$/', '', $f['titolo']);

        $htmlFile = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($tipoStr) . "'>";
        $htmlFile .= "<a href='" . htmlspecialchars($f['URL']) . "' target='_blank'>" . htmlspecialchars($title) . "</a>";

        $owner_link = "utenti.php?utente=" . urlencode($f['caricatoDa']);
        $htmlOwner = "<a href='" . htmlspecialchars($owner_link) .  "'>" . htmlspecialchars($f['nickname']) . "</a>";

        $azioni = "<div style='text-align:center;'>
            <span title='Elimina' class='btn-azione' onclick=\"rimuoviFile('{$bEnc}', {$owner}, {$f['numero']})\">
                <img src='images/trash.png' alt='Elimina'>
            </span>
        </div>";

        $datiFile[] = [
            'File' => $htmlFile,
            'Proprietario' => $htmlOwner,
            'Dimensione' => formatFileSizeHtml((int)$f['dimensione']),
            'Azioni' => $azioni
        ];
    }
    return [$datiFile, $totale];
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA VISTA DETTAGLIO (A TAB E PAGINATA)
// =========================================================
function renderDettaglioBacheca($pdo, $bacheca, $owner, $bEnc, $isAjax = false)
{
    $activeTab = $_GET['tab'] ?? 'info';
    $validTabs = ['info', 'utenti', 'file'];
    if (!in_array($activeTab, $validTabs)) $activeTab = 'info';

    $baseParams = ['vista' => 'dettaglio', 'bacheca' => $bacheca, 'owner' => $owner];
    $urlInfo   = '?' . http_build_query(array_merge($baseParams, ['tab' => 'info']));
    $urlUtenti = '?' . http_build_query(array_merge($baseParams, ['tab' => 'utenti']));
    $urlFile   = '?' . http_build_query(array_merge($baseParams, ['tab' => 'file']));

    $recordsPerPage = 20;
    list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

    if (!$isAjax) {
        echo '<div id="ajax-results">';
    }

    echo "<h2>" . htmlspecialchars($bacheca) . "</h2>";

    $btnNuovaBacheca = getBottoneNuovaBacheca();

    // Contenitore Flex per avere Tab a sinistra e Bottone a destra
    // Il bordo inferiore è stato spostato qui per dare uniformità
    echo "
    <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-soft); margin-bottom: 15px;'>
        <div class='bacheca-tabs' style='border-bottom: none; margin-bottom: 0;'>
            <a href='{$urlInfo}' class='" . ($activeTab === 'info' ? 'active' : '') . "'>Informazioni</a>
            <a href='{$urlUtenti}' class='" . ($activeTab === 'utenti' ? 'active' : '') . "'>Dettaglio Utenti</a>
            <a href='{$urlFile}' class='" . ($activeTab === 'file' ? 'active' : '') . "'>Dettaglio File</a>
        </div>
        <div style='padding-bottom: 8px;'>
            {$btnNuovaBacheca}
        </div>
    </div>";

    if ($activeTab === 'info') {
        $stmtBacheca = $pdo->prepare("
            SELECT b.dataCreazione, u.nickname 
            FROM Bacheca b
            JOIN Utente u ON b.codiceUtente = u.codice
            WHERE b.nome = :nome AND b.codiceUtente = :owner
        ");
        $stmtBacheca->execute([':nome' => $bacheca, ':owner' => $owner]);
        $datiBachecaDb = $stmtBacheca->fetch(PDO::FETCH_ASSOC);

        if ($datiBachecaDb) {
            $dataFormattata = !empty($datiBachecaDb['dataCreazione']) ? (function_exists('formattaData') ? formattaData($datiBachecaDb['dataCreazione']) : date('d/m/Y', strtotime($datiBachecaDb['dataCreazione']))) : "";

            echo "<div class='tab-info-card'>";
            echo "<h3 style='margin-top: 0; color: var(--primary-dark);'>Dettagli Bacheca</h3>";
            if (!empty($dataFormattata)) {
                echo "<p style='font-size: 1.1rem;'><strong>Data di Creazione:</strong> " . htmlspecialchars($dataFormattata) . "</p>";
                $linkOwner = "utenti.php?utente=" . urlencode($owner);
                echo "<p style='font-size: 1.1rem; margin-bottom: 0;'><strong>Creata da:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($datiBachecaDb['nickname']) . "</a></p>";
            }
            echo "</div>";
        }
    } elseif ($activeTab === 'utenti') {
        $allowed_sorts_u = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data_nascita' => 'u.dataNascita'];
        list($sort_col_u, $sort_dir_u, $sql_sort_u) = getParametriOrdinamento($allowed_sorts_u, 'nickname', 'ASC');

        list($datiUtenti, $countUtenti) = getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_u, $sort_dir_u, $limit, $start_from);
        $numero_pagine = getNumberOfPages($countUtenti, $limit);

        echo "<div class='table-top-bar'>";
        echo "<p style='margin: 0;'>Utenti autorizzati nella bacheca: <strong>{$countUtenti}</strong></p>";
        echo "<a onclick=\"aggiungiAutorizzato('{$bEnc}', {$owner})\" class='btn-aggiungi'>
            <img src='images/add.png' alt='Aggiungi'> <strong>Aggiungi utente</strong>
        </a>";
        echo "</div>";

        $_GET['tab'] = 'utenti';
        $customHeaders_u = generaIntestazioniOrdinabili(['Nickname' => 'nickname', 'Nome' => 'nome', 'Cognome' => 'cognome', 'Data Nascita' => 'data_nascita'], $sort_col_u, $sort_dir_u);

        echo '<div class="table-container">';
        stampaTabella($datiUtenti, ['Nickname', 'Azioni'], $customHeaders_u);
        echo '</div>';

        echo getPagesNav($np, $numero_pagine, 1);
    } elseif ($activeTab === 'file') {
        $allowed_sorts_f = ['file' => 'fm.titolo', 'proprietario' => 'u.nickname', 'dimensione' => 'fm.dimensione'];
        list($sort_col_f, $sort_dir_f, $sql_sort_f) = getParametriOrdinamento($allowed_sorts_f, 'file', 'ASC');

        list($datiFile, $countFile) = getFileBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_f, $sort_dir_f, $limit, $start_from);
        $numero_pagine = getNumberOfPages($countFile, $limit);

        echo "<div class='table-top-bar'>";
        echo "<p style='margin: 0;'>File pubblicati nella bacheca: <strong>{$countFile}</strong></p>";
        echo "<a onclick=\"aggiungiFile('{$bEnc}', {$owner})\" class='btn-aggiungi'>
            <img src='images/add.png' alt='Aggiungi'> <strong>Aggiungi file</strong>
        </a>";
        echo "</div>";

        $_GET['tab'] = 'file';
        $customHeaders_f = generaIntestazioniOrdinabili(['File' => 'file', 'Proprietario' => 'proprietario', 'Dimensione' => 'dimensione'], $sort_col_f, $sort_dir_f);
        echo '<div class="table-container">';
        stampaTabella($datiFile, ['File', 'Proprietario', 'Dimensione', 'Azioni'], $customHeaders_f);
        echo '</div>';

        echo getPagesNav($np, $numero_pagine, 1);
    }

    if (!$isAjax) {
        echo '</div>';
    }
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA VISTA PRINCIPALE (PAGINAZIONE SOLO IN BASSO)
// =========================================================
function renderElencoBacheche($pdo, $isAjax)
{
    $recordsPerPage = 20;
    list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

    $where  = [];
    $params = [];

    if (!empty($_GET['titolo'])) {
        $where[]           = "b.nome LIKE :titolo";
        $params[':titolo'] = '%' . $_GET['titolo'] . '%';
    }
    if (!empty($_GET['proprietario'])) {
        $where[]                 = "u.nickname LIKE :proprietario";
        $params[':proprietario'] = '%' . $_GET['proprietario'] . '%';
    }
    if (!empty($_GET['data'])) {
        $where[]         = "DATE(b.dataCreazione) >= :data";
        $params[':data'] = $_GET['data'];
    }

    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
        'nome'         => 'b.nome',
        'data'         => 'b.dataCreazione',
        'proprietario' => 'u.nickname',
    ], 'data', 'DESC');

    $tabella_count = "Bacheca b LEFT JOIN Utente u ON u.codice = b.codiceUtente";
    $totaleRisultati = getNumberOfRecords($pdo, $tabella_count, $where, $params);
    $numero_pagine = getNumberOfPages($totaleRisultati, $limit);

    $sql = "
        SELECT
            b.codiceUtente AS 'owner',
            b.nome AS 'Nome Bacheca',
            u.nickname AS 'Proprietario',
            b.dataCreazione AS 'Data Creazione'
        FROM Bacheca b
        LEFT JOIN UtenteAutorizzatoBacheca uab ON uab.codUtente = b.codiceUtente AND uab.nomeBacheca = b.nome
        LEFT JOIN FilePubblicatoBacheca f ON f.codUtente = b.codiceUtente AND f.nomeBacheca = b.nome
        LEFT JOIN Utente u ON u.codice = b.codiceUtente
    ";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);

    $sql .= " GROUP BY b.codiceUtente, u.nickname, b.nome, b.dataCreazione ORDER BY {$sql_sort} {$sort_dir}";
    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $chiave => $valore) {
        $stmt->bindValue($chiave, $valore);
    }
    $stmt->execute();
    $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$isAjax) {
        echo '<div id="ajax-results">';
    }

    echo "<div class='table-top-bar'>";
    echo "<p class='info-risultati' style='margin: 0;'>Trovate <strong>$totaleRisultati</strong> bacheche <strong>($recordsPerPage per pagina)</strong></p>";
    // Sostituito con la funzione helper astratta
    echo getBottoneNuovaBacheca();
    echo "</div>";

    if (!empty($righe)) {
        $datiBacheche = [];

        foreach ($righe as $riga) {
            $p = $_GET;
            $p['vista']   = 'dettaglio';
            $p['bacheca'] = $riga['Nome Bacheca'];
            $p['owner']   = $riga['owner'];
            $htmlNome = "<a href='bacheche.php?" . http_build_query($p) . "'>" . htmlspecialchars($riga['Nome Bacheca']) . "</a>";

            $proprietarioLink = "utenti.php?utente=" . urlencode($riga['owner']);
            $htmlProprietario = "<a href='" . htmlspecialchars($proprietarioLink) . "'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

            $nomeEnc  = htmlspecialchars(addslashes($riga['Nome Bacheca']), ENT_QUOTES);
            $ownerEnc = (int) $riga['owner'];
            $azioni = "<div style='text-align:center; white-space:nowrap;'>
                <span title='Modifica' class='btn-azione' onclick=\"modificaBacheca('{$nomeEnc}', {$ownerEnc})\">
                    <img src='images/edit.png' alt='Modifica'>
                </span>
                <span title='Elimina' class='btn-azione' onclick=\"eliminaBacheca('{$nomeEnc}', {$ownerEnc})\">
                    <img src='images/trash.png' alt='Elimina'>
                </span>
            </div>";

            $datiBacheche[] = [
                'Nome Bacheca' => $htmlNome,
                'Proprietario' => $htmlProprietario,
                'Data Creazione' => $riga['Data Creazione'],
                'Azioni' => $azioni
            ];
        }

        $customHeaders = generaIntestazioniOrdinabili([
            'Nome Bacheca'   => 'nome',
            'Proprietario'   => 'proprietario',
            'Data Creazione' => 'data'
        ], $sort_col, $sort_dir);

        echo '<div class="table-container">';
        stampaTabella($datiBacheche, ['Proprietario', 'Nome Bacheca', 'Azioni'], $customHeaders);
        echo '</div>';

        echo getPagesNav($np, $numero_pagine, 1);
    }

    if (!$isAjax) {
        echo '</div>';
    }
}

// =========================================================
// GESTIONE CORPO DELLA PAGINA E ASYNC/AJAX ROUTING
// =========================================================
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if (!$isAjax):
?>
    <!DOCTYPE html>
    <html lang="it">

    <head>
        <?php include 'head.html'; ?>
        <title>SalMeet</title>
        <script src="./js/bachecheCRUD.js" defer></script>
    </head>

    <body>

        <header>
            <h1 id="hcod1">Bacheche</h1>
        </header>

        <div class="main-container">
            <aside class="sidebar">
                <?php include 'nav.html'; ?>

                <?php
                // Catturiamo i parametri dall'URL
                $vista_corrente = $_GET['vista'] ?? '';
                $tab_corrente   = $_GET['tab'] ?? 'info';
                $bacheca_val    = $_GET['bacheca'] ?? '';
                $owner_val      = $_GET['owner'] ?? '';

                renderFiltroSidebar($pdo, $vista_corrente, $tab_corrente, $bacheca_val, $owner_val);
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php
            // =========================================================
            // ROUTER DELLE VISTE
            // =========================================================
            if (!empty($_GET['vista']) && !empty($_GET['bacheca']) && !empty($_GET['owner'])) {
                $vista   = $_GET['vista'];
                $bacheca = $_GET['bacheca'];
                $owner   = $_GET['owner'];
                $bEnc    = htmlspecialchars(addslashes($bacheca), ENT_QUOTES);

                if ($vista === 'dettaglio') {
                    renderDettaglioBacheca($pdo, $bacheca, $owner, $bEnc, $isAjax);
                } else {
                    echo "<div style='margin-bottom: 25px;'></div>";
                }
            } else {
                renderElencoBacheche($pdo, $isAjax);
            }
            ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>