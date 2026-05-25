<<?php
// Includo il database e le funzioni
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// 1. Recupero Statistiche (Tutto tramite funzioni pulite!)
$totUtenti   = getNumberOfRecords($pdo, 'Utente');
$totGruppi   = getNumberOfRecords($pdo, 'Gruppo');
$totFiles    = getNumberOfRecords($pdo, 'FileMultimediale');
$spazioUsato = getSpazioTotaleOccupato($pdo);

// 2. Recupero e Formattazione degli ultimi file
$ultimiFileDati = getUltimiFileCaricati($pdo, 5);
$ultimiFileFormattati = [];

foreach ($ultimiFileDati as $file) {
    $ultimiFileFormattati[] = [
        'Nome File'   => htmlspecialchars($file['nome_file']),
        'Caricato da' => htmlspecialchars((string)$file['autore']),
        'Tipo'        => htmlspecialchars((string)$file['tipo_file']), 
        'Dimensione'  => formatFileSizeHtml($file['dimensione']) 
    ];
}
?>
<!DOCTYPE HTML>
<html lang="it">

<head>
    <title>SalMeet - Dashboard Amministratore</title>
    <?php include 'head.html'; ?>
</head>

<body>
    <header>
        <h1 id="hcod1">SalMeet - Pannello di Controllo</h1>
    </header>
    
    <div class="main-container">
        <aside class="sidebar">
            <?php include 'nav.html'; ?>
        </aside>

        <div id="content">
            <div class="dashboard-wrapper">
                
                <div class="dashboard-header">
                    <h2>Benvenuto, Amministratore</h2>
                    <p>Panoramica generale dello stato del sistema.</p>
                </div>

                <h3 class="dashboard-section-title">Statistiche di Sistema</h3>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>Utenti Registrati</h3>
                        <div class="stat-value"><?= number_format($totUtenti, 0, ',', '.') ?></div>
                        <a href="utenti.php" class="dashboard-card-link">Gestisci Utenti</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>Gruppi Attivi</h3>
                        <div class="stat-value"><?= number_format($totGruppi, 0, ',', '.') ?></div>
                        <a href="gruppi.php" class="dashboard-card-link">Gestisci Gruppi</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>File Condivisi</h3>
                        <div class="stat-value"><?= number_format($totFiles, 0, ',', '.') ?></div>
                        <a href="media.php" class="dashboard-card-link">Esplora File</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>Spazio Occupato</h3>
                        <div class="stat-value"><?= formatFileSize($spazioUsato) ?></div>
                        <a href="bacheche.php" class="dashboard-card-link">Gestisci Bacheche</a>
                    </div>
                </div>

                <h3 class="dashboard-section-title">Ultimi File Caricati</h3>
                <?php
                if (!empty($ultimiFileFormattati)) {
                    // La funzione stamperà la tabella nativa, ma il nuovo CSS in lightmode la modellerà perfettamente
                    stampaTabella($ultimiFileFormattati, ['Dimensione']); 
                } else {
                    echo "<p class='info-risultati'>Nessun file caricato nel sistema.</p>";
                }
                ?>
                
            </div>
        </div>
    </div>

    <?php include 'footer.html'; ?>
</body>
</html>