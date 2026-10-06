<?php

namespace Redaxo\Core\Content;

final class ArticleSliceAction
{
    public const string ADD = 'add';
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    public bool $save = true;

    /** @var list<string> */
    public private(set) array $messages = [];

    /**
     * Slice column values (`value1`, `media1`, …) to be saved.
     *
     * @var array<string, string|null>
     * @internal
     */
    public private(set) array $columnValues = [];

    /**
     * @param self::ADD|self::EDIT|self::DELETE $mode
     * @internal
     */
    public function __construct(
        public readonly string $mode,
        public readonly int $articleId,
        public readonly int $languageId,
        public readonly int $ctypeId,
        public readonly int $sliceId,
    ) {}

    /** @internal */
    public function setRequestValues(): void
    {
        foreach (ArticleSlice::readRequestValues() as $key => $list) {
            foreach ($list as $index => $value) {
                $this->columnValues[$key . ($index + 1)] = $value;
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
        $this->columnValues['value' . $index] = $value;
    }

    /** @param int<1, 10> $index */
    public function setMedia(int $index, ?string $value): void
    {
        $this->columnValues['media' . $index] = $value;
    }

    /** @param int<1, 10> $index */
    public function setMediaList(int $index, ?string $value): void
    {
        $this->columnValues['medialist' . $index] = $value;
    }

    /** @param int<1, 10> $index */
    public function setLink(int $index, ?int $value): void
    {
        $this->columnValues['link' . $index] = null === $value ? null : (string) $value;
    }

    /** @param int<1, 10> $index */
    public function setLinkList(int $index, ?string $value): void
    {
        $this->columnValues['linklist' . $index] = $value;
    }

    /** @param int<1, 20> $index */
    public function getValue(int $index): ?string
    {
        return $this->columnValues['value' . $index] ?? null;
    }

    /** @param int<1, 10> $index */
    public function getMedia(int $index): ?string
    {
        return $this->columnValues['media' . $index] ?? null;
    }

    /** @param int<1, 10> $index */
    public function getMediaList(int $index): ?string
    {
        return $this->columnValues['medialist' . $index] ?? null;
    }

    /** @param int<1, 10> $index */
    public function getLink(int $index): ?int
    {
        $link = $this->columnValues['link' . $index] ?? null;

        return null === $link || '' === $link ? null : (int) $link;
    }

    /** @param int<1, 10> $index */
    public function getLinkList(int $index): ?string
    {
        return $this->columnValues['linklist' . $index] ?? null;
    }
}
