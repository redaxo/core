<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\View\Fragment;

/** @internal */
final class FragmentTest extends TestCase
{
    public function testThrowingFragmentClosesItsOutputBuffer(): void
    {
        Fragment::addDirectory(__DIR__ . '/Fixtures/fragments');
        $level = ob_get_level();

        try {
            new Fragment()->parse('test/throwing.php'); // @phpstan-ignore new.deprecatedClass
            self::fail('The fragment should throw.');
        } catch (RuntimeException $exception) {
            self::assertSame('Fragment failed', $exception->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }
}
