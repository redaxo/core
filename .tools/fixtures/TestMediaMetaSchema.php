<?php

namespace Redaxo\Core\Fixtures;

use Override;
use Redaxo\Core\MetaInfo\AsMetaSchema;
use Redaxo\Core\MetaInfo\Field\CheckboxField;
use Redaxo\Core\MetaInfo\Field\TextField;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaSchema;

#[AsMetaSchema(MetaEntity::Media)]
final class TestMediaMetaSchema extends MetaSchema
{
    #[Override]
    public function fields(): iterable
    {
        yield new CheckboxField('ai_generated', 'AI generated');
        yield new TextField('alt', 'Alternative text', translatable: true);
    }
}
