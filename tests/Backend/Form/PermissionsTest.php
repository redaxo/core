<?php

namespace Redaxo\Core\Tests\Backend\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\Form\Permissions;
use Redaxo\Core\Backend\Form\Role;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\Translatable;

/** @internal */
final class PermissionsTest extends TestCase
{
    public function testSubmitAndStore(): void
    {
        Permission::register('test_perm[]');
        $languageId = Language::getStartId();

        $role = new Role();
        $role->grantAllItems();

        $form = new Form($role, csrf: false);
        self::assertTrue($form->submit([
            'name' => 'Editor',
            'perms' => ['general:test_perm[]', 'language:' . $languageId, 'modules'],
        ]));

        self::assertSame(['test_perm[]'], $role->perms[Permission::GENERAL]);
        self::assertSame([], $role->perms[Permission::OPTIONS]);
        self::assertSame([(string) $languageId], $role->perms['language']);
        self::assertSame([], $role->perms['structure']);
        self::assertSame(ComplexPermission::ALL, $role->perms['modules']);

        $definition = FormSchema::of(Role::class)->get('perms');
        $field = $definition->field;
        self::assertInstanceOf(Permissions::class, $field);
        $stored = $field->encode($role->perms);
        self::assertSame('|test_perm[]|', $stored[Permission::GENERAL]);
        self::assertSame('', $stored['structure']);
        self::assertSame(ComplexPermission::ALL, $stored['modules']);
        self::assertSame('|' . $languageId . '|', $stored['language']);

        self::assertSame($role->perms, $field->decode($stored, $definition->type));
    }

    public function testGrantAllItems(): void
    {
        $role = new Role();
        $role->grantAllItems();

        self::assertSame(['language', 'structure', 'modules', 'media'], array_keys($role->perms));
        self::assertSame([ComplexPermission::ALL], array_values(array_unique($role->perms)));
    }

    /** @param list<string> $perms */
    #[DataProvider('provideInvalidSubmit')]
    public function testInvalidSubmit(array $perms): void
    {
        $form = new Form(new Role(), csrf: false);

        self::assertFalse($form->submit(['name' => 'Editor', 'perms' => $perms]));

        $error = $form->field('perms')->error;
        self::assertInstanceOf(Translatable::class, $error);
        self::assertSame('form_invalid_choice', $error->key);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function provideInvalidSubmit(): iterable
    {
        yield 'unknown permission' => [['general:unknown_perm[]']];
        yield 'unknown language' => [['language:999999']];
        yield 'all for simple group' => [[Permission::GENERAL]];
        yield 'unknown key' => [['unknown']];
    }

    public function testRender(): void
    {
        Permission::register('test_perm[]');

        $role = new Role();
        $role->perms = ['structure' => ComplexPermission::ALL];

        $html = (string) new Form($role, csrf: false)->row('perms');

        self::assertMatchesRegularExpression('/<input [^>]*name="role\[perms\]\[\]" id="role-perms-structure-all" value="structure" checked data-rex-hides="#role-perms-structure-items">/', $html);
        self::assertMatchesRegularExpression('/<input type="checkbox" name="role\[perms\]\[\]" id="role-perms-general-general_test_perm__" value="general:test_perm\[\]">/', $html);
        self::assertStringContainsString('<select class="form-control" name="role[perms][]" id="role-perms-structure" multiple data-rex-select>', $html);
        self::assertStringNotContainsString('id="role-perms-general-all"', $html);
    }
}
