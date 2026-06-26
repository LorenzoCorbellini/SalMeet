<?php
/**
 * @file functions.php
 * @description Raccoglie funzioni di utilità generale condivise all'interno dell'applicazione.
 * Fornisce strumenti per l'interazione col database, calcolo dimensioni file, formattazione di date 
 * e stringhe, generazione di tabelle HTML, ordinamento dati, paginazione e dashboard.
 */

// =========================================================
// UTILITIES DI BASE E GESTIONE FILE
// =========================================================

/**
 * Calcola la dimensione massima dei file presenti nel database.
 * * @param int|null $fallback Valore da restituire in caso di errore o tabella vuota.
 * @return int La dimensione massima rilevata, oppure il valore di fallback.
 */
function getMaxFileSizeFromDb(?int $fallback = 0): int
{
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT MAX(dimensione) as max_size FROM FileMultimediale");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['max_size'] !== null) {
            return (int)$result['max_size'];
        }
        return 0; 
    } catch (PDOException $e) {
        error_log("Errore nel calcolo dimensione massima file: " . $e->getMessage());
    }
    
    return $fallback ?? 0;
}

/**
 * Calcola la dimensione minima dei file presenti nel database.
 * * @param int|null $fallback Valore da restituire in caso di errore o tabella vuota.
 * @return int La dimensione minima rilevata, oppure il valore di fallback.
 */
function getMinFileSizeFromDb(?int $fallback = 0): int
{
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT MIN(dimensione) as min_size FROM FileMultimediale");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['min_size'] !== null) {
            return (int)$result['min_size'];
        }
        return 0;
    } catch (PDOException $e) {
        error_log("Errore nel calcolo dimensione minima file: " . $e->getMessage());
    }

    return $fallback ?? 0;
}

// =========================================================
// FORMATTAZIONE E VALIDAZIONE DATI
// =========================================================

/**
 * Verifica se una stringa inizia con un formato data compatibile (YYYY-MM-DD).
 * * @param string $val La stringa da analizzare.
 * @return bool True se la stringa inizia con una data valida, False altrimenti.
 */
function isData(string $val): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $val);
}

/**
 * Formatta una data dal formato standard del database (YYYY-MM-DD) 
 * al formato di lettura europeo (DD/MM/YYYY).
 * * @param string $val La data in formato YYYY-MM-DD.
 * @return string La data formattata o la stringa originale sanificata in caso di errore.
 */
function formattaData(string $val): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($val, 0, 10));
    return $d ? $d->format('d/m/Y') : htmlspecialchars($val);
}

/**
 * Genera l'espressione SQL per l'ordinamento basato sul nominativo completo 
 * o sul nickname del proprietario, rimuovendo caratteri speciali.
 * * @param string $tableAlias L'alias della tabella Utente usato nella query.
 * @return string L'espressione SQL pronta per la clausola ORDER BY.
 */
function getOwnerSortExpression(string $tableAlias = 'u'): string
{
    return "TRIM(REPLACE(REPLACE(REPLACE(CONCAT_WS(' ', {$tableAlias}.nome, {$tableAlias}.cognome, {$tableAlias}.nickname), '@', ''), '(', ''), ')', ''))";
}

/**
 * Restituisce una stringa HTML formattata "Nome Cognome (@nickname)" 
 * applicando l'escaping per la sicurezza XSS.
 * * @param string|null $nome Il nome dell'utente.
 * @param string|null $cognome Il cognome dell'utente.
 * @param string $nickname Il nickname dell'utente.
 * @return string Il markup HTML formattato.
 */
function formatOwnerDisplay(?string $nome, ?string $cognome, string $nickname): string
{
    $fullName = trim(($nome ?? '') . ' ' . ($cognome ?? ''));
    $nicknameSpan = "<span class='owner-nickname'>(@" . htmlspecialchars($nickname) . ")</span>";
    return $fullName !== ''
        ? htmlspecialchars($fullName) . " " . $nicknameSpan
        : $nicknameSpan;
}

/**
 * Verifica che una data sia nel formato YYYY-MM-DD e sia temporalmente 
 * compresa tra il 1 Gennaio 1900 e il termine della giornata odierna.
 *
 * @param string $val La data da validare.
 * @return bool True se la data è valida e nel range, False altrimenti.
 */
function isDataValidaRange(string $val): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        return false;
    }

    try {
        $dataInserita = new DateTime($val);
        $limiteMinimo = new DateTime('1900-01-01');
        $limiteMassimo = new DateTime(); 

        $limiteMassimo->setTime(23, 59, 59);

        return ($dataInserita >= $limiteMinimo && $dataInserita <= $limiteMassimo);
    } catch (Exception $e) {
        return false;
    }
}

// =========================================================
// GENERAZIONE TABELLE HTML
// =========================================================

/**
 * Esegue direttamente l'output (echo) di una tabella HTML generata dinamicamente.
 * * @param array $righe I dati estratti dal database.
 * @param array $htmlColumns Chiavi di colonne contenenti HTML da non sottoporre a escaping.
 * @param array $customHeaders Mapping opzionale per i titoli delle colonne.
 * @return void
 */
function stampaTabella(array $righe, array $htmlColumns = [], array $customHeaders = []): void
{
    echo getTabella($righe, $htmlColumns, $customHeaders);
}

/**
 * Genera la struttura HTML di una tabella analizzando un set di dati, 
 * applicando la formattazione adeguata in base al tipo di dato.
 * * @param array $righe I dati estratti dal database.
 * @param array $htmlColumns Chiavi di colonne contenenti HTML da non sottoporre a escaping.
 * @param array $customHeaders Mapping opzionale per i titoli delle colonne.
 * @return string Il markup HTML completo della tabella o un avviso di assenza dati.
 */
function getTabella(array $righe, array $htmlColumns = [], array $customHeaders = []): string {
    if (empty($righe)) {
        return "<p class='info-risultati'>Nessun risultato trovato con i criteri di ricerca selezionati.</p>";
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
 * Valida i parametri di ordinamento provenienti dalla stringa di query (GET).
 * * @param array $allowed_sorts Dizionario che mappa le chiavi URL alle colonne reali del DB.
 * @param string $default_col Colonna predefinita in assenza di parametri.
 * @param string $default_dir Direzione predefinita ('ASC' o 'DESC').
 * @return array Tuple contenente [chiave_corrente, direzione_corrente, clausola_SQL_sicura].
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
 * Genera l'array di intestazioni HTML personalizzate con i link e le icone per l'ordinamento.
 * * @param array $colonneOrdinabili Mappa associativa ['Intestazione Visibile' => 'chiave_url'].
 * @param string $sort_col La chiave di ordinamento attualmente attiva.
 * @param string $sort_dir La direzione di ordinamento attualmente attiva.
 * @return array Mappa delle intestazioni pronte per l'inserimento in tabella.
 */
function generaIntestazioniOrdinabili(array $colonneOrdinabili, string $sort_col, string $sort_dir): array
{
    $customHeaders = [];

    foreach ($colonneOrdinabili as $titoloVisibile => $chiaveSort) {
        $params = $_GET;
        $params['sort'] = $chiaveSort;

        if ($sort_col === $chiaveSort) {
            $params['dir'] = ($sort_dir === 'ASC') ? 'DESC' : 'ASC';
            $classeIcona = ($sort_dir === 'ASC') ? 'fa-sort-up' : 'fa-sort-down';
            $iconaHTML = "<i class='fa-solid {$classeIcona} icona-ordinamento attiva'></i>";
        } else {
            $params['dir'] = 'ASC';
            $iconaHTML = "<i class='fa-solid fa-sort icona-ordinamento'></i>";
        }

        $url = "?" . build_query_preserve_brackets($params);
        
        $customHeaders[$titoloVisibile] = "
            <a href='{$url}' class='filter__link'>
                " . htmlspecialchars($titoloVisibile) . " {$iconaHTML}
            </a>
        ";
    }

    return $customHeaders;
}

/**
 * Costruisce una query string gestendo correttamente gli array passati via URL,
 * preservando la sintassi `chiave[]=` anziché forzare gli indici numerici.
 * * @param array $params L'array dei parametri GET.
 * @return string La stringa URL formattata ed encoded.
 */
function build_query_preserve_brackets(array $params): string {
    $parts = [];
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $val) {
                $parts[] = rawurlencode($k) . '[]=' . rawurlencode((string)$val);
            }
        } else {
            $parts[] = rawurlencode($k) . '=' . rawurlencode((string)$v);
        }
    }
    return implode('&', $parts);
}

// =========================================================
// PAGINAZIONE
// =========================================================

/**
 * Genera la struttura HTML per la navigazione delle pagine (paginazione).
 * Mantiene la stabilità visiva calcolando dinamicamente il range di visualizzazione.
 *
 * @param int $np Il numero della pagina corrente (1-based).
 * @param int $pagine_totali Il numero complessivo di pagine.
 * @param int $range Finestra di pagine da mostrare a sinistra e a destra di quella corrente.
 * @param string $justify Allineamento CSS per il contenitore.
 * @return string Il markup HTML della paginazione.
 */
function getPagesNav(int $np, int $pagine_totali, int $range = 1, string $justify = "auto"): string {
    if ($pagine_totali <= 1) {
        return "";
    }

    $html = "<div class='pagination-container' style='justify-self: $justify;'>";
    
    $prev = $np - 1;
    $next = $np + 1;
    $leftArrowHTML = getLeftArrow();
    $rightArrowHTML = getRightArrow();

    $queryParams = array_diff_key($_GET, ['pagina' => '']);
    $query = http_build_query($queryParams);
    $queryString = !empty($query) ? "&$query" : "";

    if ($np > 1) {
        $html .= "<a href='?pagina=$prev$queryString' class='page-item arrow'>$leftArrowHTML</a>";
    } else {
        $html .= "<span class='page-item arrow disabled'>$leftArrowHTML</span>";
    }

    $start = max(1, $np - $range);
    if ($start > 1) {
        $class = 'page-item text-muted';
        $html .= "<a href='?pagina=1$queryString' class='$class'>1</a>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    if ($np - $range > 2) {
        $html .= "<span class='page-item dots'>...</span>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    $centro_start = $np - $range;
    $centro_end = $np + $range;

    for ($i = $centro_start; $i <= $centro_end; $i++) {
        if ($i >= 1 && $i <= $pagine_totali) {
            $active = ($i == $np) ? "active" : "";
            $html .= "<a href='?pagina=$i$queryString' class='page-item $active'>$i</a>";
        } else {
            $html .= "<span class='page-item dots'>.</span>";
        }
    }

    if ($pagine_totali - $np - $range > 1) {
        $html .= "<span class='page-item dots'>...</span>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    $end = min($np + $range, $pagine_totali);
    if ($end < $pagine_totali) {
        $class = 'page-item text-muted';
        $html .= "<a href='?pagina=$pagine_totali$queryString' class='$class'>$pagine_totali</a>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    if ($np < $pagine_totali) {
        $html .= "<a href='?pagina=$next$queryString' class='page-item arrow'>$rightArrowHTML</a>";
    } else {
        $html .= "<span class='page-item arrow disabled'>$rightArrowHTML</span>";
    }

    $html .= "</div>";
    return $html;
}

/**
 * Estrae e calcola da GET i parametri limit e offset necessari per la paginazione SQL.
 * * @param int $default_limit Il numero predefinito di record per pagina.
 * @return array Tuple contenente [limite, pagina_corrente, offset_partenza].
 */
function getPaginationParams(int $default_limit = 50): array {
    $limit = $default_limit;
    $np = !empty($_GET["pagina"]) ? max(1, (int)$_GET["pagina"]) : 1;
    $start_from = ($np - 1) * $limit;
    
    return [$limit, $np, $start_from];
}

/**
 * Conta il numero totale di record per una specifica tabella applicando condizioni opzionali.
 * * @param PDO $pdo L'istanza di connessione al database.
 * @param string $table Il nome della tabella da interrogare.
 * @param array $where Array di clausole WHERE (es. "campo = ?").
 * @param array $params I parametri da bindare in fase di esecuzione.
 * @return int Il numero totale dei record calcolati.
 */
function getNumberOfRecords(PDO $pdo, string $table, array $where = [], array $params = []): int {
    $sql_count = "SELECT COUNT(*) FROM " . $table;
    if ($where) $sql_count .= " WHERE " . implode(" AND ", $where);

    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    return (int)$stmt_count->fetchColumn();
}

/**
 * Calcola il numero totale di pagine necessarie per mostrare un dato set di record.
 * * @param int $records_num Totale record estratti.
 * @param int $limit Numero di elementi visualizzati per pagina.
 * @return int Il numero totale delle pagine.
 */
function getNumberOfPages(int $records_num, int $limit): int {
    return (int)ceil($records_num / $limit);
}

/**
 * Restituisce il markup SVG per l'icona direzionale di destra.
 * * @return string HTML dell'SVG.
 */
function getRightArrow(): string {
   return '
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m9 18 6-6-6-6"></path>
    </svg>
    ';
}

/**
 * Restituisce il markup SVG per l'icona direzionale di sinistra.
 * * @return string HTML dell'SVG.
 */
function getLeftArrow(): string {
    return '
    <svg data-v-b31b885d-s="" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
        <path d="m15 18-6-6 6-6"></path>
    </svg>';
}

// =========================================================
// ASSISTENTI FORMATTAZIONE E CONVERSIONE FILE SIZE
// =========================================================

/**
 * Converte la dimensione numerica di un file in un formato leggibile (B, KB, MB, GB).
 * * @param int $filesize La dimensione grezza del file.
 * @return string La dimensione arrotondata accoppiata con la relativa unità di misura.
 */
function formatFileSize(int $filesize): string {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
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
 * Converte una dimensione numerica restituendola come array separato per valore e unità.
 * * @param int $filesize La dimensione grezza del file.
 * @return array{size: float|int, unit: string} Array associativo con 'size' e 'unit'.
 */
function formatFileSize2(int $filesize): array {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $formattedSize = $filesize;
    $index = 0;
    for ($i = 0; $filesize >= 1000 && $i < count($units) - 1; $i++) {
        $filesize /= 1000;
        $formattedSize = round($filesize, 2);
        $index++;
    }

    return ['size' => $formattedSize, 'unit' => $units[$index]];
}

/**
 * Genera il contenitore HTML che mostra la dimensione del file includendo il dato grezzo nel tooltip.
 * * @param int $size La dimensione originale da formattare.
 * @return string L'elemento HTML formattato.
 */
function formatFileSizeHtml(int $size): string {
    $size_formatted = formatFileSize($size);
    return "<div id='file_size' title='$size B'>$size_formatted</div>";
}

// =========================================================
// SLIDER LOGARITMICI E CONVERSIONI
// =========================================================

/**
 * Converte un valore reale in una posizione su una scala logaritmica.
 *
 * @param int $value Valore reale della dimensione.
 * @param int $min Valore minimo reale.
 * @param int $max Valore massimo reale.
 * @param int $steps Numero di frazionamenti della scala.
 * @return int La posizione calcolata per lo slider.
 */
function getLogSliderPosition(int $value, int $min, int $max, int $steps = 1000): int {
    if ($steps <= 0 || $max <= $min) {
        return 0;
    }
    $effectiveMin = max(1, $min);
    if ($value <= $min) {
        return 0;
    }
    $valueClamped = max($value, $effectiveMin);
    $logMin = log($effectiveMin);
    $logMax = log(max($max, $effectiveMin + 1));
    $logValue = log($valueClamped);
    $ratio = ($logValue - $logMin) / max(1e-9, $logMax - $logMin);
    return (int) round(max(0, min($steps, $ratio * $steps)));
}

/**
 * Converte una posizione di uno slider logaritmico al suo valore reale stimato.
 *
 * @param int $position Posizione attuale dello slider.
 * @param int $min Valore minimo reale.
 * @param int $max Valore massimo reale.
 * @param int $steps Numero di frazionamenti della scala.
 * @return int Valore reale riconvertito.
 */
function getLogSliderValue(int $position, int $min, int $max, int $steps = 1000): int {
    if ($steps <= 0 || $max <= $min) {
        return $min;
    }
    $effectiveMin = max(1, $min);
    if ($position <= 0) {
        return $min;
    }
    if ($position >= $steps) {
        return $max;
    }
    $logMin = log($effectiveMin);
    $logMax = log(max($max, $effectiveMin + 1));
    $ratio = $position / $steps;
    $value = exp($logMin + ($logMax - $logMin) * $ratio);
    return (int) round($value);
}

// =========================================================
// FUNZIONI PER LA DASHBOARD (index.php)
// =========================================================

/**
 * Calcola la somma totale in byte dello spazio occupato da tutti i file a sistema.
 * * @param PDO $pdo L'istanza di connessione al database.
 * @return int Il totale cumulativo in byte.
 */
function getSpazioTotaleOccupato(PDO $pdo): int {
    $stmt = $pdo->query("SELECT SUM(dimensione) FROM FileMultimediale");
    return (int)$stmt->fetchColumn();
}

/**
 * Recupera i dati relativi agli ultimi file caricati per popolare la dashboard.
 * * @param PDO $pdo L'istanza di connessione al database.
 * @param int $limite Limite massimo di record da restituire.
 * @return array I record formattati degli ultimi caricamenti.
 */
function getUltimiFileCaricati(PDO $pdo, int $limite = 5): array {
    $sql = "SELECT f.titolo AS nome_file, f.tipo AS tipo_file, f.dimensione, f.URL AS url, 
                   u.codice AS owner_id, u.nickname, u.nome AS owner_name, u.cognome AS owner_surname 
            FROM FileMultimediale f 
            LEFT JOIN Utente u ON f.caricatoDa = u.codice 
            ORDER BY f.numero DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Estrae le informazioni degli ultimi gruppi formatisi all'interno del sistema.
 * * @param PDO $pdo L'istanza di connessione al database.
 * @param int $limite Limite massimo di record da restituire.
 * @return array I record dei gruppi recenti.
 */
function getUltimiGruppiCreati(PDO $pdo, int $limite = 5): array {
    $sql = "SELECT g.codice AS gruppoId, g.nome AS nome_gruppo, g.dataCreazione, 
                   u.codice AS owner_id, u.nickname, u.nome AS owner_name, u.cognome AS owner_surname 
            FROM Gruppo g 
            LEFT JOIN Utente u ON g.creatoDa = u.codice 
            ORDER BY g.dataCreazione DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Estrae i record relativi alle bacheche più recenti registrate.
 * * @param PDO $pdo L'istanza di connessione al database.
 * @param int $limite Limite massimo di record da restituire.
 * @return array L'elenco delle ultime bacheche create.
 */
function getUltimeBachecheCreate(PDO $pdo, int $limite = 5): array {
    $sql = "SELECT b.nome AS nome_bacheca, b.dataCreazione, 
                   u.codice AS owner_id, u.nickname, u.nome AS owner_name, u.cognome AS owner_surname 
            FROM Bacheca b 
            LEFT JOIN Utente u ON b.codiceUtente = u.codice 
            ORDER BY b.dataCreazione DESC LIMIT :limite";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}