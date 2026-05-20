<?php
// filter.php
// Includere DOPO aver definito $filtro_config nella pagina chiamante.
//
// Struttura di $filtro_config:
// $filtro_config = [
//     'action' => 'bacheche.php',   // opzionale, default: pagina corrente
//     'campi'  => [
//         ['tipo' => 'text',     'name' => 'titolo',    'label' => 'Titolo'],
//         ['tipo' => 'date',     'name' => 'data',      'label' => 'Data'],
//         ['tipo' => 'select',   'name' => 'stato',     'label' => 'Stato',
//          'opzioni' => ['Attivo', 'Archiviato']],
//         ['tipo' => 'checkbox', 'name' => 'solo_pub',  'label' => 'Solo pubblici'],
//         ['tipo' => 'hidden',   'name' => 'vista',     'value' => 'utenti'] // Campo nascosto
//     ]
// ];

if (empty($filtro_config['campi'])) return;

$action = htmlspecialchars($filtro_config['action'] ?? $_SERVER['PHP_SELF']);

// =========================================================
// 1. Costruzione dinamica dell'URL per il tasto "Reimposta"
// =========================================================
$reset_params = [];

if (isset($filtro_config['campi'])) {
    foreach ($filtro_config['campi'] as $campo) {
        if ($campo['tipo'] === 'hidden' && isset($campo['value'])) {
            $reset_params[$campo['name']] = $campo['value'];
        }
    }
}

$reset_url = $action;
if (!empty($reset_params)) {
    $reset_url .= '?' . http_build_query($reset_params);
}
?>

<div id="filtro">
    <h3>Filtri</h3>
    <form method="GET" action="<?= $action ?>">

        <?php foreach ($filtro_config['campi'] as $campo):
            $name  = htmlspecialchars($campo['name']);
            $label = htmlspecialchars($campo['label'] ?? '');
            
            if ($campo['tipo'] === 'hidden') {
                $value = htmlspecialchars($_GET[$campo['name']] ?? $campo['value'] ?? '');
            } else {
                $value = htmlspecialchars($_GET[$campo['name']] ?? '');
            }
        ?>

            <?php if ($campo['tipo'] === 'hidden'): ?>
                <input type="hidden" name="<?= $name ?>" value="<?= $value ?>">

            <?php elseif ($campo['tipo'] === 'checkbox'): ?>
                <label>
                    <input type="checkbox" name="<?= $name ?>"
                           value="1"
                           <?= isset($_GET[$campo['name']]) ? 'checked' : '' ?>>
                    <?= $label ?>
                </label>

            <?php elseif ($campo['tipo'] === 'select'): ?>

                <label for="<?= $name ?>"><?= $label ?></label>
                <select name="<?= $name ?>" id="<?= $name ?>">
                    <option value="">Tutti</option>
                    <?php foreach ($campo['opzioni'] as $opzione):
                        $opt = htmlspecialchars($opzione);
                    ?>
                        <option value="<?= $opt ?>"
                            <?= $value === $opt ? 'selected' : '' ?>>
                            <?= $opt ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php else: ?>

                <label for="<?= $name ?>"><?= $label ?></label>
                <input type="<?= htmlspecialchars($campo['tipo']) ?>"
                       name="<?= $name ?>"
                       id="<?= $name ?>"
                       value="<?= $value ?>"
                       placeholder="Cerca...">

            <?php endif; ?>

        <?php endforeach; ?>

        <button type="submit">Cerca</button>
        <button type="button" class="reset"
                onclick="window.location='<?= htmlspecialchars($reset_url) ?>'">
            Reimposta
        </button>
    </form>
</div>