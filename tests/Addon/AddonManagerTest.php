<?php

namespace Redaxo\Core\Tests\Addon;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

/** @internal */
final class AddonManagerTest extends TestCase
{
    private mixed $installed;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->installed = Core::getConfig('addons_installed');

        $sql = Sql::factory();
        $sql->setQuery('CREATE TABLE `rex_tests_orphan` (`id` INT NOT NULL PRIMARY KEY) ENGINE = InnoDB');
        $sql->setQuery('CREATE TABLE `rex_tests_host` (`id` INT NOT NULL PRIMARY KEY, `orphan_marker` INT NULL) ENGINE = InnoDB');
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        Core::setConfig('addons_installed', $this->installed);
        Config::removeNamespace('tests_orphan');

        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `rex_tests_orphan`');
        $sql->setQuery('DROP TABLE IF EXISTS `rex_tests_host`');
        Table::clearInstance('rex_tests_orphan');
        Table::clearInstance('rex_tests_host');
    }

    public function testOrphanIsRemovedWithItsOwnedSchema(): void
    {
        $this->addInstalled('tests_orphan', ['package' => 'acme/tests-orphan', 'tables' => ['rex_tests_orphan'], 'columns' => ['rex_tests_host' => ['orphan_marker']]]);
        Config::set('tests_orphan', 'key', 'value');

        self::assertContains('tests_orphan', AddonManager::getOrphans());
        self::assertNull(AddonManager::getOrphanRemovalBlocker('tests_orphan'));

        self::assertSame(['tables' => 1, 'columns' => 1], AddonManager::removeOrphan('tests_orphan'));

        $tables = Sql::factory()->getTables();
        self::assertNotContains('rex_tests_orphan', $tables);
        self::assertContains('rex_tests_host', $tables);
        self::assertFalse(Table::get('rex_tests_host')->hasColumn('orphan_marker'));
        self::assertFalse(Config::has('tests_orphan'));
        self::assertNotContains('tests_orphan', AddonManager::getOrphans());
    }

    /** @param array{package?: string} $entry */
    #[DataProvider('provideBlockedOrphan')]
    public function testBlockedOrphan(string $expectedBlocker, array $entry): void
    {
        $this->addInstalled('tests_orphan', $entry);

        self::assertStringContainsString($expectedBlocker, (string) AddonManager::getOrphanRemovalBlocker('tests_orphan'));
    }

    /** @return iterable<string, array{string, array{package?: string}}> */
    public static function provideBlockedOrphan(): iterable
    {
        yield 'still in composer.lock' => ['is still in the composer.lock', ['package' => 'redaxo/test']];
        yield 'package unknown' => ['composer package is unknown', []];
    }

    /** @param array<string, mixed> $entry */
    private function addInstalled(string $addon, array $entry): void
    {
        $installed = Core::getConfig('addons_installed', []);
        self::assertIsArray($installed);
        $installed[$addon] = $entry;

        Core::setConfig('addons_installed', $installed);
    }
}
