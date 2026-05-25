<?php
/*
 * Modulo per la gestione, l'estrazione e l'inizializzazione centralizzata dei filtri.
 */

/* =============================================================================
    Filtri Bacheche
   ============================================================================= */
/**
 * Genera l'array dei campi nascosti (hidden) necessari a preservare lo stato 
 * della bacheca e del tab attivo durante il filtraggio in modalità dettaglio.
 */
function getCampiBaseDettaglio(string $bacheca, $owner, string $tab): array
{
    return [
        ['tipo' => 'hidden', 'name' => 'vista',   'value' => 'dettaglio', 'label' => ''],
        ['tipo' => 'hidden', 'name' => 'bacheca', 'value' => $bacheca,    'label' => ''],
        ['tipo' => 'hidden', 'name' => 'owner',   'value' => $owner,      'label' => ''],
        ['tipo' => 'hidden', 'name' => 'tab',     'value' => $tab,        'label' => ''],
    ];
}

/**
 * Inizializza il filtro per l'elenco generale delle bacheche.
 */
function getFiltroBachecheGenerale(): array
{
    return [
        'campi' => [
            ['tipo' => 'text', 'name' => 'titolo',       'label' => 'Nome'],
            ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Nickname Proprietario'],
            ['tipo' => 'date', 'name' => 'data',         'label' => 'Creata dopo'],
        ]
    ];
}

/**
 * Inizializza il filtro per la lista degli utenti autorizzati (Tab Utenti).
 */
function getFiltroBachecheUtenti(string $bacheca, $owner, string $tab): array
{
    return [
        'campi' => array_merge(getCampiBaseDettaglio($bacheca, $owner, $tab), [
            ['tipo' => 'text',   'name' => 'utente',       'label' => 'Nickname'],
            ['tipo' => 'text',   'name' => 'nome',         'label' => 'Nome'],
            ['tipo' => 'text',   'name' => 'cognome',      'label' => 'Cognome'],
            ['tipo' => 'date',   'name' => 'data_nascita', 'label' => 'Nato dal'],
        ])
    ];
}

/**
 * Inizializza il filtro per la lista dei file multimediali (Tab File).
 * Esegue il calcolo automatico dei limiti min/max di dimensione presenti nel DB per quella bacheca.
 */
function getFiltroBachecheFile(PDO $pdo, string $bacheca, $owner, string $tab): array
{
    // Calcolo dinamico del range di dimensioni dei file legati alla bacheca
    $stmtRange = $pdo->prepare("
        SELECT MIN(fm.dimensione) as min_dim, MAX(fm.dimensione) as max_dim 
        FROM FilePubblicatoBacheca fb
        JOIN FileMultimediale fm ON fm.numero = fb.file
        WHERE fb.nomeBacheca = :bacheca AND fb.codUtente = :owner
    ");
    $stmtRange->execute([':bacheca' => $bacheca, ':owner' => $owner]);
    $rangeDati = $stmtRange->fetch(PDO::FETCH_ASSOC);

    $minSize = isset($rangeDati['min_dim']) ? floor($rangeDati['min_dim']) : 0;
    $maxSize = isset($rangeDati['max_dim']) ? ceil($rangeDati['max_dim']) : 100;
    if ($minSize == $maxSize) {
        $minSize = 0;
    }

    // Lettura dei valori correnti impostati dall'utente (o fallback sul min/max calcolato)
    $currentMin = (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') ? (int)$_GET['dimensione_min'] : $minSize;
    $currentMax = (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') ? (int)$_GET['dimensione_max'] : $maxSize;

    return [
        'campi' => array_merge(getCampiBaseDettaglio($bacheca, $owner, $tab), [
            ['tipo' => 'text',   'name' => 'file',              'label' => 'Nome'],
            ['tipo' => 'text',   'name' => 'proprietario_file', 'label' => 'Nickname Proprietario'],
            [
                'tipo' => 'multi-range',
                'name_min' => 'dimensione_min',
                'name_max' => 'dimensione_max',
                'label' => 'Dimensione',
                'min' => $minSize,
                'max' => $maxSize,
                'value_min' => $currentMin,
                'value_max' => $currentMax
            ],
        ])
    ];
}