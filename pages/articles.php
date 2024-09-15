<?php

$func = rex_get('func', 'string', '');
$funcs = ['meta-generate'];
$msg = '';

if ($func === 'meta-generate') {
    $article_queue = rex_article::getRootArticles();
    $category_stack = rex_category::getRootCategories();
    while ($category_stack) {
        $cat = array_pop($category_stack);
        $article_queue = array_merge($article_queue, $cat->getArticles());
        $category_stack = array_merge($category_stack, $cat->getChildren());
    }

    foreach($article_queue as $article) {
        naju_article::get($article->getId())->updateArticleMetadata();
    }

    $msg .= '<p class="alert alert-info">' . count($article_queue) . ' Artikel aktualisiert</p>';
}

echo $msg;

$content = '';

$content .= '<table class="table">';
$content .= '
    <tr>
        <th>Artikel-Metadaten generieren</th>
        <td><a href="' . rex_url::currentBackendPage(['func' => 'meta-generate']) . '"> <i class="rex-icon fa-cogs"></i> ausführen</a><td>
    <tr>';
$content .= '</table>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Kommandos' ,false);
$fragment->setVar('body', $content, false);

echo $fragment->parse('core/page/section.php');
