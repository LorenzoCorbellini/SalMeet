<?php
// filter.php
// Includere DOPO aver definito $filtro_config nella pagina chiamante.

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
    <form method="GET" action="<?= $action ?>">

        <?php foreach ($filtro_config['campi'] as $campo):
            $name  = $campo['name'] ?? '';
            $label = htmlspecialchars($campo['label'] ?? '');
            $value = isset($_GET[$name]) ? htmlspecialchars($_GET[$name]) : htmlspecialchars($campo['value'] ?? '');

            if ($campo['tipo'] === 'hidden'):
        ?>
                <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= $value ?>">

            <?php elseif ($campo['tipo'] === 'multi-range'):
                // Estraiamo i dati configurati per il doppio slider
                $min = (int)($campo['min'] ?? 0);
                $max = (int)($campo['max'] ?? 100);
                $name_min = htmlspecialchars($campo['name_min']);
                $name_max = htmlspecialchars($campo['name_max']);

                // Legge i valori filtrati direttamente dall'URL se presenti
                $val_min = isset($_GET[$campo['name_min']]) && $_GET[$campo['name_min']] !== '' ? (int)$_GET[$campo['name_min']] : (int)($campo['value_min'] ?? $min);
                $val_max = isset($_GET[$campo['name_max']]) && $_GET[$campo['name_max']] !== '' ? (int)$_GET[$campo['name_max']] : (int)($campo['value_max'] ?? $max);
            ?>
                <label style="margin-bottom: 5px; display: block;">
                    <?= $label ?>:
                    <span style="font-weight: bold; color: var(--primary-dark);">
                        <span id="val_<?= $name_min ?>"><?= $val_min ?></span> -
                        <span id="val_<?= $name_max ?>"><?= $val_max ?></span> MB
                    </span>
                </label>

                <div class="multi-range-container" style="margin-bottom: 25px;">
                    <input type="range"
                        name="<?= $name_min ?>"
                        id="<?= $name_min ?>"
                        min="<?= $min ?>"
                        max="<?= $max ?>"
                        value="<?= $val_min ?>"
                        step="1">

                    <input type="range"
                        name="<?= $name_max ?>"
                        id="<?= $name_max ?>"
                        min="<?= $min ?>"
                        max="<?= $max ?>"
                        value="<?= $val_max ?>"
                        step="1">
                </div>

            <?php elseif ($campo['tipo'] === 'range'):
                // Manteniamo per retrocompatibilità il vecchio range singolo se usato altrove
                $min = $campo['min'] ?? 0;
                $max = $campo['max'] ?? 100;
                $current_val = (isset($_GET[$name]) && $_GET[$name] !== '') ? htmlspecialchars($_GET[$name]) : ($campo['default'] ?? $max);
            ?>
                <label for="<?= $name ?>">
                    <?= $label ?>: <span id="val_<?= $name ?>" style="font-weight: bold; color: var(--primary-dark);"><?= $current_val ?></span> MB
                </label>
                <input type="range"
                    name="<?= $name ?>"
                    id="<?= $name ?>"
                    min="<?= $min ?>"
                    max="<?= $max ?>"
                    value="<?= $current_val ?>"
                    step="1"
                    style="width: 100%; accent-color: var(--primary); margin-bottom: 10px;"
                    oninput="document.getElementById('val_<?= $name ?>').innerText = this.value">

            <?php else: ?>
                <label for="<?= $name ?>"><?= $label ?></label>
                <input type="<?= htmlspecialchars($campo['tipo']) ?>"
                    name="<?= $name ?>"
                    id="<?= $name ?>"
                    value="<?= $value ?>"
                    placeholder="Cerca...">
            <?php endif; ?>
        <?php endforeach; ?>

        <button type="button" class="reset"
            onclick="window.location='<?= htmlspecialchars($reset_url) ?>'">
            Reimposta
        </button>
    </form>
</div>