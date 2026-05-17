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
		<h1 id="hcod1">Gruppi</h1>
	</header>

	<div class="main-container">
		<aside class="sidebar">
			<?php
			include 'nav.html';
			?>
		</aside>

		<div id="content">

			<?php

			$sql = "
			SELECT 
				utente.nickname as 'Nickname',
				gruppo.nome as 'Nome Gruppo',
				gruppo.dataCreazione as 'Data Creazione'
			FROM gruppo
			JOIN utente
			ON gruppo.creatoDa = utente.codice
			";

			$stmt = $pdo->query($sql);
			$righe = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			stampaTabella($righe);


			?>
		</div>
	</div>

	<?php include 'footer.html'; ?>
</body>

</html>