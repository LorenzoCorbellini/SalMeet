<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
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
			// =========================================================
			// GESTIONE DINAMICA DEL FILTRO NELLA SIDEBAR
			// =========================================================
			$vista_corrente = $_GET['vista'] ?? '';

			if ($vista_corrente === 'dettaglio') {
				// Filtro combinato per la vista Dettaglio (cerca sia tra gli utenti che tra i file)
				$filtro_config = [
					'campi' => [
						['tipo' => 'hidden', 'name' => 'vista',   'value' => 'dettaglio', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $_GET['bacheca'] ?? '', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'owner',   'value' => $_GET['owner'] ?? '', 'label' => ''],

						// Campi ricerca Utenti
						['tipo' => 'text',   'name' => 'utente',  'label' => 'Nickname Utente'],
						['tipo' => 'text',   'name' => 'nome',    'label' => 'Nome Utente'],
						['tipo' => 'text',   'name' => 'cognome', 'label' => 'Cognome Utente'],
						['tipo' => 'date',   'name' => 'data_nascita', 'label' => 'Data di Nascita (Da)'],

						// Campi ricerca File
						['tipo' => 'text',   'name' => 'file',    'label' => 'Nome File'],
					]
				];
				include 'filter.php';
			} elseif ($vista_corrente === 'utenti') {
				// Filtro specifico per la vista Utenti Autorizzati
				$filtro_config = [
					'campi' => [
						['tipo' => 'hidden', 'name' => 'vista',   'value' => 'utenti', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $_GET['bacheca'] ?? '', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'owner',   'value' => $_GET['owner'] ?? '', 'label' => ''],
						['tipo' => 'text',   'name' => 'utente',  'label' => 'Nickname'],
						['tipo' => 'text',   'name' => 'nome',    'label' => 'Nome'],
						['tipo' => 'text',   'name' => 'cognome', 'label' => 'Cognome'],
						['tipo' => 'date',   'name' => 'data_nascita', 'label' => 'Data di Nascita (Da)'],
					]
				];
				include 'filter.php';
			} elseif ($vista_corrente === 'file') {
				// Filtro specifico per la vista File Pubblicati
				$filtro_config = [
					'campi' => [
						['tipo' => 'hidden', 'name' => 'vista',   'value' => 'file', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $_GET['bacheca'] ?? '', 'label' => ''],
						['tipo' => 'hidden', 'name' => 'owner',   'value' => $_GET['owner'] ?? '', 'label' => ''],
						['tipo' => 'text',   'name' => 'file',    'label' => 'Nome File'],
					]
				];
				include 'filter.php';
			} else {
				// Filtro standard per l'elenco generale delle bacheche
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
			<?php
			// =========================================================
			// ROUTING VISTE
			// =========================================================
			if (!empty($_GET['vista']) && !empty($_GET['bacheca']) && !empty($_GET['owner'])) {
				$vista   = $_GET['vista'];
				$bacheca = $_GET['bacheca'];
				$owner   = $_GET['owner'];
				$bEnc    = htmlspecialchars(addslashes($bacheca), ENT_QUOTES);

				echo "<p><a href='javascript:history.back()' class='btn-back' style='text-decoration:none; font-weight:bold; color:var(--accent);'>&larr; Torna alla pagina precedente</a></p>";
				echo "<h2>" . htmlspecialchars($bacheca) . "</h2>";

				if ($vista === 'dettaglio') {
					$stmtBacheca = $pdo->prepare("SELECT dataCreazione FROM Bacheca WHERE nome = :nome AND codiceUtente = :owner");
					$stmtBacheca->execute([':nome' => $bacheca, ':owner' => $owner]);
					$datiBachecaDb = $stmtBacheca->fetch(PDO::FETCH_ASSOC);

					if ($datiBachecaDb && !empty($datiBachecaDb['dataCreazione'])) {
						$dataFormattata = function_exists('formattaData') ? formattaData($datiBachecaDb['dataCreazione']) : date('d/m/Y', strtotime($datiBachecaDb['dataCreazione']));
						echo "<p style='margin-bottom: 25px;'><strong>Data di Creazione:</strong> " . htmlspecialchars($dataFormattata) . "</p>";
					}
				} else {
					echo "<div style='margin-bottom: 25px;'></div>";
				}

				// VISTA TABELLA UTENTI (Attiva in 'dettaglio' o in 'utenti')
				if ($vista === 'dettaglio' || $vista === 'utenti') {
					$allowed_sorts_u = [
						'nickname'     => 'u.nickname',
						'nome'         => 'u.nome',
						'cognome'      => 'u.cognome',
						'data_nascita' => 'u.dataNascita'
					];
					list($sort_col_u, $sort_dir_u, $sql_sort_u) = getParametriOrdinamento($allowed_sorts_u, 'nickname', 'ASC');

					list($datiUtenti, $countUtenti) = getUtentiBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_u, $sort_dir_u);

					echo "<h3>Utenti autorizzati: <strong>{$countUtenti}</strong></h3>";
					echo "<p><a onclick=\"aggiungiAutorizzato('{$bEnc}', {$owner})\" class='btn-aggiungi'>
                    <img src='images/add.png' alt='Aggiungi'> <strong>Aggiungi utente autorizzato</strong>
                </a></p>";

					$customHeaders_u = generaIntestazioniOrdinabili([
						'Nickname'     => 'nickname',
						'Nome'         => 'nome',
						'Cognome'      => 'cognome',
						'Data Nascita' => 'data_nascita'
					], $sort_col_u, $sort_dir_u);

					stampaTabella($datiUtenti, ['Nickname', 'Azioni'], $customHeaders_u);
				}

				// VISTA TABELLA FILE (Attiva in 'dettaglio' o in 'file')
				if ($vista === 'dettaglio' || $vista === 'file') {
					$allowed_sorts_f = [
						'file'         => 'fm.titolo',
						'dimensione'   => 'fm.dimensione',
						'proprietario' => 'u.nickname'
					];
					list($sort_col_f, $sort_dir_f, $sql_sort_f) = getParametriOrdinamento($allowed_sorts_f, 'file', 'ASC');

					list($datiFile, $countFile) = getFileBacheca($pdo, $bacheca, $owner, $bEnc, $sql_sort_f, $sort_dir_f);

					echo "<h3>File pubblicati: <strong>{$countFile}</strong></h3>";
					echo "<p><a onclick=\"aggiungiFile('{$bEnc}', {$owner})\" class='btn-aggiungi'>
                    <img src='images/add.png' alt='Aggiungi'> <strong>Aggiungi file alla bacheca</strong>
                </a></p>";

					$customHeaders_f = generaIntestazioniOrdinabili([
						'File'            => 'file',
						'Dimensione (MB)' => 'dimensione',
						'Proprietario'    => 'proprietario'
					], $sort_col_f, $sort_dir_f);

					stampaTabella($datiFile, ['File', 'Proprietario', 'Azioni'], $customHeaders_f);
				}
			} else {
				// =========================================================
				// VISTA PRINCIPALE (ELENCO BACHECHE)
				// =========================================================
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

				list($pagina, $limit, $offset) = getParametriPaginazione(50);

				list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
					'nome'         => 'b.nome',
					'data'         => 'b.dataCreazione',
					'proprietario' => 'u.nickname',
					'utenti'       => '`Numero Utenti`',
					'file_count'   => '`Numero File`'
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
                    b.dataCreazione AS 'Data Creazione',
                    COUNT(DISTINCT uab.utenteAutorizzato) AS 'Numero Utenti',
                    COUNT(DISTINCT f.file) AS 'Numero File'
                FROM Bacheca b
                LEFT JOIN UtenteAutorizzatoBacheca uab ON uab.codUtente = b.codiceUtente AND uab.nomeBacheca = b.nome
                LEFT JOIN FilePubblicatoBacheca f ON f.codUtente = b.codiceUtente AND f.nomeBacheca = b.nome
                LEFT JOIN Utente u ON u.codice = b.codiceUtente
            ";
				if ($where) $sql .= " WHERE " . implode(" AND ", $where);

				$sql .= " GROUP BY b.codiceUtente, u.nickname, b.nome, b.dataCreazione ORDER BY {$sql_sort} {$sort_dir} LIMIT :limit OFFSET :offset";

				$stmt = $pdo->prepare($sql);
				foreach ($params as $chiave => $valore) {
					$stmt->bindValue($chiave, $valore);
				}
				$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
				$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
				$stmt->execute();
				$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

				echo "<p class='info-risultati'>Trovate <strong>$totaleRisultati</strong> bacheche ($limit per pagina).</p>";
				echo "<p><a onclick='aggiungiBacheca()' class='btn-aggiungi'>
                    <img src='images/add.png' alt='Aggiungi'> <strong>Aggiungi una nuova bacheca</strong>
                </a></p>";

				if (!empty($righe)) {
					$datiBacheche = [];
					foreach ($righe as $riga) {
						$p = $_GET;

						$p['vista']   = 'dettaglio';
						$p['bacheca'] = $riga['Nome Bacheca'];
						$p['owner']   = $riga['owner'];
						$htmlNome = "<a href='bacheche.php?" . http_build_query($p) . "'>" . htmlspecialchars($riga['Nome Bacheca']) . "</a>";

						$p['vista']   = 'utenti';
						$htmlUtenti = "<div style='text-align: right;'><a href='bacheche.php?" . http_build_query($p) . "'>" . htmlspecialchars($riga['Numero Utenti']) . "</a></div>";

						$p['vista']   = 'file';
						$htmlFile = "<div style='text-align: right;'><a href='bacheche.php?" . http_build_query($p) . "'>" . htmlspecialchars($riga['Numero File']) . "</a></div>";

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
							'Proprietario' => $htmlProprietario,
							'Nome Bacheca' => $htmlNome,
							'Data Creazione' => $riga['Data Creazione'],
							'Numero Utenti' => $htmlUtenti,
							'Numero File' => $htmlFile,
							'Azioni' => $azioni
						];
					}

					$customHeaders = generaIntestazioniOrdinabili([
						'Proprietario'   => 'proprietario',
						'Nome Bacheca'   => 'nome',
						'Data Creazione' => 'data',
						'Numero Utenti'  => 'utenti',
						'Numero File'    => 'file_count'
					], $sort_col, $sort_dir);

					stampaTabella($datiBacheche, ['Proprietario', 'Nome Bacheca', 'Numero Utenti', 'Numero File', 'Azioni'], $customHeaders);

					stampaPaginazione($pagina, $totaleRisultati, $limit);
				}
			}
			?>
		</div>
	</div>

	<?php include 'footer.html'; ?>

</body>

</html>