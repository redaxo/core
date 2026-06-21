<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\MetaInfo\Field\Fieldset;
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
     * A {@see Fieldset} opens a new fieldset that wraps the following fields; it is closed by the next
     * fieldset or after the last field. Fields before the first fieldset stay outside any fieldset.
     */
    public function renderFields(MetaContext $context): string
    {
        $html = '';
        $openFieldset = false;

        foreach (MetaSchema::getFields($context->entity) as $field) {
            if (!$field->isAllowed($context)) {
                continue;
            }

            if ($field instanceof Fieldset) {
                if ($openFieldset) {
                    $html .= '</fieldset>';
                }
                $html .= $field->render($context);
                $openFieldset = true;
                continue;
            }

            $html .= $field->render($context);
        }

        if ($openFieldset) {
            $html .= '</fieldset>';
        }

        return $html;
    }

    /** Whether the schema defines at least one field visible in the given context. */
    public function hasFields(MetaContext $context): bool
    {
        foreach (MetaSchema::getFields($context->entity) as $field) {
            if ($field->isAllowed($context)) {
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
    public function saveRequestValues(Sql $sqlSave, MetaContext $context): array
    {
        $saved = [];
        foreach (MetaSchema::getFields($context->entity) as $field) {
            if (!$field->isAllowed($context) || null === $field->column($context->entity)) {
                // hidden field, or a structural field without a value (e.g. legend)
                continue;
            }

            $column = $field->columnName($context->entity);
            $value = $field->parseRequest($context);
            $sqlSave->setValue($column, $value);
            $saved[$column] = $value;
        }

        return $saved;
    }

    /**
     * Retrieves the subject from the current context and renders (and saves) the meta form.
     *
     * @param ExtensionPoint<string> $ep
     */
    abstract public function extendForm(ExtensionPoint $ep): string;
}
