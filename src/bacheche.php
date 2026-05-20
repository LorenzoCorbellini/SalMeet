<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// =========================================================
//  FUNZIONE PER RECUPERARE UTENTI
// =========================================================
function getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort = 'u.nickname', $sort_dir = 'ASC')
{
	$sql = "
        SELECT u.codice, u.nickname, u.nome, u.cognome, u.dataNascita
        FROM UtenteAutorizzatoBacheca uab
        JOIN Utente u ON u.codice = uab.utenteAutorizzato
        WHERE uab.nomeBacheca = :bacheca AND uab.codUtente = :owner
    ";

	$params = [
		':bacheca' => $bacheca,
		':owner' => $owner
	];

	if (!empty($_GET['utente'])) {
		$sql .= " AND u.nickname LIKE :utente";
		$params[':utente'] = '%' . $_GET['utente'] . '%';
	}
	if (!empty($_GET['nome'])) {
		$sql .= " AND u.nome LIKE :nome";
		$params[':nome'] = '%' . $_GET['nome'] . '%';
	}
	if (!empty($_GET['cognome'])) {
		$sql .= " AND u.cognome LIKE :cognome";
		$params[':cognome'] = '%' . $_GET['cognome'] . '%';
	}
	if (!empty($_GET['data_nascita'])) {
		$sql .= " AND u.dataNascita >= :data_nascita";
		$params[':data_nascita'] = $_GET['data_nascita'];
	}

	$sql .= " ORDER BY {$sql_sort} {$sort_dir}";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$datiUtenti = [];
	foreach ($utenti as $u) {
		$azioni = ((int)$u['codice'] !== (int)$owner)
			? "<div style='text-align:center;'><img src='images/trash.png' alt='Elimina' style='width:16px; cursor:pointer;' onclick=\"rimuoviAutorizzato('{$bEnc}', {$owner}, {$u['codice']})\"></div>"
			: "<div style='text-align:center;'><small style='color:gray;'>Proprietario</small></div>";

		$user_link = "utenti.php?utente=" . urlencode($u['codice']);

		$htmlNickname = "<a href='" . htmlspecialchars($user_link) .  "'>" . htmlspecialchars($u['nickname']) . "</a>";

		$datiUtenti[] = [
			'Nickname' => $htmlNickname,
			'Nome' => $u['nome'],
			'Cognome' => $u['cognome'],
			'Data Nascita' => $u['dataNascita'],
			'Azioni' => $azioni
		];
	}
	return [$datiUtenti, count($utenti)];
}

// =========================================================
//  FUNZIONE PER RECUPERARE FILE 
// =========================================================
function getFileBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort = 'fm.titolo', $sort_dir = 'ASC')
{
	$sql = "
        SELECT fm.numero, fm.titolo, u.codice as caricatoDa, u.nickname, fm.dimensione, fm.URL, fm.tipo
        FROM FilePubblicatoBacheca fb
        JOIN FileMultimediale fm ON fm.numero = fb.file
        JOIN Utente u ON u.codice = fm.caricatoDa
        WHERE fb.nomeBacheca = :bacheca AND fb.codUtente = :owner
    ";

	$params = [
		':bacheca' => $bacheca,
		':owner' => $owner
	];

	if (!empty($_GET['file'])) {
		$sql .= " AND fm.titolo LIKE :file";
		$params[':file'] = '%' . $_GET['file'] . '%';
	}

	$sql .= " ORDER BY {$sql_sort} {$sort_dir}";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$file = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$icon_types = [
		'immagine' => 'images/image.png',
		'video' => 'images/video.png',
		'audio' => 'images/headphones.png',
		'default' => 'images/document.png'
	];

	$datiFile = [];
	foreach ($file as $f) {
		$tipoStr = strtolower($f['tipo']);
		$icon_path = $icon_types[$tipoStr] ?? $icon_types['default'];

		$title = preg_replace('/\d{3}$/', '', $f['titolo']);

		$htmlFile = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($tipoStr) . "'>";
		$htmlFile .= "<a href='" . htmlspecialchars($f['URL']) . "' target='_blank'>" . htmlspecialchars($title) . "</a>";

		$owner_link = "utenti.php?utente=" . urlencode($f['caricatoDa']);
		$htmlOwner = "<a href='" . htmlspecialchars($owner_link) .  "'>" . htmlspecialchars($f['nickname']) . "</a>";

		$azioni   = "<div style='text-align:center;'><img src='images/trash.png' alt='Elimina' style='width:16px; cursor:pointer;' onclick=\"rimuoviFile('{$bEnc}', {$owner}, {$f['numero']})\"></div>";

		$datiFile[] = [
			'File' => $htmlFile,
			'Proprietario' => $htmlOwner,
			'Dimensione (MB)' => $f['dimensione'],
			'Azioni' => $azioni
		];
	}
	return [$datiFile, count($file)];
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA VISTA DETTAGLIO
// =========================================================
function renderDettaglioBacheca($pdo, $bacheca, $owner, $bEnc)
{
	echo "<h2>" . htmlspecialchars($bacheca) . "</h2>";

	$stmtBacheca = $pdo->prepare("
        SELECT b.dataCreazione, u.nickname 
        FROM Bacheca b
        JOIN Utente u ON b.codiceUtente = u.codice
        WHERE b.nome = :nome AND b.codiceUtente = :owner
    ");
	$stmtBacheca->execute([':nome' => $bacheca, ':owner' => $owner]);
	$datiBachecaDb = $stmtBacheca->fetch(PDO::FETCH_ASSOC);

	if ($datiBachecaDb) {
		$dataFormattata = "";
		if (!empty($datiBachecaDb['dataCreazione'])) {
			$dataFormattata = function_exists('formattaData') ? formattaData($datiBachecaDb['dataCreazione']) : date('d/m/Y', strtotime($datiBachecaDb['dataCreazione']));
		}

		if (!empty($dataFormattata)) {
			echo "<p style='margin-bottom: 25px;'><strong>Data di Creazione:</strong> " . htmlspecialchars($dataFormattata) . "</p>";
			$linkOwner = "utenti.php?utente=" . urlencode($owner);

			echo "<p><strong>Creata da:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($datiBachecaDb['nickname']) . "</a></p>";
		}
	}

	// Gestione ordinamento e dati Utenti
	$allowed_sorts_u = [
		'nickname'     => 'u.nickname',
		'nome'         => 'u.nome',
		'cognome'      => 'u.cognome',
		'data_nascita' => 'u.dataNascita'
	];
	list($sort_col_u, $sort_dir_u, $sql_sort_u) = getParametriOrdinamento($allowed_sorts_u, 'nickname', 'ASC');

	list($datiUtenti, $countUtenti) = getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_u, $sort_dir_u);

	echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;'>";
	echo "<p style='margin: 0;'>Utenti autorizzati nella bacheca: <strong>{$countUtenti}</strong></p>";
	echo "<a onclick=\"aggiungiAutorizzato('{$bEnc}', {$owner})\" class='btn-aggiungi' style='cursor: pointer;'>
        <img src='images/add.png' alt='Aggiungi' style='vertical-align: middle;'> <strong>Aggiungi utente</strong>
    </a>";
	echo "</div>";

	$customHeaders_u = generaIntestazioniOrdinabili([
		'Nickname'     => 'nickname',
		'Nome'         => 'nome',
		'Cognome'      => 'cognome',
		'Data Nascita' => 'data_nascita'
	], $sort_col_u, $sort_dir_u);

	stampaTabella($datiUtenti, ['Nickname', 'Azioni'], $customHeaders_u);

	// Gestione ordinamento e dati File
	$allowed_sorts_f = [
		'file'         => 'fm.titolo',
		'proprietario' => 'u.nickname',
		'dimensione'   => 'fm.dimensione'
	];
	list($sort_col_f, $sort_dir_f, $sql_sort_f) = getParametriOrdinamento($allowed_sorts_f, 'file', 'ASC');

	list($datiFile, $countFile) = getFileBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_f, $sort_dir_f);

	echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; margin-top: 30px;'>";
	echo "<p style='margin: 0;'>File pubblicati nella bacheca: <strong>{$countFile}</strong></p>";
	echo "<a onclick=\"aggiungiFile('{$bEnc}', {$owner})\" class='btn-aggiungi' style='cursor: pointer;'>
        <img src='images/add.png' alt='Aggiungi' style='vertical-align: middle;'> <strong>Aggiungi file</strong>
    </a>";
	echo "</div>";

	$customHeaders_f = generaIntestazioniOrdinabili([
		'File'            => 'file',
		'Proprietario'    => 'proprietario',
		'Dimensione (MB)' => 'dimensione'
	], $sort_col_f, $sort_dir_f);

	stampaTabella($datiFile, ['File', 'Proprietario', 'Azioni'], $customHeaders_f);
}

// =========================================================
//  FUNZIONE PER RENDERIZZARE LA VISTA PRINCIPALE
// =========================================================
function renderElencoBacheche($pdo, $isAjax)
{
	$where  = [];
	$params = [];

	if (!empty($_GET['titolo'])) {
		$where[]           = "b.nome LIKE :titolo";
		$params[':titolo'] = '%' . $_GET['titolo'] . '%';
	}
	if (!empty($_GET['proprietario'])) {
		$where[]                 = "u.nickname LIKE :proprietario";
		$params[':proprietario'] = '%' . $_GET['proprietario'] . '%';
	}
	if (!empty($_GET['data'])) {
		$where[]         = "DATE(b.dataCreazione) >= :data";
		$params[':data'] = $_GET['data'];
	}

	list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
		'nome'         => 'b.nome',
		'data'         => 'b.dataCreazione',
		'proprietario' => 'u.nickname',
	], 'data', 'DESC');

	$sqlCount = "SELECT COUNT(*) AS totale FROM Bacheca b LEFT JOIN Utente u ON u.codice = b.codiceUtente";
	if ($where) $sqlCount .= " WHERE " . implode(" AND ", $where);
	$stmtCount = $pdo->prepare($sqlCount);
	$stmtCount->execute($params);
	$totaleRisultati = $stmtCount->fetch(PDO::FETCH_ASSOC)['totale'];

	$sql = "
        SELECT
            b.codiceUtente AS 'owner',
            b.nome AS 'Nome Bacheca',
            u.nickname AS 'Proprietario',
            b.dataCreazione AS 'Data Creazione'
        FROM Bacheca b
        LEFT JOIN UtenteAutorizzatoBacheca uab ON uab.codUtente = b.codiceUtente AND uab.nomeBacheca = b.nome
        LEFT JOIN FilePubblicatoBacheca f ON f.codUtente = b.codiceUtente AND f.nomeBacheca = b.nome
        LEFT JOIN Utente u ON u.codice = b.codiceUtente
    ";
	if ($where) $sql .= " WHERE " . implode(" AND ", $where);

	$sql .= " GROUP BY b.codiceUtente, u.nickname, b.nome, b.dataCreazione ORDER BY {$sql_sort} {$sort_dir}";

	$stmt = $pdo->prepare($sql);
	foreach ($params as $chiave => $valore) {
		$stmt->bindValue($chiave, $valore);
	}
	$stmt->execute();
	$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$isAjax) {
		echo '<div id="ajax-results">';
	}

	echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;'>";
	echo "<p class='info-risultati' style='margin: 0;'>Trovate <strong>$totaleRisultati</strong> bacheche</p>";
	echo "<a onclick='aggiungiBacheca()' class='btn-aggiungi' style='cursor: pointer;'>
        <img src='images/add.png' alt='Aggiungi' style='vertical-align: middle;'> <strong>Aggiungi una nuova bacheca</strong>
    </a>";
	echo "</div>";

	if (!empty($righe)) {
		$datiBacheche = [];

		foreach ($righe as $riga) {
			$p = $_GET;
			$p['vista']   = 'dettaglio';
			$p['bacheca'] = $riga['Nome Bacheca'];
			$p['owner']   = $riga['owner'];
			$htmlNome = "<a href='bacheche.php?" . http_build_query($p) . "'>" . htmlspecialchars($riga['Nome Bacheca']) . "</a>";

			$proprietarioLink = "utenti.php?utente=" . urlencode($riga['owner']);
			$htmlProprietario = "<a href='" . htmlspecialchars($proprietarioLink) . "'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

			$nomeEnc  = htmlspecialchars(addslashes($riga['Nome Bacheca']), ENT_QUOTES);
			$ownerEnc = (int) $riga['owner'];
			$azioni = "<div style='text-align:center; white-space:nowrap;'>
                <span title='Modifica' style='cursor:pointer; font-size:1.1rem; margin-right:8px;' onclick=\"modificaBacheca('{$nomeEnc}', {$ownerEnc})\">
                    <img src='images/edit.png' alt='Modifica' style='width:16px; height:16px;'>
                </span>
                <span title='Elimina' style='cursor:pointer; font-size:1.1rem;' onclick=\"eliminaBacheca('{$nomeEnc}', {$ownerEnc})\">
                    <img src='images/trash.png' alt='Elimina' style='width:16px; height:16px;'>
                </span>
            </div>";

			$datiBacheche[] = [
				'Nome Bacheca' => $htmlNome,
				'Proprietario' => $htmlProprietario,
				'Data Creazione' => $riga['Data Creazione'],
				'Azioni' => $azioni
			];
		}

		$customHeaders = generaIntestazioniOrdinabili([
			'Nome Bacheca'   => 'nome',
			'Proprietario'   => 'proprietario',
			'Data Creazione' => 'data'
		], $sort_col, $sort_dir);

		stampaTabella($datiBacheche, ['Proprietario', 'Nome Bacheca', 'Azioni'], $customHeaders);
	}

	if (!$isAjax) {
		echo '</div>';
	}
}


// =========================================================
// GESTIONE CORPO DELLA PAGINA E ASYNC/AJAX ROUTING
// =========================================================
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if (!$isAjax):
?>
	<!DOCTYPE html>
	<html lang="it">

	<head>
		<?php include 'head.html'; ?>
		<title>SalMeet</title>
		<script src="./js/bachecheCRUD.js" defer></script>
	</head>

	<body>

		<header>
			<h1 id="hcod1">Bacheche</h1>
		</header>

		<div class="main-container">
			<aside class="sidebar">
				<?php include 'nav.html'; ?>

				<?php
				$vista_corrente = $_GET['vista'] ?? '';

				if ($vista_corrente === 'dettaglio') {
					$filtro_config = [
						'campi' => [
							['tipo' => 'hidden', 'name' => 'vista',   'value' => 'dettaglio', 'label' => ''],
							['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $_GET['bacheca'] ?? '', 'label' => ''],
							['tipo' => 'hidden', 'name' => 'owner',   'value' => $_GET['owner'] ?? '', 'label' => ''],
							['tipo' => 'text',   'name' => 'utente',  'label' => 'Nickname Utente'],
							['tipo' => 'text',   'name' => 'nome',    'label' => 'Nome Utente'],
							['tipo' => 'text',   'name' => 'cognome', 'label' => 'Cognome Utente'],
							['tipo' => 'date',   'name' => 'data_nascita', 'label' => 'Data di Nascita (Da)'],
							['tipo' => 'text',   'name' => 'file',    'label' => 'Nome File'],
						]
					];
					include 'filter.php';
				} else {
					$filtro_config = [
						'campi' => [
							['tipo' => 'text', 'name' => 'titolo',       'label' => 'Nome Bacheca'],
							['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Proprietario (nickname)'],
							['tipo' => 'date', 'name' => 'data',         'label' => 'Data Creazione (Da)'],
						]
					];
					include 'filter.php';
				}
				?>
			</aside>

			<div id="content">
			<?php endif; ?>

			<?php
			// =========================================================
			// ROUTER DELLE VISTE
			// =========================================================
			if (!empty($_GET['vista']) && !empty($_GET['bacheca']) && !empty($_GET['owner'])) {
				$vista   = $_GET['vista'];
				$bacheca = $_GET['bacheca'];
				$owner   = $_GET['owner'];
				$bEnc    = htmlspecialchars(addslashes($bacheca), ENT_QUOTES);

				if ($vista === 'dettaglio') {
					renderDettaglioBacheca($pdo, $bacheca, $owner, $bEnc);
				} else {
					echo "<div style='margin-bottom: 25px;'></div>";
				}
			} else {
				renderElencoBacheche($pdo, $isAjax);
			}
			?>

			<?php if (!$isAjax): ?>
			</div>
		</div>

		<?php include 'footer.html'; ?>
	</body>

	</html>
<?php endif; ?>