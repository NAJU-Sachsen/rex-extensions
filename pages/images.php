<?php
$sql = rex_sql::factory();
$media_count = $sql->setQuery('SELECT COUNT(*) AS count FROM ' . rex::getTable('media'))->getValue('count');

$func = rex_get('func', 'string', '');
$msg = '';

$offset = rex_request('offset', 'int', 0);
$limit = rex_request('limit', 'int', 20);

if ($func === 'webp_generate') {
    $maintenance = rex_addon::get('maintenance');
    $exec_operation = true;
    if ($maintenance->isAvailable() && !$maintenance->getConfig('backend_aktiv')) {
        $msg .= '<p class="alert alert-danger">Nur im Wartungsmodus verfügbar</p>';
        $exec_operation = false;
    }

    if ($exec_operation) {
        $inflate_opts = ['offset' => $offset];
        if ($limit > 0) {
            $inflate_opts['limit'] = $limit;
        }
        $res = naju_image::inflateOptimizedVersionsToMediapool($inflate_opts);
        $msg .= '<p class="alert alert-info">' . $res['total'] . ' Dateien verarbeitet (' . ($offset + $limit) . ' von ' . $media_count . ')</p>';

        if ($res['success']) {
            $msg .= '<div class="alert alert-success"><p>Folgende Dateien wurden erfolgreich konvertiert:</p><ul>';
            foreach ($res['success'] as $success) {
                $msg .= '<li>' . rex_escape($success) . '</li>';
            }
            $msg .= '</ul></div>';
        }

        if ($res['failure']) {
            $msg .= '<div class="alert alert-danger"><p>Folgende Dateien konnten nicht konvertiert werden:</p><ul>';
            foreach ($res['failure'] as $failed) {
                $msg .= '<li>' . rex_escape($failed) . '</li>';
            }
            $msg .= '</ul></div>';
        }

        if ($res['skipped']) {
            $msg .= '<div class="alert alert-warning"><p>Folgende Dateien wurden übersprungen:</p><ul>';
            foreach ($res['skipped'] as $skipped) {
                $msg .= '<li>' . rex_escape($skipped) . '</li>';
            }
            $msg .= '</ul></div>';
        }
        $offset += $limit;
    }
} elseif ($func == 'update_config') {

    $img_breakpoints = rex_request('img_breakpoints', 'string', '');
    if ($img_breakpoints) {
        rex_config::set('naju_rex_extensions', 'img_breakpoints', $img_breakpoints);
    }
    
    $img_init_quality = rex_request('img_init_quality', 'int', 0);
    if ($img_init_quality) {
        rex_config::set('naju_rex_extensions', 'img_init_quality', $img_init_quality);
    }
    
    $img_quality_thresh = rex_request('img_quality_thresh', 'int', 0);
    if ($img_quality_thresh) {
        rex_config::set('naju_rex_extensions', 'img_quality_threshold', $img_quality_thresh);
    }
    
    $img_quality_interval = rex_request('img_quality_interval', 'int', 0);
    if ($img_quality_interval) {
        rex_config::set('naju_rex_extensions', 'img_quality_interval', $img_quality_interval);
    }

    $img_quality_improvement = rex_request('img_quality_improvement', 'int', 0);
    if ($img_quality_improvement) {
        rex_config::set('naju_rex_extensions', 'img_min_improvement', $img_quality_improvement);
    }
}

if ($func === 'webp_clear') {
    naju_image::clearOptimizedVersionsFromMediapool();
    $msg .= '<p class="alert alert-info">Optimierte Bilder entfernt</p>';
}

if ($fun === 'webp_generate' && $offset >= $media_count) {
    $msg .= '<p class="alert alert-success">Alle Medien konvertiert</p>';
    $offset = 0;
}

echo $msg;

// WebP generation switches
$fragment = new rex_fragment();
$content = '';

$content .= '<table class="table">';
$content .= '
    <tr>
        <th>WebP Bilder generieren</th>
        <td class="text-right"><button class="btn btn-link" form="webp-generation-settings" style="padding: 0; vertical-align: unset;"> <i class="rex-icon fa-cogs"></i> ausführen</button>&nbsp;<strong class="text text-warning">Das kann sehr lange dauern!</strong></td>
    </tr>
    <tr>
        <th>WebP Bilder entfernen</th>
        <td class="text-right"><a href="' . rex_url::currentBackendPage(['func' => 'webp_clear']) . '" onclick="return confirm(\'Wirklich entfernen?\')"> <i class="rex-icon fa-cogs"></i> ausführen</a>&nbsp;<strong class="text text-warning">Das kann sehr lange dauern!</strong></td>
    </tr>';
$content .= '</table>';

$fragment->setVar('title', 'WebP Optimierungen' ,false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// WebP generation control settings
$fragment = new rex_fragment();
$content = '
    <form method="post" action="' . rex_url::currentBackendPage(['func' => 'webp_generate']) . '" id="webp-generation-settings">
        <div class="form-group">
            <label for="webp-generation-offset">Offset</label>
            <input type="number" min="0" class="form-control" name="offset" id="webp-generation-offset" value="' . $offset . '">
        </div>
        <div class="form-group">
        <label for="webp-generation-limit">Medien</label>
        <input type="number" min="1" class="form-control" name="limit" id="webp-generation-limit" value="' . $limit . '">
        </div>
    </form>';

$fragment->setVar('title', 'Generierungs-Einstellungen' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// General image optimization settings
$fragment = new rex_fragment();
$content = '';

$img_breakpoints = $img_breakpoints ?? implode(';', naju_image::$WIDTH_BREAKPOINTS);
$img_init_quality = $img_init_quality ?? naju_image::$COMPRESSION_QUALITY_INIT;
$img_quality_thresh = $img_quality_thresh ?? naju_image::$COMPRESSION_QUALITY_THRESH;
$img_quality_interval = $img_quality_interval ?? naju_image::$COMPRESSION_QUALITY_DEC;
$img_quality_improvement = $img_quality_improvement ?? naju_image::$COMPRESSION_IMPROVEMENT_RATIO;


$content .= '
    <form method="post" action="' . rex_url::currentBackendPage(['func' => 'update_config']) . '" id="optimization-settings">
        <div class="form-group">
            <label for="img-breakpoints">Breakpoints</label>
            <input type="text" name="img_breakpoints" value="' . rex_escape($img_breakpoints) . '" id="img-breakpoints" placeholder="mehrere Breakpoints durch ; trennen" class="form-control">
        </div>
        <div class="form-group">
            <label for="img-init-quality">Komprimierung Ausgangsqualität</label>
            <input type="number" name="img_init_quality" value="' . rex_escape($img_init_quality) . '" id="img-init-quality" min="0" max="100" class="form-control">
        </div>
        <div class="form-group">
            <label for="img-quality-thresh">Komprimierung Minimalqualität</label>
            <input type="number" name="img_quality_thresh" value="' . rex_escape($img_quality_thresh) . '" min="0" max="100" id="img-quality-thresh" class="form-control">
        </div>
        <div class="form-group">
            <label for="img-quality-interval">Komprimierungsstufen</label>
            <input type="number" name="img_quality_interval" value="' . rex_escape($img_quality_interval) . '" min="0" max="100" id="img-quality-interval" class="form-control">
        </div>
        <div class="form-group">
            <label for="img-quality-improvement">minimale Verbesserungsfaktor</label>
            <input type="number" name="img_quality_improvement" value="' . rex_escape($img_quality_improvement) . '" min="0.0" max="1.0" step="0.05" id="img-quality-improvement" class="form-control">
            <p class="help-block rex-note">Faktor 0.2 bedeutet, dass das komprimierte Bild max. 20% des Ausgangsspeicherplatzes einnehmen darf</p>
        </div>
    </form>';
$form_btn = '<button type="submit" form="optimization-settings" class="btn btn-primary">Aktualisieren</button>';

$fragment->setVar('title', 'Optimierungseinstellungen' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $form_btn, false);
echo $fragment->parse('core/page/section.php');
