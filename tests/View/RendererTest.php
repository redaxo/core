<?php

namespace Redaxo\Core\Tests\View;

use Closure;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Renderer;
use TypeError;

use function ob_get_clean;
use function ob_get_level;
use function ob_start;

/** @internal */
final class RendererTest extends TestCase
{
    private const string VIEW_DIR = __DIR__ . '/Fixtures';

    public function testThrowsWhenFileMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Renderer::render(self::VIEW_DIR . '/DoesNotExist.view.php');
    }

    public function testThrowsWhenFileDoesNotReturnClosure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Renderer::render(self::VIEW_DIR . '/NotAClosure.view.php');
    }

    public function testViewFileIsInertWhenIncluded(): void
    {
        ob_start();
        /** @var Closure $closure */
        $closure = require self::VIEW_DIR . '/Greeting.view.php';
        $output = ob_get_clean();

        self::assertInstanceOf(Closure::class, $closure);
        self::assertSame('', $output); // requiring the file emits nothing, it only returns the closure
    }

    public function testClosureTypeIsEnforcedAndBufferIsCleanedOnThrow(): void
    {
        $level = ob_get_level();

        try {
            Renderer::render(self::VIEW_DIR . '/Greeting.view.php', ['not a greeting']);
            self::fail('Expected a TypeError because the closure requires a Greeting.');
        } catch (TypeError) {
            // expected
        }

        self::assertSame($level, ob_get_level(), 'The output buffer must be cleaned up after a throw.');
    }
}
