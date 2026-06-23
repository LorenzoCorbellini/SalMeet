<?php
/**
 * @file gruppi.php
 * @brief Gestione della pagina dei Gruppi di SalMeet.
 * * Questo file gestisce sia la visualizzazione dell'elenco globale dei gruppi 
 * sia la visualizzazione dettagliata di un singolo gruppo suddivisa in tab 
 * (Informazioni, Membri, File Condivisi), supportando il caricamento asincrono via AJAX.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

// Verifica se la richiesta arriva tramite AJAX (XMLHttpRequest)
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
                /**
                 * Sezione per il rendering dinamico dei filtri laterali.
                 * I filtri cambiano in base alla vista corrente (globale o dettaglio gruppo/tab).
                 */
                if (empty($_GET['gruppo'])) {
                    // Vista Globale: Usa il filtro centralizzato nativo per i gruppi
                    $filtro_config = getFiltroConfig('gruppi');
                    include 'filter.php';
                } else {
                    // Vista Dettaglio: Configurazione dei filtri in base al tab selezionato
                    $tab_corrente = $_GET['tab'] ?? 'info';
                    $idGruppo = (int)$_GET['gruppo'];

                    if ($tab_corrente === 'info') {
                        // Tab Info: Nessun filtro applicabile, mostra box vuoto
                        $filtro_config = getFiltroConfig('vuoto');
                        echo '<div id="filtro" class="filter-empty"><p>' . htmlspecialchars($filtro_config['messaggio'] ?? 'Non sono presenti filtri per questa sezione.') . '</p></div>';
                    } elseif ($tab_corrente === 'membri') {
                        // Tab Membri: Usa il filtro centralizzato 'utenti' per filtrare i membri del gruppo
                        $filtro_config = getFiltroConfig('utenti', ['gruppo' => $idGruppo, 'tab' => 'membri']);
                        include 'filter.php';
                    } elseif ($tab_corrente === 'file') {
                        // Tab File: Estrazione dinamica del range di dimensioni per configurare il filtro 'file'
                        $stmtRange = $pdo->prepare("
                            SELECT MIN(f.dimensione) as min_dim, MAX(f.dimensione) as max_dim 
                            FROM FileAssociatoGruppo fag 
                            JOIN FileMultimediale f ON fag.file = f.numero 
                            WHERE fag.codGruppo = :codice
                        ");
                        $stmtRange->execute([':codice' => $idGruppo]);
                        $range = $stmtRange->fetch(PDO::FETCH_ASSOC);

                        $minSize = isset($range['min_dim']) ? floor($range['min_dim']) : 0;
                        $maxSize = isset($range['max_dim']) ? ceil($range['max_dim']) : 100;

                        // Configura il filtro centralizzato 'file' con i limiti calcolati per la dimensione
                        $filtro_config = getFiltroConfig('file', [
                            'gruppo' => $idGruppo,
                            'tab' => 'file',
                            'min_size' => $minSize,
                            'max_size' => $maxSize ?: 100
                        ]);
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
                // ROUTING VISTE: DETTAGLIO GRUPPO E GLOBALE
                // =========================================================

                // Cattura l'URL attuale per navigare indietro correttamente da Utenti
                $current_url = $_SERVER['REQUEST_URI'];

                if (!empty($_GET['gruppo'])) {
                    /**
                     * -----------------------------------------------------
                     * GESTIONE VISTA DETTAGLIO SINGOLO GRUPPO
                     * -----------------------------------------------------
                     */
                    $idGruppo = (int)$_GET['gruppo'];
                    $tab_corrente = $_GET['tab'] ?? 'info';

                    // Recupero dei dati principali del gruppo e del suo proprietario dal database
                    $stmtGruppo = $pdo->prepare("
                        SELECT g.nome, g.dataCreazione, u.nickname, u.codice as ownerId
                        FROM Gruppo g
                        JOIN Utente u ON g.creatoDa = u.codice
                        WHERE g.codice = :id
                    ");
                    $stmtGruppo->execute([':id' => $idGruppo]);
                    $infoGruppo = $stmtGruppo->fetch(PDO::FETCH_ASSOC);

                    if ($infoGruppo) {
                        // Rendering del pulsante di ritorno e dell'intestazione del dettaglio
                        echo "<a href='gruppi.php' id='btn-torna-indietro' class='btn-indietro'>Torna alla pagina precedente</a>";
                        echo "<h2>" . htmlspecialchars($infoGruppo['nome']) . "</h2>";

                        // Generazione degli URL per la navigazione interna ai tab di dettaglio
                        $urlInfo   = "?gruppo=" . urlencode($idGruppo) . "&tab=info";
                        $urlMembri = "?gruppo=" . urlencode($idGruppo) . "&tab=membri";
                        $urlFile   = "?gruppo=" . urlencode($idGruppo) . "&tab=file";

                        echo "<div class='detail-tabs-header'>
                                <div class='bacheca-tabs tabs-reset'>
                                    <a href='{$urlInfo}' class='" . ($tab_corrente === 'info' ? 'active' : '') . "'>Informazioni</a>
                                    <a href='{$urlMembri}' class='" . ($tab_corrente === 'membri' ? 'active' : '') . "'>Membri del Gruppo</a>
                                    <a href='{$urlFile}' class='" . ($tab_corrente === 'file' ? 'active' : '') . "'>File Condivisi</a>
                                </div>
                              </div>";

                        $ownerId = (int)$infoGruppo['ownerId'];

                        if ($tab_corrente === 'info') {
                            /**
                             * Sotto-vista: INFORMAZIONI GENERALI DEL GRUPPO
                             */
                            // Conteggio totale dei file associati al gruppo specifico
                            $stmtFile = $pdo->prepare("SELECT COUNT(*) FROM FileAssociatoGruppo WHERE codGruppo = :id");
                            $stmtFile->execute([':id' => $idGruppo]);
                            $numFile = $stmtFile->fetchColumn();

                            // Conteggio totale dei membri appartenenti al gruppo specifico
                            $stmtMembri = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoGruppo WHERE codGruppo = :id");
                            $stmtMembri->execute([':id' => $idGruppo]);
                            $numMembri = $stmtMembri->fetchColumn();

                            $linkOwner = "utenti.php?utente=" . urlencode($ownerId) . "&return_to=" . urlencode($current_url);

                            // Stampa della scheda informativa di riepilogo del gruppo
                            echo "<div class='tab-info-card'>
                                    <p class='info-card-text'><strong>Proprietario:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($infoGruppo['nickname']) . "</a></p>
                                    <p class='info-card-text'><strong>Data Creazione:</strong> " . formattaData($infoGruppo['dataCreazione']) . "</p>
                                    
                                    <p class='info-card-text'><strong>Numero di membri del gruppo:</strong> 
                                        <a href='?gruppo={$idGruppo}&tab=membri'>{$numMembri}</a>
                                    </p>
                                    <p class='info-card-text-last'><strong>Numero di file totali caricati nel gruppo:</strong> 
                                        <a href='?gruppo={$idGruppo}&tab=file'>{$numFile}</a>
                                    </p>
                                </div>";
                        } elseif ($tab_corrente === 'membri') {
                            /**
                             * Sotto-vista: ELENCO MEMBRI DEL GRUPPO
                             */
                            $recordsPerPage = 20;
                            list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                            // Configurazione dei parametri di ordinamento consentiti per i membri
                            $allowed_sorts = ['nickname' => 'u.nickname', 'nome' => 'u.nome', 'cognome' => 'u.cognome', 'data' => 'u.dataNascita'];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nickname', 'ASC');

                            // Estrazione ed applicazione dei filtri di ricerca centralizzati degli utenti
                            $filtri = applicaFiltriDinamici($_GET, 'utenti');
                            $params = array_merge([':id' => $idGruppo], $filtri['parametri']);
                            $whereSql = $filtri['sql'];

                            // Conteggio del totale dei record risultanti dai filtri applicati (per la paginazione)
                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM UtenteAutorizzatoGruppo uag JOIN Utente u ON uag.codUtente = u.codice WHERE uag.codGruppo = :id" . $whereSql);
                            $stmtCount->execute($params);
                            $totale = $stmtCount->fetchColumn();

                            $numero_pagine = getNumberOfPages($totale, $limit);

                            // Query per il recupero dei membri appartenenti al gruppo corrente
                            $stmtMembri = $pdo->prepare("
                                SELECT u.codice, u.nickname, u.nome, u.cognome, u.dataNascita
                                FROM UtenteAutorizzatoGruppo uag
                                JOIN Utente u ON uag.codUtente = u.codice
                                WHERE uag.codGruppo = :id {$whereSql}
                                ORDER BY {$sql_sort} {$sort_dir}
                                LIMIT {$start_from}, {$limit}
                            ");
                            $stmtMembri->execute($params);
                            $membriRaw = $stmtMembri->fetchAll(PDO::FETCH_ASSOC);

                            $testoPerPagina = ($totale > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>{$totale}</strong> utenti nel gruppo {$testoPerPagina}</p></div>";

                            if (!empty($membriRaw)) {
                                $datiMembri = [];
                                foreach ($membriRaw as $membro) {
                                    $linkMembro = "utenti.php?utente=" . urlencode($membro['codice']) . "&return_to=" . urlencode($current_url);

                                    // Mostra un'icona speciale (corona) se l'utente corrente è anche il creatore del gruppo
                                    $iconaCorona = ((int)$membro['codice'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' class='group-owner-crown'>" : "";

                                    $htmlMembroNickname = "<a href='{$linkMembro}'>" . $iconaCorona . htmlspecialchars($membro['nickname']) . "</a>";

                                    $datiMembri[] = [
                                        'Nickname' => $htmlMembroNickname,
                                        'Nome' => htmlspecialchars($membro['nome']),
                                        'Cognome' => htmlspecialchars($membro['cognome']),
                                        // Passando la data cruda, stampaTabella le assegnerà la class='data' e la formatterà
                                        'Data di Nascita' => $membro['dataNascita']
                                    ];
                                }

                                $customHeaders = generaIntestazioniOrdinabili([
                                    'Nickname' => 'nickname',
                                    'Nome' => 'nome',
                                    'Cognome' => 'cognome',
                                    'Data di Nascita' => 'data'
                                ], $sort_col, $sort_dir);

                                // Renderizzazione finale della tabella membri e della pulsantiera delle pagine
                                echo '<div class="table-container tabella-utenti">';
                                stampaTabella($datiMembri, ['Nickname'], $customHeaders);
                                echo '</div>';
                                echo "<div class='pagination-spacer'>";
                                echo getPagesNav($np, $numero_pagine, 1);
                                echo "</div>";
                            } else {
                                echo '<div class="table-container table-container-empty">';
                                echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                                echo '</div>';
                                echo "<div class='pagination-spacer'></div>";
                            }
                        } elseif ($tab_corrente === 'file') {
                            /**
                             * Sotto-vista: ELENCO FILE CONDIVISI NEL GRUPPO
                             */
                            $recordsPerPage = 20;
                            list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                            // Mappatura delle colonne abilitate per l'ordinamento interattivo della tabella dei file
                            $allowed_sorts = [
                                'file' => 'fm.titolo',
                                'proprietario' => getOwnerSortExpression(),
                                'dimensione' => 'fm.dimensione'
                            ];
                            list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'file', 'ASC');

                            // Estrazione ed applicazione dei filtri di ricerca centralizzati per i file multimediali
                            $filtri = applicaFiltriDinamici($_GET, 'file');
                            // Riassegnazione fm.nome -> fm.titolo per compatibilità DB / Interfaccia filtro
                            $filtri['sql'] = str_replace('fm.nome', 'fm.titolo', $filtri['sql']);

                            $params = array_merge([':codice' => $idGruppo], $filtri['parametri']);
                            $whereSql = $filtri['sql'];

                            // Controllo e filtraggio custom basato sulla tipologia del file selezionata (checkboxes)
                            $filetypes = ['immagine' => 'Immagini', 'audio' => 'Audio', 'video' => 'Video'];
                            if (!empty($_GET['filetype']) && is_array($_GET['filetype'])) {
                                $selectedTypes = array_filter((array)$_GET['filetype'], fn($t) => isset($filetypes[$t]));
                                if ($selectedTypes) {
                                    $placeholders = [];
                                    foreach (array_values($selectedTypes) as $i => $type) {
                                        $placeholders[] = ":ft_$i";
                                        $params[":ft_$i"] = $type;
                                    }
                                    $whereSql .= ' AND fm.tipo IN (' . implode(', ', $placeholders) . ')';
                                }
                            }

                            // Conteggio complessivo dei file filtrati associati al gruppo
                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM FileAssociatoGruppo fag JOIN FileMultimediale fm ON fag.file = fm.numero JOIN Utente u ON u.codice = fm.caricatoDa WHERE fag.codGruppo = :codice" . $whereSql);
                            $stmtCount->execute($params);
                            $totale = $stmtCount->fetchColumn();

                            $numero_pagine = getNumberOfPages($totale, $limit);

                            // Estrazione dei file multimediali associati con relative informazioni di caricamento
                            $stmtFile = $pdo->prepare("
                                SELECT fm.numero, fm.titolo, fm.tipo, u.codice as caricatoDa, u.nickname, u.nome AS owner_nome, u.cognome AS owner_cognome, fm.dimensione, fm.URL 
                                FROM FileAssociatoGruppo fag 
                                JOIN FileMultimediale fm ON fag.file = fm.numero 
                                JOIN Utente u ON u.codice = fm.caricatoDa 
                                WHERE fag.codGruppo = :codice {$whereSql}
                                ORDER BY {$sql_sort} {$sort_dir}
                                LIMIT {$start_from}, {$limit}
                            ");
                            $stmtFile->execute($params);
                            $filesRaw = $stmtFile->fetchAll(PDO::FETCH_ASSOC);

                            $testoPerPagina = ($totale > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                            echo "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>{$totale}</strong> file nel gruppo{$testoPerPagina}</p></div>";

                            if (!empty($filesRaw)) {
                                $datiFiles = [];
                                $icon_types = [
                                    'immagine' => 'images/image.png',
                                    'video'    => 'images/video.png',
                                    'audio'    => 'images/headphones.png',
                                    'default'  => 'images/document.png'
                                ];

                                foreach ($filesRaw as $file) {
                                    $tipo_file   = strtolower($file['tipo']);
                                    $url_file    = $file['URL'];
                                    $id_file     = $file['numero'];
                                    $titolo_file = $file['titolo'];

                                    // Associazione dell'icona grafica appropriata basandoci sul tipo di file multimediale
                                    $icon_path = $icon_types[$tipo_file] ?? $icon_types['default'];
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

                                    $owner_link = "utenti.php?utente=" . urlencode($file['caricatoDa']) . "&return_to=" . urlencode($current_url);

                                    $ownerDisplay = formatOwnerDisplay($file['owner_nome'] ?? null, $file['owner_cognome'] ?? null, $file['nickname']);

                                    // Visualizza la corona distintiva se il file è stato caricato dal proprietario del gruppo
                                    $iconaCorona = ((int)$file['caricatoDa'] === $ownerId) ? " <img src='images/crown.png' alt='Owner' title='Proprietario' class='group-owner-crown'>" : "";

                                    $htmlOwner = "<a href='" . htmlspecialchars($owner_link) . "'>" . $ownerDisplay . "</a>" . $iconaCorona;

                                    $datiFiles[] = [
                                        'File' => $html_colonna_file,
                                        'Proprietario' => $htmlOwner,
                                        'Dimensione' => formatFileSizeHtml((float)$file['dimensione'])
                                    ];
                                }

                                $customHeaders = generaIntestazioniOrdinabili([
                                    'File'       => 'file',
                                    'Proprietario'   => 'proprietario',
                                    'Dimensione' => 'dimensione'
                                ], $sort_col, $sort_dir);

                                // Rendering finale della tabella file e dei link di navigazione tra le pagine
                                echo '<div class="table-container tabella-media">';
                                stampaTabella($datiFiles, ['File', 'Proprietario', 'Dimensione'], $customHeaders);
                                echo '</div>';
                                echo "<div class='pagination-spacer'>";
                                echo getPagesNav($np, $numero_pagine, 1);
                                echo "</div>";
                            } else {
                                echo '<div class="table-container table-container-empty">';
                                echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                                echo '</div>';
                                echo "<div class='pagination-spacer'></div>";
                            }
                        }
                    } else {
                        echo "<p class='info-risultati'>Gruppo non trovato.</p>";
                    }
                } else {
                    /**
                     * -----------------------------------------------------
                     * ELENCO GLOBALE GRUPPI
                     * -----------------------------------------------------
                     */
                    $recordsPerPage = 20;
                    list($limit, $np, $start_from) = getPaginationParams($recordsPerPage);

                    // Configurazione dei parametri di ordinamento consentiti per l'elenco globale dei gruppi
                    $allowed_sorts = [
                        'nome' => 'g.nome',
                        'data' => 'g.dataCreazione',
                        'proprietario' => getOwnerSortExpression(),
                    ];
                    list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento($allowed_sorts, 'nome', 'ASC');

                    // Utilizzo del filtro dinamico centralizzato per l'elenco principale dei gruppi
                    $filtri = applicaFiltriDinamici($_GET, 'gruppi');
                    $where = [];
                    $params = $filtri['parametri'];

                    if (!empty($filtri['sql'])) {
                        $where[] = preg_replace('/^\s*AND\s*/', '', $filtri['sql']);
                    }

                    // Conteggio dei record complessivi dei gruppi per definire il numero di pagine
                    $tabella_count = "Gruppo g JOIN Utente u ON g.creatoDa = u.codice";
                    $totaleRisultati = getNumberOfRecords($pdo, $tabella_count, $where, $params);
                    $numero_pagine = getNumberOfPages($totaleRisultati, $limit);

                    // Composizione della query SQL principale con filtri, ordinamento e paginazione (LIMIT/OFFSET)
                    $sql = "
                        SELECT g.codice as 'gruppoId', g.nome as 'Nome Gruppo', g.dataCreazione as 'Data Creazione', u.nome AS owner_nome, u.cognome AS owner_cognome, u.nickname as 'Proprietario', u.codice as 'ownerId'
                        FROM Gruppo g
                        JOIN Utente u ON g.creatoDa = u.codice
                    ";

                    if (!empty($where)) {
                        $sql .= " WHERE " . implode(" AND ", $where);
                    }

                    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";
                    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$start_from;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $datiOriginale = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $testoPerPagina = ($totaleRisultati > $recordsPerPage) ? " (<strong>{$recordsPerPage}</strong> per pagina)" : "";
                    echo "<div class='table-top-bar'>";
                    echo "<p class='info-risultati zero-margin'>Trovati <strong>{$totaleRisultati}</strong> gruppi{$testoPerPagina}</p>";
                    echo "</div>";

                    if ($datiOriginale) {
                        $datiGruppi = [];
                        foreach ($datiOriginale as $riga) {
                            $linkGruppo = "gruppi.php?gruppo=" . urlencode($riga['gruppoId']) . "&return_to=" . urlencode($current_url);
                            $htmlNomeGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";

                            $linkOwner = "utenti.php?utente=" . urlencode($riga['ownerId']) . "&return_to=" . urlencode($current_url);
                            $ownerDisplay = formatOwnerDisplay($riga['owner_nome'] ?? null, $riga['owner_cognome'] ?? null, $riga['Proprietario']);
                            $htmlProprietario = "<a href='{$linkOwner}'>" . $ownerDisplay . "</a>";

                            $datiGruppi[] = [
                                'Nome Gruppo'    => $htmlNomeGruppo,
                                'Proprietario'   => $htmlProprietario,
                                'Data Creazione' => $riga['Data Creazione']
                            ];
                        }

                        $customHeaders = generaIntestazioniOrdinabili([
                            'Nome Gruppo'    => 'nome',
                            'Proprietario'   => 'proprietario',
                            'Data Creazione' => 'data'
                        ], $sort_col, $sort_dir);

                        // Output della tabella strutturata dei gruppi e del navigatore delle pagine
                        echo '<div class="table-container tabella-gruppi">';
                        stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders);
                        echo '</div>';

                        echo "<div class='pagination-spacer'>";
                        echo getPagesNav($np, $numero_pagine, 1);
                        echo "</div>";
                    } else {
                        echo '<div class="table-container table-container-empty">';
                        echo "<p class='empty-message'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
                        echo '</div>';
                        echo "<div class='pagination-spacer'></div>";
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