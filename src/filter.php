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

            if (isset($_GET[$name])) {
                $raw = $_GET[$name];
                if (is_array($raw)) {
                    $value = $raw;
                } else {
                    $value = htmlspecialchars($raw);
                }
            } else {
                $value = htmlspecialchars($campo['value'] ?? '');
            }

            if ($campo['tipo'] === 'hidden'):
        ?>
                <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= $value ?>">

            <?php elseif ($campo['tipo'] === 'multi-range'):
                $min = (int)($campo['min'] ?? 0);
                $max = (int)($campo['max'] ?? 100);
                $name_min = htmlspecialchars($campo['name_min']);
                $name_max = htmlspecialchars($campo['name_max']);
                $steps = isset($campo['steps']) ? (int)$campo['steps'] : 1000;
                $scale = $campo['scale'] ?? 'linear';

                $val_min = isset($_GET[$campo['name_min']]) && $_GET[$campo['name_min']] !== '' ? (int)$_GET[$campo['name_min']] : (int)($campo['value_min'] ?? $min);
                $val_max = isset($_GET[$campo['name_max']]) && $_GET[$campo['name_max']] !== '' ? (int)$_GET[$campo['name_max']] : (int)($campo['value_max'] ?? $max);
                if ($scale === 'log') {
                    $slider_min = getLogSliderPosition($val_min, $min, $max, $steps);
                    $slider_max = getLogSliderPosition($val_max, $min, $max, $steps);
                } else {
                    $slider_min = $val_min;
                    $slider_max = $val_max;
                }
                $val_min_t = formatFileSize2($val_min);
                $val_max_t = formatFileSize2($val_max);
            ?>
                <label style="margin-bottom: 5px; display: block;">
                    <?= $label ?>:
                    <span style="font-weight: bold; color: var(--primary-dark);">
                        <span id="val_<?= $name_min ?>"><?= htmlspecialchars($val_min_t['size'] . ' ' . $val_min_t['unit']) ?></span> -
                        <span id="val_<?= $name_max ?>"><?= htmlspecialchars($val_max_t['size'] . ' ' . $val_max_t['unit']) ?></span>
                    </span>
                </label>

                <input type="hidden" name="<?= $name_min ?>" id="<?= $name_min ?>" value="<?= $val_min ?>">
                <input type="hidden" name="<?= $name_max ?>" id="<?= $name_max ?>" value="<?= $val_max ?>">
                <div class="multi-range-container" style="margin-bottom: 25px;">
                    <input type="range"
                        id="<?= $name_min ?>_slider"
                        data-hidden="<?= $name_min ?>"
                        data-scale="<?= htmlspecialchars($scale) ?>"
                        data-min="<?= $min ?>"
                        data-max="<?= $max ?>"
                        data-steps="<?= $steps ?>"
                        min="0"
                        max="<?= $steps ?>"
                        value="<?= $slider_min ?>"
                        step="1">

                    <input type="range"
                        id="<?= $name_max ?>_slider"
                        data-hidden="<?= $name_max ?>"
                        data-scale="<?= htmlspecialchars($scale) ?>"
                        data-min="<?= $min ?>"
                        data-max="<?= $max ?>"
                        data-steps="<?= $steps ?>"
                        min="0"
                        max="<?= $steps ?>"
                        value="<?= $slider_max ?>"
                        step="1">
                </div>

            <?php elseif ($campo['tipo'] === 'range'):
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

            <?php elseif ($campo['tipo'] === 'date'):
                $max_date = date('Y-m-d');
            ?>
                <label for="<?= $name ?>"><?= $label ?></label>
                <input type="date"
                    lang="it"
                    name="<?= $name ?>"
                    id="<?= $name ?>"
                    value="<?= $value ?>"
                    min="1900-01-01"
                    max="<?= $max_date ?>">

            <?php elseif ($campo['tipo'] === 'select'):
                $opzioni = $campo['opzioni'] ?? [];
                $current_val = $value;
            ?>
                <label for="<?= $name ?>"><?= $label ?></label>
                <select name="<?= $name ?>" id="<?= $name ?>">
                    <option value="">Tutti</option>
                    <?php foreach ($opzioni as $opt_key => $opt_label):
                        if (is_int($opt_key)) {
                            $opt_key = $opt_label;
                        }
                        $selected = ($opt_key === $current_val) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($opt_key) ?>" <?= $selected ?>><?= htmlspecialchars($opt_label) ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($campo['tipo'] === 'checkbox-group'):
                $opzioni = $campo['opzioni'] ?? [];
                $selected_values = [];
                if (isset($_GET[$name])) {
                    $selected_values = is_array($_GET[$name]) ? $_GET[$name] : [$_GET[$name]];
                }
            ?>
                <fieldset class="checkbox-group">
                    <legend><?= $label ?></legend>
                    <?php foreach ($opzioni as $opt_key => $opt_label):
                        if (is_int($opt_key)) {
                            $opt_key = $opt_label;
                        }
                        $input_id = htmlspecialchars($name . '_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $opt_key));
                        $checked = in_array($opt_key, $selected_values, true) ? 'checked' : '';
                    ?>
                        <label for="<?= $input_id ?>" class="checkbox-label">
                            <input type="checkbox"
                                name="<?= htmlspecialchars($name) ?>[]"
                                id="<?= $input_id ?>"
                                value="<?= htmlspecialchars($opt_key) ?>"
                                <?= $checked ?> >
                            <?= htmlspecialchars($opt_label) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

            <?php else: ?>
                <label for="<?= $name ?>"><?= $label ?></label>
                
                <?php if ($campo['tipo'] === 'text'): ?>
                    <div class="input-clearable-wrapper">
                        <input type="text"
                            name="<?= $name ?>"
                            id="<?= $name ?>"
                            value="<?= $value ?>"
                            placeholder="<?= htmlspecialchars($campo['placeholder'] ?? 'Cerca...') ?>">
                        <span class="clear-input-btn" title="Cancella il testo">&times;</span>
                    </div>
                <?php else: ?>
                    <input type="<?= htmlspecialchars($campo['tipo']) ?>"
                        name="<?= $name ?>"
                        id="<?= $name ?>"
                        value="<?= $value ?>"
                        placeholder="<?= htmlspecialchars($campo['placeholder'] ?? 'Cerca...') ?>">
                <?php endif; ?>

            <?php endif; ?>
        <?php endforeach; ?>

        <button type="button" class="reset"
            onclick="window.location='<?= htmlspecialchars($reset_url) ?>'">
            Reimposta
        </button>
    </form>
</div>