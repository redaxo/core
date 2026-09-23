<?php

namespace Redaxo\Core\Tests\Security;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\UserRole;

/** @internal */
final class UserRoleTest extends TestCase
{
    private const string NAME = 'user_role_test';

    private int $roleId;

    #[Override]
    protected function setUp(): void
    {
        $sql = Sql::factory();
        $sql->setTable('rex_user_role');
        $sql->setValue('name', self::NAME);
        $sql->setArrayValue('perms', [
            'general' => '|foo|',
            'structure' => '|3|13|5|',
            'media' => '|3|',
        ]);
        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();
        $sql->insert();

        $this->roleId = $sql->getLastId();
    }

    #[Override]
    protected function tearDown(): void
    {
        Sql::factory()->setQuery('DELETE FROM rex_user_role WHERE name = ?', [self::NAME]);
    }

    /** @param array<string, string> $expected */
    #[DataProvider('provideReplaceComplexPermItem')]
    public function testReplaceComplexPermItem(array $expected, int|string $item, int|string|null $new): void
    {
        UserRole::replaceComplexPermItem('structure', $item, $new);

        $sql = Sql::factory()->setQuery('SELECT perms FROM rex_user_role WHERE id = ?', [$this->roleId]);

        self::assertSame($expected, $sql->getArrayValue('perms'));
    }

    /** @return iterable<string, array{array<string, string>, int|string, int|string|null}> */
    public static function provideReplaceComplexPermItem(): iterable
    {
        yield 'replace' => [['general' => '|foo|', 'structure' => '|7|13|5|', 'media' => '|3|'], 3, 7];
        yield 'remove' => [['general' => '|foo|', 'structure' => '|13|5|', 'media' => '|3|'], 3, null];
        yield 'remove last' => [['general' => '|foo|', 'structure' => '|3|13|', 'media' => '|3|'], 5, null];
        yield 'unknown item' => [['general' => '|foo|', 'structure' => '|3|13|5|', 'media' => '|3|'], 1, null];
    }
}
