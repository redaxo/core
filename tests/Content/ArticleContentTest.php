<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;

/** @internal */
final class ArticleContentTest extends TestCase
{
    protected function setUp(): void
    {
        // fake article
        $articleFile = Path::coreCache('structure/1.1.article');
        File::putCache($articleFile, [
            'pid' => 1,
            'id' => 1,
            'parent_id' => 0,
            'name' => 'Testarticle',
            'catname' => 'Testcategory',
            'catpriority' => 1,
            'startarticle' => 1,
            'priority' => 1,
            'path' => '|',
            'status' => 1,
            'template_id' => 1,
            'clang_id' => 1,
            'createdate' => '2020-01-01 12:30:00',
            'createuser' => 'tests',
            'updatedate' => '2020-01-02 13:40:00',
            'updateuser' => 'tests',
            'revision' => 0,

            'art_foo' => 'teststring',
        ]);
    }

    protected function tearDown(): void
    {
        // delete all fake structure cache files
        $finder = Finder::factory(Path::coreCache('structure/'))
            ->recursive()
            ->childFirst()
            ->ignoreSystemStuff(false);
        Dir::deleteIterator($finder);

        Article::clearInstancePool();
    }

    public function testProvidesTheArticle(): void
    {
        $instance = new ArticleContent(1, 1);

        self::assertSame(1, $instance->article->id);
        self::assertSame('Testarticle', $instance->article->name);
        self::assertSame('teststring', $instance->article->getValue('foo'));
    }

    public function testThrowsForMissingArticle(): void
    {
        $this->expectException(ArticleNotFoundException::class);

        new ArticleContent(999, 1);
    }
}
