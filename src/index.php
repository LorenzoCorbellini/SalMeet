<?php
// Includo il database e le funzioni
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/functions.php';

// =========================================================
// 1. RECUPERO STATISTICHE GLOBALI
// =========================================================
$totUtenti   = getNumberOfRecords($pdo, 'Utente');
$totGruppi   = getNumberOfRecords($pdo, 'Gruppo');
$totFiles    = getNumberOfRecords($pdo, 'FileMultimediale');
$totBacheche = getNumberOfRecords($pdo, 'Bacheca');
$spazioUsato = getSpazioTotaleOccupato($pdo);

// =========================================================
// 2. RECUPERO E FORMATTAZIONE ULTIMI FILE
// =========================================================
$ultimiFileDati = getUltimiFileCaricati($pdo, 5);
$ultimiFileFormattati = [];

$icon_types = [
    'immagine' => 'images/image.png',
    'video'    => 'images/video.png',
    'audio'    => 'images/headphones.png',
    'default'  => 'images/document.png'
];

foreach ($ultimiFileDati as $file) {
    $tipo = strtolower((string)$file['tipo_file']);
    $icon_path = $icon_types[$tipo] ?? $icon_types['default'];
    $file_icon = "<img class='icona icona-filetype' src='" . htmlspecialchars($icon_path) . "' alt='" . htmlspecialchars($tipo) . "' style='vertical-align:middle; margin-right:8px;'>";
    
    $owner_name = trim(($file['owner_name'] ?? '') . ' ' . ($file['owner_surname'] ?? ''));
    $owner_display = $owner_name !== ''
        ? formatOwnerDisplay($file['owner_name'] ?? null, $file['owner_surname'] ?? null, $file['nickname'])
        : htmlspecialchars('(@' . $file['nickname'] . ')');
    $owner_html = "<a href='utenti.php?utente=" . urlencode($file['owner_id']) . "'>" . $owner_display . "</a>";

    $ultimiFileFormattati[] = [
        'Nome File'    => $file_icon . " <a href='" . htmlspecialchars((string)$file['url']) . "' target='_blank' class='file-link'>" . htmlspecialchars((string)$file['nome_file']) . "</a>",
        'Proprietario' => $owner_html,
        'Dimensione'   => formatFileSizeHtml($file['dimensione']),
    ];
}

// =========================================================
// 3. RECUPERO E FORMATTAZIONE ULTIMI GRUPPI
// =========================================================
$gruppiDati = getUltimiGruppiCreati($pdo, 5);
$ultimiGruppiFormattati = [];

foreach ($gruppiDati as $gruppo) {
    $owner_name = trim(($gruppo['owner_name'] ?? '') . ' ' . ($gruppo['owner_surname'] ?? ''));
    $owner_display = $owner_name !== ''
        ? formatOwnerDisplay($gruppo['owner_name'] ?? null, $gruppo['owner_surname'] ?? null, $gruppo['nickname'])
        : htmlspecialchars('(@' . $gruppo['nickname'] . ')');
    $owner_html = "<a href='utenti.php?utente=" . urlencode($gruppo['owner_id']) . "'>" . $owner_display . "</a>";

    $ultimiGruppiFormattati[] = [
        'Nome Gruppo'    => "<a href='gruppi.php?gruppo=" . urlencode($gruppo['gruppoId']) . "'>" . htmlspecialchars((string)$gruppo['nome_gruppo']) . "</a>",
        'Proprietario'   => $owner_html,
        'Data Creazione' => formattaData($gruppo['dataCreazione'])
    ];
}

// =========================================================
// 4. RECUPERO E FORMATTAZIONE ULTIME BACHECHE
// =========================================================
$bachecheDati = getUltimeBachecheCreate($pdo, 5);
$ultimeBachecheFormattate = [];

foreach ($bachecheDati as $bacheca) {
    $owner_name = trim(($bacheca['owner_name'] ?? '') . ' ' . ($bacheca['owner_surname'] ?? ''));
    $owner_display = $owner_name !== ''
        ? formatOwnerDisplay($bacheca['owner_name'] ?? null, $bacheca['owner_surname'] ?? null, $bacheca['nickname'])
        : htmlspecialchars('(@' . $bacheca['nickname'] . ')');
    $owner_html = "<a href='utenti.php?utente=" . urlencode($bacheca['owner_id']) . "'>" . $owner_display . "</a>";

    $ultimeBachecheFormattate[] = [
        'Nome Bacheca'   => "<a href='bacheche.php?vista=dettaglio&bacheca=" . urlencode($bacheca['nome_bacheca']) . "&owner=" . urlencode($bacheca['owner_id']) . "'>" . htmlspecialchars((string)$bacheca['nome_bacheca']) . "</a>",
        'Proprietario'   => $owner_html,
        'Data Creazione' => formattaData($bacheca['dataCreazione'])
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
                        <div class="stat-value" style="margin-bottom: 5px;"><?= number_format($totFiles, 0, ',', '.') ?></div>
                        <div class="text-gray-small" style="margin-bottom: 15px;">(Spazio totale: <?= formatFileSize($spazioUsato) ?>)</div>
                        <a href="media.php" class="dashboard-card-link">Esplora File</a>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3>Bacheche Attive</h3>
                        <div class="stat-value"><?= number_format($totBacheche, 0, ',', '.') ?></div>
                        <a href="bacheche.php" class="dashboard-card-link">Gestisci Bacheche</a>
                    </div>
                </div>

                <h3 class="dashboard-section-title">Ultimi File Caricati</h3>
                <div class="table-container">
                    <?php
                    if (!empty($ultimiFileFormattati)) {
                        stampaTabella($ultimiFileFormattati, ['Nome File', 'Proprietario', 'Dimensione']); 
                    } else {
                        echo "<p class='info-risultati'>Nessun file caricato nel sistema.</p>";
                    }
                    ?>
                </div>

                <h3 class="dashboard-section-title" style="margin-top: 40px;">Ultimi Gruppi Creati</h3>
                <div class="table-container dashboard-table">
                    <?php
                    if (!empty($ultimiGruppiFormattati)) {
                        stampaTabella($ultimiGruppiFormattati, ['Nome Gruppo', 'Proprietario', 'Data Creazione']); 
                    } else {
                        echo "<p class='info-risultati'>Nessun gruppo presente nel sistema.</p>";
                    }
                    ?>
                </div>

                <h3 class="dashboard-section-title" style="margin-top: 40px;">Ultime Bacheche Create</h3>
                <div class="table-container dashboard-table" style="margin-bottom: 40px;">
                    <?php
                    if (!empty($ultimeBachecheFormattate)) {
                        stampaTabella($ultimeBachecheFormattate, ['Nome Bacheca', 'Proprietario', 'Data Creazione']); 
                    } else {
                        echo "<p class='info-risultati'>Nessuna bacheca presente nel sistema.</p>";
                    }
                    ?>
                </div>
                
            </div>
        </div>
    </div>

    <?php include 'footer.html'; ?>
</body>
</html>