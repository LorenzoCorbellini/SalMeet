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
                     fmm.dimensione AS 'size', fmm.URL AS 'url', fmm.tipo AS 'type', u.nickname AS 'nickname', u.nome AS owner_name, u.cognome AS owner_surname"
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
		$detail_link = "media.php?vista=dettaglio&file_id=" . urlencode((int)$dati_riga['file_id']);
		$follow_link_html = "<a class='media-download-link' href='" . htmlspecialchars($file_link) . "' target='_blank'>"
				. "<img class='media-external-icon' src='images/external-link.png'>"
				. "</a>";
		$media_item_html = "<div class='media-item'>" . $file_icon . "<a href='" . htmlspecialchars($detail_link) . "'>" . $file_name . "</a>"
				. "<div class='media-action-wrapper'>" . $follow_link_html . "</div>"
				. "</div>";
		$owner_link = "utenti.php?utente=" . (int)$dati_riga['owner'];
		$owner_name = trim(($dati_riga['owner_name'] ?? '') . ' ' . ($dati_riga['owner_surname'] ?? ''));
		$owner_display = $owner_name !== ''
			? htmlspecialchars($owner_name . ' (@' . $dati_riga['nickname'] .')')
			: htmlspecialchars('(@' . $dati_riga['nickname'] . ')');
		$owner_html = "<a href='" . htmlspecialchars($owner_link) . "'>" . $owner_display . "</a>";
		
		$size_html = formatFileSizeHtml((int)$dati_riga['size']);

		$result[] = [
			'File' => $media_item_html,
			'Proprietario' => $owner_html,
			'Dimensione' => $size_html
		];
	}
	return $result;
}

function isAjaxRequest(): bool {
	return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function getParametriRichiestaMedia(): array {
	return [
		'vista'   => $_GET['vista'] ?? '',
		'tab'     => $_GET['tab'] ?? 'info',
		'file_id' => $_GET['file_id'] ?? ''
	];
}

function getMediaFileDettaglio(PDO $pdo, string $file_id): ?array {
	$stmt = $pdo->prepare(
		"SELECT fmm.numero AS file_id, fmm.titolo AS title, fmm.dimensione AS size, fmm.URL AS url,
				fmm.tipo AS type, u.codice AS owner_id, u.nickname AS owner_nickname,
				u.nome AS owner_name, u.cognome AS owner_surname
		 FROM FileMultimediale fmm
		 LEFT JOIN Utente u ON fmm.caricatoDa = u.codice
		 WHERE fmm.numero = :file_id"
	);
	$stmt->execute([':file_id' => $file_id]);
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	return $result ?: null;
}

function getGruppiDelFile(PDO $pdo,
    string $file_id,
    string $sql_sort,
    string $sort_dir = 'ASC',
    array $where = [],
    array $params = [],
    int $start_from = 0,
    int $limit = 0
): array {

    $sql = "SELECT g.codice AS gruppoId, g.nome AS 'Nome Gruppo', u.nickname AS 'Proprietario', u.codice AS ownerId, g.dataCreazione AS dataCreazione
             FROM Gruppo g
             JOIN Utente u ON g.creatoDa = u.codice
             JOIN FileAssociatoGruppo ag ON g.codice = ag.codGruppo
             WHERE ag.file = :file_id";

    if ($where) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$start_from . ", " . (int)$limit;
    }

    $params[':file_id'] = $file_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNumberOfGruppiDelFile(PDO $pdo, string $file_id, array $where = [], array $params = []): int {
    $sql = "SELECT COUNT(*) AS totale
             FROM Gruppo g
             JOIN FileAssociatoGruppo ag ON g.codice = ag.codGruppo
			 JOIN Utente u ON u.codice = g.creatoDa
             WHERE ag.file = :file_id";

    if ($where) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $params[':file_id'] = $file_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function getBachecheDelFile(PDO $pdo,
    string $file_id,
    string $sql_sort,
    string $sort_dir = 'ASC',
    array $where = [],
    array $params = [],
    int $start_from = 0,
    int $limit = 0
): array {

    $sql = "SELECT pb.nomeBacheca AS 'Nome Bacheca', u.nickname AS 'Proprietario', u.codice AS ownerId, b.dataCreazione AS dataCreazione
             FROM FilePubblicatoBacheca pb
             JOIN Utente u ON pb.codUtente = u.codice
             JOIN Bacheca b ON pb.nomeBacheca = b.nome
             WHERE pb.file = :file_id";

    if ($where) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY {$sql_sort} {$sort_dir}";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$start_from . ", " . (int)$limit;
    }

    $params[':file_id'] = $file_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNumberOfBachecheDelFile(PDO $pdo, string $file_id, array $where = [], array $params = []): int {
    $sql = "SELECT COUNT(*) AS totale
             FROM FilePubblicatoBacheca pb
             JOIN Bacheca b ON pb.nomeBacheca = b.nome
			 JOIN Utente u ON u.codice = pb.codUtente
             WHERE pb.file = :file_id";

    if ($where) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $params[':file_id'] = $file_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function renderDettaglioMedia(PDO $pdo, string $file_id, string $activeTab, bool $isAjax)
{
	$file = getMediaFileDettaglio($pdo, $file_id);
	$contentHtml  = "<a href='media.php' onclick='history.back(); return false;' class='btn-indietro'>Torna alla pagina precedente</a>";
	$contentHtml .= "<h2>" . htmlspecialchars($file['title']) . "</h2>";
	
	if (!$file) {
		$contentHtml .= "<div class='info-risultati'>File non trovato.</div>";
		if ($isAjax) {
			echo $contentHtml;
			exit;
		}
		// continue to render page skeleton with message
	} else {
		$validTabs = ['info', 'gruppi', 'bacheche'];
		if (!in_array($activeTab, $validTabs, true)) {
			$activeTab = 'info';
		}

		$baseParams = ['vista' => 'dettaglio', 'file_id' => $file_id];
		$urlInfo = '?' . http_build_query(array_merge($baseParams, ['tab' => 'info']));
		$urlGruppi = '?' . http_build_query(array_merge($baseParams, ['tab' => 'gruppi']));
		$urlBacheche = '?' . http_build_query(array_merge($baseParams, ['tab' => 'bacheche']));

		
		$tabsHtml = "<div class='detail-tabs-header'>
			<div class='bacheca-tabs tabs-reset'>
				<a href='" . htmlspecialchars($urlInfo) . "' class='" . ($activeTab === 'info' ? 'active' : '') . "'>Informazioni</a>
				<a href='" . htmlspecialchars($urlGruppi) . "' class='" . ($activeTab === 'gruppi' ? 'active' : '') . "'>Gruppi</a>
				<a href='" . htmlspecialchars($urlBacheche) . "' class='" . ($activeTab === 'bacheche' ? 'active' : '') . "'>Bacheche</a>
			</div>
		</div>";

		$contentHtml .= $tabsHtml;

		if ($activeTab === 'info') {
			$ownerLink = "utenti.php?utente=" . urlencode((int)$file['owner_id']);
			$contentHtml .= "<div class='tab-info-card'>
			<p><strong>Proprietario:</strong> <a href='" . htmlspecialchars($ownerLink) . "'>" . htmlspecialchars($file['owner_nickname']) . "</a></p>
			<p><strong>Nome:</strong> " . htmlspecialchars($file['owner_name']) . "</p>
			<p><strong>Cognome:</strong> " . htmlspecialchars($file['owner_surname']) . "</p>
			<p><strong>Tipo file:</strong> " . htmlspecialchars($file['type']) . "</p>
			<p><strong>Dimensione:</strong> " . htmlspecialchars(formatFileSize($file['size'])) . "</p>
			<p><a href='" . htmlspecialchars($file['url']) . "' target='_blank'>Apri file</a></p>
			</div>";
		} elseif ($activeTab === 'gruppi') {
			list($limit, $np, $start_from) = getPaginationParams(20);

			$allowed_sorts_f = ['nomeGruppo' => 'g.nome', 'proprietario' => 'u.nickname', 'dataCreazione' => 'g.dataCreazione'];
			list($sort_col_f, $sort_dir_f, $sql_sort_f) = getParametriOrdinamento($allowed_sorts_f, 'nomeGruppo', 'ASC');
			
			// Applica i filtri se presenti
			$filtri_result = applicaFiltriDinamici($_GET, 'gruppi');
			$where_f = [];
			$params_f = $filtri_result['parametri'];
			if ($filtri_result['sql']) {
				$where_f[] = ltrim($filtri_result['sql'], " AND ");
			}

			$totalGroups = getNumberOfGruppiDelFile($pdo, $file_id, $where_f, $params_f);
			$groups = getGruppiDelFile($pdo, $file_id, $sql_sort_f, $sort_dir_f, $where_f, $params_f, $start_from, $limit);
			$numero_pagine = getNumberOfPages($totalGroups, $limit);

			if (empty($groups)) {
				$contentHtml .= "<p class='info-risultati'>Nessun gruppo trovato per questo file.</p>";
			} else {
				$_GET['tab'] = 'gruppi';
				$datiGruppi = [];
				$customHeaders_f = generaIntestazioniOrdinabili(['Nome Gruppo' => 'nomeGruppo', 'Proprietario' => 'proprietario', 'Data Creazione' => 'dataCreazione'], $sort_col_f, $sort_dir_f);

				$contentHtml .= "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovati <strong>" . $totalGroups . "</strong> gruppi";
				if ($totalGroups > $limit) {
					$contentHtml .= " (<strong>" . $limit . "</strong> per pagina)";
				}
				$contentHtml .= "</p></div>";

				foreach ($groups as $group) {
					$groupLink = "gruppi.php?gruppo=" . urlencode($group['gruppoId']);
					$ownerLink = "utenti.php?utente=" . urlencode($group['ownerId']);
					$datiGruppi[] = [
						'Nome Gruppo' => "<a href='" . htmlspecialchars($groupLink) . "'>" . htmlspecialchars($group['Nome Gruppo']) . "</a>",
						'Proprietario' => "<a href='" . htmlspecialchars($ownerLink) . "'>" . htmlspecialchars($group['Proprietario']) . "</a>",
						'Data Creazione' => $group['dataCreazione']
					];
				}

				$contentHtml .= "<div class='table-container'>" . getTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders_f) . "</div>";
				$contentHtml .= "<div class='pagination-spacer'>" . getPagesNav($np, $numero_pagine, 1) . "</div>";
			}
		} elseif ($activeTab === 'bacheche') {
			list($limit, $np, $start_from) = getPaginationParams(20);

			$allowed_sorts_f = ['nomeBacheca' => 'pb.nomeBacheca', 'proprietario' => 'u.nickname', 'dataCreazione' => 'b.dataCreazione'];
			list($sort_col_f, $sort_dir_f, $sql_sort_f) = getParametriOrdinamento($allowed_sorts_f, 'nomeBacheca', 'ASC');
			
			// Applica i filtri se presenti
			$filtri_result = applicaFiltriDinamici($_GET, 'file_bacheche');
			$where_f = [];
			$params_f = $filtri_result['parametri'];
			if ($filtri_result['sql']) {
				$where_f[] = ltrim($filtri_result['sql'], " AND ");
			}

			$totalBacheche = getNumberOfBachecheDelFile($pdo, $file_id, $where_f, $params_f);
			$bacheche = getBachecheDelFile($pdo, $file_id, $sql_sort_f, $sort_dir_f, $where_f, $params_f, $start_from, $limit);
			$numero_pagine = getNumberOfPages($totalBacheche, $limit);

			if (empty($bacheche)) {
				$contentHtml .= "<p class='info-risultati'>Nessuna bacheca trovata per questo file.</p>";
			} else {
				$_GET['tab'] = 'bacheche';
				$datiBacheche = [];
				$customHeaders_f = generaIntestazioniOrdinabili(['Nome Bacheca' => 'nomeBacheca', 'Proprietario' => 'proprietario', 'Data Creazione' => 'dataCreazione'], $sort_col_f, $sort_dir_f);

				$contentHtml .= "<div class='table-top-bar'><p class='info-risultati zero-margin'>Trovate <strong>" . $totalBacheche . "</strong> bacheche";
				if ($totalBacheche > $limit) {
					$contentHtml .= " (<strong>" . $limit . "</strong> per pagina)";
				}
				$contentHtml .= "</p></div>";

				foreach ($bacheche as $bacheca) {
					$bachecaLink = "bacheche.php?vista=dettaglio&bacheca=" . urlencode($bacheca['Nome Bacheca']) . "&owner=" . urlencode($bacheca['ownerId']);
					$ownerLink = "utenti.php?utente=" . urlencode($bacheca['ownerId']);
					$datiBacheche[] = [
						'Nome Bacheca' => "<a href='" . htmlspecialchars($bachecaLink) . "'>" . htmlspecialchars($bacheca['Nome Bacheca']) . "</a>",
						'Proprietario' => "<a href='" . htmlspecialchars($ownerLink) . "'>" . htmlspecialchars($bacheca['Proprietario']) . "</a>",
						'Data Creazione' => $bacheca['dataCreazione']
					];
				}

				$contentHtml .= "<div class='table-container'>" . getTabella($datiBacheche, ['Nome Bacheca', 'Proprietario'], $customHeaders_f) . "</div>";
				$contentHtml .= "<div class='pagination-spacer'>" . getPagesNav($np, $numero_pagine, 1) . "</div>";
			}
		}
	}

	if ($isAjax) {
		echo $contentHtml;
		exit;
	}

	renderDettaglioMediaPage($pdo, $contentHtml);
}

function renderDettaglioMediaPage(PDO $pdo, string $contentHtml)
{
	echo "<!DOCTYPE html>\n<html>\n<head>\n";
	include 'head.html';
	echo "<title>Dettaglio File</title>\n</head>\n<body>\n";
	echo "<header>\n		<h1 id='hcod1'>File Multimediali</h1>\n	</header>\n\n	<div class='main-container'>\n	<aside class='sidebar'>\n";
	include 'nav.html';

	$mediaParams = getParametriRichiestaMedia();
	setupFiltroConfig($pdo, $mediaParams['tab']);
	echo "</aside>\n<div id='content'>\n";
	echo $contentHtml;
	echo "</div>\n</div>\n";
	include 'footer.html';
	echo "</body>\n</html>\n";
	exit;
}

function setupFiltroConfig(PDO $pdo, string $activeTab)
{
	if(!isset($activeTab)) $entita = 'file';

	// Recupera i parametri di routing per mantenere il contesto di dettaglio
	$mediaParams = getParametriRichiestaMedia();
	$isDetailView = $mediaParams['vista'] === 'dettaglio' && !empty($mediaParams['file_id']);

	if ($activeTab === 'file') {
		$entita = 'file';
		$parametriExtra = [];
	}
	else if ($activeTab === 'info') {
		$entita = 'vuoto';
		$parametriExtra = [];
	} else if ($activeTab === 'bacheche') {
		$entita = $isDetailView ? 'file_bacheche' : 'bacheche';
		// Preserva i parametri di routing nel dettaglio del file
		$parametriExtra = $isDetailView ? [
			'vista' => 'dettaglio',
			'file_id' => $mediaParams['file_id'],
			'tab' => 'bacheche'
		] : [];
	} else if ($activeTab === 'gruppi') {
		$entita = 'gruppi';
		// Preserva i parametri di routing nel dettaglio del file
		$parametriExtra = $isDetailView ? [
			'vista' => 'dettaglio',
			'file_id' => $mediaParams['file_id'],
			'tab' => 'gruppi'
		] : [];
	} else {
		$entita = 'vuoto';
		$parametriExtra = [];
	}

	$filtro_config = getFiltroConfig($entita, $parametriExtra);
	if (isset($filtro_config['vuoto']) && $filtro_config['vuoto'] === true) {
		echo '<div id="filtro" class="filter-empty">';
		echo '    <p>' . htmlspecialchars($filtro_config['messaggio']) . '</p>';
		echo '</div>';
	} else {
		include 'filter.php';
	}
}

/* PAGINAZIONE */
list($limit, $np, $start_from) = getPaginationParams(20);

/* ROUTING VISTA DETTAGLIO */
$mediaParams = getParametriRichiestaMedia();
if ($mediaParams['vista'] === 'dettaglio' && !empty($mediaParams['file_id'])) {
	renderDettaglioMedia($pdo, $mediaParams['file_id'], $mediaParams['tab'], isAjaxRequest());
	exit;
}

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
	$where[] = "(
		u.nickname LIKE :proprietario_file OR
		u.nome LIKE :proprietario_file OR
		u.cognome LIKE :proprietario_file OR
		CONCAT(u.nome, ' ', u.cognome) LIKE :proprietario_fullname OR
		CONCAT(u.cognome, ' ', u.nome) LIKE :proprietario_fullname
	)";
	$params[':proprietario_file'] = '%' . $_GET['proprietario_file'] . '%';
	$params[':proprietario_fullname'] = '%' . $_GET['proprietario_file'] . '%';
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
], 'File', 'ASC');

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
$output_html .= "<p class='info-risultati zero-margin'>Trovati <strong>$numero_records</strong> file";
if ($numero_records >= $limit) $output_html .= " (<strong>$limit</strong> per pagina)";
$output_html .= "</p>";
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
		<?php setupFiltroConfig($pdo, 'file') ?>
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
