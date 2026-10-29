<?php

namespace Redaxo\Core\Tests\Form;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Form\ButtonStyle;
use Redaxo\Core\Form\Field\Text;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\SubmitButton;
use Redaxo\Core\Tests\Form\Fixtures\Color;
use Redaxo\Core\Tests\Form\Fixtures\Fieldsets;
use Redaxo\Core\Tests\Form\Fixtures\Settings;
use Redaxo\Core\Translation\Translatable;

/** @internal */
final class FormTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        Accesskey::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testNotSubmitted(): void
    {
        $form = new Form(new Settings(), csrf: false);

        self::assertSame('settings', $form->name);
        self::assertFalse($form->isSubmitted());
        self::assertFalse($form->handleRequest());
        self::assertSame('Hello', $form->field('title')->raw);
        self::assertSame('1', $form->field('active')->raw);
        self::assertSame('settings[title]', $form->field('title')->inputName);
        self::assertSame('settings-title', $form->field('title')->id);
    }

    public function testValidSubmit(): void
    {
        $_POST['settings'] = ['_submit' => '1', 'title' => 'New', 'subtitle' => '', 'size' => '2', 'color' => 'blue'];

        $settings = new Settings();
        $form = new Form($settings, csrf: false);

        self::assertTrue($form->handleRequest());
        self::assertTrue($form->isValid());
        self::assertSame('New', $settings->title);
        self::assertNull($settings->subtitle);
        self::assertSame(2, $settings->size);
        self::assertSame(Color::Blue, $settings->color);
        self::assertFalse($settings->active, 'a checkbox missing in the request is unchecked');
        self::assertSame('untouched', $settings->notAField);
    }

    /** @param array<string, string> $submitted */
    #[DataProvider('provideInvalidSubmit')]
    public function testInvalidSubmit(string $expectedField, string $expectedError, array $submitted): void
    {
        $_POST['settings'] = $submitted + ['_submit' => '1', 'title' => 'New', 'size' => '1', 'color' => ''];

        $settings = new Settings();
        $form = new Form($settings, csrf: false);

        self::assertFalse($form->handleRequest());
        self::assertFalse($form->isValid());

        $error = $form->field($expectedField)->error;
        self::assertInstanceOf(Translatable::class, $error);
        self::assertSame($expectedError, $error->key);

        self::assertSame('Hello', $settings->title, 'nothing is written into the object');
        self::assertSame($submitted[$expectedField], $form->field($expectedField)->raw, 'the submitted value is kept for redisplay');
    }

    /** @return iterable<string, array{string, string, array<string, string>}> */
    public static function provideInvalidSubmit(): iterable
    {
        yield 'required' => ['title', 'form_required', ['title' => '']];
        yield 'too long' => ['title', 'form_max_length', ['title' => str_repeat('ä', 21)]];
        yield 'unknown choice' => ['size', 'form_invalid_choice', ['size' => '3']];
        yield 'unknown enum choice' => ['color', 'form_invalid_choice', ['color' => 'green']];
    }

    public function testAddError(): void
    {
        $_POST['settings'] = ['_submit' => '1', 'title' => 'New', 'size' => '1'];

        $form = new Form(new Settings(), csrf: false);

        self::assertTrue($form->handleRequest());

        $form->addError('Already taken', 'title');

        self::assertFalse($form->isValid());
        self::assertSame('Already taken', $form->field('title')->error);
    }

    public function testRender(): void
    {
        $form = new Form(new Settings(), csrf: false);

        $html = (string) $form->row('title');
        self::assertMatchesRegularExpression('/<label class="rex-field-label" for="settings-title">\s*Title\s*<span class="rex-field-required" aria-hidden="true">\*<\/span>\s*<\/label>/', $html);
        self::assertStringContainsString('name="settings[title]"', $html);
        self::assertStringContainsString('value="Hello"', $html);
        self::assertStringContainsString('maxlength="20"', $html);

        $rest = (string) $form->rest();
        self::assertStringNotContainsString('settings[title]', $rest, 'rest() skips already rendered fields');
        self::assertStringContainsString('<option value="1" selected>Small</option>', $rest);
        self::assertStringContainsString('<option value="red">Red</option>', $rest);
        self::assertMatchesRegularExpression('/<input type="checkbox" name="settings\[active\]"[^>]* checked/', $rest);
    }

    public function testButtons(): void
    {
        $form = new Form(new Settings(), csrf: false);
        self::assertStringContainsString('<button class="rex-button rex-form-aligned" data-variant="success" type="submit" name="settings[_button]" value="save" accesskey="s" title="Save [s]">Save</button>', (string) $form->buttons());
        self::assertStringContainsString('<button class="rex-button foo" data-variant="success" type="submit"', (string) $form->button('save', ['class' => ['foo']]));

        $form = new Form(
            new Settings(),
            csrf: false,
            buttons: [new SubmitButton('reset', 'Reset <all>', style: ButtonStyle::Danger), SubmitButton::save('Store')],
            cancelUrl: 'index.php?a=1&b=2',
        );

        $html = (string) $form->buttons();
        self::assertMatchesRegularExpression('/<button class="rex-button rex-form-aligned" data-variant="danger" type="submit" name="settings\[_button\]" value="reset">Reset &lt;all&gt;<\/button>\s*<button class="rex-button" data-variant="success" [^>]*value="save"[^>]*>Store<\/button>\s*<a class="rex-button" data-align="end" href="index.php\?a=1&amp;b=2">/', $html);

        self::assertTrue($form->submit(['title' => 'Saved', 'size' => '1']));
        self::assertFalse($form->isSubmittedBy('save'), 'no button when submitted without one, e.g. by script');

        self::assertTrue($form->submit(['title' => 'Saved', 'size' => '1', '_button' => 'save']));
        self::assertTrue($form->isSubmittedBy('save'));
        self::assertFalse($form->isSubmittedBy('reset'));

        self::assertFalse($form->submit(['title' => '', 'size' => '1', '_button' => 'reset']));
        self::assertTrue($form->isSubmittedBy('reset'), 'the button is known even if values are invalid');

        $form->submit(['title' => 'Unknown', 'size' => '1', '_button' => 'unknown']);
        self::assertFalse($form->isSubmittedBy('unknown'));

        $this->expectException(LogicException::class);
        $form->button('unknown');
    }

    public function testSubmitEmbedded(): void
    {
        $settings = new Settings();
        $form = new Form($settings, name: 'meta', only: ['title', 'size']);

        self::assertFalse($form->isSubmitted());
        self::assertTrue($form->submit(['title' => 'Embedded', 'size' => '2', 'active' => '']));
        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame('Embedded', $settings->title);
        self::assertSame(2, $settings->size);
        self::assertTrue($settings->active, 'fields outside of `only` are left untouched');

        self::assertSame('meta[title]', $form->field('title')->inputName);

        $html = (string) $form->rest();
        self::assertStringContainsString('meta[size]', $html);
        self::assertStringNotContainsString('meta[active]', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testAnonymousClass(): void
    {
        $data = new class {
            #[Text('Title')]
            public string $title = '';
        };

        $form = new Form($data, name: 'inline', csrf: false);
        self::assertTrue($form->submit(['title' => 'Inline']));
        self::assertSame('Inline', $data->title);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A form for an anonymous class needs an explicit name.');
        new Form($data);
    }

    public function testCapture(): void
    {
        $form = new Form(new Settings(), csrf: false);

        $html = (string) $form->capture(static function (Form $form): void { ?>
            <div class="custom"><?= $form->row('title') ?></div>
        <?php }, ['class' => ['my-form']]);

        self::assertMatchesRegularExpression('/^<form [^>]*class="my-form"[^>]*><input type="hidden" name="settings\\[_submit\\]" value="1">\s*<div class="custom">.*name="settings\\[title\\]".*<\/div>\s*<\/form>$/s', $html);
    }

    public function testVertical(): void
    {
        $form = new Form(new Settings(), csrf: false);
        self::assertStringNotContainsString('data-layout="vertical"', (string) $form->row('title'));

        $form = new Form(new Settings(), csrf: false, vertical: true);
        self::assertStringContainsString('data-layout="vertical"', (string) $form->row('title'));
        self::assertStringContainsString('data-layout="vertical"', (string) $form->row('active'), 'checkbox rows as well');
    }

    public function testUnknownField(): void
    {
        $form = new Form(new Settings(), only: ['title']);

        $this->expectException(LogicException::class);
        $form->field('size');
    }

    public function testDisabled(): void
    {
        $_POST['settings'] = ['_submit' => '1', 'title' => '', 'subtitle' => 'Sub', 'size' => '2', 'color' => 'blue'];

        $settings = new Settings();
        $form = new Form($settings, csrf: false, disabled: ['title', 'active']);

        self::assertTrue($form->handleRequest(), 'a disabled required field is not validated');
        self::assertSame('Hello', $settings->title, 'the submitted value of a disabled field is ignored');
        self::assertTrue($settings->active, 'a disabled checkbox missing in the request keeps its value');
        self::assertSame('Sub', $settings->subtitle);

        self::assertTrue($form->field('title')->disabled);
        self::assertFalse($form->field('subtitle')->disabled);
        self::assertSame('Hello', $form->field('title')->raw, 'a disabled field shows the value of the data object');
        self::assertMatchesRegularExpression('/<input [^>]*name="settings\\[title\\]"[^>]* disabled/', (string) $form->row('title'));
        self::assertMatchesRegularExpression('/<input type="checkbox" [^>]* checked disabled>/', (string) $form->row('active'));
        self::assertStringNotContainsString('disabled', (string) $form->row('subtitle'));
    }

    public function testDisabledUnknownField(): void
    {
        $this->expectException(LogicException::class);
        new Form(new Settings(), only: ['title'], disabled: ['size']);
    }

    public function testFieldsets(): void
    {
        $form = new Form(new Fieldsets(), csrf: false);

        $html = (string) $form->rest();

        self::assertMatchesRegularExpression('/name="fieldsets\\[a\\]".*<fieldset>\s*<legend>Group 1<\/legend>.*name="fieldsets\\[b\\]".*name="fieldsets\\[c\\]".*<\/fieldset>\s*<fieldset>\s*<legend>Group 2<\/legend>.*name="fieldsets\\[d\\]".*<\/fieldset>/s', $html);

        $html = (string) new Form(new Fieldsets(), only: ['a', 'c'])->rest();
        self::assertMatchesRegularExpression('/<legend>Group 1<\/legend>.*name="fieldsets\\[c\\]"/s', $html, 'a field stays in its group even if the field starting the group is left out');
    }
}
