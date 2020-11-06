<?php

$func = rex_get('func', 'string', '');
$funcs = ['kvs-clear'];
$msg = '';

if ($func === 'kvs-clear') {
    naju_kvs::clear();
    $msg .= '<p class="alert alert-info">KVS geleert</p>';
}

echo $msg;

// provide KVS actions
$fragment = new rex_fragment();

$content = '';

$content .= '<table class="table">';
$content .= '
    <tr>
        <th>KVS leeren</th>
        <td><a href="' . rex_url::currentBackendPage(['func' => 'kvs-clear']) . '"> <i class="rex-icon fa-cogs"></i> ausführen</a><td>
    <tr>';
$content .= '</table>';

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

foreach (naju_kvs::content() as $key => $value) {
    $content .= '
        <tr>
            <td><code>' . rex_escape($key) . '</code></td>
            <td>' . rex_escape($value) . '</td>
            <td></td>
        </tr>';
}

$content .= '</tbody></table>';

$fragment->setVar('title', 'KVS Inhalt' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
