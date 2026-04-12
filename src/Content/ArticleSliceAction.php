<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Database\Sql;

final class ArticleSliceAction
{
    public const string ADD = 'add';
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    public bool $save = true;

    /** @var list<string> */
    public private(set) array $messages = [];

    /**
     * @param self::ADD|self::EDIT|self::DELETE $mode
     * @internal
     */
    public function __construct(
        public readonly string $mode,
        public readonly int $articleId,
        public readonly int $clangId,
        public readonly int $ctypeId,
        public readonly int $sliceId,
        private readonly Sql $sql,
    ) {}

    /** @internal */
    public function setRequestValues(): void
    {
        foreach (ArticleSlice::readRequestValues() as $key => $list) {
            foreach ($list as $index => $value) {
                $this->sql->setValue($key . ($index + 1), $value);
            }
        }
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /** @param int<1, 20> $index */
    public function setValue(int $index, ?string $value): void
    {
        $this->sql->setValue('value' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setMedia(int $index, ?string $value): void
    {
        $this->sql->setValue('media' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setMediaList(int $index, ?string $value): void
    {
        $this->sql->setValue('medialist' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setLink(int $index, ?int $value): void
    {
        $this->sql->setValue('link' . $index, $value);
    }

    /** @param int<1, 10> $index */
    public function setLinkList(int $index, ?string $value): void
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
