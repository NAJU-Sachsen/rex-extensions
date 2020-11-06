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
}

if ($func === 'webp_clear') {
    naju_image::clearOptimizedVersionsFromMediapool();
    $msg .= '<p class="alert alert-info">Optimierte Bilder entfernt</p>';
}

if ($offset >= $media_count) {
    $msg .= '<p class="alert alert-success">Alle Medien konvertiert</p>';
    $offset = 0;
}

echo $msg;

// generate WebP versions switch
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
        <td class="text-right"><a href="' . rex_url::currentBackendPage(['func' => 'webp_clear']) . '"> <i class="rex-icon fa-cogs"></i> ausführen</a>&nbsp;<strong class="text text-warning">Das kann sehr lange dauern!</strong></td>
    </tr>';
$content .= '</table>';

$fragment->setVar('title', 'WebP Optimierungen' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// WebP generation settings
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
