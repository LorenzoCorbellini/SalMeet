<?php
function isData(string $val): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $val);
}

function formattaData(string $val): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($val, 0, 10));
    return $d ? $d->format('d/m/Y') : htmlspecialchars($val);
}

function stampaTabella(array $righe): void
{
    if (empty($righe)) {
        echo "<p>Nessun risultato trovato.</p>";
        return;
    }
    echo "<table border='1'><tr>";
    foreach (array_keys($righe[0]) as $colonna) {
        echo "<th>" . htmlspecialchars($colonna) . "</th>";
    }
    echo "</tr>";
    foreach ($righe as $riga) {
        echo "<tr>";
        foreach ($riga as $valore) {
            $val = (string) $valore;
            if (is_numeric($val))  echo "<td class='numero'>" . htmlspecialchars($val) . "</td>";
            elseif (isData($val))  echo "<td class='data'>"   . formattaData($val) . "</td>";
            else                   echo "<td>"                 . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Helper per link di ritorno
function urlRitorno(): string {
	$p = $_GET;
	unset($p['vista'], $p['bacheca'], $p['owner']);
	$q = http_build_query($p);
	return 'bacheche.php' . ($q ? "?$q" : '');
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

    if ($np - 1 > 1) {
        $html .= "<a href='?pagina=$prev' class='page-item arrow'>$leftArrowHTML</a>";
        $html .= "<a href='?pagina=1' class='page-item'>1</a>";
        if ($np - 1 > 2) $html .= '<span class="page-dots">...</span>';
    }
    for ($i=$start; $i <= $end; $i++) {
        $active = "";
        if ($i == $np) $active = "active";
        $html .= "<a href='?pagina=$i' class='page-item $active'>$i</a>";
    }
    if ($pagine_totali - $np > 1) {
        if ($pagine_totali - $np > 2) $html .= '<span class="page-dots">...</span>';
        $html .= "<a href='?pagina=$pagine_totali' class='page-item'>$pagine_totali</a>";
        $html .= "<a href='?pagina=$next' class='page-item arrow'>$rightArrowHTML</a>";
    }
    
    $html .= "</div>";
    return $html;
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
 * Genera la tabella HTML per la visualizzazione dei file multimediali.
 *
 * La funzione riceve i record estratti dal database e restituisce una stringa 
 * contenente la struttura HTML di una tabella. Gestisce la mappatura dei nomi 
 * delle colonne in italiano, l'occultamento di chiavi sensibili o tecniche (blacklist), 
 * l'aggiunta di icone specifiche in base al tipo di file e la formattazione dei link 
 * per i file e per i profili dei proprietari.
 *
 * @param array $righe          Insieme dei record dei file estratti dal DB (array associativo multidimensionale).
 * Ogni riga deve contenere: 'title', 'size', 'type', 'url', 'nickname', 'owner'.
 * @param int   $numero_records Il numero totale di file trovati nel database (rispettando i filtri attivi, prima del LIMIT).
 * @param int   $limit          Il numero massimo di record visualizzabili per singola pagina.
 * * @return string Stringa HTML contenente il riepilogo dei record e la tabella formattata, 
 * oppure un messaggio di avviso se l'array delle righe è vuoto.
 */
function get_media_table(array $righe, int $numero_records, int $limit): string {
    if (empty($righe)) {
        return "<p>Nessun file trovato.</p>";
    }

    $html = "<p>Trovati <strong>$numero_records</strong> file ($limit per pagina).</p>";

    $html .= "<table border='1'><tr>";

    $mappa_colonne = [
        'title'     => 'File',
        'size' 		=> 'Dimensione',
        'type'      => 'Tipo',
        'nickname'  => 'Proprietario'
    ];
    
    /* Imposta i nomi delle colonne,
        * saltando quelle nella blacklist.
        * Il parame 'true' di 'in_array(...)' impone strict comparison
        */
    $blacklist = ['owner', 'file_id', 'url', 'type'];
    foreach (array_keys($righe[0]) as $colonna) {
        if (in_array($colonna, $blacklist, true)) continue;
        $titolo_visibile = $mappa_colonne[$colonna] ?? ucfirst($colonna);
        $html .= "<th>" . htmlspecialchars($titolo_visibile) . "</th>";
    }

    $html .= "</tr>";
    
    $icon_types = [
        'immagine' => 'images/image.png',
        'video' => 'images/video.png',
        'audio' => 'images/headphones.png',

        'default' => 'images/document.png'
    ];

    foreach ($righe as $riga) {
        $html .= "<tr>";

        // $item as $key => $value
        foreach ($riga as $colonna => $valore) {
            $val = (string) $valore;
            if (in_array($colonna, $blacklist, true)) {
                continue;
            // Mostra link cliccabile sui nomi dei file
            } elseif ($colonna === 'title') {
                // Rimuove i 3 numeri alla fine del filename
                $title = preg_replace('/\d{3}$/', '', $riga['title']);
                $icon_path = $icon_types[$riga['type']] ?? $icon_types['default'];
                
                $html .= "<td class='titolo'>";
                $html .= "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($riga['type']) . "'>";
                $html .= "<a href='" . htmlspecialchars($riga['url']) .  "'>" . htmlspecialchars($title) . "</a>";
                $html .= "</td>";
            } elseif ($colonna === 'nickname') {
                $owner_link = "utenti.php?utente=" . urlencode($riga['owner']);
                $html .= "<td class='titolo'><a href='" . htmlspecialchars($owner_link) .  "'>" . htmlspecialchars($val) . "</a></td>";
            } elseif (is_numeric($val)) {
                $html .= "<td class='numero'>" . htmlspecialchars($val) . "</td>";
            } elseif (isData($val)) {
                $html .= "<td class='data'>"   . htmlspecialchars($val) . "</td>";
            } else {
                $html .= "<td>"                . htmlspecialchars($val) . "</td>";
            }
        }
        $html .= "</tr>";
    }
    $html .= "</table>";
    return $html;
}