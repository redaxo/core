<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Content\ArticleContentEditor;

assert(isset($articleId) && is_int($articleId));
assert(isset($clang) && is_int($clang));
assert(isset($ctype) && is_int($ctype));
assert(isset($sliceId) && is_int($sliceId));
assert(isset($templateAttributes) && is_array($templateAttributes));
assert(isset($sliceRevision) && is_int($sliceRevision));
assert(isset($function) && is_string($function));
assert(isset($info) && is_string($info));
assert(isset($warning) && is_string($warning));

$apiFunc = ApiFunction::factory();
if ($apiFunc && $result = $apiFunc->getResult()) {
    if ($result->isSuccessfull()) {
        $info = $result->getMessage();
    } else {
        $warning = $result->getMessage();
    }
}

$CONT = new ArticleContentEditor();
$CONT->getContentAsQuery();
$CONT->info = $info;
$CONT->warning = $warning;
$CONT->template_attributes = $templateAttributes;
$CONT->setArticleId($articleId);
$CONT->setSliceId($sliceId);
$CONT->setMode('edit');
$CONT->setClang($clang);
$CONT->setEval(true);
$CONT->setSliceRevision($sliceRevision);
/** @var 'add'|'edit' $function */
$CONT->setFunction($function);
$content = $CONT->getArticle($ctype);

return '' != $content ? '<ul class="rex-slices">' . $content . '</ul>' : '';
