<?php

namespace Project\Module;

use Override;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;

#[AsModule('testmodule1', 'Test Module 1')]
final class TestModule1 extends Module
{
    #[Override]
    public function input(ArticleSlice $slice): string
    {
        return 'input';
    }

    #[Override]
    public function output(ArticleSlice $slice): string
    {
        return 'output';
    }
}
