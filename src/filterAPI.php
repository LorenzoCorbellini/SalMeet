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
    echo "";

    switch ($entita) {
        //filtro bacheche
        case 'bacheche':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'titolo',               'label' => 'Nome Bacheca', 'placeholder' => 'es. Botanica di notte'],
                    ['tipo' => 'date', 'name' => 'data',                 'label' => 'Creata dopo'],
                    ['tipo' => 'text', 'name' => 'ricerca_proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome Cognome / Nickname'],
                    /*['tipo' => 'text', 'name' => 'proprietario',         'label' => 'Nickname Proprietario', 'placeholder' => 'es. mrossi, giuse_verdi99...'],
                    ['tipo' => 'text', 'name' => 'proprietario_nome',    'label' => 'Nome Proprietario', 'placeholder' => 'es. Mario, Anna...'],
                    ['tipo' => 'text', 'name' => 'proprietario_cognome', 'label' => 'Cognome Proprietario', 'placeholder' => 'es. Rossi, Bianchi...'],*/
                ])
            ];

            //filtro utenti
        case 'utenti':
            return [
                'campi' => array_merge($campiBase, [
                    /*['tipo' => 'text', 'name' => 'utente',       'label' => 'Nickname Utente', 'placeholder' => 'es. mrossi, giuse_verdi99...'],
                    ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Utente', 'placeholder' => 'es. Mario, Luca...'],
                    ['tipo' => 'text', 'name' => 'cognome',      'label' => 'Cognome Utente', 'placeholder' => 'es. Rossi, Verdi...'],*/
                    ['tipo' => 'text', 'name' => 'ricerca_globale', 'label' => 'Utente', 'placeholder' => 'Nome Cognome / Nickname'],
                    ['tipo' => 'date', 'name' => 'data_nascita', 'label' => 'Nato dopo'],
                ])
            ];

            //filtro gruppi
        case 'gruppi':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'nome',         'label' => 'Nome Gruppo', 'placeholder' => 'es. Campeggio'],
                    ['tipo' => 'date', 'name' => 'data',         'label' => 'Creato dopo'],
                    ['tipo' => 'text', 'name' => 'proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome Cognome / Nickname'],
                ])
            ];

            //filtro file multimediali
        case 'file':
            // Usa la dimensione minima reale del database per il range iniziale.
            // Se viene passato un min_size esplicito (es. contesti speciali), lo usa;
            // altrimenti calcola il valore minimo presente in FileMultimediale.
            $minSize = isset($parametriExtra['min_size']) ? (int)$parametriExtra['min_size'] : getMinFileSizeFromDb();
            $maxSize = getMaxFileSizeFromDb($parametriExtra['max_size'] ?? null);
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
                    ['tipo' => 'text', 'name' => 'proprietario_file', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome Cognome / Nickname'],
                ])
            ];

        case 'file_bacheche':
            return [
                'campi' => array_merge($campiBase, [
                    ['tipo' => 'text', 'name' => 'titolo',               'label' => 'Nome Bacheca', 'placeholder' => 'es. Botanica di notte'],
                    ['tipo' => 'text', 'name' => 'ricerca_proprietario', 'label' => 'Utente Proprietario', 'placeholder' => 'Nome Cognome / Nickname'],
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
        /*'bacheche' => [
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
        ],*/
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
            'data_nascita' => ['colonna' => 'u.dataNascita', 'operatore' => '=', 'formato' => 'val'],
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
            'ricerca_proprietario' => [
                'tipo'    => 'ricerca_multipla',
                'colonne' => ['u.nickname', 'u.nome', 'u.cognome']
            ],
        ],
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

            // =========================================================
            // RICERCA GLOBALE (Multi-colonna e Multi-parola)
            // =========================================================
            if (isset($regola['tipo']) && $regola['tipo'] === 'ricerca_multipla') {
                $parole = preg_split('/\s+/', $valore);
                $subCondizioniAND = [];

                foreach ($parole as $indexParola => $parola) {
                    $subCondizioniOR = [];

                    // Cicliamo le colonne e creiamo un parametro al 100% univoco
                    foreach ($regola['colonne'] as $indexColonna => $colonna) {
                        // Creiamo un nome parametro che include sia l'indice della parola che quello della colonna
                        $paramName = ":rg_{$chiaveInput}_{$indexParola}_{$indexColonna}";

                        $subCondizioniOR[] = "$colonna LIKE $paramName";
                        // Assegniamo il valore al suo parametro univoco
                        $parametri[$paramName] = "%" . $parola . "%";
                    }

                    // Ogni singola parola deve trovarsi in ALMENO UNA delle colonne (OR)
                    $subCondizioniAND[] = "(" . implode(' OR ', $subCondizioniOR) . ")";
                }

                // Tutte le parole digitate devono trovare un riscontro contemporaneamente (AND)
                $condizioni[] = "(" . implode(' AND ', $subCondizioniAND) . ")";
                continue; // Passa al prossimo campo saltando la logica standard
            }

            // =========================================================
            // LOGICA STANDARD (Singola colonna)
            // =========================================================
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
