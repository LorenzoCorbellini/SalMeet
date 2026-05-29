<?php
/*
 * Modulo universale per l'inizializzazione dei filtri grafici e la compilazione SQL dinamica.
 */

/**
 * 1. CONFIGURAZIONE DEI FILTRI (Interfaccia Grafica)
 * Genera la configurazione dei campi input da passare al componente filter.php.
 *
 * @param string $entita L'entità da filtrare ('bacheche', 'utenti', 'file', 'gruppi', 'vuoto')
 * @param array $parametriExtra Chiave-valore di campi hidden (es. ['vista' => 'dettaglio', 'tab' => 'utenti'])
 */
function getFiltroConfig(string $entita, array $parametriExtra = []): array
{
    $campiBase = [];

    // Genera automaticamente i campi hidden strutturali passati dalla pagina corrente
    foreach ($parametriExtra as $name => $value) {
        $campiBase[] = ['tipo' => 'hidden', 'name' => $name, 'value' => $value, 'label' => ''];
    }

    switch ($entita) {
        //filtro bacheche
        case 'bacheche':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'titolo',               'label' => 'Nome Bacheca'],
                    ['tipo' => 'date', 'name' => 'data',                 'label' => 'Creata dopo'],
                    ['tipo' => 'text', 'name' => 'proprietario',         'label' => 'Nickname Proprietario'],
                    ['tipo' => 'text', 'name' => 'proprietario_nome',    'label' => 'Nome Proprietario'],
                    ['tipo' => 'text', 'name' => 'proprietario_cognome', 'label' => 'Cognome Proprietario'],
                ])
            ];

            //filtro utenti
        case 'utenti':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'utente',       'label' => 'Nickname Utente'],
                    ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Utente'],
                    ['tipo' => 'text', 'name' => 'cognome',      'label' => 'Cognome Utente'],
                    ['tipo' => 'date', 'name' => 'data_nascita', 'label' => 'Nato dopo'],
                ])
            ];

            //filtro file
        case 'gruppi':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Gruppo'],
                    ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Nickname Proprietario'],
                    ['tipo' => 'date', 'name' => 'data',         'label' => 'Creata dopo'],
                ])
            ];
        case 'file':
            $minSize = $parametriExtra['min_size'] ?? 0;
            $maxSize = $parametriExtra['max_size'] ?? 100;
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'file',              'label' => 'Nome File'],
                    ['tipo' => 'text', 'name' => 'proprietario_file', 'label' => 'Nickname Proprietario'],
                    [
                        'tipo' => 'checkbox-group',
                        'name' => 'filetype',
                        'label' => 'Tipo',
                        'opzioni' => [
                            'immagine' => 'Immagini',
                            'audio' => 'Audio',
                            'video' => 'Video'
                        ]
                    ],
                    [
                        'tipo' => 'multi-range',
                        'name_min' => 'dimensione_min',
                        'name_max' => 'dimensione_max',
                        'label' => 'Dimensione',
                        'min' => $minSize,
                        'max' => $maxSize,
                        'value_min' => (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') ? (int)$_GET['dimensione_min'] : $minSize,
                        'value_max' => (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') ? (int)$_GET['dimensione_max'] : $maxSize
                    ],
                ])
            ];

            //filtro vuoto
        default:
            return [
                'vuoto' => true,
                'messaggio' => 'Non sono presenti filtri per questa sezione',
                'campi' => []
            ];
    }
}

/**
 * 2. DIZIONARIO DELLE REGOLE SQL
 * Mappa le chiavi ricevute dal form HTML con le colonne reali del database e i relativi operatori.
 */
function getRegoleFiltroSQL(string $entita): array
{
    $mappe = [
        'bacheche' => [
            'titolo'               => ['colonna' => 'b.nome',          'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario'         => ['colonna' => 'u.nickname',      'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario_nome'    => ['colonna' => 'u.nome',          'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario_cognome' => ['colonna' => 'u.cognome',       'operatore' => 'LIKE', 'formato' => '%val%'],
            'data'                 => ['colonna' => 'b.dataCreazione', 'operatore' => '>=',   'formato' => 'val'],
        ],
        'utenti' => [
            'utente'        => ['colonna' => 'u.nickname',      'operatore' => 'LIKE', 'formato' => '%val%'],
            'nome'          => ['colonna' => 'u.nome',          'operatore' => 'LIKE', 'formato' => '%val%'],
            'cognome'       => ['colonna' => 'u.cognome',       'operatore' => 'LIKE', 'formato' => '%val%'],
            'data_nascita'  => ['colonna' => 'u.dataNascita',   'operatore' => '>=',   'formato' => 'val'],
        ],
        'file' => [
            'file'              => ['colonna' => 'fm.nome',       'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario_file' => ['colonna' => 'u.nickname',    'operatore' => 'LIKE', 'formato' => '%val%'],
            'dimensione_min'    => ['colonna' => 'fm.dimensione', 'operatore' => '>=',   'formato' => 'val'],
            'dimensione_max'    => ['colonna' => 'fm.dimensione', 'operatore' => '<=',   'formato' => 'val'],
        ]
    ];

    return $mappe[$entita] ?? [];
}

/**
 * 3. COMPILATORE DI QUERY UNIVERSALE
 * Analizza i dati in ingresso (es. $_GET) in base alle regole dell'entità e restituisce la clausola SQL e i parametri PDO sanificati.
 */
function applicaFiltriDinamici(array $inputs, string $entita): array
{
    $regole = getRegoleFiltroSQL($entita);
    $condizioni = [];
    $parametri = [];

    foreach ($regole as $chiaveInput => $regola) {
        if (isset($inputs[$chiaveInput]) && $inputs[$chiaveInput] !== '') {
            $valore = trim($inputs[$chiaveInput]);
            $colonna = $regola['colonna'];
            $operatore = $regola['operatore'];

            // Genera placeholder univoco per PDO (es. :dimensione_min)
            $placeholder = ":" . str_replace('.', '_', $chiaveInput);

            if ($regola['formato'] === '%val%') {
                $valore = "%" . $valore . "%";
            }

            $condizioni[] = "{$colonna} {$operatore} {$placeholder}";
            $parametri[$placeholder] = $valore;
        }
    }

    return [
        'sql' => !empty($condizioni) ? " AND " . implode(" AND ", $condizioni) : "",
        'parametri' => $parametri
    ];
}
