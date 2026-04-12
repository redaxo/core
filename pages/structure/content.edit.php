<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Content\ArticleContentEditor;
use Redaxo\Core\View\Fragment;

assert(isset($articleId) && is_int($articleId));
assert(isset($clang) && is_int($clang));
assert(isset($ctype) && is_int($ctype));
assert(isset($sliceId) && is_int($sliceId));
assert(isset($sliceRevision) && is_int($sliceRevision));
assert(isset($function) && is_string($function));
assert(isset($info) && is_string($info));
assert(isset($warning) && is_string($warning));

if ($result = ApiFunction::factory()?->result) {
    if ($result->succeeded) {
        $info = $result->message;
    } else {
        $warning = $result->message;
    }
}

$CONT = new ArticleContentEditor();
$CONT->getContentAsQuery();
$CONT->info = $info;
$CONT->warning = $warning;
$CONT->setArticleId($articleId);
$CONT->setSliceId($sliceId);
$CONT->setMode('edit');
$CONT->setClang($clang);
$CONT->setEval(true);
$CONT->setSliceRevision($sliceRevision);
/** @var 'add'|'edit' $function */
$CONT->setFunction($function);
$content = $CONT->getArticle($ctype);

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
return $fragment->parse('core/structure/content/slice_list.php');
