<?php

namespace Redaxo\Core\Tests\ExtensionPoint;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Tests\ExtensionPoint\Fixtures\InstanceExtensionFixture;
use ReflectionProperty;

/** @internal */
final class ExtensionTest extends TestCase
{
    private const INSTANCE_EP = 'TEST_INSTANCE_EXTENSION_EP';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Reset the static instance registry so instance-method tests don't leak into each other.
        new ReflectionProperty(Extension::class, 'instances')->setValue(null, []);

        parent::tearDown();
    }

    public function testHasExtensions(): void
    {
        $EP = 'TEST_IS_REGISTERED';

        self::assertFalse(Extension::hasExtensions($EP), 'hasExtensions() returns false for non-registered extension points');

        Extension::register($EP, static function () {});

        self::assertTrue(Extension::hasExtensions($EP), 'hasExtensions() returns true for registered extension points');
    }

    public function testDispatch(): void
    {
        $EP = 'TEST_EP';

        $EPParam = null;
        Extension::register($EP, static function (ExtensionPoint $ep) use (&$EPParam) {
            $EPParam = $ep->name;
            return $ep->subject . ' test2';
        });

        Extension::register($EP, static function () {});

        Extension::register($EP, static function (ExtensionPoint $ep) {
            return $ep->subject . ' test3';
        });

        $result = Extension::dispatch(new ExtensionPoint($EP, 'test'));

        self::assertEquals($EP, $EPParam, '$params["extension_point"] contains the extension point name');
        self::assertEquals('test test2 test3', $result, 'dispatch() returns the returned value of last extension');
    }

    public function testDispatchReadOnly(): void
    {
        $EP = 'TEST_EP_READ_ONLY';

        Extension::register($EP, static function () {
            return 'test2';
        });

        $subjectActual = null;
        Extension::register($EP, static function (ExtensionPoint $ep) use (&$subjectActual) {
            $subjectActual = $ep->subject;
        });

        $subject = 'test';
        Extension::dispatch(new ExtensionPoint($EP, $subject, [], true));

        self::assertEquals($subject, $subjectActual, "read-only extention points don't change subject param");
    }

    public function testDispatchWithParams(): void
    {
        $EP = 'TEST_EP_WITH_PARAMS';

        $myparamActual = null;
        Extension::register($EP, static function (ExtensionPoint $ep) use (&$myparamActual) {
            $myparamActual = $ep->getParam('myparam');
        });

        $myparam = 'myparam';
        Extension::dispatch(new ExtensionPoint($EP, null, ['myparam' => $myparam]));

        self::assertEquals($myparam, $myparamActual, 'additional params will be available in extentions');
    }

    public function testRegister(): void
    {
        $EP = 'TEST_EP_LEVELS';

        $callback = static function ($str) {
            return static function (ExtensionPoint $ep) use ($str) {
                return $ep->subject . $str . ' ';
            };
        };

        Extension::register($EP, $callback('late1'), ExtensionLevel::Late);
        Extension::register($EP, $callback('normal1'));
        Extension::register($EP, $callback('early1'), ExtensionLevel::Early);
        Extension::register($EP, $callback('late2'), ExtensionLevel::Late);
        Extension::register($EP, $callback('normal2'), ExtensionLevel::Normal);
        Extension::register($EP, $callback('early2'), ExtensionLevel::Early);

        $expected = 'early1 early2 normal1 normal2 late1 late2 ';
        $actual = Extension::dispatch(new ExtensionPoint($EP, ''));

        self::assertEquals($expected, $actual);
    }

    public function testRegisterMultiple(): void
    {
        $EP1 = 'TEST_EP_MULTIPLE_1';
        $EP2 = 'TEST_EP_MULTIPLE_2';

        Extension::register([$EP1, $EP2], static function () {
            return 'foo';
        });

        /** @var string|null $subject */
        $subject = null;

        self::assertSame('foo', Extension::dispatch(new ExtensionPoint($EP1, $subject)));
        self::assertSame('foo', Extension::dispatch(new ExtensionPoint($EP2, $subject)));
    }

    public function testInstanceExtensionNoInstanceIsNoop(): void
    {
        // The #[AsExtension] method is discovered and wired at boot, but with no registered instance it's a no-op.
        self::assertSame('start', Extension::dispatch(new ExtensionPoint(self::INSTANCE_EP, 'start')));
    }

    public function testInstanceExtensionRunsForEachRegisteredInstance(): void
    {
        Extension::registerInstance(new InstanceExtensionFixture('A'));
        Extension::registerInstance(new InstanceExtensionFixture('B'));

        // The method runs once per instance, in registration order, chaining the subject between them.
        self::assertSame('startAB', Extension::dispatch(new ExtensionPoint(self::INSTANCE_EP, 'start')));
    }

    public function testInstanceExtensionMatchesSubclassInstances(): void
    {
        // The attribute sits on the parent; a registered subclass instance must still serve it. Discovery only
        // needs to find the parent, so an anonymous subclass created here is enough — it matches via instanceof.
        Extension::registerInstance(new class('C') extends InstanceExtensionFixture {});

        self::assertSame('startC', Extension::dispatch(new ExtensionPoint(self::INSTANCE_EP, 'start')));
    }

    public function testInstanceExtensionRespectsReadonly(): void
    {
        Extension::registerInstance(new InstanceExtensionFixture('A'));

        // Readonly EP: the method still runs (no exception), but the subject is not changed.
        self::assertSame('start', Extension::dispatch(new ExtensionPoint(self::INSTANCE_EP, 'start', [], true)));
    }
}
