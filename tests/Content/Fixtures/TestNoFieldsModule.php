<?php

namespace Redaxo\Core\Tests\Content\Fixtures;

use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Translation\Translatable;

#[AsModule('test_no_fields', new Translatable('module'))]
final class TestNoFieldsModule extends Module
{
    #[Override]
    public function output(): string
    {
        return 'no fields';
    }
}
