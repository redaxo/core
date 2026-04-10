<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Util\Stream;

use function in_array;
use function is_array;

final class ArticleAction
{
    public const int ADD = 1;
    public const int EDIT = 2;
    public const int DELETE = 4;

    public const string PREVIEW = 'preview';
    public const string PRESAVE = 'presave';
    public const string POSTSAVE = 'postsave';

    public readonly int $articleId;
    public readonly int $clangId;
    public readonly int $ctypeId;
    public readonly int $sliceId;

    /** @param self::ADD|self::EDIT|self::DELETE $mode */
    public readonly int $mode;

    public bool $save = true;

    /** @var list<string> */
    public private(set) array $messages = [];

    /** @internal */
    public function __construct(
        public readonly int $moduleId,
        public readonly string $event,
        private readonly Sql $sql,
    ) {
        if ('edit' == $event) {
            $this->mode = self::EDIT;
        } elseif ('delete' == $event) {
            $this->mode = self::DELETE;
        } else {
            $this->mode = self::ADD;
        }

        $this->articleId = Request::request('article_id', 'int');
        $this->clangId = Request::request('clang', 'int');
        $this->ctypeId = Request::request('ctype', 'int');
        $this->sliceId = self::ADD === $this->mode ? 0 : Request::request('slice_id', 'int');
    }

    /** @internal */
    public function setRequestValues(): void
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

    /** @param self::PREVIEW|self::PRESAVE|self::POSTSAVE $type */
    public function exec(string $type): void
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
            $articleId = (int) $row->getValue('id');
            require Stream::factory('action/' . $articleId . '/' . $type, $action);
        }
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /** @param int<1, 20> $index */
    public function setValue(int $index, string $value): void
    {
        $this->sql->setValue('value' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setMedia(int $index, string $value): void
    {
        $this->sql->setValue('media' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setMediaList(int $index, string $value): void
    {
        $this->sql->setValue('medialist' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setLink(int $index, int $value): void
    {
        $this->sql->setValue('link' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setLinkList(int $index, string $value): void
    {
        $this->sql->setValue('linklist' . $index, $value);
    }

    /** @param int<1, 20> $index */
    public function getValue(int $index): ?string
    {
        return $this->sql->getValue('value' . $index);
    }

    /** @param int<1, 10> $index */
    public function getMedia(int $index): ?string
    {
        return $this->sql->getValue('media' . $index);
    }

    /** @param int<1, 10> $index */
    public function getMediaList(int $index): ?string
    {
        return $this->sql->getValue('medialist' . $index);
    }

    /** @param int<1, 10> $index */
    public function getLink(int $index): ?int
    {
        return $this->sql->getValue('link' . $index);
    }

    /** @param int<1, 10> $index */
    public function getLinkList(int $index): ?string
    {
        return $this->sql->getValue('linklist' . $index);
    }
}
