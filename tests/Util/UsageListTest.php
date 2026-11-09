<?php

namespace Redaxo\Core\Tests\Util;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Util\UsageList;

/** @internal */
final class UsageListTest extends TestCase
{
    public function testRender(): void
    {
        $usages = new UsageList();
        self::assertTrue($usages->isEmpty());

        $usages->add('Articles', 'Foo & Bar', 'index.php?page=content&article_id=1');
        $usages->add('Languages', '<b>de</b>');
        $usages->add('Articles', 'Baz', 'index.php?page=content&article_id=2');

        self::assertFalse($usages->isEmpty());
        self::assertSame(
            '<div>'
            . '<p>Articles</p><ul>'
            . '<li><a href="index.php?page=content&amp;article_id=1">Foo &amp; Bar</a></li>'
            . '<li><a href="index.php?page=content&amp;article_id=2">Baz</a></li>'
            . '</ul>'
            . '<p>Languages</p><ul><li>&lt;b&gt;de&lt;/b&gt;</li></ul>'
            . '</div>',
            $usages->render(),
        );
    }
}
