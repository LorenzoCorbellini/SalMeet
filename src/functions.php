<?php
// =========================================================
// UTILITIES DI BASE
// =========================================================

/**
 * Calcola la dimensione massima di file presenti nel database
 * @param int|null $fallback Valore di fallback se la query fallisce
 * @return int Dimensione massima in MB oppure il fallback
 */
function getMaxFileSizeFromDb(?int $fallback = 0): int
{
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT MAX(dimensione) as max_size FROM FileMultimediale");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se il risultato è NULL (tabella vuota), restituiamo 0
        if ($result && $result['max_size'] !== null) {
            return (int)$result['max_size'];
        }
        return 0; 
    } catch (PDOException $e) {
        // Se c'è un errore, usa il fallback
        error_log("Errore nel calcolo dimensione massima file: " . $e->getMessage());
    }
    
    // Fallback in caso di eccezione
    return $fallback ?? 0;
}

/**
 * Calcola la dimensione minima dei file presenti nel database
 * @param int|null $fallback Valore di fallback se la query fallisce
 * @return int Dimensione minima in MB oppure il fallback
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

function isData(string $val): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $val);
}

function formattaData(string $val): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($val, 0, 10));
    return $d ? $d->format('d/m/Y') : htmlspecialchars($val);
}

function getOwnerSortExpression(string $tableAlias = 'u'): string
{
    return "TRIM(REPLACE(REPLACE(REPLACE(CONCAT_WS(' ', {$tableAlias}.nome, {$tableAlias}.cognome, {$tableAlias}.nickname), '@', ''), '(', ''), ')', ''))";
}

/**
 * Restituisce la stringa formatta "Nome Cognome (@nickname)" per la colonna Proprietario
 * @param mixed $nome
 * @param mixed $cognome
 * @param string $nickname
 * @return string
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

        // Controlliamo se questa è la colonna per cui stiamo ordinando ORA
        if ($sort_col === $chiaveSort) {
            // Se clicco di nuovo, inverto l'ordinamento
            $params['dir'] = ($sort_dir === 'ASC') ? 'DESC' : 'ASC';
            
            // Icona con freccia SINGOLA (su o giù) e classe 'attiva' per tenerla sempre visibile
            $classeIcona = ($sort_dir === 'ASC') ? 'fa-sort-up' : 'fa-sort-down';
            $iconaHTML = "<i class='fa-solid {$classeIcona} icona-ordinamento attiva'></i>";
        } else {
            // Se non è ordinata, il prossimo clic ordinerà per ASC
            $params['dir'] = 'ASC';
            
            // Icona DOPPIA freccia e nessuna classe 'attiva' (nascosta di default)
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
 * Costruisce una query string da un array di parametri preservando i parametri array
 * come `key[]=` (ripetuti) invece di `key[0]=` con indici numerici.
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

/**
 * Genera la struttura HTML per la navigazione delle pagine (paginazione) a larghezza fissa.
 * * Mantiene lo stesso numero di elementi sullo schermo sostituendo i link non disponibili
 * con punti di sospensione o frecce disabilitate, garantendo la stabilità visiva.
 *
 * @param int    $np             Il numero della pagina corrente (1-based).
 * @param int    $pagine_totali  Il numero complessivo di pagine disponibili.
 * @param int    $range          Il numero di pagine da mostrare a sinistra e a destra di quella corrente. Default: 1.
 * @param string $justify        L'allineamento CSS per la proprietà `justify-self`. Default: "auto".
 * * @return string La stringa HTML contenente i link di paginazione, oppure una stringa vuota
 * se il numero totale di pagine è inferiore o uguale a 1.
 */
function getPagesNav(int $np, int $pagine_totali, int $range = 1, string $justify = "auto"): string {
    if ($pagine_totali <= 1) {
        return "";
    }

    //Stile dinamico inline
    $html = "<div class='pagination-container' style='justify-self: $justify;'>";
    
    $prev = $np - 1;
    $next = $np + 1;
    $leftArrowHTML = getLeftArrow();
    $rightArrowHTML = getRightArrow();

    $queryParams = array_diff_key($_GET, ['pagina' => '']);
    $query = http_build_query($queryParams);
    $queryString = !empty($query) ? "&$query" : "";

    // --- FRECCIA SINISTRA ---
    if ($np > 1) {
        $html .= "<a href='?pagina=$prev$queryString' class='page-item arrow'>$leftArrowHTML</a>";
    } else {
        $html .= "<span class='page-item arrow disabled'>$leftArrowHTML</span>";
    }

    // --- PRIMA PAGINA (1) ---
    // Se la finestra centrale include già la pagina 1, mostriamo un segnaposto per non duplicarla
    $start = max(1, $np - $range);
    if ($start > 1) {
        $class = 'page-item text-muted';
        $html .= "<a href='?pagina=1$queryString' class='$class'>1</a>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    // --- PUNTI DI SOSPENSIONE A SINISTRA ---
    if ($np - $range > 2) {
        $html .= "<span class='page-item dots'>...</span>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    // --- FINESTRA CENTRALE (RANGE) ---
    $centro_start = $np - $range;
    $centro_end = $np + $range;

    for ($i = $centro_start; $i <= $centro_end; $i++) {
        if ($i >= 1 && $i <= $pagine_totali) {
            $active = ($i == $np) ? "active" : "";
            $html .= "<a href='?pagina=$i$queryString' class='page-item $active'>$i</a>";
        } else {
            // Se usciamo dai bordi (es. pagina -1 o oltre il totale), stampiamo un punto vuoto strutturale
            $html .= "<span class='page-item dots'>.</span>";
        }
    }

    // --- PUNTI DI SOSPENSIONE A DESTRA ---
    if ($pagine_totali - $np - $range > 1) {
        $html .= "<span class='page-item dots'>...</span>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    // --- ULTIMA PAGINA ---
    $end = min($np + $range, $pagine_totali);
    if ($end < $pagine_totali) {
        $class = 'page-item text-muted';
        $html .= "<a href='?pagina=$pagine_totali$queryString' class='$class'>$pagine_totali</a>";
    } else {
        $html .= "<span class='page-item dots'>.</span>";
    }

    // --- FRECCIA DESTRA ---
    if ($np < $pagine_totali) {
        $html .= "<a href='?pagina=$next$queryString' class='page-item arrow'>$rightArrowHTML</a>";
    } else {
        $html .= "<span class='page-item arrow disabled'>$rightArrowHTML</span>";
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
 * Converte una dimensione numerica in una struttura leggibile composta da valore e unità.
 *
 * @param int $filesize Dimensione del file in unità base (ad esempio byte o kilobyte
 * a seconda del contesto del database).
 * @return array{size: float|int, unit: string} Restituisce un array associativo contenente:
 * - 'size': il valore numerico convertito e arrotondato a 2 decimali,
 * oppure l'intero originale se non è necessaria la conversione.
 * - 'unit': l'unità di misura corrispondente ('B', 'KB', 'MB', 'GB', 'TB').
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
 * Converte un valore reale in una posizione su una scala logaritmica.
 *
 * @param int $value Valore reale della dimensione (MB).
 * @param int $min Valore minimo reale.
 * @param int $max Valore massimo reale.
 * @param int $steps Numero di passi della scala.
 * @return int Posizione dello slider.
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
 * Converte una posizione dello slider logaritmico in un valore reale.
 *
 * @param int $position Posizione dello slider.
 * @param int $min Valore minimo reale.
 * @param int $max Valore massimo reale.
 * @param int $steps Numero di passi della scala.
 * @return int Valore reale corrispondente.
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

/**
 * Genera il wrapper HTML per la visualizzazione della dimensione del file nelle tabelle.
 *
 * Restituisce un elemento `<div>` stilizzato con l'identificativo `file_size`. 
 * Include un attributo `title` nativo che mostra il valore grezzo in MB al passaggio 
 * del mouse (tooltip), utile per mantenere l'accessibilità del dato originale.
 * * @param int $size La dimensione grezza del file da passare a {@see formatFileSize()}.
 * @return string Il blocco HTML (`<div>`) pronto per essere renderizzato a schermo.
 */
function formatFileSizeHtml(int $size): string {
    $size_formatted = formatFileSize($size);
    return "<div id='file_size' title='$size B'>$size_formatted</div>";
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
 * Recupera gli ultimi N gruppi creati, ordinati per data di creazione.
 */
function getUltimiGruppiCreati(PDO $pdo, int $limite = 5): array {
    // CORREZIONE: Sostituito g.nome con g.codice per catturare l'ID reale del gruppo
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
 * Recupera le ultime N bacheche create, ordinate per data di creazione.
 */
function getUltimeBachecheCreate(PDO $pdo, int $limite = 5): array {
    // Aggiunto b.dataCreazione alla SELECT
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