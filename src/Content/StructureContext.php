<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Language\Language;

use function count;

/**
 * @internal
 */
final readonly class StructureContext
{
    public int $categoryId;
    public int $articleId;
    public int $clangId;

    public function __construct(
        int $categoryId,
        int $articleId,
        int $clangId,
        public int $ctypeId = 0,
        public int $artStart = 0,
        public int $catStart = 0,
        public int $editId = 0,
        public string $function = '',
        public int $rowsPerPage = 30,
    ) {
        if (!Category::get($categoryId)) {
            $categoryId = 0;
        }
        // Only one mountpoint -> jump to category
        $mountpoints = $this->getMountpoints();
        if (1 == count($mountpoints) && 0 == $categoryId) {
            $categoryId = (int) current($mountpoints);
        }
        $this->categoryId = $categoryId;

        if (!Article::get($articleId)) {
            $articleId = 0;
        }
        $this->articleId = $articleId;

        if (Language::count() > 1 && !Core::requireUser()->getComplexPerm('clang')->hasPerm($clangId)) {
            $clangId = 0;
            foreach (Language::getAllIds() as $key) {
                if (Core::requireUser()->getComplexPerm('clang')->hasPerm($key)) {
                    $clangId = $key;
                    break;
                }
            }
        } elseif (!$clangId) {
            $clangId = Language::getStartId();
        }
        $this->clangId = $clangId;
    }

    /** @return list<int> */
    public function getMountpoints(): array
    {
        return Core::requireUser()->getComplexPerm('structure')->getMountpoints();
    }

    public function hasCategoryPermission(): bool
    {
        return Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($this->categoryId);
    }

    public function getContext(): Context
    {
        return new Context([
            'page' => 'structure',
            'category_id' => $this->categoryId,
            'article_id' => $this->articleId,
            'clang' => $this->clangId,
        ]);
    }
}
