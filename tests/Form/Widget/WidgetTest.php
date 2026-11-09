<?php

namespace Redaxo\Core\Tests\Form\Widget;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\Form\Widget\CheckboxInput;
use Redaxo\Core\Form\Widget\Input;
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/** @internal */
final class WidgetTest extends TestCase
{
    public function testInput(): void
    {
        $html = (string) new Input('foo', 'a "b"', 'foo-id', attributes: new HtmlAttributes(['class' => ['custom'], 'placeholder' => 'Foo']))->render();

        self::assertStringContainsString('class="rex-input custom"', $html);
        self::assertStringContainsString('name="foo"', $html);
        self::assertStringContainsString('value="a &quot;b&quot;"', $html);
        self::assertStringContainsString('placeholder="Foo"', $html);
    }

    public function testSelectInput(): void
    {
        $html = (string) new SelectInput('size', [1 => 'Small', 2 => 'Large <XL>'], '2', placeholder: '')->render();

        self::assertStringContainsString('<option value=""></option>', $html);
        self::assertStringContainsString('<option value="1">Small</option>', $html);
        self::assertStringContainsString('<option value="2" selected>Large &lt;XL&gt;</option>', $html);
    }

    public function testCheckboxInputInFieldRow(): void
    {
        $html = (string) new FieldRow(new CheckboxInput('active', 'Active', checked: true), error: 'Wrong')->render();

        self::assertStringNotContainsString('<dt>', $html, 'the checkbox renders its label itself');
        self::assertMatchesRegularExpression('/<label>\s*<input type="checkbox" name="active" value="1" checked aria-describedby="([\w-]+-error)" aria-invalid="true">\s*Active\s*<\/label>.*<p class="rex-field-error" id="\1">Wrong<\/p>/s', $html, 'the error describes the checkbox');
    }

    public function testFieldRowTexts(): void
    {
        I18n::addMsg('widget_test_note', 'Use <code>{0}</code>');

        $html = (string) new FieldRow(
            new Input('foo', id: 'foo'),
            label: 'A <b>plain</b> label',
            note: new Translatable('widget_test_note', 'foo'),
            error: Html::raw('<em>trusted</em> error'),
        )->render();

        self::assertMatchesRegularExpression('/<label class="rex-field-label" for="foo">\s*A &lt;b&gt;plain&lt;\/b&gt; label\s*<\/label>/', $html, 'strings are plain text');
        self::assertStringContainsString('<p class="rex-field-note" id="foo-note">Use <code>foo</code></p>', $html, 'translations keep basic tags');
        self::assertStringContainsString('<p class="rex-field-error" id="foo-error"><em>trusted</em> error</p>', $html, 'html is passed through');
        self::assertStringContainsString('aria-describedby="foo-error foo-note" aria-invalid="true"', $html, 'note and error describe the field');
    }

    public function testSelectInputTextsArePlain(): void
    {
        I18n::addMsg('widget_test_option', 'Option <b>B</b>');

        $html = (string) new SelectInput('foo', ['a' => 'Option <b>A</b>', 'b' => new Translatable('widget_test_option')])->render();

        self::assertStringContainsString('<option value="a">Option &lt;b&gt;A&lt;/b&gt;</option>', $html);
        self::assertStringContainsString('<option value="b">Option &lt;b&gt;B&lt;/b&gt;</option>', $html);
    }
}
