<?php

namespace Redaxo\Core\Tests\Backend;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Core;
use Redaxo\Core\Security\User;
use ReflectionClass;

/** @internal */
final class NavigationTest extends TestCase
{
    public function testSetPrio(): void
    {
        $navi = Navigation::factory();
        $navi->addPage(new MainPage('addons', 'addon', 'addon')->setIsActive(false));
        $navi->addPage(new MainPage('system', 'system', 'system')->setIsActive(true));
        $navi->addPage(new MainPage('test', 'test', 'test')->setIsActive(false));

        $navi->setHeadline('test', 'Test');
        $navi->setHeadline('system', 'System');
        $navi->setHeadline('addons', 'Addons');

        $navi->setPrio('test', 15);

        $user = Core::getUser();

        try {
            Core::setProperty('user', new ReflectionClass(User::class)->newInstanceWithoutConstructor());
            $navi = $navi->getNavigation();
        } finally {
            Core::setProperty('user', $user);
        }

        self::assertSame('System', $navi[0]['headline']['title']);
        self::assertSame('Test', $navi[1]['headline']['title']);
        self::assertSame('Addons', $navi[2]['headline']['title']);
    }
}
