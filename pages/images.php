<?php

// generate WebP versions switch
$fragment = new rex_fragment();

$content = '';

$content .= '<form method="get" action="' . rex_url::currentBackendPage(['func' => 'webp-generate']) . '">';
$content .= '
    <div class="form-group">
        <label for="webp-generate-btn">WebP-Optimierungen generieren</label>
        <button type="submit" class="btn btn-default">Ausführen</button>
    </div>';
$content .= '</form>';

$fragment->setVar('title', 'WebP Optimierungen' ,false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
