<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\MetaInfo\Field\LegendField;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaSchema;

/**
 * Renders and saves the meta fields of an entity, driven by its {@see MetaSchema}.
 *
 * @internal
 */
abstract class AbstractHandler
{
    /**
     * Renders all fields visible in the given context.
     *
     * A {@see LegendField} opens a new (nested) fieldset that wraps the following fields; it is closed by the
     * next legend or after the last field. Fields before the first legend stay outside any fieldset.
     */
    public function renderFields(MetaContext $ctx): string
    {
        $html = '';
        $openFieldset = false;

        foreach (MetaSchema::getFields($ctx->entity) as $field) {
            if (!$field->isAllowed($ctx)) {
                continue;
            }

            if ($field instanceof LegendField) {
                if ($openFieldset) {
                    $html .= '</fieldset>';
                }
                $html .= '<fieldset>' . $field->render($ctx);
                $openFieldset = true;
                continue;
            }

            $html .= $field->render($ctx);
        }

        if ($openFieldset) {
            $html .= '</fieldset>';
        }

        return $html;
    }

    /** Whether the schema defines at least one field visible in the given context. */
    public function hasFields(MetaContext $ctx): bool
    {
        foreach (MetaSchema::getFields($ctx->entity) as $field) {
            if ($field->isAllowed($ctx)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads the submitted values into the given save object.
     *
     * @return array<string, int|string|null> the parsed values keyed by column name (e.g. to redisplay them after the save)
     */
    public function saveRequestValues(Sql $sqlSave, MetaContext $ctx): array
    {
        $saved = [];
        foreach (MetaSchema::getFields($ctx->entity) as $field) {
            if (!$field->isAllowed($ctx) || null === $field->column($ctx->entity)) {
                // hidden field, or a structural field without a value (e.g. legend)
                continue;
            }

            $column = $field->columnName($ctx->entity);
            $value = $field->parseRequest($ctx);
            $sqlSave->setValue($column, $value);
            $saved[$column] = $value;
        }

        return $saved;
    }

    /** Retrieves the subject from the current context and renders (and saves) the meta form. */
    abstract public function extendForm(ExtensionPoint $ep): string;
}
