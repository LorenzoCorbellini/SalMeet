<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
	<title>SalMeet - Utenti</title>
	<?php include 'head.html'; ?>
</head>

<body>
	<header>
		<h1 id="hcod1">Utenti</h1>
	</header>

	<div class="main-container">
		<aside class="sidebar">
			<?php include 'nav.html'; ?>

			<?php
			// =========================================================
			// CONFIGURAZIONE DINAMICA DEI FILTRI NELLA SIDEBAR
			// =========================================================
			if (empty($_GET['utente'])) {
				// Filtri per la lista globale degli utenti
				$filtro_config = [
					'campi' => [
						['tipo'  => 'text', 'name' => 'nickname', 'label' => 'Nickname'],
						['tipo'  => 'text', 'name' => 'nome',     'label' => 'Nome'],
						['tipo'  => 'text', 'name' => 'cognome',  'label' => 'Cognome'],
						['tipo'  => 'date', 'name' => 'data',     'label' => 'Data di Nascita (Da)'],
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
			if (!empty($_GET['utente'])) {
				// ---------------------------------------------------------
				// VISTA DETTAGLIO: Profilo del singolo utente
				// ---------------------------------------------------------
				$codiceUtente = $_GET['utente'];

				$sqlUtente = "SELECT nickname, nome, cognome, dataNascita FROM utente WHERE codice = :codice";
				$stmtUtente = $pdo->prepare($sqlUtente);
				$stmtUtente->execute([':codice' => $codiceUtente]);
				$utente = $stmtUtente->fetch(PDO::FETCH_ASSOC);

				if ($utente) {
					echo "<h2>Profilo di " . htmlspecialchars($utente['nickname']) . "</h2>";
					echo "<p><strong>Nome:</strong> " . htmlspecialchars($utente['nome']) . "</p>";
					echo "<p><strong>Cognome:</strong> " . htmlspecialchars($utente['cognome']) . "</p>";
					echo "<p><strong>Data di Nascita:</strong> " . formattaData($utente['dataNascita']) . "</p>";
					echo "<p><a href='utenti.php'>&larr; Torna alla lista utenti</a></p>";
				} else {
					echo "<p>Utente non trovato.</p>";
				}
			} else {
				// ---------------------------------------------------------
				// VISTA PRINCIPALE: Tabella globale con filtri e paginazione
				// ---------------------------------------------------------
				$where = [];
				$params = [];

				if (!empty($_GET['nickname'])) {
					$where[] = "nickname LIKE :nickname";
					$params[':nickname'] = '%' . $_GET['nickname'] . '%';
				}
				if (!empty($_GET['nome'])) {
					$where[] = "nome LIKE :nome";
					$params[':nome'] = '%' . $_GET['nome'] . '%';
				}
				if (!empty($_GET['cognome'])) {
					$where[] = "cognome LIKE :cognome";
					$params[':cognome'] = '%' . $_GET['cognome'] . '%';
				}
				
				// Filtro data modificato: tipo 'date' nativo (aaaa-mm-gg) come in bacheche.php
				if (!empty($_GET['data'])) {
					$where[] = "DATE(dataNascita) >= :data";
					$params[':data'] = $_GET['data'];
				}

				// Parametri di Paginazione (es. 50 record per pagina)
				list($pagina, $limit, $offset) = getParametriPaginazione(50);

				// Parametri di Ordinamento Dinamico
				list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
					'nickname' => 'nickname',
					'nome'     => 'nome',
					'cognome'  => 'cognome',
					'data'     => 'dataNascita'
				], 'nickname', 'ASC');

				// Conteggio totale dei risultati per la paginazione
				$sqlContatore = "SELECT COUNT(*) FROM utente";
				if ($where) {
					$sqlContatore .= " WHERE " . implode(" AND ", $where);
				}
				$stmtContatore = $pdo->prepare($sqlContatore);
				$stmtContatore->execute($params);
				$totaleRisultati = $stmtContatore->fetchColumn();

				// Query principale con filtri, ordinamento e limiti
				$sql = "
					SELECT codice as 'owner',
					       nickname as 'Nickname',
					       nome as 'Nome',
					       cognome as 'Cognome',
					       dataNascita as 'Data di Nascita'
					FROM utente
				";
				if ($where) {
					$sql .= " WHERE " . implode(" AND ", $where);
				}
				$sql .= " ORDER BY {$sql_sort} {$sort_dir} LIMIT :limit OFFSET :offset";

				$stmt = $pdo->prepare($sql);
				foreach ($params as $chiave => $valore) {
					$stmt->bindValue($chiave, $valore);
				}
				$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
				$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
				$stmt->execute();
				$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);

				echo "<p>Trovati <strong>$totaleRisultati</strong> utenti ($limit per pagina).</p>";

				if (!empty($righe)) {
					$datiUtenti = [];
					foreach ($righe as $riga) {
						// Generiamo il link ipertestuale che punta alla vista dettaglio dello specifico utente
						$linkDettaglio = "utenti.php?utente=" . urlencode($riga['owner']);
						$htmlNickname = "<a href='{$linkDettaglio}'>" . htmlspecialchars($riga['Nickname']) . "</a>";

						$datiUtenti[] = [
							'Nickname'        => $htmlNickname,
							'Nome'            => $riga['Nome'],
							'Cognome'         => $riga['Cognome'],
							'Data di Nascita' => formattaData($riga['Data di Nascita'])
						];
					}

					// Generazione dinamica dei link per l'ordinamento delle colonne
					$customHeaders = generaIntestazioniOrdinabili([
						'Nickname'        => 'nickname',
						'Nome'            => 'nome',
						'Cognome'         => 'cognome',
						'Data di Nascita' => 'data'
					], $sort_col, $sort_dir);

					// Stampiamo la tabella passando 'Nickname' nelle colonne HTML consentite per preservare il link <a>
					stampaTabella($datiUtenti, ['Nickname'], $customHeaders);

					// Barra di navigazione per la paginazione
					stampaPaginazione($pagina, $totaleRisultati, $limit);
				} else {
					echo "<p class='info-risultati'>Nessun utente trovato con i criteri di ricerca selezionati.</p>";
				}
			}
			?>
		</div>
	</div>

	<?php include 'footer.html'; ?>
</body>

</html>