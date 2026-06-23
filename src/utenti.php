<?php
/**
 * GESTIONE UTENTI - SalMeet
 * * Questo file si occupa di gestire la sezione "Utenti" della piattaforma.
 * Permette di visualizzare l'elenco globale di tutti gli utenti registrati,
 * applicare filtri di ricerca, e accedere alla vista di dettaglio di un singolo
 * utente (suddivisa in tab: Informazioni, Gruppi, Bacheche, File Condivisi).
 * Il file è progettato per supportare chiamate asincrone (AJAX) per aggiornare
 * solo la tabella e i filtri senza ricaricare l'intera pagina.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// Verifica se la richiesta arriva tramite AJAX controllando gli header della richiesta HTTP
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// =========================================================
//  1. GESTIONE FILTRI SIDEBAR
// =========================================================
/**
 * Renderizza i filtri nella sidebar laterale in base al contesto corrente.
 * Se si è nell'elenco generale, mostra i filtri globali per gli utenti.
 * Se si è nel dettaglio di un utente, mostra i filtri specifici per la tab aperta.
 *
 * @param PDO    $pdo      L'istanza di connessione al database.
 * @param int    $idUtente L'ID dell'utente visualizzato (vuoto se vista generale).
 * @param string $tab      Il nome della tab attualmente attiva nel dettaglio utente.
 */
function renderFiltroSidebarUtenti($pdo, $idUtente, $tab)
{
    // Se non è specificato un utente, siamo nella vista generale dell'elenco utenti
    if (empty($idUtente) || !is_numeric($idUtente)) {
        $filtro_config = getFiltroConfig('utenti');
        include 'filter.php';
        return;
    }

    // Gestione dei filtri specifici per la vista di DETTAGLIO di un singolo utente
    if ($tab === 'info') {
        // La tab informazioni non ha filtri applicabili
        $filtro_config = getFiltroConfig('vuoto');
        echo '<div id="filtro" class="filter-empty"><p>' . htmlspecialchars($filtro_config['messaggio']) . '</p></div>';
    } elseif ($tab === 'gruppi') {
        // Carica la configurazione dei filtri per i gruppi a cui l'utente partecipa
        $filtro_config = getFiltroConfig('gruppi', ['utente' => $idUtente, 'tab' => 'gruppi']);
        include 'filter.php';
    } elseif ($tab === 'bacheche') {
        // Carica la configurazione dei filtri per le bacheche
        $filtro_config = getFiltroConfig('bacheche', ['utente' => $idUtente, 'tab' => 'bacheche']);

        // Rimuoviamo i campi del nome e cognome proprietario che non servono nella tab corrente,
        // poiché l'utente in questione potrebbe essere il proprietario o un partecipante
        if (isset($filtro_config['campi'])) {
            $filtro_config['campi'] = array_filter($filtro_config['campi'], function ($c) {
                return !in_array($c['name'] ?? '', ['proprietario_nome', 'proprietario_cognome']);
            });
        }
        include 'filter.php';
    } elseif ($tab === 'file') {
        // Calcola dinamicamente la dimensione minima e massima dei file caricati 
        // dall'utente per popolare correttamente il filtro "Range dimensione"
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
/**
 * Renderizza la scheda di dettaglio di un singolo utente.
 * Suddivide le informazioni in più schede (Info, Gruppi, Bacheche, File) 
 * e recupera i dati corrispondenti dal database a seconda della tab attiva.
 *
 * @param PDO    $pdo          L'istanza di connessione al database.
 * @param int    $idUtente     L'ID dell'utente di cui mostrare i dettagli.
 * @param string $tab_corrente Il nome della tab richiesta via GET.
 * @param bool   $isAjax       Determina se stampare o meno i wrapper per l'aggiornamento parziale.
 */
function renderDettaglioUtente($pdo, $idUtente, $tab_corrente, $isAjax)
{
    // Recupero delle informazioni anagrafiche base dell'utente
    $stmtUtente = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
    $stmtUtente->execute([':codice' => $idUtente]);
    $infoUtente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

    // Se l'utente non esiste nel database, mostriamo un errore
    if (!$infoUtente) {
        echo "<p class='info-risultati'>Errore: l'utente richiesto non è presente a sistema.</p>";
        return;
    }

    // Otteniamo i parametri per la paginazione (limite di righe, numero pagina, offset di partenza)
    list($limit, $np, $start_from) = getPaginationParams(20);

    // Apriamo il container AJAX se si tratta di un caricamento completo della pagina
    if (!$isAjax) echo '<div id="ajax-results">';

    echo "<a href='utenti.php' id='btn-torna-indietro' class='btn-indietro'>Torna alla pagina precedente</a>";

    // Stampa il nickname dell'utente come titolo principale
    echo "<h2>" . htmlspecialchars($infoUtente['nickname']) . "</h2>";

    // Creazione del menu di navigazione a tab
    $base = "?utente=" . urlencode($idUtente) . "&tab=";
    echo "<div class='detail-tabs-header'><div class='bacheca-tabs tabs-reset'>
            <a href='{$base}info' class='" . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>
            <a href='{$base}gruppi' class='" . ($tab_corrente === 'gruppi' ? 'active' : '') . "'>Gruppi</a>
            <a href='{$base}bacheche' class='" . ($tab_corrente === 'bacheche' ? 'active' : '') . "'>Bacheche</a>
            <a href='{$base}file' class='" . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>
          </div></div>";

    // --- LOGICA TAB INFORMAZIONI ---
    if ($tab_corrente === 'info') {
        // Conta quante entità l'utente possiede/condivide
        $numFile = getNumberOfRecords($pdo, "FileMultimediale", ["caricatoDa = :c"], [':c' => $idUtente]);
        $numGruppi = getNumberOfRecords($pdo, "UtenteAutorizzatoGruppo", ["codUtente = :c"], [':c' => $idUtente]);
        $numBacheche = getNumberOfRecords($pdo, "UtenteAutorizzatoBacheca", ["utenteAutorizzato = :c", "autorizzato = 1"], [':c' => $idUtente]);
        $dataFormattata = formattaData($infoUtente['dataNascita']);

        // Stampa la card riepilogativa
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

    // --- LOGICA TAB GRUPPI ---
    } elseif ($tab_corrente === 'gruppi') {
        // Ottiene parametri per ordinare le colonne della tabella
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'g.nome', 'proprietario' => 'u.nickname', 'data' => 'g.dataCreazione'], 'data', 'DESC');
        
        // Estrapola i filtri passati in GET (es. ricerca per nome gruppo)
        $filtri = applicaFiltriDinamici($_GET, 'gruppi');
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        // Costruisce la condizione WHERE di base per filtrare i gruppi dell'utente
        $where = ["uag.codUtente = :c"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        // Recupera il numero totale di risultati e calcola le pagine
        $tabella = "UtenteAutorizzatoGruppo uag JOIN Gruppo g ON uag.codGruppo = g.codice JOIN Utente u ON g.creatoDa = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        // Compone ed esegue la query principale
        $sql = "SELECT g.codice AS id_gruppo, g.nome AS `Nome Gruppo`, u.codice AS id_proprietario, u.nickname AS `Proprietario`, g.dataCreazione AS `Data Creazione`, u.nome AS unome, u.cognome AS ucognome
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Stampa la barra info (risultati trovati)
        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> gruppi a cui appartiene l'utente {$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            // Itera sui risultati e formatta l'array per la funzione di stampa tabella
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                // Aggiunge un'icona a corona se l'utente è il proprietario del gruppo
                $icona = ((int)$g['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
                $dati[] = [
                    'Nome Gruppo' => $icona . "<a href='gruppi.php?gruppo={$g['id_gruppo']}&tab=info'>" . htmlspecialchars($g['Nome Gruppo']) . "</a>",
                    'Proprietario' => "<a href='utenti.php?utente={$g['id_proprietario']}'>" . formatOwnerDisplay($g['unome'] ?? null, $g['ucognome'] ?? null, $g['Proprietario']) . "</a>",
                    'Data Creazione' => htmlspecialchars($g['Data Creazione'] ?? '')
                ];
            }

            echo '<div class="table-container tabella-gruppi">';
            stampaTabella($dati, ['Nome Gruppo', 'Proprietario'], generaIntestazioniOrdinabili(['Nome Gruppo' => 'nome', 'Proprietario' => 'proprietario', 'Data Creazione' => 'data'], $sort_col, $sort_dir));
            echo '</div>';
            
            // Stampa la barra di paginazione
            echo "<div class='pagination-spacer'>";
            echo getPagesNav($np, $npagine, 1);
            echo "</div>";
        } else {
            // Nessun risultato: mostra messaggio empty state
            echo '<div class="table-container table-container-empty">';
            echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
            echo '</div>';
            echo "<div class='pagination-spacer'></div>";
        }

    // --- LOGICA TAB BACHECHE ---
    } elseif ($tab_corrente === 'bacheche') {
        // Ottiene parametri per ordinare le colonne della tabella
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nome' => 'uab.nomeBacheca', 'proprietario' => 'u.nickname', 'data' => 'b.dataCreazione'], 'data', 'DESC');
        
        $filtri = applicaFiltriDinamici($_GET, 'bacheche');
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        // Cerca solo le bacheche a cui l'utente è autorizzato (autorizzato = 1)
        $where = ["uab.utenteAutorizzato = :c", "uab.autorizzato = 1"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        $tabella = "UtenteAutorizzatoBacheca uab JOIN Bacheca b ON uab.nomeBacheca = b.nome AND uab.codUtente = b.codiceUtente JOIN Utente u ON b.codiceUtente = u.codice";
        $totale = getNumberOfRecords($pdo, $tabella, $where, $params);
        $npagine = getNumberOfPages($totale, $limit);

        // Query principale
        $sql = "SELECT b.codiceUtente AS id_proprietario, uab.nomeBacheca AS `Nome Bacheca`, u.nickname AS `Proprietario`, b.dataCreazione AS `Data Creazione`, u.nome AS unome, u.cognome AS ucognome
                FROM $tabella WHERE " . implode(" AND ", $where) . " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>$totale</strong> bacheche a cui appartiene l'utente {$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                // Aggiunge un'icona a corona se l'utente è il proprietario della bacheca
                $icona = ((int)$b['id_proprietario'] === $idUtente) ? "<img src='images/crown.png' alt='Owner' class='owner-crown-icon'> " : "";
                $dati[] = [
                    'Nome Bacheca' => $icona . "<a href='bacheche.php?vista=dettaglio&bacheca=" . urlencode($b['Nome Bacheca']) . "&owner={$b['id_proprietario']}'>" . htmlspecialchars($b['Nome Bacheca']) . "</a>",
                    'Proprietario' => "<a href='utenti.php?utente={$b['id_proprietario']}'>" . formatOwnerDisplay($b['unome'] ?? null, $b['ucognome'] ?? null, $b['Proprietario']) . "</a>",
                    'Data Creazione' => htmlspecialchars($b['Data Creazione'] ?? '')
                ];
            }

            echo '<div class="table-container tabella-bacheche">';
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
        
    // --- LOGICA TAB FILE CARICATI ---
    } elseif ($tab_corrente === 'file') {
        // Ottiene parametri per ordinamento
        list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['file' => 'fm.titolo', 'proprietario' => getOwnerSortExpression(), 'dimensione' => 'fm.dimensione'], 'file', 'ASC');
        
        $filtri = applicaFiltriDinamici($_GET, 'file');
        // Riassegnazione nome -> titolo per compatibilità DB / Interfaccia filtro
        $filtri['sql'] = str_replace('fm.nome', 'fm.titolo', $filtri['sql']);
        
        $params = array_merge([':c' => $idUtente], $filtri['parametri']);
        
        $where = ["fm.caricatoDa = :c"];
        if (!empty($filtri['sql'])) {
            $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
        }

        // Il checkbox per filetype richiede integrazione logica manuale tramite IN clause
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

        // Query principale per i file
        $sql = "SELECT fm.url, fm.tipo, fm.titolo AS `File`, fm.dimensione AS `Dimensione`, u.nome AS proprietario_nome, u.cognome AS proprietario_cognome, u.nickname AS proprietario_nickname, fm.numero AS numero 
                FROM FileMultimediale fm 
                LEFT JOIN Utente u ON fm.caricatoDa = u.codice 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
        echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> file caricati dall'utente{$testoPerPagina}</p></div>";
        
        if ($totale > 0) {
            $dati = [];
            // Mappatura delle icone corrispondenti al tipo MIME o alla categoria
            $icons = ['immagine' => 'images/image.png', 'video' => 'images/video.png', 'audio' => 'images/headphones.png', 'default' => 'images/document.png'];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $tipo_file   = strtolower($f['tipo']);
                $url_file    = $f['url'];      
                $id_file     = $f['numero'];   
                $titolo_file = $f['File']; // Nella query è estratto come "fm.titolo AS File"
                
                // --- INIZIO BLOCCO COLONNA FILE ---
                // Costruisce l'HTML per mostrare l'icona e i link di reindirizzamento al file
                $icon_path = $icons[$tipo_file] ?? $icons['default'];
                $file_icon = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($tipo_file) . "'>";
                $detail_link = "media.php?vista=dettaglio&file_id=" . urlencode((int)$id_file);
                
                $follow_link_html = "<a class='media-download-link' href='" . htmlspecialchars($url_file) . "' target='_blank'>"
                    . "<img class='media-external-icon' src='images/external-link.png'>"
                    . "</a>";
                
                $html_colonna_file = "<div class='media-item'>" 
                    . $file_icon 
                    . "<a href='" . htmlspecialchars($detail_link) . "'>" . htmlspecialchars($titolo_file) . "</a>"
                    . "<div class='media-action-wrapper'>" . $follow_link_html . "</div>"
                    . "</div>";
                // --- FINE BLOCCO COLONNA FILE ---

                // Assegna il blocco HTML generato alla colonna 'File'
                $dati[] = [
                    'File' => $html_colonna_file,
                    'Dimensione' => formatFileSizeHtml((int)$f['Dimensione'])
                ];
            }

            echo '<div class="table-container tabella-media">';
            stampaTabella($dati, ['File', 'Proprietario', 'Dimensione'], generaIntestazioniOrdinabili(['File' => 'file', 'Proprietario' => 'proprietario', 'Dimensione' => 'dimensione'], $sort_col, $sort_dir));
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

    // Chiudiamo il container dei risultati AJAX
    if (!$isAjax) echo '</div>';
}

// =========================================================
//  3. VISTA GENERALE ELENCO UTENTI
// =========================================================
/**
 * Renderizza la vista globale che elenca tutti gli utenti iscritti al sistema.
 * Viene mostrata quando non è specificato l'ID di un utente.
 *
 * @param PDO  $pdo    L'istanza di connessione al database.
 * @param bool $isAjax Determina se stampare o meno i wrapper per l'aggiornamento parziale.
 */
function renderElencoUtenti($pdo, $isAjax)
{
    // Recupera offset e limiti per la paginazione 
    list($limit, $np, $start_from) = getPaginationParams(20);
    // Imposta la direzione dell'ordinamento
    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento(['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'u.dataNascita'], 'nickname', 'ASC');

    // Richiede l'applicazione dei filtri di ricerca dalla barra laterale
    $filtri = applicaFiltriDinamici($_GET, 'utenti');
    $where = [];
    $params = $filtri['parametri'];

    // Accoda la clausola SQL generata dal filtro se presente
    if (!empty($filtri['sql'])) {
        $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
    }

    // Calcolo righe totali per la paginazione
    $totale = getNumberOfRecords($pdo, "Utente u", $where, $params);
    $npagine = getNumberOfPages($totale, $limit);

    // Query per prelevare la porzione di utenti della pagina attuale
    $sql = "SELECT u.codice, u.nickname, u.nome AS Nome, u.cognome AS Cognome, u.dataNascita AS `Data di Nascita` FROM Utente u";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY $sql_sort $sort_dir LIMIT $start_from, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Apriamo il container dei risultati AJAX se necessario
    if (!$isAjax) echo '<div id="ajax-results">';

    $testoPerPagina = ($totale > $limit) ? " (<strong>$limit</strong> per pagina)" : "";
    echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>$totale</strong> utenti{$testoPerPagina}</p></div>";

    if ($totale > 0) {
        $dati = [];
        // Itera sugli utenti e compone i link per l'interfaccia
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dati[] = [
                'Nickname' => "<a href='?utente={$r['codice']}' class='row-link' title='Visualizza profilo'>" . htmlspecialchars($r['nickname']) . "</a>",
                'Nome' => htmlspecialchars($r['Nome']),
                'Cognome' => htmlspecialchars($r['Cognome']),
                'Data di Nascita' => htmlspecialchars($r['Data di Nascita'])
            ];
        }

        echo '<div class="table-container tabella-utenti">';
        // Affida la stampa visiva alla funzione generalizzata
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

// Legge i parametri GET per determinare lo stato corrente della pagina
$idUtente = (!empty($_GET['utente']) && is_numeric($_GET['utente'])) ? (int)$_GET['utente'] : null;
$tab_corrente = $_GET['tab'] ?? 'info';

// Se la richiesta NON è un aggiornamento AJAX parziale, stampa l'HTML strutturale della pagina
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
            // Motore di routing: Se c'è un ID, carica il Dettaglio, altrimenti carica l'Elenco Generale
            if ($idUtente) {
                renderDettaglioUtente($pdo, $idUtente, $tab_corrente, $isAjax);
            } else {
                renderElencoUtenti($pdo, $isAjax);
            }
            ?>

            <?php 
            // Chiusura dei tag HTML strutturali in caso di caricamento classico
            if (!$isAjax): ?>
            </div>
        </div>
        <?php include 'footer.html'; ?>
    </body>

    </html>
<?php endif; ?>