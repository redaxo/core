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
    private array $params;

    public function __construct(array $params)
    {
        if (!isset($params['category_id']) || !Category::get($params['category_id'])) {
            $params['category_id'] = 0;
        }
        // Only one mountpoint -> jump to category
        $mountpoints = $this->getMountpoints();
        if (1 == count($mountpoints) && 0 == $params['category_id']) {
            $params['category_id'] = current($mountpoints);
        }

        if (!isset($params['article_id']) || !Article::get($params['article_id'])) {
            $params['article_id'] = 0;
        }

        if (!isset($params['clang_id'])) {
            $params['clang_id'] = 0;
        }
        if (Language::count() > 1 && !Core::requireUser()->getComplexPerm('clang')->hasPerm($params['clang_id'])) {
            $params['clang_id'] = 0;
            foreach (Language::getAllIds() as $key) {
                if (Core::requireUser()->getComplexPerm('clang')->hasPerm($key)) {
                    $params['clang_id'] = $key;
                    break;
                }
            }
        } elseif (!$params['clang_id']) {
            $params['clang_id'] = Language::getStartId();
        }

        $this->params = $params;
    }

    public function getCategoryId(): int
    {
        return (int) $this->getValue('category_id', 0);
    }

    public function getArticleId(): int
    {
        return (int) $this->getValue('article_id', 0);
    }

    public function getClangId(): int
    {
        return (int) $this->getValue('clang_id', 0);
    }

    public function getCtypeId(): int
    {
        return (int) $this->getValue('ctype_id', 0);
    }

    public function getArtStart(): int
    {
        return (int) $this->getValue('artstart', 0);
    }

    public function getCatStart(): int
    {
        return (int) $this->getValue('catstart', 0);
    }

    public function getEditId(): int
    {
        return (int) $this->getValue('edit_id', 0);
    }

    public function getFunction(): string
    {
        return (string) $this->getValue('function', '');
    }

    public function getMountpoints(): array
    {
        return Core::requireUser()->getComplexPerm('structure')->getMountpoints();
    }

    public function hasCategoryPermission(): bool
    {
        return Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($this->getCategoryId());
    }

    public function getRowsPerPage(): int
    {
        return (int) $this->getValue('rows_per_page', 30);
    }

    public function getContext(): Context
    {
        return new Context([
            'page' => 'structure',
            'category_id' => $this->getCategoryId(),
            'article_id' => $this->getArticleId(),
            'clang' => $this->getClangId(),
        ]);
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    private function getValue($key, $default)
    {
        return $this->params[$key] ?? $default;
    }
}
