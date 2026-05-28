<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/filterAPI.php';

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

function fetchGruppiConFile(PDO $pdo,
	string $file_id,
	array $where,
	array $params,
	int $start_from,
	int $limit,
	string $sql_sort,
	string $sort_dir): array {

	$sql = "SELECT *
				FROM Gruppo g
					LEFT JOIN FileAssociatoGruppo ag
						ON g.codGruppo = ag.codGruppo
				WHERE ag.file = $file_id
		";
	if ($where) $sql .= " WHERE " . implode(" AND ", $where);
	$sql .= " ORDER BY {$sql_sort} {$sort_dir}";
	$sql .= " LIMIT " . (int)$start_from . ", " . (int)$limit;

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchBachecheConFile(PDO $pdo,
	string $file_id,
	array $where,
	array $params,
	int $start_from,
	int $limit,
	string $sql_sort,
	string $sort_dir): array {

	$sql = "SELECT *
				FROM Bacheca b
					LEFT JOIN FilePubblicatoBacheca pb
						ON b.nome = pb.nomeBacheca
				WHERE pb.file = $file_id
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
        return [];
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
		$follow_link_html = "<a class='media-download-link' href='$file_link' target='_blank'>
								<img class='media-external-icon' src='images/external-link.png'>
						</a>";
		$media_item_html = "<div class='media-item'>$file_icon<a href='$file_link'>$file_name</a>
							<div class='media-action-wrapper'>$follow_link_html</div>
						</div>";
		
		$owner_link = "utenti.php?utente=" . (int)$dati_riga['owner'];
		$owner_html = "<a href='" . htmlspecialchars($owner_link) . "'>" . htmlspecialchars($riga['Proprietario']) . "</a>";
		
		$size_html = formatFileSizeHtml((int)$dati_riga['size']);

		$result[] = [
			'File' => $media_item_html,
			'Proprietario' => $owner_html,
			'Dimensione' => $size_html
		];
	}
	return $result;
}

function initFilters(): void {
	$filtro_config = [
		'campi' => [
			['tipo'  => 'text',  'name' => 'filename', 'label' => 'File'],
			['tipo'  => 'text',  'name' => 'owner', 'label' => 'Proprietario'],
			['tipo'  => 'checkbox-group',  'name' => 'filetype', 'label' => 'Tipo',
				'opzioni' => [
					'immagine' => 'Immagini',
					'audio' => 'Audio',
					'video' => 'Video'
				]
			]
		]
	];
	include 'filter.php';
}

function isAjaxRequest(): bool {
	return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function setupFiltroConfig(PDO $pdo)
{
	$entita = 'file';

	$stmtRange = $pdo->query("SELECT MIN(dimensione) AS min_dim, MAX(dimensione) AS max_dim FROM FileMultimediale");
	$rangeDati = $stmtRange->fetch(PDO::FETCH_ASSOC);

	$minSize = isset($rangeDati['min_dim']) ? floor($rangeDati['min_dim']) : 1;
	$maxSize = isset($rangeDati['max_dim']) ? ceil($rangeDati['max_dim']) : 100;
	if ($minSize == $maxSize) {
		$minSize = 0;
	}

	$parametriExtra = [
		'min_size' => $minSize,
		'max_size' => $maxSize
	];

	$filtro_config = getFiltroConfig($entita, $parametriExtra);
	include 'filter.php';
}

/* PAGINAZIONE */
list($limit, $np, $start_from) = getPaginationParams(20);

/* FILTRI */
$where  = [];
$params = [];

// Filtro per nome del file
if (!empty($_GET['file'])) {
	$where[]             = "fmm.titolo LIKE :file";
	$params[':file'] = '%' . $_GET['file'] . '%';
}

// Filtro per proprietario
if (!empty($_GET['proprietario_file'])) {
	$where[]             = "u.nickname LIKE :proprietario_file";
	$params[':proprietario_file'] = '%' . $_GET['proprietario_file'] . '%';
}

if (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') {
	$where[] = "fmm.dimensione >= :dimensione_min";
	$params[':dimensione_min'] = (float) $_GET['dimensione_min'];
}

if (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') {
	$where[] = "fmm.dimensione <= :dimensione_max";
	$params[':dimensione_max'] = (float) $_GET['dimensione_max'];
}

// Filtro per tipo di file
$filetypes = [
	'immagine' => 'Immagini',
	'audio' => 'Audio',
	'video' => 'Video'
];

if (!empty($_GET['filetype'])) {
	$selectedTypes = array_filter((array) $_GET['filetype'], function ($type) use ($filetypes) {
		return isset($filetypes[$type]);
	});

	if (!empty($selectedTypes)) {
		$placeholders = [];
		foreach (array_values($selectedTypes) as $index => $type) {
			$placeholder = ':filetype_' . $index;
			$placeholders[] = $placeholder;
			$params[$placeholder] = $type;
		}
		$where[] = 'fmm.tipo IN (' . implode(', ', $placeholders) . ')';
	}
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

$table = "FileMultimediale as fmm";
if (!empty($_GET['proprietario_file'])) {
	$table .= " LEFT JOIN Utente AS u ON fmm.caricatoDa = u.codice ";
}
$numero_records = getNumberOfRecords($pdo, $table, $where, $params);
$numero_pagine = getNumberOfPages($numero_records, $limit);

/* PREPARAZIONE HTML DA STAMPARE */
$output_html  = "<div class='table-top-bar'>";
$output_html .= "<p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> file (<strong>$limit</strong> per pagina)</p>";
$output_html .= "</div>";

$output_html .= "<div class='table-container'>";
$output_html .= getTabella($righe, ['File', 'Proprietario', 'Dimensione'], $customHeaders);
$output_html .= "</div>";
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
		<h1 id="hcod1">File Multimediali</h1>
	</header>
	
	
	<div class="main-container">
	<aside class="sidebar">
		<?php include 'nav.html'; ?>
		<?php setupFiltroConfig($pdo) ?>
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
