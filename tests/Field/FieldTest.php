<?php

namespace Redaxo\Core\Tests\Field;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Field\CheckboxField;
use Redaxo\Core\Field\ChoiceField;
use Redaxo\Core\Field\DateField;
use Redaxo\Core\Field\FieldBinding;
use Redaxo\Core\Field\FieldGroup;
use Redaxo\Core\Field\TextareaField;
use Redaxo\Core\Field\TextField;
use Redaxo\Core\View\ViewResolver;

use function mktime;

/** @internal */
final class FieldTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewResolver::reset();
        $_POST = [];
    }

    public function testTextFieldRendersControl(): void
    {
        $html = (string) new TextField('email', 'E-Mail', maxLength: 100)->renderInput();

        self::assertStringContainsString('type="text"', $html);
        self::assertStringContainsString('name="email"', $html);
        self::assertStringContainsString('id="email"', $html);
        self::assertStringContainsString('maxlength="100"', $html);
        self::assertStringContainsString('class="form-control"', $html);
    }

    public function testGroupWrapsLabelControlNoteAndError(): void
    {
        $field = new TextField('name', 'Name', note: 'a hint', required: true)
            ->boundTo(new FieldBinding('name', 'value', 'is wrong'));

        $html = (string) $field->render();

        self::assertStringContainsString('<dl', $html);
        self::assertStringContainsString('rex-is-required', $html);
        self::assertStringContainsString('has-error', $html);
        self::assertStringContainsString('<label for="name">Name</label>', $html);
        self::assertStringContainsString('a hint', $html);
        self::assertStringContainsString('is wrong', $html);
        self::assertStringContainsString('value="value"', $html);
    }

    public function testEmptyLabelOmitsLabelElement(): void
    {
        $html = (string) new TextField('x')->render();

        self::assertStringNotContainsString('<label', $html);
    }

    public function testValueIsEscaped(): void
    {
        $html = (string) new TextField('x')->withValue('<script>"alert"')->renderInput();

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testBoundToLeavesOriginalUntouched(): void
    {
        $field = new TextField('x');
        $bound = $field->withValue('changed');

        self::assertNull($field->binding->value);
        self::assertSame('changed', $bound->binding->value);
    }

    public function testFieldGroupViewIsOverridable(): void
    {
        ViewResolver::override(FieldGroup::class, __DIR__ . '/Fixtures/custom_group.view.php');

        $html = (string) new TextField('x', 'Label')->render();

        self::assertStringContainsString('<div class="my-group">', $html);
        self::assertStringNotContainsString('<dl', $html);
    }

    public function testTextareaRendersContentEscaped(): void
    {
        $html = (string) new TextareaField('bio', rows: 8)->withValue('<b>hi</b>')->renderInput();

        self::assertStringContainsString('rows="8"', $html);
        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
    }

    public function testCheckboxGroupAndCheckedState(): void
    {
        $checked = (string) new CheckboxField('active', 'Active')->withValue('1')->render();
        self::assertStringContainsString('<div class="checkbox">', $checked);
        self::assertStringContainsString('type="checkbox"', $checked);
        self::assertStringContainsString('checked', $checked);

        $unchecked = (string) new CheckboxField('active', 'Active')->withValue('0')->renderInput();
        self::assertStringNotContainsString('checked', $unchecked);
    }

    public function testCheckboxParseAndFormat(): void
    {
        $field = new CheckboxField('active');

        $_POST = ['active' => '1'];
        self::assertSame(1, $field->parseRequest('active'));

        $_POST = [];
        self::assertSame(0, $field->parseRequest('active'));

        self::assertTrue($field->format('1'));
        self::assertFalse($field->format('0'));
    }

    public function testChoiceRendersSelectWithSelectedOption(): void
    {
        $field = new ChoiceField('color', 'Color', choices: ['r' => 'Red', 'g' => 'Green'])
            ->withValue('g');

        $html = (string) $field->renderInput();

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('<option value="r">Red</option>', $html);
        self::assertStringContainsString('<option value="g" selected>Green</option>', $html);
    }

    public function testChoiceRendersOptgroups(): void
    {
        $field = new ChoiceField('c', choices: ['Group' => ['a' => 'A', 'b' => 'B']]);

        $html = (string) $field->renderInput();

        self::assertStringContainsString('<optgroup label="Group">', $html);
        self::assertStringContainsString('<option value="a">A</option>', $html);
    }

    public function testChoiceExpandedRendersRadios(): void
    {
        $field = new ChoiceField('c', choices: ['a' => 'A', 'b' => 'B'], expanded: true)
            ->withValue('b');

        $html = (string) $field->renderInput();

        self::assertStringContainsString('type="radio"', $html);
        self::assertStringContainsString('value="b" checked', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    public function testChoiceMultipleParseAndFormat(): void
    {
        $field = new ChoiceField('c', choices: ['a' => 'A', 'b' => 'B'], multiple: true);

        $_POST = ['c' => ['a', 'b']];
        self::assertSame('|a|b|', $field->parseRequest('c'));

        self::assertSame(['a', 'b'], $field->format('|a|b|'));
    }

    public function testDateFieldFormatsTimestampForDisplay(): void
    {
        $timestamp = mktime(0, 0, 0, 6, 22, 2026);
        $html = (string) new DateField('d')->withValue($timestamp)->renderInput();

        self::assertStringContainsString('type="date"', $html);
        self::assertStringContainsString('value="2026-06-22"', $html);
    }
}
