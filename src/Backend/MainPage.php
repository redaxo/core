<?php

namespace Redaxo\Core\Backend;

class MainPage extends Page
{
    private string $block;
    private int $prio = 0;

    /** @param string $block Navigation block */
    public function __construct(string $block, string $key, string $title)
    {
        $this->block = $block;

        parent::__construct($key, $title);
    }

    /**
     * Sets the navigation block.
     *
     * @return $this
     */
    public function setBlock(string $block): static
    {
        $this->block = $block;

        return $this;
    }

    /** Returns the navigation block. */
    public function getBlock(): string
    {
        return $this->block;
    }

    /**
     * Sets the priority.
     *
     * @return $this
     */
    public function setPrio(int $prio): static
    {
        $this->prio = $prio;

        return $this;
    }

    /** Returns the priority. */
    public function getPrio(): int
    {
        return $this->prio;
    }
}
