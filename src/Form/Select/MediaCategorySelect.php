<?php

namespace Redaxo\Core\Form\Select;

use Redaxo\Core\Core;
use Redaxo\Core\MediaPool\MediaCategory;

use function is_array;

class MediaCategorySelect extends Select
{
    /** @var int|list<int>|null */
    private int|array|null $rootId = null;

    private bool $loaded = false;

    public function __construct(
        private readonly bool $checkPerms = true,
    ) {
        parent::__construct();
    }

    /**
     * Kategorie-Id oder ein Array von Kategorie-Ids als Wurzelelemente der Select-Box.
     *
     * @param int|list<int>|null $rootId Kategorie-Id oder Array von Kategorie-Ids zur Identifikation der Wurzelelemente
     */
    public function setRootId(int|array|null $rootId): void
    {
        $this->rootId = $rootId;
    }

    /** @return void */
    protected function addCatOptions()
    {
        if (null !== $this->rootId) {
            if (is_array($this->rootId)) {
                foreach ($this->rootId as $rootId) {
                    if ($rootCat = MediaCategory::get($rootId)) {
                        $this->addCatOption($rootCat);
                    }
                }
            } else {
                if ($rootCat = MediaCategory::get($this->rootId)) {
                    $this->addCatOption($rootCat);
                }
            }
        } else {
            if ($rootCats = MediaCategory::getRootCategories()) {
                foreach ($rootCats as $rootCat) {
                    $this->addCatOption($rootCat);
                }
            }
        }
    }

    /** @return void */
    protected function addCatOption(MediaCategory $mediacat, int $parentId = 0)
    {
        if (!$this->checkPerms || Core::requireUser()->getComplexPerm('media')->hasCategoryPerm($mediacat->id)
        ) {
            $mid = $mediacat->id;
            $mname = $mediacat->name;

            $this->addOption($mname, $mid, $mid, $parentId);

            $parentId = $mediacat->id;
        }

        foreach ($mediacat->getChildren() as $child) {
            $this->addCatOption($child, $parentId);
        }
    }

    public function get()
    {
        if (!$this->loaded) {
            $this->addCatOptions();
            $this->loaded = true;
        }

        return parent::get();
    }
}
