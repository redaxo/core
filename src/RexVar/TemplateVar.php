<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Content\ArticleContentBase;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Util\Stream;
use Redaxo\Core\Util\Timer;

/**
 * REX_TEMPLATE[2].
 */
class TemplateVar extends RexVar
{
    protected function getOutput()
    {
        $templateId = $this->getParsedArg('id', 0, true);
        $templateKey = $this->getArg('key', null, true);

        if (0 === $templateId && $templateKey) {
            $template = Template::forKey($templateKey);

            if ($template) {
                $templateId = $template->getId();
            }
        }

        if ($templateId > 0) {
            // the `require` statement must be in outer context, so that the included template uses the same variable scope
            return self::class . '::getTemplateOutput(' . $templateId . ', new \\Redaxo\\Core\\Util\\Timer(), require ' . self::class . '::getTemplateStream(' . $templateId . ', $this))';
        }

        return false;
    }

    /**
     * @internal
     *
     * @param int|numeric-string $id
     *
     * @return string
     */
    public static function getTemplateStream($id, ?ArticleContentBase $article = null)
    {
        ob_start(); // will be closed in getTemplateOutput()

        return Stream::factory('template/' . $id, new Template($id)->getTemplate());
    }

    /**
     * @internal
     *
     * @param int|numeric-string $id
     * @param mixed $template Param is not used, but the template file is included here so that it is timed between timer param and the execution of this method
     * @return false|string
     */
    public static function getTemplateOutput($id, ?Timer $timer = null, $template = null)
    {
        if ($timer && Core::isDebugMode()) {
            $timer->stop();
            $tmpl = new Template($id);
            Timer::measured('Template: ' . ($tmpl->getKey() ?? $tmpl->getId()), $timer);
        }

        return ob_get_clean();
    }
}
