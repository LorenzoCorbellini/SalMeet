<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// =========================================================
//  ASTRAZIONE BOTTONI AZIONE BACHECA
// =========================================================
function getBottoneNuovaBacheca(): string
{
    return "<a onclick='aggiungiBacheca()' class='btn-aggiungi'>
        <img src='images/add.png' alt='Aggiungi' class='btn-img-align'> <strong>Aggiungi una nuova bacheca</strong>
    </a>";
}

function getBottoneRinominaBacheca($bEnc, $owner, $isIcona = false): string
{
    if ($isIcona) {
        return "<span title='Rinomina' class='btn-azione' onclick=\"rinominaBacheca('{$bEnc}', {$owner})\">
                    <img src='images/edit.png' alt='Rinomina'>
                </span>";
    }
    return "<a onclick=\"rinominaBacheca('{$bEnc}', {$owner})\" class='btn-aggiungi'>
        <img src='images/edit.png' alt='Rinomina' class='btn-img-align'> <strong>Rinomina</strong>
    </a>";
}

function getBottoneEliminaBacheca($bEnc, $owner, $ownerNickname, $isIcona = false): string
{
    $ownerNicknameEnc = htmlspecialchars(addslashes($ownerNickname), ENT_QUOTES);
    if ($isIcona) {
        return "<span title='Elimina' class='btn-azione' onclick=\"eliminaBacheca('{$bEnc}', {$owner}, '{$ownerNicknameEnc}')\">
                    <img src='images/trash.png' alt='Elimina'>
                </span>";
    }
    return "<a onclick=\"eliminaBacheca('{$bEnc}', {$owner}, '{$ownerNicknameEnc}')\" class='btn-aggiungi'>
        <img src='images/trash.png' alt='Elimina' class='btn-img-align'> <strong>Elimina</strong>
    </a>";
}

function getBottoneAggiungiUtente($bEnc, $owner): string
{
    return "<a onclick=\"aggiungiAutorizzato('{$bEnc}', {$owner})\" class='btn-aggiungi'>
        <img src='images/add.png' alt='Aggiungi' class='btn-img-align'> <strong>Aggiungi utente</strong>
    </a>";
}

function getBottoneAggiungiFile($bEnc, $owner): string
{
    return "<a onclick=\"aggiungiFile('{$bEnc}', {$owner})\" class='btn-aggiungi'>
        <img src='images/add.png' alt='Aggiungi' class='btn-img-align'> <strong>Aggiungi file</strong>
    </a>";
}

// =========================================================
//  ASTRAZIONE ROUTING E GESTIONE PARAMETRI GET
// =========================================================

/**
 * Recupera e normalizza i parametri GET principali per la vista.
 */
function getParametriRichiestaBacheche(): array
{
    return [
        'vista'   => $_GET['vista'] ?? '',
        'tab'     => $_GET['tab'] ?? 'info',
        'bacheca' => $_GET['bacheca'] ?? '',
        'owner'   => $_GET['owner'] ?? ''
    ];
}

/**
 * Gestisce il routing caricando la vista appropriata (Dettaglio o Elenco Generale).
 */
function gestisciRoutingBacheche($pdo, $isAjax, $params)
{
    if (!empty($params['vista']) && !empty($params['bacheca']) && !empty($params['owner'])) {
        $bEnc = htmlspecialchars(addslashes($params['bacheca']), ENT_QUOTES);

        if ($params['vista'] === 'dettaglio') {
            renderDettaglioBacheca($pdo, $params['bacheca'], $params['owner'], $bEnc, $isAjax);
        } else {
            echo "<div class='routing-error-msg'>Errore! La pagina richiesta non esiste.</div>";
        }
    } else {
        renderElencoBacheche($pdo, $isAjax);
    }
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA LOGICA DEI FILTRI NELLA SIDEBAR
// =========================================================
function renderFiltroSidebar($pdo, $vista_corrente, $tab_corrente, $bacheca, $owner)
{

    $filtro_config = null;

    if ($vista_corrente === 'dettaglio') {
        if ($tab_corrente === 'utenti') {
            $filtro_config = getFiltroBachecheUtenti($bacheca, $owner, $tab_corrente);
        } elseif ($tab_corrente === 'file') {
            $filtro_config = getFiltroBachecheFile($pdo, $bacheca, $owner, $tab_corrente);
        }
    } else {
        // Vista generale elenco bacheche
        $filtro_config = getFiltroBachecheGenerale();
    }

    // Se la configurazione del filtro esiste, la stampa, altrimenti mostra il box vuoto
    if ($filtro_config !== null) {
        include 'filter.php';
    } else {
        echo '<div id="filtro" class="filter-empty">';
        echo '    <p>Nessun filtro disponibile per questa sezione</p>';
        echo '</div>';
    }
}

// =========================================================
//  FUNZIONE PER RECUPERARE UTENTI 
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

        $nicknameJS = htmlspecialchars(addslashes($u['nickname']), ENT_QUOTES);

        $azioni = !$isOwner
            ? "<div class='cell-center'>
                <span title='Elimina' class='btn-azione' onclick=\"rimuoviAutorizzato('{$bEnc}', {$owner}, {$u['codice']}, '{$nicknameJS}')\">
                    <img src='images/trash.png' alt='Elimina'>
                </span>
               </div>"
            : "<div class='cell-center'><small class='text-gray-small'>Proprietario</small></div>";

        $user_link = "utenti.php?utente=" . urlencode($u['codice']);
        $htmlNickname = "<a href='" . htmlspecialchars($user_link) .  "'>" . htmlspecialchars($u['nickname']) . "</a>";

        if ($isOwner) {
            $htmlNickname = "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'>" . $htmlNickname;
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
//  FUNZIONE PER RECUPERARE FILE 
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

        $titleJS = htmlspecialchars(addslashes($title), ENT_QUOTES);
        $caricatoDaJS = htmlspecialchars(addslashes($f['nickname']), ENT_QUOTES);

        $htmlFile = "<div class='file-cell-wrapper'>";
        $htmlFile .= "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($tipoStr) . "'>";
        $htmlFile .= "<a href='" . htmlspecialchars($f['URL']) . "' target='_blank'>" . htmlspecialchars($title) . "</a>";
        $htmlFile .= "</div>";

        $owner_link = "utenti.php?utente=" . urlencode($f['caricatoDa']);
        $htmlOwner = "<a href='" . htmlspecialchars($owner_link) .  "'>" . htmlspecialchars($f['nickname']) . "</a>";

        $azioni = "<div class='cell-center'>
            <span title='Elimina' class='btn-azione' onclick=\"rimuoviFile('{$bEnc}', {$owner}, {$f['numero']}, '{$titleJS}', '{$caricatoDaJS}')\">
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

    echo "
    <div class='detail-tabs-header'>
        <div class='bacheca-tabs tabs-reset'>
            <a href='{$urlInfo}' class='" . ($activeTab === 'info' ? 'active' : '') . "'>Informazioni</a>
            <a href='{$urlUtenti}' class='" . ($activeTab === 'utenti' ? 'active' : '') . "'>Dettaglio Utenti</a>
            <a href='{$urlFile}' class='" . ($activeTab === 'file' ? 'active' : '') . "'>Dettaglio File</a>
        </div>
    </div>";

    if ($activeTab === 'info') {
        $stmtBacheca = $pdo->prepare("
            SELECT b.dataCreazione, u.nickname,
                   (SELECT COUNT(*) FROM UtenteAutorizzatoBacheca uab WHERE uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente) AS total_utenti,
                   (SELECT COUNT(*) FROM FilePubblicatoBacheca fpb WHERE fpb.nomeBacheca = b.nome AND fpb.codUtente = b.codiceUtente) AS total_file
            FROM Bacheca b
            JOIN Utente u ON b.codiceUtente = u.codice
            WHERE b.nome = :nome AND b.codiceUtente = :owner
        ");
        $stmtBacheca->execute([':nome' => $bacheca, ':owner' => $owner]);
        $datiBachecaDb = $stmtBacheca->fetch(PDO::FETCH_ASSOC);

        if ($datiBachecaDb) {
            $dataFormattata = !empty($datiBachecaDb['dataCreazione']) ? (function_exists('formattaData') ? formattaData($datiBachecaDb['dataCreazione']) : date('d/m/Y', strtotime($datiBachecaDb['dataCreazione']))) : "";

            echo "<div class='tab-info-card'>";
            echo "<h3 class='info-card-title'>Dettagli Bacheca</h3>";
            if (!empty($dataFormattata)) {
                echo "<p class='info-card-text'><strong>Data di Creazione:</strong> " . htmlspecialchars($dataFormattata) . "</p>";
                $linkOwner = "utenti.php?utente=" . urlencode($owner);
                echo "<p class='info-card-text'><strong>Proprietario:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($datiBachecaDb['nickname']) . "</a></p>";
                echo "<p class='info-card-text'><strong>Utenti autorizzati:</strong> <a href='{$urlUtenti}'>" . (int)$datiBachecaDb['total_utenti'] . "</a></p>";
                echo "<p class='info-card-text-last'><strong>File caricati:</strong> <a href='{$urlFile}'>" . (int)$datiBachecaDb['total_file'] . "</a></p>";
            }
            echo "</div>";

            $btnRinomina = getBottoneRinominaBacheca($bEnc, $owner);
            $btnElimina  = getBottoneEliminaBacheca($bEnc, $owner, $datiBachecaDb['nickname']);

            echo "<div class='info-card-actions'>
                    {$btnRinomina}
                    {$btnElimina}
                  </div>";
        }
    } elseif ($activeTab === 'utenti') {
        $allowed_sorts_u = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data_nascita' => 'u.dataNascita'];
        list($sort_col_u, $sort_dir_u, $sql_sort_u) = getParametriOrdinamento($allowed_sorts_u, 'nickname', 'ASC');

        list($datiUtenti, $countUtenti) = getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_u, $sort_dir_u, $limit, $start_from);
        $numero_pagine = getNumberOfPages($countUtenti, $limit);

        echo "<div class='table-top-bar'>";
        echo "<p class='zero-margin'>Utenti trovati nella bacheca: <strong>{$countUtenti}</strong></p>";
        echo getBottoneAggiungiUtente($bEnc, $owner);
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
        echo "<p class='zero-margin'>File trovati nella bacheca: <strong>{$countFile}</strong></p>";
        echo getBottoneAggiungiFile($bEnc, $owner);
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
//  FUNZIONE PER RENDERIZZARE LA VISTA PRINCIPALE
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
    echo "<p class='info-risultati zero-margin'>Trovate <strong>$totaleRisultati</strong> bacheche <strong>($recordsPerPage per pagina)</strong></p>";
    echo getBottoneNuovaBacheca();
    echo "</div>";

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
        $proprietarioEnc = htmlspecialchars(addslashes($riga['Proprietario']), ENT_QUOTES);

        $btnRinominaIcona = getBottoneRinominaBacheca($nomeEnc, $ownerEnc, true);
        $btnEliminaIcona  = getBottoneEliminaBacheca($nomeEnc, $ownerEnc, $proprietarioEnc, true);

        $azioni = "<div class='actions-cell-nowrap'>
            {$btnRinominaIcona}
            {$btnEliminaIcona}
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

    if (!empty($righe)) {
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
                $req_params = getParametriRichiestaBacheche();
                renderFiltroSidebar($pdo, $req_params['vista'], $req_params['tab'], $req_params['bacheca'], $req_params['owner']);
                ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php
            $req_params = getParametriRichiestaBacheche();
            gestisciRoutingBacheche($pdo, $isAjax, $req_params);
            ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>

        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>