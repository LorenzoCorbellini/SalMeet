<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
	<title>SalMeet - Gruppi</title>
	<?php include 'head.html'; ?>
</head>

<body>
	<header>
		<h1 id=\"hcod1\">Gruppi</h1>
	</header>

	<div class="main-container">
		<aside class="sidebar">
			<?php include 'nav.html'; ?>

			<?php
			// Mostriamo la barra laterale di filtro solo se siamo nella lista principale dei gruppi
			if (empty($_GET['gruppo'])) {
				$filtro_config = [
					'campi' => [
						['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Gruppo'],
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
			// ROUTING VISTE: DETTAGLIO GRUPPO (Profilo, Membri e File)
			// =========================================================
			if (!empty($_GET['gruppo'])) {
				$idGruppo = (int)$_GET['gruppo'];

				// 1. Recupero informazioni generali del gruppo specifico
				$stmtGruppo = $pdo->prepare("
					SELECT g.nome, g.dataCreazione, u.nickname, u.codice as owner_id
					FROM gruppo g
					JOIN utente u ON g.creatoDa = u.codice
					WHERE g.codice = :id
				");
				$stmtGruppo->execute([':id' => $idGruppo]);
				$infoGruppo = $stmtGruppo->fetch(PDO::FETCH_ASSOC);

				if ($infoGruppo) {
					echo "<h2>Gruppo: " . htmlspecialchars($infoGruppo['nome']) . "</h2>";
					echo "<p><strong>Data Creazione:</strong> " . formattaData($infoGruppo['dataCreazione']) . "</p>";
					
					$linkOwner = "utenti.php?utente=" . urlencode($infoGruppo['owner_id']);
					echo "<p><strong>Creato da:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($infoGruppo['nickname']) . "</a></p>";
					echo "<hr>";

					// 2. Estrazione e visualizzazione dei membri appartenenti al gruppo
					echo "<h3>Membri del gruppo</h3>";
					$stmtMembri = $pdo->prepare("
						SELECT u.codice, u.nickname, u.nome, u.cognome
						FROM utenteautorizzatogruppo uag
						JOIN utente u ON uag.codUtente = u.codice
						WHERE uag.codGruppo = :id
						ORDER BY u.nickname ASC
					");
					$stmtMembri->execute([':id' => $idGruppo]);
					$membriRaw = $stmtMembri->fetchAll(PDO::FETCH_ASSOC);

					if (!empty($membriRaw)) {
						$datiMembri = [];
						foreach ($membriRaw as $membro) {
							$linkMembro = "utenti.php?utente=" . urlencode($membro['codice']);
							$htmlMembroNickname = "<a href='{$linkMembro}'>" . htmlspecialchars($membro['nickname']) . "</a>";
							
							$datiMembri[] = [
								'Nickname' => $htmlMembroNickname,
								'Nome'     => $membro['nome'],
								'Cognome'  => $membro['cognome']
							];
						}
						stampaTabella($datiMembri, ['Nickname']);
					} else {
						echo "<p>Nessun membro associato a questo gruppo.</p>";
					}
					echo "<br><hr>";

					// 3. Estrazione e visualizzazione dei file multimediali associati al gruppo
					echo "<h3>File multimediali del gruppo</h3>";
					$stmtFile = $pdo->prepare("
						SELECT f.numero, f.titolo, f.tipo, f.dimensione, f.url
						FROM fileassociatogruppo fag
						JOIN FileMultimediale f ON fag.file = f.numero
						WHERE fag.codGruppo = :codice
						ORDER BY f.titolo ASC
					");
					$stmtFile->execute([':codice' => $idGruppo]);
					$filesRaw = $stmtFile->fetchAll(PDO::FETCH_ASSOC);

					if (!empty($filesRaw)) {
						$datiFile = [];
						foreach ($filesRaw as $file) {
							$htmlNomeFile = "<a href='" . htmlspecialchars($file['url']) . "' target='_blank'>" . htmlspecialchars($file['titolo']) . "</a>";
							
							$datiFile[] = [
								'Codice File' => $file['numero'],
								'Nome File'   => $htmlNomeFile,
								'Tipo'        => $file['tipo'],
								'Dimensione'  => $file['dimensione'] . " KB"
							];
						}
						stampaTabella($datiFile, ['Nome File']);
					} else {
						echo "<p>Nessun file multimediale associato o caricato in questo gruppo.</p>";
					}

					echo "<br><p><a href='gruppi.php'>&larr; Torna alla lista dei gruppi</a></p>";

				} else {
					echo "<p>Gruppo non trovato o non esistente.</p>";
					echo "<p><a href='gruppi.php'>Torna alla lista dei gruppi</a></p>";
				}

			} else {
				// =========================================================
				// VISTA PRINCIPALE: LISTA DEI GRUPPI (Con Filtri e Paginazione)
				// =========================================================
				$where = [];
				$params = [];

				if (!empty($_GET['nome'])) {
					$where[] = "gruppo.nome LIKE :nome";
					$params[':nome'] = "%" . $_GET['nome'] . "%";
				}
				if (!empty($_GET['proprietario'])) {
					$where[] = "utente.nickname LIKE :proprietario";
					$params[':proprietario'] = "%" . $_GET['proprietario'] . "%";
				}
				if (!empty($_GET['data'])) {
					$where[] = "DATE(gruppo.dataCreazione) >= :data";
					$params[':data'] = $_GET['data'];
				}

				// Calcolo della paginazione (Usa la utility condivisa getParametriPaginazione se esistente, altrimenti fallback)
				if (function_exists('getParametriPaginazione')) {
					list($pagina, $limit, $offset) = getParametriPaginazione(50);
				} else {
					$limit = 50;
					$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
					if ($pagina < 1) $pagina = 1;
					$offset = ($pagina - 1) * $limit;
				}

				// Calcolo dei parametri di ordinamento
				if (function_exists('getParametriOrdinamento')) {
					list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
						'nome' => 'gruppo.nome',
						'data' => 'gruppo.dataCreazione'
					], 'data', 'DESC');
				} else {
					$sort_col = 'data';
					$sort_dir = 'DESC';
					$sql_sort = 'gruppo.dataCreazione DESC';
				}

				// Query per contare i risultati totali filtrati
				$sqlConto = "SELECT COUNT(*) FROM gruppo JOIN utente ON gruppo.creatoDa = utente.codice";
				if (!empty($where)) {
					$sqlConto .= " WHERE " . implode(" AND ", $where);
				}
				$stmtConto = $pdo->prepare($sqlConto);
				$stmtConto->execute($params);
				$totaleRisultati = $stmtConto->fetchColumn();

				// Costruzione ed esecuzione query principale
				$sql = "
					SELECT 
						gruppo.codice as 'gruppo_id',
						gruppo.nome as 'Nome Gruppo',
						gruppo.dataCreazione as 'Data Creazione',
						utente.nickname as 'Proprietario',
						utente.codice as 'owner_id'
					FROM gruppo
					JOIN utente ON gruppo.creatoDa = utente.codice
				";

				if (!empty($where)) {
					$sql .= " WHERE " . implode(" AND ", $where);
				}
				
				$sql .= " ORDER BY " . $sql_sort . " LIMIT :limit OFFSET :offset";

				$stmt = $pdo->prepare($sql);
				foreach ($params as $chiave => $valore) {
					$stmt->bindValue($chiave, $valore);
				}
				$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
				$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
				$stmt->execute();
				$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

				echo "<p>Trovati <strong>$totaleRisultati</strong> gruppi ($limit per pagina).</p>";

				if (!empty($righe)) {
					$datiGruppi = [];
					foreach ($righe as $riga) {
						$linkGruppo = "gruppi.php?gruppo=" . urlencode($riga['gruppo_id']);
						$htmlNomeGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";

						$linkOwner = "utenti.php?utente=" . urlencode($riga['owner_id']);
						$htmlProprietario = "<a href='{$linkOwner}'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

						$datiGruppi[] = [
							'Nome Gruppo'    => $htmlNomeGruppo,
							'Proprietario'   => $htmlProprietario,
							'Data Creazione' => formattaData($riga['Data Creazione'])
						];
					}

					$customHeaders = generaIntestazioniOrdinabili([
						'Nome Gruppo'    => 'nome',
						'Data Creazione' => 'data'
					], $sort_col, $sort_dir);

					stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders);

					// Stampa della paginazione a fondo pagina
					if (function_exists('stampaPaginazione')) {
						stampaPaginazione($pagina, $totaleRisultati, $limit);
					} else {
						echo "<div style='margin-top:20px;'>";
						$queryParams = $_GET;
						if ($pagina > 1) {
							$queryParams['pagina'] = $pagina - 1;
							echo "<a href='?" . http_build_query($queryParams) . "'>&larr; Precedente</a> ";
						}
						echo "<span>Pagina $pagina</span> ";
						if (($pagina * $limit) < $totaleRisultati) {
							$queryParams['pagina'] = $pagina + 1;
							echo "<a href='?" . http_build_query($queryParams) . "'>Successiva &rarr;</a>";
						}
						echo "</div>";
					}
				} else {
					echo "<p>Nessun risultato trovato.</p>";
				}
			}
			?>
		</div>
	</div>

	<?php include 'footer.html'; ?>
</body>

</html>