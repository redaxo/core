<?php

namespace Redaxo\Core\Tests\View;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Tests\View\Fixtures\Greeting;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\ViewResolver;

use function str_replace;

/** @internal */
final class ViewResolverTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        ViewResolver::reset();
    }

    public function testResolvesCoLocatedViewByDefault(): void
    {
        $path = str_replace('\\', '/', ViewResolver::resolve(Greeting::class));

        self::assertStringEndsWith('tests/View/Fixtures/Greeting.view.php', $path);
    }

    public function testRegisteredOverrideWins(): void
    {
        $override = __DIR__ . '/Fixtures/GreetingOverride.view.php';
        ViewResolver::override(Greeting::class, $override);

        self::assertSame($override, ViewResolver::resolve(Greeting::class));
    }

    public function testOverrideChangesRenderedMarkup(): void
    {
        ViewResolver::override(Greeting::class, __DIR__ . '/Fixtures/GreetingOverride.view.php');

        $html = (string) new Greeting(name: 'World', message: Html::raw('x'))->render();

        self::assertStringContainsString('OVERRIDDEN: World', $html);
        self::assertStringNotContainsString('<section class="greeting">', $html);
    }
}
