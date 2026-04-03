<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\RexVar\RexVar;
use Redaxo\Core\Util\Stream;

use function in_array;
use function is_array;

class ArticleAction
{
    public const PREVIEW = 'preview';
    public const PRESAVE = 'presave';
    public const POSTSAVE = 'postsave';

    protected readonly int $moduleId;
    protected readonly int $articleId;
    protected readonly int $clangId;
    protected readonly int $ctypeId;
    protected readonly int $sliceId;

    /** @var string */
    private $event;
    /** @var int */
    private $mode;
    /** @var bool */
    private $save = true;
    /** @var array */
    private $messages = [];
    /** @var Sql */
    private $sql;

    public function __construct($moduleId, $function, Sql $sql)
    {
        $this->moduleId = $moduleId;
        $this->event = $function;
        if ('edit' == $function) {
            $this->mode = 2;
        } elseif ('delete' == $function) {
            $this->mode = 4;
        } else {
            $this->mode = 1;
        }
        $this->sql = $sql;

        $this->articleId = Request::request('article_id', 'int');
        $this->clangId = Request::request('clang', 'int');
        $this->ctypeId = Request::request('ctype', 'int');
        $this->sliceId = 1 == $this->mode ? 0 : Request::request('slice_id', 'int');
    }

    /** @return void */
    public function setRequestValues()
    {
        $request = ['value' => 20, 'media' => 10, 'medialist' => 10, 'link' => 10, 'linklist' => 10];
        foreach ($request as $key => $max) {
            $values = Request::request('REX_INPUT_' . strtoupper($key), 'array');
            for ($i = 1; $i <= $max; ++$i) {
                if (isset($values[$i])) {
                    if (is_array($values[$i])) {
                        $this->sql->setArrayValue($key . $i, $values[$i]);
                    } else {
                        $this->sql->setValue($key . $i, $values[$i]);
                    }
                } else {
                    $this->sql->setValue($key . $i, null);
                }
            }
        }
    }

    /**
     * @param self::PREVIEW|self::PRESAVE|self::POSTSAVE $type
     * @return void
     */
    public function exec($type)
    {
        if (!in_array($type, [self::PREVIEW, self::PRESAVE, self::POSTSAVE])) {
            throw new InvalidArgumentException('$type must be ArticleAction::PREVIEW, ::PRESAVE or ::POSTSAVE.');
        }

        $this->messages = [];
        $this->save = true;

        $ga = Sql::factory();
        $ga->setQuery('SELECT a.id, `' . $type . '` as code FROM ' . Core::getTable('module_action') . ' ma,' . Core::getTable('action') . ' a WHERE `' . $type . '` != "" AND ma.action_id=a.id AND module_id=? AND (a.' . $type . 'mode & ?)', [$this->moduleId, $this->mode]);

        foreach ($ga as $row) {
            $action = (string) $row->getValue('code');
            $action = RexVar::parse($action, RexVar::ENV_BACKEND | RexVar::ENV_INPUT, 'action', $this->sql);

            $articleId = (int) $row->getValue('id');
            require Stream::factory('action/' . $articleId . '/' . $type, $action);
        }
    }

    /** @return void */
    protected function setSave($save)
    {
        $this->save = $save;
    }

    /** @return void */
    protected function addMessage($message)
    {
        $this->messages[] = $message;
    }

    /** @return bool */
    public function getSave()
    {
        return $this->save;
    }

    /** @return array */
    public function getMessages()
    {
        return $this->messages;
    }

    /** @return string */
    public function getEvent()
    {
        return $this->event;
    }

    /** @return void */
    protected function setValue($id, $value)
    {
        if ($id < 1 || $id > 20) {
            throw new InvalidArgumentException('ID for REX_VALUE out of range (1..20)');
        }
        $this->sql->setValue('value' . $id, $value);
    }

    /** @return void */
    protected function setMedia($id, $value)
    {
        if ($id < 1 || $id > 10) {
            throw new InvalidArgumentException('ID for REX_MEDIA out of range (1..10)');
        }
        $this->sql->setValue('media' . $id, $value);
    }

    /** @return void */
    protected function setMediaList($id, $value)
    {
        if ($id < 1 || $id > 10) {
            throw new InvalidArgumentException('ID for REX_MEDIALIST out of range (1..10)');
        }
        $this->sql->setValue('medialist' . $id, $value);
    }

    /** @return void */
    protected function setLink($id, $value)
    {
        if ($id < 1 || $id > 10) {
            throw new InvalidArgumentException('ID for REX_LINK out of range (1..10)');
        }
        $this->sql->setValue('link' . $id, $value);
    }

    /** @return void */
    protected function setLinkList($id, $value)
    {
        if ($id < 1 || $id > 10) {
            throw new InvalidArgumentException('ID for REX_LINKLIST out of range (1..10)');
        }
        $this->sql->setValue('linklist' . $id, $value);
    }

    /** @return string|null */
    protected function getValue($id)
    {
        return $this->sql->getValue('value' . $id);
    }

    /** @return string|null */
    protected function getMedia($id)
    {
        return $this->sql->getValue('media' . $id);
    }

    /** @return string|null */
    protected function getMediaList($id)
    {
        return $this->sql->getValue('medialist' . $id);
    }

    /** @return string|null */
    protected function getLink($id)
    {
        return $this->sql->getValue('link' . $id);
    }

    /** @return string|null */
    protected function getLinkList($id)
    {
        return $this->sql->getValue('linklist' . $id);
    }
}
