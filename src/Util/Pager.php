<?php

namespace Redaxo\Core\Util;

use Redaxo\Core\Http\Request;

/**
 * The Pager-class implements all the logic
 * which is necessary to implement some sort of pagination.
 */
final class Pager
{
    private ?int $rowCount = null;
    private ?int $cursor = null;

    /**
     * @param int $rowsPerPage The number of rows which should be displayed on one page
     * @param string $cursorName The name of the parameter used for pagination
     */
    public function __construct(
        private readonly int $rowsPerPage = 30,
        private readonly string $cursorName = 'start',
    ) {}

    /**
     * Sets the row count.
     */
    public function setRowCount(int $rowCount): void
    {
        $this->rowCount = $rowCount;
    }

    /**
     * Returns the number of rows for pagination.
     *
     * @return int The number of rows
     */
    public function getRowCount(): int
    {
        return $this->rowCount ?? 0;
    }

    /**
     * Returns the number of pages
     * which result from the given number of rows and the rows per page.
     *
     * @return int The number of pages
     */
    public function getPageCount(): int
    {
        return $this->getLastPage() + 1;
    }

    /**
     * Returns the number of rows which will be displayed on a page.
     *
     * @return int The rows displayed on a page
     */
    public function getRowsPerPage(): int
    {
        return $this->rowsPerPage;
    }

    public function setPage(int $page): void
    {
        $this->cursor = $page * $this->rowsPerPage;
    }

    public function setCursor(int $cursor): void
    {
        $this->cursor = $cursor;
    }

    /**
     * Returns the current pagination position.
     *
     * When the parameter pageNo is given, the cursor for the given page is returned.
     * When no parameter is given, the cursor for active page is returned.
     */
    public function getCursor(?int $pageNo = null): int
    {
        if (null !== $pageNo) {
            return $pageNo * $this->rowsPerPage;
        }

        if (null === $this->cursor) {
            $this->cursor = Request::request($this->cursorName, 'int', 0);

            if (null !== $this->rowCount) {
                $this->cursor = $this->validateCursor($this->cursor);
            }
        }

        return $this->cursor;
    }

    /**
     * Validates the cursor.
     */
    public function validateCursor(int $cursor): int
    {
        // $cursor innerhalb des zulässigen Zahlenbereichs?
        if ($cursor < 0) {
            $cursor = 0;
        } elseif ($cursor > $this->rowCount) {
            $cursor = (int) ((int) $this->rowCount / $this->rowsPerPage) * $this->rowsPerPage;
        }

        return $cursor;
    }

    /**
     * Returns the name of the parameter which is used for pagination.
     */
    public function getCursorName(): string
    {
        return $this->cursorName;
    }

    /**
     * Returns the first page for pagination.
     */
    public function getFirstPage(): int
    {
        return 0;
    }

    /**
     * Returns the previous page in respect to the current page.
     */
    public function getPrevPage(): int
    {
        $prevPage = $this->getCurrentPage() - 1;
        if ($prevPage < $this->getFirstPage()) {
            return $this->getFirstPage();
        }
        return $prevPage;
    }

    /**
     * Returns the number of the current page.
     */
    public function getCurrentPage(): int
    {
        $cursor = $this->cursor ?? Request::request($this->cursorName, 'int', null);

        if (null === $cursor) {
            return $this->getFirstPage();
        }

        return (int) ($cursor / $this->rowsPerPage);
    }

    /**
     * Returns the number of the next page in respect to the current page.
     */
    public function getNextPage(): int
    {
        $nextPage = $this->getCurrentPage() + 1;
        if ($nextPage > $this->getLastPage()) {
            return $this->getLastPage();
        }
        return $nextPage;
    }

    /**
     * Return the page number of the last page.
     */
    public function getLastPage(): int
    {
        return (int) (ceil((int) $this->rowCount / $this->rowsPerPage) - 1);
    }

    /**
     * Checks wheter the given page number is the active/current page.
     *
     * @return bool True when the given pageNo is the current page, otherwise False
     */
    public function isActivePage(int $pageNo): bool
    {
        return $pageNo == $this->getCurrentPage();
    }
}
