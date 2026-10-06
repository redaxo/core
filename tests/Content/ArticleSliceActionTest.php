<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleSliceAction;

/** @internal */
final class ArticleSliceActionTest extends TestCase
{
    public function testSetAndGetValues(): void
    {
        $action = new ArticleSliceAction(ArticleSliceAction::ADD, 1, 1, 1, 0);

        self::assertNull($action->getValue(1));
        self::assertNull($action->getLink(1));

        $action->setValue(1, 'foo');
        $action->setMedia(2, 'image.jpg');
        $action->setMediaList(3, 'a.jpg,b.jpg');
        $action->setLink(4, 5);
        $action->setLinkList(5, '1,2');

        self::assertSame('foo', $action->getValue(1));
        self::assertSame('image.jpg', $action->getMedia(2));
        self::assertSame('a.jpg,b.jpg', $action->getMediaList(3));
        self::assertSame(5, $action->getLink(4));
        self::assertSame('1,2', $action->getLinkList(5));

        self::assertSame([
            'value1' => 'foo',
            'media2' => 'image.jpg',
            'medialist3' => 'a.jpg,b.jpg',
            'link4' => '5',
            'linklist5' => '1,2',
        ], $action->columnValues);

        $action->setValue(1, null);
        self::assertNull($action->getValue(1));
        self::assertArrayHasKey('value1', $action->columnValues);
    }

    public function testEmptyLinkIsNull(): void
    {
        $_REQUEST['REX_INPUT_LINK'] = [1 => ''];

        try {
            $action = new ArticleSliceAction(ArticleSliceAction::ADD, 1, 1, 1, 0);
            $action->setRequestValues();
        } finally {
            unset($_REQUEST['REX_INPUT_LINK']);
        }

        self::assertNull($action->getLink(1));
        self::assertSame('', $action->columnValues['link1']);
    }
}
