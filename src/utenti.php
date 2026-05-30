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

    echo "<h2>" . htmlspecialchars($infoUtente['nickname']) . "</h2>";

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
            
            <p class='info-card-text'><strong>Numero di gruppi a cui appartiene:</strong> 
                <a href='?utente={$idUtente}&tab=gruppi'>{$numGruppi}</a>
            </p>
            <p class='info-card-text'><strong>Numero di bacheche a cui appartiene:</strong> 
                <a href='?utente={$idUtente}&tab=bacheche'>{$numBacheche}</a>
            </p>
            <p class='info-card-text-last'><strong>Numero di file caricati:</strong> 
                <a href='?utente={$idUtente}&tab=file'>{$numFile}</a>
            </p>
          </div>";
    } elseif ($tab_corrente === 'gruppi') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'g.nome', 'proprietario' => 'u.nickname', 'data' => 'g.dataCreazione'], 'data', 'DESC');
        
        $filtri = applicaFiltriDinamici($_GET, 'gruppi');
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        $where = ["uag.codUtente = :c"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        $tabella = "UtenteAutorizzatoGruppo uag JOIN Gruppo g ON uag.codGruppo = g.codice JOIN Utente u ON g.creatoDa = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT g.codice AS id_gruppo, g.nome AS `Nome Gruppo`, u.codice AS id_proprietario, u.nickname AS `Proprietario`, g.dataCreazione AS `Data Creazione` 
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> gruppi{$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $icona = ((int)$g['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
                $dati[] = [
                    'Nome Gruppo' => $icona . "<a href='gruppi.php?gruppo={$g['id_gruppo']}&tab=info'>" . htmlspecialchars($g['Nome Gruppo']) . "</a>",
                    'Proprietario' => "<a href='utenti.php?utente={$g['id_proprietario']}'>" . htmlspecialchars($g['Proprietario']) . "</a>",
                    'Data Creazione' => htmlspecialchars($g['Data Creazione'] ?? '')
                ];
            }

            echo '<div class="table-container">';
            stampaTabella($dati, ['Nome Gruppo', 'Proprietario'], generaIntestazioniOrdinabili(['Nome Gruppo' => 'nome', 'Proprietario' => 'proprietario', 'Data Creazione' => 'data'], $sort_col, $sort_dir));
            echo '</div>';
            
            echo "<div class='pagination-spacer'>";
            echo getPagesNav($np, $npagine, 1);
            echo "</div>";
        } else {
            echo '<div class="table-container table-container-empty">';
            echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
            echo '</div>';
            echo "<div class='pagination-spacer'></div>";
        }
        
    } elseif ($tab_corrente === 'bacheche') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'uab.nomeBacheca', 'proprietario' => 'u.nickname', 'data' => 'b.dataCreazione'], 'data', 'DESC');
        
        $filtri = applicaFiltriDinamici($_GET, 'bacheche');
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        $where = ["uab.utenteAutorizzato = :c", "uab.autorizzato = 1"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        $tabella = "UtenteAutorizzatoBacheca uab JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente JOIN Utente u ON b.codiceUtente = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT b.codiceUtente AS id_proprietario, uab.nomeBacheca AS `Nome Bacheca`, u.nickname AS `Proprietario`, b.dataCreazione AS `Data Creazione` 
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>$totale</strong> bacheche{$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $icona = ((int)$b['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
                $dati[] = [
                    'Nome Bacheca' => $icona . "<a href='bacheche.php?vista=dettaglio&bacheca=" . urlencode($b['Nome Bacheca']) . "&owner={$b['id_proprietario']}'>" . htmlspecialchars($b['Nome Bacheca']) . "</a>",
                    'Proprietario' => "<a href='utenti.php?utente={$b['id_proprietario']}'>" . htmlspecialchars($b['Proprietario']) . "</a>",
                    'Data Creazione' => htmlspecialchars($b['Data Creazione'] ?? '')
                ];
            }

            echo '<div class="table-container">';
            stampaTabella($dati, ['Nome Bacheca', 'Proprietario'], generaIntestazioniOrdinabili(['Nome Bacheca' => 'nome', 'Proprietario' => 'proprietario', 'Data Creazione' => 'data'], $sort_col, $sort_dir));
            echo '</div>';
            
            echo "<div class='pagination-spacer'>";
            echo getPagesNav($np, $npagine, 1);
            echo "</div>";
        } else {
            echo '<div class="table-container table-container-empty">';
            echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
            echo '</div>';
            echo "<div class='pagination-spacer'></div>";
        }
        
    } elseif ($tab_corrente === 'file') {
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['file' => 'fm.titolo', 'dimensione' => 'fm.dimensione'], 'file', 'ASC');
        
        $filtri = applicaFiltriDinamici($_GET, 'file');
        // Riassegnazione nome -> titolo per compatibilità DB / Interfaccia filtro
        $filtri['sql'] = str_replace('fm.nome', 'fm.titolo', $filtri['sql']);
        
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        $where = ["fm.caricatoDa = :c"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        // Il checkbox per filetype richiede integrazione logica manuale come in tutte le altre viste
        $filetypes = ['immagine' => 'Immagini', 'audio' => 'Audio', 'video' => 'Video'];
        if (!empty($_GET['filetype']) && is_array($_GET['filetype'])) {
            $selectedTypes = array_filter((array)$_GET['filetype'], fn($t) => isset($filetypes[$t]));
            if ($selectedTypes) {
                $placeholders = [];
                foreach (array_values($selectedTypes) as $i => $type) {
                    $placeholders[] = ":ft_$i";
                    $params[":ft_$i"] = $type;
                }
                $where[] = 'fm.tipo IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $tabella = "FileMultimediale fm LEFT JOIN Utente u ON fm.caricatoDa = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        $sql = "SELECT fm.url, fm.tipo, fm.titolo AS `File`, fm.dimensione AS `Dimensione` 
                FROM FileMultimediale fm 
                LEFT JOIN Utente u ON fm.caricatoDa = u.codice 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> file condivisi{$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            $icons = ['immagine' => 'images/image.png', 'video' => 'images/video.png', 'audio' => 'images/headphones.png', 'default' => 'images/document.png'];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $icon = $icons[strtolower($f['tipo'])] ?? $icons['default'];
                $dati[] = [
                    'File' => "<a href='" . htmlspecialchars($f['url']) . "' target='_blank' class='file-link'><img src='{$icon}' class='icona icona-filetype' style='vertical-align:middle;'>" . htmlspecialchars($f['File']) . "</a>",
                    'Dimensione' => formatFileSizeHtml((int)$f['Dimensione'])
                ];
            }

            echo '<div class="table-container">';
            stampaTabella($dati, ['File', 'Dimensione'], generaIntestazioniOrdinabili(['File' => 'file', 'Dimensione' => 'dimensione'], $sort_col, $sort_dir));
            echo '</div>';
            
            echo "<div class='pagination-spacer'>";
            echo getPagesNav($np, $npagine, 1);
            echo "</div>";
        } else {
            echo '<div class="table-container table-container-empty">';
            echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
            echo '</div>';
            echo "<div class='pagination-spacer'></div>";
        }
    }

    if (!$isAjax) echo '</div>';
}

// =========================================================
//  3. VISTA GENERALE ELENCO UTENTI
// =========================================================
function renderElencoUtenti($pdo, $isAjax)
{
    list($limit, $np, $start_from) = getPaginationParams(20);
    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'u.dataNascita'], 'nickname', 'ASC');

    $filtri = applicaFiltriDinamici($_GET, 'utenti');
    $where = [];
    $params = $filtri['parametri'];

    if (!empty($filtri['sql'])) {
        $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
    }

    $totale = getNumberOfRecords($pdo, "Utente u", $where, $params);
    $npagine = getNumberOfPages($totale, $limit);

    $sql = "SELECT u.codice, u.nickname, u.nome AS Nome, u.cognome AS Cognome, u.dataNascita AS `Data di Nascita` FROM Utente u";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (!$isAjax) echo '<div id="ajax-results">';

    $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
    echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> utenti{$testoPerPagina}</p></div>";

    if ($totale > 0) {
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
        echo '</div>';
        
        echo "<div class='pagination-spacer'>";
        echo getPagesNav($np, $npagine, 1);
        echo "</div>";
    } else {
        echo '<div class="table-container table-container-empty">';
        echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
        echo '</div>';
        echo "<div class='pagination-spacer'></div>";
    }

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