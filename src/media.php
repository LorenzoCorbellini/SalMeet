<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
?>

<!DOCTYPE html>
<html>

<head>
	<title>SalMeet</title>
	<?php include 'head.html'; ?>
</head>

<body>
	<header>
		<h1 id="hcod1">Media</h1>
	</header>
	
	
	<div class="main-container">
	<aside class="sidebar">
		<?php include 'nav.html'; ?>
		
		<?php 
			$filtro_config = [
				'campi' => [
					['tipo'  => 'text',  'name' => 'filename', 'label' => 'File'],
					['tipo'  => 'select',  'name' => 'filetype', 'label' => 'Tipo',
						'opzioni' => ['Immagini', 'Audio', 'Video']]
				]
			];
			include 'filter.php';
		
			$filetypes = [
				'Immagini' => 'immagine',
				'Audio' => 'audio',
				'Video' => 'video'
			];
			?>
	</aside>
		
		<div id="content">
			<?php 
		
		/* PAGINAZIONE */
				$limit = 50;
				if (!empty($_GET["pagina"])) { 
					$np  = $_GET["pagina"];
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
				if (!empty($_GET['filetype'])) {
					$filetype = $filetypes[$_GET['filetype']];
					$where[]             = "fmm.tipo = :filetype";
					$params[':filetype'] =  $filetype;
				}
	
			/* VISTA DEI DATI */
	
				// Query per ottenere i dati da mostare all'utente
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
				$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

				// Query per ottenere il numero di file memorizzati nel db
				$sql_count = "SELECT COUNT(*) FROM FileMultimediale as fmm";
				if ($where) $sql_count .= " WHERE " . implode(" AND ", $where);

				$stmt_count = $pdo->prepare($sql_count);
				$stmt_count->execute($params);
				$numero_records = $stmt_count->fetchColumn();

				$numero_pagine = ceil($numero_records / $limit);

				echo getPagesNav($np, $numero_pagine, 1, 'flex-end');

				$tabella_html = get_media_table($righe, $numero_records, $limit);
				echo $tabella_html;

				echo getPagesNav($np, $numero_pagine);
				?>
		</div>
		
	</div>

	<?php include 'footer.html'; ?>
</body>
<?php $pdo = null; // Chiude esplicitamente la connessione al db ?>
</html>