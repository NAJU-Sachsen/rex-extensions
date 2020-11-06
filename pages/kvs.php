<?php

$func = rex_get('func', 'string', '');
$funcs = ['kvs-clear'];

if ($func === 'kvs-clear') {
    naju_kvs::clear();
    $msg = '<p>KVS geleert</p>';
}

$msg = '';


if ($msg) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Info' ,false);
    $fragment->setVar('class', 'info', false);
    $fragment->setVar('body', $msg, false);
    echo $fragment->parse('core/page/section.php');
}

// provide KVS actions
$fragment = new rex_fragment();

$content = '';

$content .= '<form method="get" action="' . rex_url::currentBackendPage(['func' => 'kvs-clear']) . '">';
$content .= '
    <div class="form-group">
        <label for="webp-generate-btn">KVS leeren</label>
        <button type="submit" class="btn btn-default">Ausführen</button>
    </div>';
$content .= '</form>';

$fragment->setVar('title', 'Kommandos' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// list KVS content
$fragment = new rex_fragment();

$content = '';
$content = '
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>';

$content .= '</tbody></table>';

foreach (naju_kvs::content() as $key => $value) {
    $content .= '<tr><td><code>' . rex_escape($key) . '</code></td><td>' . rex_escape($value) . '</td><td></td></tr>';
}

$fragment->setVar('title', 'KVS Inhalt' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
