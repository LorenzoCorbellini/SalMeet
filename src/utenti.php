<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
	<title>SalMeet</title>
	<?php include 'head.html'; ?>
</head>

<body>
	<header>
		<h1 id="hcod1">Utenti</h1>
	</header>

	<div class="main-container">
		<aside class="sidebar">
			<?php include 'nav.html';


			//Config dei filtri
			if (empty($_GET['utente'])) {
				$filtro_config = [
					'campi' => [
						['tipo'  => 'text', 'name' => 'nickname', 'label' => 'Nickname'],
						['tipo'  => 'text', 'name' => 'nome',     'label' => 'Nome'],
						['tipo'  => 'text', 'name' => 'cognome',  'label' => 'Cognome'],
						['tipo'  => 'text', 'name' => 'data',     'label' => 'Data di Nascita (gg/mm/aaaa)'],
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
				$utenteId = (int)$_GET['utente'];

				// Query per i dati dell'utente
				$stmt = $pdo->prepare("SELECT nickname, nome, cognome, dataNascita FROM Utente WHERE codice = :codice");
				$stmt->execute([':codice' => $utenteId]);
				$utente = $stmt->fetch(PDO::FETCH_ASSOC);

				if ($utente) {
					echo "<p><a href='utenti.php'>&larr; Torna all'elenco utenti</a></p>";
					echo "<h2 class='h2utente'> Profilo di <i>" . htmlspecialchars($utente['nickname']) . "</i></b></h2>";
					echo "<p><strong>Nome e Cognome:</strong> " . htmlspecialchars($utente['nome'] . " " . $utente['cognome']) . "</p>";

					// Query delle bacheche di cui è Proprietario
					$stmtBachecheProprietario = $pdo->prepare("
						SELECT nome AS 'Nome Bacheca', dataCreazione AS 'Data Creazione' 
						FROM Bacheca 
						WHERE codiceUtente = :codice
						ORDER BY dataCreazione DESC
					");
					$stmtBachecheProprietario->execute([':codice' => $utenteId]);
					$bachecheProprietario = $stmtBachecheProprietario->fetchAll(PDO::FETCH_ASSOC);

					echo "<h3>Bacheche gestite (Proprietario)</h3>";
					stampaTabella($bachecheProprietario);

					// Bacheche in cui è semplicemente presente come utente Autorizzato
					$stmtBachecheAutorizzato = $pdo->prepare("
						SELECT uab.nomeBacheca AS 'Nome Bacheca', u.nickname AS 'Proprietario' 
						FROM UtenteAutorizzatoBacheca uab
						JOIN Utente u ON u.codice = uab.codUtente
						WHERE uab.utenteAutorizzato = :codice
						ORDER BY uab.nomeBacheca ASC
					");
					$stmtBachecheAutorizzato->execute([':codice' => $utenteId]);
					$bachecheAutorizzato = $stmtBachecheAutorizzato->fetchAll(PDO::FETCH_ASSOC);

					echo "<h3>Bacheche in cui è autorizzato</h3>";
					stampaTabella($bachecheAutorizzato);

					echo "<h3>Gruppi gestiti (Proprietario)</h3>";

					$stmtGruppiProprietario = $pdo->prepare("
							SELECT g.nome AS 'Nome Gruppo', g.dataCreazione AS 'Creato il' 
							FROM Gruppo g
							JOIN Utente u ON u.codice = g.creatoDa
							WHERE u.codice = :codice
							ORDER BY g.nome ASC
						");
					$stmtGruppiProprietario->execute([':codice' => $utenteId]);
					$gruppiProprietario = $stmtGruppiProprietario->fetchAll(PDO::FETCH_ASSOC);
					stampaTabella($gruppiProprietario);



					echo "<h3>Gruppi a cui appartiene</h3>";

					$stmtGruppi = $pdo->prepare("
							SELECT g.nome AS 'Nome Gruppo', p.nickname AS 'Proprietario'
							FROM Gruppo g
							JOIN UtenteAutorizzatoGruppo uag ON g.codice = uag.codGruppo
							JOIN Utente u ON u.codice = uag.codUtente
							JOIN Utente p ON p.codice = g.creatoDa
							WHERE u.codice = :codice
							ORDER BY g.nome ASC
						");
					$stmtGruppi->execute([':codice' => $utenteId]);
					$gruppi = $stmtGruppi->fetchAll(PDO::FETCH_ASSOC);
					stampaTabella($gruppi);
				} else {
					echo "<p>Utente non trovato.</p>";
				}
			} else {
				// =========================================================
				// VISTA PRINCIPALE (ELENCO UTENTI COMPLETO)
				// =========================================================
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
				if (!empty($_GET['data'])) {
					$dataConvertita = DateTime::createFromFormat('d/m/Y', $_GET['data']);
					if ($dataConvertita) {
						$where[]         = "DATE(dataNascita) = :data";
						$params[':data'] = $dataConvertita->format('Y-m-d');
					}
				}

				// Gestione parametri paginazione (Limit predefinito a 50)
				list($pagina, $limit, $offset) = getParametriPaginazione(50);

				// Configurazione colonne ordinabili (Incrocio URL GET => Colonna DB reale)
				list($sort_col, $sort_dir, $sql_sort) = getParametriOrdinamento([
					'nickname' => 'nickname',
					'nome'     => 'nome',
					'cognome'  => 'cognome',
					'data'     => 'dataNascita'
				], 'nickname', 'ASC');

				// Conteggio risultati totali filtrati
				$sqlCount = "SELECT COUNT(*) AS totale FROM Utente";
				if ($where) $sqlCount .= " WHERE " . implode(" AND ", $where);
				$stmtCount = $pdo->prepare($sqlCount);
				$stmtCount->execute($params);
				$totaleRisultati = $stmtCount->fetch(PDO::FETCH_ASSOC)['totale'];

				// Query principale di estrazione dati
				$sql = "
					SELECT 
						codice AS 'owner',
						nickname AS 'Nickname',
						nome AS 'Nome',
						cognome AS 'Cognome',
						dataNascita AS 'Data di Nascita'
					FROM Utente
				";
				if ($where) $sql .= " WHERE " . implode(" AND ", $where);
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
							'Data di Nascita' => $riga['Data di Nascita']
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

					// Stampa dei link di navigazione delle pagine sotto la tabella
					stampaPaginazione($pagina, $totaleRisultati, $limit);
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