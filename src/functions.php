<?php
// =========================================================
// UTILITIES DI BASE
// =========================================================
function isData(string $val): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $val);
}

function formattaData(string $val): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($val, 0, 10));
    return $d ? $d->format('d/m/Y') : htmlspecialchars($val);
}

/**
 * Verifica che una data sia nel formato corretto e compresa 
 * tra il 1 Gennaio 1900 e il giorno corrente (incluso).
 *
 * @param string $val La data in formato YYYY-MM-DD
 * @return bool True se valida e nel range, False altrimenti.
 */
function isDataValidaRange(string $val): bool
{
    // 1. Controllo base sul formato (usa la tua Regex per assicurarsi che sia YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        return false;
    }

    try {
        // 2. Crea gli oggetti DateTime per i confronti
        $dataInserita = new DateTime($val);
        $limiteMinimo = new DateTime('1900-01-01');
        $limiteMassimo = new DateTime(); // Prende in automatico data e ora di oggi

        // Imposta l'orario del limite massimo alle 23:59:59 di oggi
        // per permettere inserimenti relativi alla giornata odierna
        $limiteMassimo->setTime(23, 59, 59);

        // 3. Verifica i range (>= 1 Gennaio 1900 E <= Oggi)
        return ($dataInserita >= $limiteMinimo && $dataInserita <= $limiteMassimo);

    } catch (Exception $e) {
        // Se la data è un calendario impossibile (es: 2023-13-45), DateTime lancia eccezione
        return false;
    }
}

// Risultato query, titoli, titoli custom
function stampaTabella(array $righe, array $htmlColumns = [], array $customHeaders = []): void
{
    echo getTabella($righe, $htmlColumns, $customHeaders);
}

function getTabella(array $righe, array $htmlColumns = [], array $customHeaders = []): string {
    if (empty($righe)) {
        return "<p class='info-risultati'>Nessun risultato trovato.</p>";
    }

    $html = "<table border='1'><tr>";
    foreach (array_keys($righe[0]) as $colonna) {
        $titolo = isset($customHeaders[$colonna]) ? $customHeaders[$colonna] : htmlspecialchars($colonna);
        $html .= "<th>" . $titolo . "</th>";
    }
    $html .= "</tr>";
    foreach ($righe as $riga) {
        $html .= "<tr>";
        foreach ($riga as $colonna => $valore) {
            $val = (string) $valore;

            if (in_array($colonna, $htmlColumns, true)) {
                $html .= "<td>" . $val . "</td>";
            } elseif (is_numeric($val)) {
                $html .= "<td class='numero'>" . htmlspecialchars($val) . "</td>";
            } elseif (isData($val)) {
                $html .= "<td class='data'>"   . formattaData($val) . "</td>";
            } else {
                $html .= "<td>"                . htmlspecialchars($val) . "</td>";
            }
        }
        $html .= "</tr>";
    }
    $html .= "</table>";
    return $html;
}

// =========================================================
// GESTIONE ORDINAMENTO (ASC/DESC)
// =========================================================

/**
 * Valida i parametri di ordinamento passati in GET
 * @param array $allowed_sorts Dizionario con chiave url => colonna DB
 * @param string $default_col Colonna di default
 * @param string $default_dir Direzione di default
 * @return array [Chiave attuale, Direzione attuale, Stringa per ORDER BY]
 */
function getParametriOrdinamento(array $allowed_sorts, string $default_col = 'data', string $default_dir = 'DESC'): array
{
    $sort_col = $_GET['sort'] ?? $default_col;
    $sort_dir = strtoupper($_GET['dir'] ?? $default_dir);

    if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
        $sort_dir = $default_dir;
    }

    $sql_sort = $allowed_sorts[$sort_col] ?? $allowed_sorts[$default_col];

    return [$sort_col, $sort_dir, $sql_sort];
}

/**
 * Genera l'array per $customHeaders di stampaTabella, con le icone per l'ordinamento
 * @param array $colonneOrdinabili ['Intestazione Visibile' => 'chiave_url']
 * @param string $sort_col La chiave di ordinamento attuale
 * @param string $sort_dir La direzione di ordinamento attuale
 * @return array Array formattato di intestazioni HTML
 */
function generaIntestazioniOrdinabili(array $colonneOrdinabili, string $sort_col, string $sort_dir): array
{
    $customHeaders = [];

    foreach ($colonneOrdinabili as $titoloVisibile => $chiaveSort) {
        $params = $_GET;
        $params['sort'] = $chiaveSort;

        $params['dir'] = ($sort_col === $chiaveSort && $sort_dir === 'ASC') ? 'DESC' : 'ASC';

        $url = "?" . http_build_query($params);
        $icona = "<img src='images/bi-directional-arrow.png' alt='Ordina' class='icona-ordinamento'>";
        $customHeaders[$titoloVisibile] = "
            <a href='{$url}' style='text-decoration: none; color: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 5px; width: 100%; height: 100%;'>
                " . htmlspecialchars($titoloVisibile) . " {$icona}
            </a>
        ";
    }

    return $customHeaders;
}

/**
 * Genera la struttura HTML per la navigazione delle pagine (paginazione).
 * 
 * Gestisce la visualizzazione dinamica dei numeri di pagina limitando il range visibile
 * attorno alla pagina corrente e inserendo i punti di sospensione (...) per le pagine omesse.
 * Include le frecce di navigazione avanti/indietro se necessario e permette l'allineamento CSS.
 *
 * @param int    $np             Il numero della pagina corrente (1-based).
 * @param int    $pagine_totali  Il numero complessivo di pagine disponibili.
 * @param int    $range          Il numero di pagine da mostrare a sinistra e a destra di quella corrente. Default: 1.
 * @param string $justify        L'allineamento CSS per la proprietà `justify-self`. Default: "auto".
 * 
 * @return string La stringa HTML contenente i link di paginazione, oppure una stringa vuota
 *                se il numero totale di pagine è inferiore o uguale a 1.
 */
function getPagesNav(int $np,
    int $pagine_totali,
    int $range=1,
    string $justify = "auto",): string {
    // Se c'è solo una pagina, non serve mostrare la navigazione
    if ($pagine_totali <= 1 ) {
        return "";
    }

    $html = "<div class='pagination-container' style='justify-self: $justify;'>";
    
    // Valore a sx di $np
    $start = max(1, $np - $range);
    // Valore a dx di $np
    $end = min($np + $range, $pagine_totali);

    $prev = $np - 1;
    $next = $np + 1;

    $leftArrowHTML = getLeftArrow();
    $rightArrowHTML = getRightArrow();

    // Togliamo 'pagina=n' che sta nella $_GET quando clicchiamo più volte il bottone
    $queryParams = array_diff_key($_GET, ['pagina' => '']);
    $query = http_build_query($queryParams);

    //TODO: rimuovere '&' in fondo all'url quando $query non ha parametri

    if ($np - 1 > 1) {
        $html .= "<a href='?pagina=$prev&$query' class='page-item arrow'>$leftArrowHTML</a>";
        if ($np - 1 > 2) $html .= "<a href='?pagina=1&$query' class='page-item text-muted'>1</a>";
        else $html .= "<a href='?pagina=1&$query' class='page-item'>1</a>";
        // if ($np - 1 > 2) $html .= '<span class="page-dots">...</span>';
    }
    for ($i=$start; $i <= $end; $i++) {
        $active = "";
        if ($i == $np) $active = "active";
        $html .= "<a href='?pagina=$i&$query' class='page-item $active'>$i</a>";
    }
    if ($pagine_totali - $np > 1) {
        // if ($pagine_totali - $np > 2) $html .= '<span class="page-dots">...</span>';
        if ($pagine_totali - $np > 2) $html .= "<a href='?pagina=$pagine_totali&$query' class='page-item text-muted'>$pagine_totali</a>";
        else $html .= "<a href='?pagina=$pagine_totali&$query' class='page-item'>$pagine_totali</a>";
        $html .= "<a href='?pagina=$next&$query' class='page-item arrow'>$rightArrowHTML</a>";
    }
    
    $html .= "</div>";
    return $html;
}
// =========================================================
// ASSISTENTI DI CALCOLO PER LA PAGINAZIONE
// =========================================================

/**
 * Estrae e calcola i parametri numerici per la paginazione da $_GET.
 */
function getPaginationParams(int $default_limit = 50): array {
    $limit = $default_limit;
    $np = !empty($_GET["pagina"]) ? max(1, (int)$_GET["pagina"]) : 1;
    $start_from = ($np - 1) * $limit;
    
    return [$limit, $np, $start_from];
}

/**
 * Conta i record totali in modo agnostico rispetto alla tabella.
 */
function getNumberOfRecords(PDO $pdo, string $table, array $where = [], array $params = []): int {
    $sql_count = "SELECT COUNT(*) FROM " . $table;
    if ($where) $sql_count .= " WHERE " . implode(" AND ", $where);

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    return (int)$stmt_count->fetchColumn();
}

/**
 * Calcola il numero totale di pagine.
 */
function getNumberOfPages(int $records_num, int $limit): int {
    return (int)ceil($records_num / $limit);
}

function getRightArrow(): string {
   $html = '
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m9 18 6-6-6-6"></path>
    </svg>
    ';

    return $html;
}

function getLeftArrow(): string {
    $html = '
    <svg data-v-b31b885d-s="" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
        <path d="m15 18-6-6 6-6"></path>
    </svg>';

    return $html;
}

/**
 * Formatta una dimensione in byte/kilobyte in un formato leggibile (MB, GB, TB).
 *
 * La funzione converte iterativamente la dimensione passata dividendo per 1000
 * (notazione commerciale/SI) fino a raggiungere l'unità di misura corretta, 
 * arrotondando il risultato a due cifre decimali.
 *
 * @param int $filesize La dimensione del file espressa nell'unità base (KB/Byte a seconda del DB).
 * @return string La stringa formattata contenente il valore numerico e l'unità di misura (es. "4.25 MB").
 */
function formatFileSize(int $filesize): string {
    $units = array('MB', 'GB', 'TB');
    $formattedSize = $filesize;
	$index = 0;
    for ($i = 0; $filesize >= 1000 && $i < count($units) - 1; $i++) {
        $filesize /= 1000;
        $formattedSize = round($filesize, 2);
		$index++;
    }

    return $formattedSize . ' ' . $units[$index];
}

/**
 * Genera il wrapper HTML per la visualizzazione della dimensione del file nelle tabelle.
 *
 * Restituisce un elemento `<div>` stilizzato con l'identificativo `file_size`. 
 * Include un attributo `title` nativo che mostra il valore grezzo in MB al passaggio 
 * del mouse (tooltip), utile per mantenere l'accessibilità del dato originale.
 * 
 * @param int $size La dimensione grezza del file da passare a {@see formatFileSize()}.
 * @return string Il blocco HTML (`<div>`) pronto per essere renderizzato a schermo.
 */
function formatFileSizeHtml(int $size): string {
    $size_formatted = formatFileSize($size);
    return "<div id='file_size' title='$size MB'>$size_formatted</div>";
}

// =========================================================
// FUNZIONI PER LA DASHBOARD (index.php)
// =========================================================

/**
 * Calcola la somma totale dello spazio occupato da tutti i file.
 */
function getSpazioTotaleOccupato($pdo) {
    $stmt = $pdo->query("SELECT SUM(dimensione) FROM FileMultimediale");
    return (int)$stmt->fetchColumn();
}

/**
 * Recupera gli ultimi N file caricati nel sistema.
 */
function getUltimiFileCaricati($pdo, $limite = 5) {
    $sql = "SELECT f.titolo AS nome_file, u.nickname AS autore, f.tipo AS tipo_file, f.dimensione, f.URL AS url 
            FROM FileMultimediale f 
            LEFT JOIN Utente u ON f.caricatoDa = u.codice 
            ORDER BY f.numero DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recupera gli ultimi N gruppi creati, ordinati per data di creazione.
 */
function getUltimiGruppiCreati(PDO $pdo, int $limite = 5): array {
    // CORREZIONE: Sostituito g.nome con g.codice per catturare l'ID reale del gruppo
    $sql = "SELECT g.codice AS gruppoId, g.nome AS nome_gruppo, u.nickname AS proprietario, u.codice AS ownerId, g.dataCreazione 
            FROM Gruppo g 
            LEFT JOIN Utente u ON g.creatoDa = u.codice 
            ORDER BY g.dataCreazione DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recupera le ultime N bacheche create, ordinate per data di creazione.
 */
function getUltimeBachecheCreate(PDO $pdo, int $limite = 5): array {
    // Aggiunto b.dataCreazione alla SELECT
    $sql = "SELECT b.nome AS nome_bacheca, u.nickname AS proprietario, u.codice AS ownerId, b.dataCreazione 
            FROM Bacheca b 
            LEFT JOIN Utente u ON b.codiceUtente = u.codice 
            ORDER BY b.dataCreazione DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}