<?php

namespace Redaxo\Core\Fixtures;

use Redaxo\Core\Form\Field\Checkbox;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\MetaInfo\AsMetaSchema;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaSchema;
use Redaxo\Core\MetaInfo\Translated;

#[AsMetaSchema(MetaEntity::Media)]
final class TestMediaMetaSchema extends MetaSchema
{
    #[Checkbox('AI generated')]
    public bool $aiGenerated = false;

    #[Text('Alternative text')]
    #[Translated]
    public string $altText = '';
}
