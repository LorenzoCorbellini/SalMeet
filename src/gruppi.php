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
		<h1 id="hcod1">Gruppi</h1>
	</header>

	<div class="main-container">
		<aside class="sidebar">
			<?php include 'nav.html'; ?>

			<?php
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

				// Cattura l'URL attuale per navigare indietro correttamente da Utenti
				$current_url = $_SERVER['REQUEST_URI'];

				$stmtGruppo = $pdo->prepare("
                    SELECT g.nome, g.dataCreazione, u.nickname, u.codice as ownerId
                    FROM Gruppo g
                    JOIN Utente u ON g.creatoDa = u.codice
                    WHERE g.codice = :id
                ");
				$stmtGruppo->execute([':id' => $idGruppo]);
				$infoGruppo = $stmtGruppo->fetch(PDO::FETCH_ASSOC);

				if ($infoGruppo) {
					echo "<h2>Gruppo: " . htmlspecialchars($infoGruppo['nome']) . "</h2>";
					echo "<p><strong>Data Creazione:</strong> " . formattaData($infoGruppo['dataCreazione']) . "</p>";

					$linkOwner = "utenti.php?utente=" . urlencode($infoGruppo['ownerId']) . "&return_to=" . urlencode($current_url);
					echo "<p><strong>Creato da:</strong> <a href='{$linkOwner}'>" . htmlspecialchars($infoGruppo['nickname']) . "</a></p>";
					//echo "<hr>";

					echo "<h3>Membri del gruppo</h3>";
					$stmtMembri = $pdo->prepare("
                        SELECT u.codice, u.nickname, u.nome, u.cognome
                        FROM UtenteAutorizzatoGruppo uag
                        JOIN Utente u ON uag.codUtente = u.codice
                        WHERE uag.codGruppo = :id
                        ORDER BY u.nickname ASC
                    ");
					$stmtMembri->execute([':id' => $idGruppo]);
					$membriRaw = $stmtMembri->fetchAll(PDO::FETCH_ASSOC);

					if (!empty($membriRaw)) {
						$datiMembri = [];
						foreach ($membriRaw as $membro) {
							// MODIFICA QUI: Aggiunto return_to
							$linkMembro = "utenti.php?utente=" . urlencode($membro['codice']) . "&return_to=" . urlencode($current_url);
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

					// ---------------------------------------------------------
					// TABELLA 3: FILE MULTIMEDIALI CARICATI DALL'UTENTE (MODIFICATA)
					// ---------------------------------------------------------

					echo "<h3>File multimediali del gruppo</h3>";
					$stmtFile = $pdo->prepare("
                        SELECT uProp.nickname, uProp.codice as caricatoDa, f.titolo, f.tipo, f.dimensione, f.URL
                        FROM FileAssociatoGruppo fag
                        JOIN FileMultimediale f ON fag.file = f.numero
						JOIN Utente uProp ON uProp.codice=f.caricatoDa
                        WHERE fag.codGruppo = :codice
                        ORDER BY f.titolo ASC
                    ");
					$stmtFile->execute([':codice' => $idGruppo]);
					$filesRaw = $stmtFile->fetchAll(PDO::FETCH_ASSOC);

					if (!empty($filesRaw)) {
						$datiFiles = [];

						$icon_types = [
							'immagine' => 'images/image.png',
							'video'    => 'images/video.png',
							'audio'    => 'images/headphones.png',
							'default'  => 'images/document.png'
						];

						foreach ($filesRaw as $file) {
							$icon_path = $icon_types[$file['tipo']] ?? $icon_types['default'];

							$file_icon = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($file['tipo']) . "'>";
							$file_name = htmlspecialchars($file['titolo']);
							$file_link = htmlspecialchars($file['URL']);
							$owner_link = "utenti.php?utente=" . urlencode($file['caricatoDa']);
							if (!empty($current_url)) {
								$owner_link .= "&return_to=" . urlencode($current_url);
							}
							$htmlOwner = "<a href='" . htmlspecialchars($owner_link) .  "'>" . htmlspecialchars($file['nickname']) . "</a>";

							$title_html = "<div id='file_name'>{$file_icon}<a href='{$file_link}'>{$file_name}</a></div>";
							$size_html = formatFileSizeHtml((int)$file['dimensione']);

							$datiFiles[] = [
								'File'       => $title_html,
								'Proprietario' => $htmlOwner,
								'Dimensione' => $size_html
							];
						}
						stampaTabella($datiFiles, ['File', 'Proprietario', 'Dimensione']);
					} else {
						echo "<p>Nessun file multimediale associato o caricato in questo gruppo.</p>";
					}

					$back_url = !empty($_GET['return_to']) ? $_GET['return_to'] : 'gruppi.php';
					echo "<br><p><a href='" . htmlspecialchars($back_url) . "'>&larr; Torna alla pagina precedente</a></p>";
				} else {
					echo "<p>Gruppo non trovato o non esistente.</p>";
					echo "<p><a href='gruppi.php'>Torna alla pagina precedente</a></p>";
				}
			} else {
				// =========================================================
				// VISTA PRINCIPALE: LISTA DEI GRUPPI
				// =========================================================
				$where = [];
				$params = [];

				if (!empty($_GET['nome'])) {
					$where[] = "Gruppo.nome LIKE :nome";
					$params[':nome'] = "%" . $_GET['nome'] . "%";
				}
				if (!empty($_GET['proprietario'])) {
					$where[] = "Utente.nickname LIKE :proprietario";
					$params[':proprietario'] = "%" . $_GET['proprietario'] . "%";
				}
				if (!empty($_GET['data'])) {
					$where[] = "DATE(Gruppo.dataCreazione) >= :data";
					$params[':data'] = $_GET['data'];
				}

				
					list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
						'nome' => 'Gruppo.nome',
						'Proprietario' => 'Proprietario',
						'data' => 'Gruppo.dataCreazione'
					], 'data', 'ASC');
				

				$sqlContatore = "SELECT COUNT(*) FROM Gruppo JOIN Utente ON Gruppo.creatoDa = Utente.codice";
				if (!empty($where)) {
					$sqlContatore .= " WHERE " . implode(" AND ", $where);
				}
				$stmtConto = $pdo->prepare($sqlContatore);
				$stmtConto->execute($params);
				$totaleRisultati = $stmtConto->fetchColumn();

				$sql = "
                    SELECT 
                        Gruppo.codice as 'gruppoId',
                        Gruppo.nome as 'Nome Gruppo',
                        Gruppo.dataCreazione as 'Data Creazione',
                        Utente.nickname as 'Proprietario',
                        Utente.codice as 'ownerId'
                    FROM Gruppo
                    JOIN Utente ON Gruppo.creatoDa = Utente.codice
                ";

				if (!empty($where)) {
					$sql .= " WHERE " . implode(" AND ", $where);
				}

				$sql .= " ORDER BY " . $sql_sort;

				$stmt = $pdo->prepare($sql);
				foreach ($params as $chiave => $valore) {
					$stmt->bindValue($chiave, $valore);
				}
				$stmt->execute();
				$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

				echo "<p class='info-risultati'>Trovati <strong>$totaleRisultati</strong> gruppi.</p>";

				if (!empty($righe)) {
					$datiGruppi = [];
					$current_url = $_SERVER['REQUEST_URI'];

					foreach ($righe as $riga) {
						$linkGruppo = "gruppi.php?gruppo=" . urlencode($riga['gruppoId']) . "&return_to=" . urlencode($current_url);
						$htmlNomeGruppo = "<a href='{$linkGruppo}'>" . htmlspecialchars($riga['Nome Gruppo']) . "</a>";

						$linkOwner = "utenti.php?utente=" . urlencode($riga['ownerId']) . "&return_to=" . urlencode($current_url);
						$htmlProprietario = "<a href='{$linkOwner}'>" . htmlspecialchars($riga['Proprietario']) . "</a>";

						$datiGruppi[] = [
							'Nome Gruppo'    => $htmlNomeGruppo,
							'Proprietario'   => $htmlProprietario,
							'Data Creazione' => formattaData($riga['Data Creazione'])
						];
					}

					$customHeaders = generaIntestazioniOrdinabili([
						'Nome Gruppo'    => 'nome',
						'Proprietario'	 => 'Proprietario',
						'Data Creazione' => 'data'
					], $sort_col, $sort_dir);

					stampaTabella($datiGruppi, ['Nome Gruppo', 'Proprietario'], $customHeaders);
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