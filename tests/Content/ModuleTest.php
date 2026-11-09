<?php

namespace Redaxo\Core\Tests\Content;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Fixtures\TestModule1;
use Redaxo\Core\Tests\Content\Fixtures\TestFieldsModule;
use Redaxo\Core\Tests\Content\Fixtures\TestNoFieldsModule;
use Redaxo\Core\Translation\I18n;

/** @internal */
final class ModuleTest extends TestCase
{
    private const int FAKE_ID = 2_147_483_647;

    #[Override]
    protected function setUp(): void
    {
        // the slices need an article to hang off, see the foreign key of `rex_article_slice`
        Sql::factory()
            ->setTable('rex_article')
            ->setValues(['id' => self::FAKE_ID, 'parent_id' => null, 'priority' => 1, 'path' => '|'])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert();

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setValues(['article_id' => self::FAKE_ID, 'language_id' => 1, 'name' => 'test', 'status' => 1])
            ->insert();
    }

    #[Override]
    protected function tearDown(): void
    {
        // the translation and the slices are removed by the foreign keys
        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => self::FAKE_ID])
            ->delete();

        ArticleCache::delete(self::FAKE_ID);
    }

    public function testRegistry(): void
    {
        self::assertSame(TestModule1::class, Module::getAll()['testmodule1'] ?? null);
        self::assertSame(TestModule1::class, Module::getClass('testmodule1'));
        self::assertNull(Module::getClass('unknown'));
        self::assertTrue(Module::exists('testmodule1'));
        self::assertFalse(Module::exists('unknown'));

        self::assertSame('testmodule1', TestModule1::key());
        self::assertSame('Test Module 1', TestModule1::name());
        self::assertSame(I18n::rawMsg('module'), TestNoFieldsModule::name());
    }

    public function testKeyOfUnregisteredClass(): void
    {
        $this->expectException(LogicException::class);

        Module::key();
    }

    public function testOf(): void
    {
        $slice = $this->createSlice('testmodule1');

        $module = Module::of($slice);
        self::assertInstanceOf(TestModule1::class, $module);
        self::assertSame($slice, $module->slice);

        self::assertSame($slice, TestModule1::of($slice)->slice);
    }

    public function testOfSharesTheInstancePerSlice(): void
    {
        $slice = $this->createSlice('testmodule1');

        self::assertSame(Module::of($slice), TestModule1::of($slice));
        self::assertNotSame(Module::of($slice), Module::of($this->createSlice('testmodule1')));
    }

    public function testOfSliceOfAnotherModule(): void
    {
        $this->expectException(LogicException::class);

        TestFieldsModule::of($this->createSlice('testmodule1'));
    }

    public function testOfUnknownModule(): void
    {
        $this->expectException(LogicException::class);

        Module::of($this->createSlice('unknown'));
    }

    public function testHydratesTheStoredValues(): void
    {
        $module = TestFieldsModule::of($this->createSlice('test_fields', ['headline' => 'Hello', 'item_count' => 3, 'hl' => true, 'unknown' => 'foo']));

        self::assertSame('Hello', $module->headline);
        self::assertSame(3, $module->itemCount);
        self::assertTrue($module->highlight);
    }

    public function testStoredValuesNotFittingKeepTheDefault(): void
    {
        $module = TestFieldsModule::of($this->createSlice('test_fields', ['headline' => 'Hello', 'item_count' => 'many']));

        self::assertSame('Hello', $module->headline);
        self::assertNull($module->itemCount);
        self::assertFalse($module->highlight);
    }

    public function testEncodeData(): void
    {
        $module = TestFieldsModule::of($this->createSlice('test_fields'));
        $module->headline = 'Grüße/1';
        $module->itemCount = 2;

        self::assertSame('{"headline":"Grüße/1","item_count":2,"hl":false,"image":null,"gallery":[],"link":null,"items":[]}', $module->encodeData());
        self::assertNull(TestNoFieldsModule::of($this->createSlice('test_no_fields'))->encodeData());
    }

    /**
     * @param list<string> $expectedFieldErrors
     * @param list<string> $expectedFormErrors
     * @param array<string, string> $submitted
     */
    #[DataProvider('provideSubmitForm')]
    public function testSubmitForm(bool $expected, array $expectedFieldErrors, array $expectedFormErrors, array $submitted): void
    {
        $module = TestFieldsModule::of($this->createSlice('test_fields', ['headline' => 'Old']));
        $form = $module->createForm();

        $_POST['slice'] = $submitted;
        try {
            self::assertSame($expected, $module->submitForm($form));
        } finally {
            unset($_POST['slice']);
        }

        self::assertSame($expectedFieldErrors, array_keys($form->getFieldErrors()));
        self::assertSame($expectedFormErrors, $form->getFormErrors());
        if ([] === $expectedFieldErrors) {
            self::assertSame($submitted['headline'], $module->headline);
        } else {
            self::assertSame('Old', $module->headline);
        }
    }

    /** @return iterable<string, array{bool, list<string>, list<string>, array<string, string>}> */
    public static function provideSubmitForm(): iterable
    {
        yield 'valid' => [true, [], [], ['headline' => 'Hello', 'itemCount' => '3', 'highlight' => '1']];
        yield 'required field missing' => [false, ['headline'], [], ['headline' => '', 'itemCount' => '3']];
        yield 'rejected by validate()' => [false, [], ['Rejected'], ['headline' => 'reject']];
    }

    public function testOutputFromArticleCache(): void
    {
        $this->createSlice('testmodule1');
        $this->createSlice('test_fields', ['headline' => 'Hello', 'item_count' => 3]);
        $this->createSlice('unknown');

        ContentHandler::generateArticleContent(self::FAKE_ID, 1);

        self::assertSame('outputHello|3|', new ArticleContent(self::FAKE_ID, 1)->renderContent());
    }

    /** @param array<string, mixed> $values */
    private function createSlice(string $moduleKey, array $values = []): ArticleSlice
    {
        return ContentHandler::addSlice(self::FAKE_ID, 1, 1, $moduleKey, $values);
    }
}
