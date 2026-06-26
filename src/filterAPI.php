<?php
/**
 * @file filterAPI.php
 * @description Modulo universale per l'inizializzazione dei filtri grafici e la compilazione SQL dinamica.
 */

/**
 * Genera la configurazione dei campi di input da passare al componente filter.php per il rendering dell'interfaccia grafica.
 *
 * @param string $entita L'entità da filtrare ('bacheche', 'utenti', 'file', 'gruppi', 'file_bacheche', 'vuoto').
 * @param array $parametriExtra Array associativo per i campi hidden strutturali (es. ['vista' => 'dettaglio', 'tab' => 'utenti']).
 * @return array Configurazione strutturata dei campi del filtro.
 */
function getFiltroConfig(string $entita, array $parametriExtra = []): array
{
    $campiBase = [];

    foreach ($parametriExtra as $name => $value) {
        $campiBase[] = ['tipo' => 'hidden', 'name' => $name, 'value' => $value, 'label' => ''];
    }
    echo "";

    switch ($entita) {
        case 'bacheche':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'titolo',               'label' => 'Nome Bacheca', 'placeholder' => 'es. Botanica di notte'],
                    ['tipo' => 'date', 'name' => 'data',                 'label' => 'Creata dopo'],
                    ['tipo' => 'text', 'name' => 'ricerca_proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome, Cognome, Nickname'],
                ])
            ];

        case 'utenti':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'ricerca_globale', 'label' => 'Utente', 'placeholder' => 'Nome, Cognome, Nickname'],
                    ['tipo' => 'date', 'name' => 'data_nascita', 'label' => 'Nato dopo'],
                ])
            ];

        case 'gruppi':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Gruppo', 'placeholder' => 'es. Campeggio'],
                    ['tipo' => 'date', 'name' => 'data',         'label' => 'Creato dopo'],
                    ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome, Cognome, Nickname'],
                ])
            ];

        case 'file':
            $minSize = isset($parametriExtra['min_size']) ? (int)$parametriExtra['min_size'] : getMinFileSizeFromDb();
            $maxSize = isset($parametriExtra['max_size']) ? (int)$parametriExtra['max_size'] : getMaxFileSizeFromDb();
            
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'file',              'label' => 'Nome File', 'placeholder' => 'es. Vlog dal campeggio'],
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
                        'scale' => 'log',
                        'steps' => 1000,
                        'min' => $minSize,
                        'max' => $maxSize,
                        'value_min' => (isset($_GET['dimensione_min']) && $_GET['dimensione_min'] !== '') ? (int)$_GET['dimensione_min'] : $minSize,
                        'value_max' => (isset($_GET['dimensione_max']) && $_GET['dimensione_max'] !== '') ? (int)$_GET['dimensione_max'] : $maxSize
                    ],
                    ['tipo' => 'text', 'name' => 'proprietario_file', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome, Cognome, Nickname'],
                ])
            ];

        case 'file_bacheche':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'titolo',               'label' => 'Nome Bacheca', 'placeholder' => 'es. Botanica di notte'],
                    ['tipo' => 'date', 'name' => 'data',                 'label' => 'Creata dopo'],
                    ['tipo' => 'text', 'name' => 'ricerca_proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome, Cognome, Nickname'],
                ])
            ];

        default:
            return [
                'vuoto' => true,
                'messaggio' => 'Non sono presenti filtri per questa sezione',
                'campi' => []
            ];
    }
}

/**
 * Mappa le chiavi ricevute dal form HTML con le colonne reali del database e i relativi operatori per la costruzione della query.
 *
 * @param string $entita L'entità di riferimento per caricare il dizionario regole.
 * @return array Array multidimensionale contenente le direttive SQL associate agli input.
 */
function getRegoleFiltroSQL(string $entita): array
{
    $mappe = [
        'bacheche' => [
            'titolo'               => ['colonna' => 'b.nome', 'operatore' => 'LIKE', 'formato' => '%val%'],
            'ricerca_proprietario' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
            'data' => ['colonna' => 'b.dataCreazione', 'operatore' => '>=', 'formato' => 'val'],
        ],
        'utenti' => [
            'ricerca_globale' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
            'data_nascita' => ['colonna' => 'u.dataNascita', 'operatore' => '>=', 'formato' => 'val'],
        ],
        'gruppi' => [
            'nome'         => ['colonna' => 'g.nome', 'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
            'data'         => ['colonna' => 'g.dataCreazione', 'operatore' => '>=', 'formato' => 'val'],
        ],
        'file' => [
            'file'              => ['colonna' => 'fm.nome',       'operatore' => 'LIKE', 'formato' => '%val%'],
            'proprietario_file' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
            'dimensione_min'    => ['colonna' => 'fm.dimensione', 'operatore' => '>=',   'formato' => 'val'],
            'dimensione_max'    => ['colonna' => 'fm.dimensione', 'operatore' => '<=',   'formato' => 'val'],
        ],
        'file_bacheche' => [
            'titolo'               => ['colonna' => 'pb.nomeBacheca', 'operatore' => 'LIKE', 'formato' => '%val%'],
            'data'                 => ['colonna' => 'b.dataCreazione', 'operatore' => '>=', 'formato' => 'val'],
            'ricerca_proprietario' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
        ],
    ];

    return $mappe[$entita] ?? [];
}

/**
 * Analizza i dati in ingresso e restituisce la stringa di condizioni e l'array di parametri PDO sanificati.
 * Gestisce automaticamente la logica standard (singola colonna) e la logica di ricerca globale (multi-colonna/multi-parola).
 *
 * @param array $inputs Array dei dati di input attivi (es. $_GET).
 * @param string $entita L'entità di riferimento per l'applicazione delle regole SQL.
 * @return array Ritorna un array associativo con 'sql' (clausola WHERE) e 'parametri' (array per PDO).
 */
function applicaFiltriDinamici(array $inputs, string $entita): array
{
    $regole = getRegoleFiltroSQL($entita);
    $condizioni = [];
    $parametri = [];

    foreach ($regole as $chiaveInput => $regola) {
        if (isset($inputs[$chiaveInput]) && $inputs[$chiaveInput] !== '') {
            $valore = trim($inputs[$chiaveInput]);

            if (isset($regola['tipo']) && $regola['tipo'] === 'ricerca_multipla') {
                $parole = preg_split('/\s+/', $valore);
                $subCondizioniAND = [];

                foreach ($parole as $indexParola => $parola) {
                    $subCondizioniOR = [];

                    foreach ($regola['colonne'] as $indexColonna => $colonna) {
                        $paramName = ":rg_{$chiaveInput}_{$indexParola}_{$indexColonna}";

                        $subCondizioniOR[] = "$colonna LIKE $paramName";
                        $parametri[$paramName] = "%" . $parola . "%";
                    }

                    $subCondizioniAND[] = "(" . implode(' OR ', $subCondizioniOR) . ")";
                }

                $condizioni[] = "(" . implode(' AND ', $subCondizioniAND) . ")";
                continue; 
            }

            $colonna = $regola['colonna'] ?? '';
            $operatore = $regola['operatore'] ?? '=';

            $placeholder = ":" . str_replace('.', '_', $chiaveInput);

            if (($regola['formato'] ?? '') === '%val%') {
                $condizioni[] = "$colonna $operatore $placeholder";
                $parametri[$placeholder] = "%" . $valore . "%";
            } elseif (($regola['formato'] ?? '') === 'val%') {
                $condizioni[] = "$colonna $operatore $placeholder";
                $parametri[$placeholder] = $valore . "%";
            } else {
                $condizioni[] = "$colonna $operatore $placeholder";
                $parametri[$placeholder] = $valore;
            }
        }
    }

    return [
        'sql' => !empty($condizioni) ? " AND " . implode(" AND ", $condizioni) : "",
        'parametri' => $parametri
    ];
}