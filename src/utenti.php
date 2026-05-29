<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// Verifica se la richiesta arriva tramite AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// =========================================================
//  1. GESTIONE FILTRI SIDEBAR
// =========================================================
function renderFiltroSidebarUtenti($pdo, $idUtente, $tab)
{
    if (empty($idUtente) || !is_numeric($idUtente)) {
        $filtro_config = getFiltroConfig('utenti');
        include 'filter.php';
        return;
    }

    if ($tab === 'info') {
        $filtro_config = getFiltroConfig('vuoto');
        echo '<div id="filtro" class="filter-empty"><p>' . htmlspecialchars($filtro_config['messaggio']) . '</p></div>';
    } elseif ($tab === 'gruppi') {
        $filtro_config = getFiltroConfig('gruppi', ['utente' => $idUtente, 'tab' => 'gruppi']);
        include 'filter.php';
    } elseif ($tab === 'bacheche') {
        $filtro_config = getFiltroConfig('bacheche', ['utente' => $idUtente, 'tab' => 'bacheche']);

        // Rimuoviamo i campi del nome e cognome proprietario che non servono nella tab corrente
        if (isset($filtro_config['campi'])) {
            $filtro_config['campi'] = array_filter($filtro_config['campi'], function ($c) {
                return !in_array($c['name'] ?? '', ['proprietario_nome', 'proprietario_cognome']);
            });
        }
        include 'filter.php';
    } elseif ($tab === 'file') {
        $stmtRange = $pdo->prepare("SELECT MIN(dimensione) as min_dim, MAX(dimensione) as max_dim FROM FileMultimediale WHERE caricatoDa = :owner");
        $stmtRange->execute([':owner' => $idUtente]);
        $range = $stmtRange->fetch(PDO::FETCH_ASSOC);

        $minSize = isset($range['min_dim']) ? floor($range['min_dim']) : 0;
        $maxSize = isset($range['max_dim']) ? ceil($range['max_dim']) : 100;

        $filtro_config = getFiltroConfig('file', [
            'utente' => $idUtente,
            'tab' => 'file',
            'min_size' => $minSize,
            'max_size' => $maxSize ?: 100
        ]);
        
        // Rimuoviamo il campo del proprietario visto che i file mostrati sono già filtrati per l'utente corrente
        if (isset($filtro_config['campi'])) {
            $filtro_config['campi'] = array_filter($filtro_config['campi'], function ($c) {
                return ($c['name'] ?? '') !== 'proprietario_file';
            });
        }
        include 'filter.php';
    }
}

// =========================================================
//  2. VISTA DETTAGLIO UTENTE (TAB)
// =========================================================
function renderDettaglioUtente($pdo, $idUtente, $tab_corrente, $isAjax)
{
    $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
    $stmtUtente->execute([':codice' => $idUtente]);
    $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

    if (!$infoUtente) {
        echo "<p class='info-risultati'>Errore: l'utente richiesto non è presente a sistema.</p>";
        return;
    }

    list($limit, $np, $start_from) = getPaginationParams(20);

    if (!$isAjax) echo '<div id="ajax-results">';

    echo "<a href='utenti.php' onclick='history.back(); return false;' class='btn-indietro'>Torna alla pagina precedente</a>";

    echo "<h2>Profilo Utente: " . htmlspecialchars($infoUtente['nickname']) . "</h2>";

    $base = "?utente=" . urlencode($idUtente) . "&tab=";
    echo "<div class='detail-tabs-header'><div class='bacheca-tabs tabs-reset'>
            <a href='{$base}info' class='" . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>
            <a href='{$base}gruppi' class='" . ($tab_corrente === 'gruppi' ? 'active' : '') . "'>Gruppi</a>
            <a href='{$base}bacheche' class='" . ($tab_corrente === 'bacheche' ? 'active' : '') . "'>Bacheche</a>
            <a href='{$base}file' class='" . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>
          </div></div>";

    if ($tab_corrente === 'info') {
        $numFile = getNumberOfRecords($pdo, "FileMultimediale", ["caricatoDa = :c"], [':c' => $idUtente]);
        $numGruppi = getNumberOfRecords($pdo, "UtenteAutorizzatoGruppo", ["codUtente = :c"], [':c' => $idUtente]);
        $numBacheche = getNumberOfRecords($pdo, "UtenteAutorizzatoBacheca", ["utenteAutorizzato = :c", "autorizzato = 1"], [':c' => $idUtente]);
        $dataFormattata = formattaData($infoUtente['dataNascita']);

        echo "<div class='tab-info-card'>
                <p class='info-card-text'><strong>Nickname:</strong> " . htmlspecialchars($infoUtente['nickname']) . "</p>
                <p class='info-card-text'><strong>Nome:</strong> " . htmlspecialchars($infoUtente['nome']) . "</p>
                <p class='info-card-text'><strong>Cognome:</strong> " . htmlspecialchars($infoUtente['cognome']) . "</p>
                <p class='info-card-text'><strong>Data di Nascita:</strong> {$dataFormattata}</p>
                <p class='info-card-text'><strong>Numero di gruppi a cui appartiene:</strong> {$numGruppi}</p>
                <p class='info-card-text'><strong>Numero di bacheche a cui appartiene:</strong> {$numBacheche}</p>
                <p class='info-card-text-last'><strong>Numero di file caricati:</strong> {$numFile}</p>
              </div>";
    } elseif ($tab_corrente === 'gruppi') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'g.nome', 'proprietario' => 'u.nickname', 'data' => 'g.dataCreazione'], 'data', 'DESC');
        $where = ["uag.codUtente = :c"];
        $params = [':c' => $idUtente];

        if (!empty($_GET['nome'])) {
            $where[] = "g.nome LIKE :nome";
            $params[':nome'] = '%' . $_GET['nome'] . '%';
        }
        if (!empty($_GET['proprietario'])) {
            $where[] = "u.nickname LIKE :prop";
            $params[':prop'] = '%' . $_GET['proprietario'] . '%';
        }
        if (!empty($_GET['data']) && isDataValidaRange($_GET['data'])) {
            $where[] = "g.dataCreazione >= :data";
            $params[':data'] = $_GET['data'];
        }

        $tabella = "UtenteAutorizzatoGruppo uag JOIN Gruppo g ON uag.codGruppo = g.codice JOIN Utente u ON g.creatoDa = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT g.codice AS id_gruppo, g.nome AS `Nome Gruppo`, u.codice AS id_proprietario, u.nickname AS `Proprietario`, g.dataCreazione AS `Data Creazione` 
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dati = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $icona = ((int)$g['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
            $dati[] = [
                'Nome Gruppo' => $icona . "<a href='gruppi.php?codice={$g['id_gruppo']}'>" . htmlspecialchars($g['Nome Gruppo']) . "</a>",
                'Proprietario' => "<a href='utenti.php?utente={$g['id_proprietario']}'>" . htmlspecialchars($g['Proprietario']) . "</a>",
                'Data Creazione' => htmlspecialchars($g['Data Creazione'] ?? '')
            ];
        }

        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> gruppi (<strong>$limit</strong> per pagina)</p></div>";
        echo '<div class="table-container">';
        stampaTabella($dati, ['Nome Gruppo', 'Proprietario'], generaIntestazioniOrdinabili(['Nome Gruppo' => 'nome', 'Proprietario' => 'proprietario', 'Data Creazione' => 'data'], $sort_col, $sort_dir));
        echo '</div>' . getPagesNav($np, $npagine, 1);
    } elseif ($tab_corrente === 'bacheche') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'uab.nomeBacheca', 'proprietario' => 'u.nickname', 'data' => 'b.dataCreazione'], 'data', 'DESC');
        $where = ["uab.utenteAutorizzato = :c", "uab.autorizzato = 1"];
        $params = [':c' => $idUtente];

        if (!empty($_GET['titolo'])) {
            $where[] = "uab.nomeBacheca LIKE :tit";
            $params[':tit'] = '%' . $_GET['titolo'] . '%';
        }
        if (!empty($_GET['proprietario'])) {
            $where[] = "u.nickname LIKE :prop";
            $params[':prop'] = '%' . $_GET['proprietario'] . '%';
        }
        if (!empty($_GET['data']) && isDataValidaRange($_GET['data'])) {
            $where[] = "b.dataCreazione >= :data";
            $params[':data'] = $_GET['data'];
        }

        $tabella = "UtenteAutorizzatoBacheca uab JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente JOIN Utente u ON b.codiceUtente = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT b.codiceUtente AS id_proprietario, uab.nomeBacheca AS `Nome Bacheca`, u.nickname AS `Proprietario`, b.dataCreazione AS `Data Creazione` 
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dati = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $icona = ((int)$b['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
            $dati[] = [
                'Nome Bacheca' => $icona . "<a href='bacheche.php?vista=dettaglio&bacheca=" . urlencode($b['Nome Bacheca']) . "&owner={$b['id_proprietario']}'>" . htmlspecialchars($b['Nome Bacheca']) . "</a>",
                'Proprietario' => "<a href='utenti.php?utente={$b['id_proprietario']}'>" . htmlspecialchars($b['Proprietario']) . "</a>",
                'Data Creazione' => htmlspecialchars($b['Data Creazione'] ?? '')
            ];
        }

        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>$totale</strong> bacheche (<strong>$limit</strong> per pagina)</p></div>";
        echo '<div class="table-container">';
        stampaTabella($dati, ['Nome Bacheca', 'Proprietario'], generaIntestazioniOrdinabili(['Nome Bacheca' => 'nome', 'Proprietario' => 'proprietario', 'Data Creazione' => 'data'], $sort_col, $sort_dir));
        echo '</div>' . getPagesNav($np, $npagine, 1);
    } elseif ($tab_corrente === 'file') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['file' => 'titolo', 'dimensione' => 'dimensione'], 'file', 'ASC');
        $where = ["caricatoDa = :c"];
        $params = [':c' => $idUtente];

        if (!empty($_GET['file'])) {
            $where[] = "titolo LIKE :file";
            $params[':file'] = '%' . $_GET['file'] . '%';
        }
        if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
            $where[] = "dimensione >= :dmin";
            $params[':dmin'] = (float)$_GET['dimensione_min'];
        }
        if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
            $where[] = "dimensione <= :dmax";
            $params[':dmax'] = (float)$_GET['dimensione_max'];
        }

        $filetypes = ['immagine' => 'Immagini', 'audio' => 'Audio', 'video' => 'Video'];
        if (!empty($_GET['filetype'])) {
            $selectedTypes = array_filter((array)$_GET['filetype'], fn($t) => isset($filetypes[$t]));
            if ($selectedTypes) {
                $placeholders = [];
                foreach (array_values($selectedTypes) as $i => $type) {
                    $placeholders[] = ":ft_$i";
                    $params[":ft_$i"] = $type;
                }
                $where[] = 'tipo IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $totale = getNumberOfRecords($pdo, "FileMultimediale", $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT url, tipo, titolo AS `File`, dimensione AS `Dimensione` FROM FileMultimediale WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $dati = [];
        $icons = ['immagine' => 'images/image.png', 'video' => 'images/video.png', 'audio' => 'images/headphones.png', 'default' => 'images/document.png'];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $icon = $icons[strtolower($f['tipo'])] ?? $icons['default'];
            $dati[] = [
                'File' => "<a href='" . htmlspecialchars($f['url']) . "' target='_blank' class='file-link'><img src='{$icon}' class='icona icona-filetype' style='vertical-align:middle;'>" . htmlspecialchars($f['File']) . "</a>",
                'Dimensione' => formatFileSizeHtml((int)$f['Dimensione'])
            ];
        }

        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> file condivisi (<strong>$limit</strong> per pagina)</p></div>";
        echo '<div class="table-container">';
        stampaTabella($dati, ['File', 'Dimensione'], generaIntestazioniOrdinabili(['File' => 'file', 'Dimensione' => 'dimensione'], $sort_col, $sort_dir));
        echo '</div>' . getPagesNav($np, $npagine, 1);
    }

    if (!$isAjax) echo '</div>';
}

// =========================================================
//  3. VISTA GENERALE ELENCO UTENTI
// =========================================================
function renderElencoUtenti($pdo, $isAjax)
{
    list($limit, $np, $start_from) = getPaginationParams(20);
    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nickname' => 'nickname', 'nome' => 'nome', 'cognome' => 'cognome', 'data' => 'dataNascita'], 'nickname', 'ASC');

    $where = [];
    $params = [];
    if (!empty($_GET['utente'])) {
        $where[] = "nickname LIKE :u";
        $params[':u'] = '%' . $_GET['utente'] . '%';
    }
    if (!empty($_GET['nome'])) {
        $where[] = "nome LIKE :n";
        $params[':n'] = '%' . $_GET['nome'] . '%';
    }
    if (!empty($_GET['cognome'])) {
        $where[] = "cognome LIKE :c";
        $params[':c'] = '%' . $_GET['cognome'] . '%';
    }
    if (!empty($_GET['data_nascita']) && isDataValidaRange($_GET['data_nascita'])) {
        $where[] = "dataNascita >= :d";
        $params[':d'] = $_GET['data_nascita'];
    }

    $totale = getNumberOfRecords($pdo, "Utente", $where, $params);
    $npagine = getNumberOfPages($totale, $limit);

    $sql = "SELECT codice, nickname, nome AS Nome, cognome AS Cognome, dataNascita AS `Data di Nascita` FROM Utente";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (!$isAjax) echo '<div id="ajax-results">';

    echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> utenti (<strong>$limit</strong> per pagina)</p></div>";

    $dati = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dati[] = [
            'Nickname' => "<a href='?utente={$r['codice']}' class='row-link' title='Visualizza profilo'>" . htmlspecialchars($r['nickname']) . "</a>",
            'Nome' => htmlspecialchars($r['Nome']),
            'Cognome' => htmlspecialchars($r['Cognome']),
            'Data di Nascita' => htmlspecialchars($r['Data di Nascita'])
        ];
    }

    echo '<div class="table-container">';
    stampaTabella($dati, ['Nickname'], generaIntestazioniOrdinabili(['Nickname' => 'nickname', 'Nome' => 'nome', 'Cognome' => 'cognome', 'Data di Nascita' => 'data'], $sort_col, $sort_dir));
    echo '</div>' . getPagesNav($np, $npagine, 1);

    if (!$isAjax) echo '</div>';
}

// =========================================================
//  ESECUZIONE PAGINA E ROUTING
// =========================================================
$idUtente = (!empty($_GET['utente']) && is_numeric($_GET['utente'])) ? (int)$_GET['utente'] : null;
$tab_corrente = $_GET['tab'] ?? 'info';

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
                <?php renderFiltroSidebarUtenti($pdo, $idUtente, $tab_corrente); ?>
            </aside>

            <div id="content">
            <?php endif; ?>

            <?php
            if ($idUtente) {
                renderDettaglioUtente($pdo, $idUtente, $tab_corrente, $isAjax);
            } else {
                renderElencoUtenti($pdo, $isAjax);
            }
            ?>

            <?php if (!$isAjax): ?>
            </div>
        </div>
        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>