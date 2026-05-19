<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Estrae i record dei file multimediali dal database applicando i filtri e l'ordinamento richiesti.
 *
 * Questa funzione supporta due modalità di estrazione (`$view_mode`):
 * - 'visual': Estrae solo le colonne destinate alla visualizzazione testuale immediata.
 * - 'full': Estrae anche le chiavi e gli URL necessari alla business logic e alla generazione dei link.
 *
 * @param PDO    $pdo        Connessione attiva al database.
 * @param array  $where      Array contenente le clausole WHERE (es. ["fmm.titolo LIKE :filename"]).
 * @param array  $params     Array associativo dei parametri per il prepared statement (es. [':filename' => '%test%']).
 * @param int    $start_from Indice del record da cui iniziare l'estrazione (per la paginazione).
 * @param int    $limit      Numero massimo di record da restituire per pagina.
 * @param string $sql_sort   Colonna SQL su cui applicare l'ordinamento.
 * @param string $sort_dir   Direzione dell'ordinamento ('ASC' o 'DESC').
 * @param string $view_mode  Modalità di estrazione dei campi. Accetta 'visual' o 'full'.
 * * @throws \RuntimeException Se la modalità $view_mode passata non esiste nella mappatura delle colonne.
 * @return array Lista di righe (array associativi) restituite dalla query.
 */
function fetchMediaRecords(PDO $pdo,
	array $where,
	array $params,
	int $start_from,
	int $limit,
	string $sql_sort,
	string $sort_dir,
	string $view_mode
	): array {

	/* 'visual' per selezionare i dati nel formato pronto da stampare
	 * 'full' per selezionare i dati richiesti dalla business logic
	 */
	$columns_map = [
        'visual' => "fmm.titolo AS 'File', u.nickname AS 'Proprietario', fmm.dimensione AS 'Dimensione'",
        
        'full'   => "fmm.caricatoDa AS 'owner', fmm.numero AS 'file_id', fmm.titolo AS 'title', 
                     fmm.dimensione AS 'size', fmm.URL AS 'url', fmm.tipo AS 'type', u.nickname AS 'nickname'"
    ];

	$fields = $columns_map[$view_mode];
	$sql = "SELECT $fields
				FROM FileMultimediale fmm
					LEFT JOIN Utente u
						ON fmm.caricatoDa = u.codice
			";
	if ($where) $sql .= " WHERE " . implode(" AND ", $where);
	$sql .= " ORDER BY {$sql_sort} {$sort_dir}";
	$sql .= " LIMIT " . (int)$start_from . ", " . (int)$limit;

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Trasforma i dati grezzi del database in righe strutturate e formattate in HTML per la tabella dei media.
 *
 * Combina i dati testuali superficiali (`$visualRows`) con i metadati di sistema (`$fullRows`) 
 * per generare dinamicamente i link ipertestuali ai file e ai profili dei proprietari.
 * I due array in ingresso devono essere speculari (stesso ordine e stessa dimensione).
 *
 * @param array $righe Righe estratte in modalità 'visual' (contengono 'File' e 'Proprietario').
 * @param array $dati   Righe estratte in modalità 'full' (contengono i metadati come 'url' e 'owner').
 * * @throws \Exception Se l'array dei metadati ($fullRows) è vuoto.
 * @return array Array di righe formattate, dove ogni riga contiene le chiavi 'File' e 'Proprietario' convertite in HTML.
 */
function prepareMediaTableRows(array $righe, array $dati): array {
	if (empty($righe)) {
        throw new Exception("Errore: il set di dati dei media è vuoto o non valido.");
    }
	
	$icon_types = [
		'immagine' => 'images/image.png',
		'video' => 'images/video.png',
		'audio' => 'images/headphones.png',
		'default' => 'images/document.png'
	];

	$result = [];
	foreach ($righe as $key => $riga) {
		$dati_riga = $dati[$key];
		$icon_path = $icon_types[$dati_riga['type']] ?? $icon_types['default'];
		
		$file_link = $dati_riga['url'];
		$file_icon  = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($dati_riga['type']) . "'>";
		$file_name   = htmlspecialchars($riga['File']);
		$title_html = "<div id='file_name'>$file_icon<a href='$file_link'>$file_name</a></div>";
		
		$owner_link = "utenti.php?utente=" . (int)$dati_riga['owner'];
		$owner_html = "<a href='" . htmlspecialchars($owner_link) . "'>" . htmlspecialchars($riga['Proprietario']) . "</a>";
		
		$size_html = formatFileSizeHtml((int)$dati_riga['size']);

		$result[] = [
			'File' => $title_html,
			'Proprietario' => $owner_html,
			'Dimensione' => $size_html
		];
	}
	return $result;
}

// Calcola il numero di records nel db che rispettano i filtri
function getNumberOfRecords(PDO $pdo, array $where, array $params): int {
	$sql_count = "SELECT COUNT(*) FROM FileMultimediale as fmm";
	if ($where) $sql_count .= " WHERE " . implode(" AND ", $where);

	$stmt_count = $pdo->prepare($sql_count);
	$stmt_count->execute($params);
	return (int)$stmt_count->fetchColumn();
}

// calcola il numero di pagine richieste per mostrare records_num records
function getNumberOfPages(int $records_num, int $limit): int {
	return (int)ceil($records_num / $limit);
}

function initFilters(): void {
	$filtro_config = [
		'campi' => [
			['tipo'  => 'text',  'name' => 'filename', 'label' => 'File'],
			['tipo'  => 'select',  'name' => 'filetype', 'label' => 'Tipo',
				'opzioni' => ['Immagini', 'Audio', 'Video']]
		]
	];
	include 'filter.php';
}

function isAjaxRequest(): bool {
	return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/* PAGINAZIONE */
$limit = 50;
if (!empty($_GET["pagina"])) { 
	$np  = (int)$_GET["pagina"];
} else { 
	$np=1; 
};
$start_from = ($np - 1) * $limit;

/* FILTRI */
$where  = [];
$params = [];

// Filtro per nome del file
if (!empty($_GET['filename'])) {
	$where[]             = "fmm.titolo LIKE :filename";
	$params[':filename'] = '%' . $_GET['filename'] . '%';
}
// Filtro per tipo di file
$filetypes = [
	'Immagini' => 'immagine',
	'Audio' => 'audio',
	'Video' => 'video'
];

if (!empty($_GET['filetype'])) {
	$filetype = $filetypes[$_GET['filetype']];
	$where[]             = "fmm.tipo = :filetype";
	$params[':filetype'] =  $filetype;
}

/* SETUP NOMI DELLE COLONNE */
list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
	'File' => 'fmm.titolo',
	'owner' => 'u.nickname',
	'size' => 'fmm.dimensione'
], 'File', 'DESC');

$customHeaders = generaIntestazioniOrdinabili([
	'File'   => 'File',
	'Proprietario' => 'owner',
	'Dimensione' => 'size'
], $sort_col, $sort_dir);

/* PREPARAZIONE DATI PER LA TABELLA */
$righe = prepareMediaTableRows(
	fetchMediaRecords($pdo, $where, $params, $start_from, $limit, $sql_sort, $sort_dir, 'visual'),
	fetchMediaRecords($pdo, $where, $params, $start_from, $limit, $sql_sort, $sort_dir, 'full'),
);
$numero_records = getNumberOfRecords($pdo, $where, $params);
$numero_pagine = getNumberOfPages($numero_records, $limit);

/* PREPARAZIONE HTML DA STAMPARE */
$output_html  = "<div id='results-and-page-nav'>";
$output_html .= "<div id='results-number'>Trovati $numero_records risultati ($limit per pagina)</div>";
$output_html .= getPagesNav($np, $numero_pagine, 1);
$output_html .= "</div>";
$output_html .= getTabella($righe, ['File', 'Proprietario', 'Dimensione'], $customHeaders);
$output_html .= getPagesNav($np, $numero_pagine, 1);

if (isAjaxRequest()) {
    echo $output_html;
    $pdo = null;
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
	<?php include 'head.html'; ?>
	<title>SalMeet</title>
</head>

<body>
	<header>
		<h1 id="hcod1">Media</h1>
	</header>
	
	
	<div class="main-container">
	<aside class="sidebar">
		<?php include 'nav.html'; ?>
		<?php initFilters(); ?>
	</aside>
		<div id="content">
			<?php 
				echo '<div id="ajax-results">';
				echo $output_html;
				echo '</div>';
			?>
		</div>
	</div>
	<?php include 'footer.html'; ?>
</body>

</html>
