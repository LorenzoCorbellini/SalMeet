<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// Prende dal db i dati fa mostrare e li restituisce
function getMediaFromDb(PDO $pdo,
	array $where,
	array $params,
	int $start_from,
	int $limit): array {
	$sql = "
		SELECT
			fmm.caricatoDa	AS 'owner',
			fmm.numero		AS 'file_id',
			fmm.titolo		AS 'title',
			fmm.dimensione  AS 'size',
			fmm.URL			AS 'url',
			fmm.tipo		AS 'type',
			u.nickname		AS 'nickname'
		FROM FileMultimediale fmm
			LEFT JOIN Utente u
				ON fmm.caricatoDa = u.codice
	";
	if ($where) $sql .= " WHERE " . implode(" AND ", $where);
	$sql .= " LIMIT " . (int)$start_from . ", " . (int)$limit;

	/*
		* prepara la query (statement)
		* la esegue con i $params 
		* salva il risultato (una tabella) in $righe
		*/
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

/* PREPARAZIONE OUTPUT */
$righe = getMediaFromDb($pdo, $where, $params, $start_from, $limit);
$numero_records = getNumberOfRecords($pdo, $where, $params);
$numero_pagine = getNumberOfPages($numero_records, $limit);

$output_html  = getPagesNav($np, $numero_pagine, 1, 'flex-end');
$output_html .= get_media_table($righe, $numero_records, $limit);
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
	<script src="./js/AJAXHandler.js" defer></script>
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
