<?php

/**
 * Class to represent sql columns.
 *
 * @author gharlan
 *
 * @package redaxo\core\sql
 */
class rex_sql_column
{
    private bool $modified = false;

    public function __construct(
        private string $name,
        private string $type,
        private bool $nullable = false,
        private ?string $default = null,
        private ?string $extra = null,
        private ?string $comment = null,
    ) {}

    /**
     * @param bool $modified
     *
     * @return $this
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /** @return bool */
    public function isModified()
    {
        return $this->modified;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this->setModified(true);
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this->setModified(true);
    }

    /** @return string The column type, including its size, e.g. int(10) or varchar(255) */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param bool $nullable
     *
     * @return $this
     */
    public function setNullable($nullable)
    {
        $this->nullable = $nullable;

        return $this->setModified(true);
    }

    /** @return bool */
    public function isNullable()
    {
        return $this->nullable;
    }

    /**
     * @param string|null $default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this->setModified(true);
    }

    /** @return string|null */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param string|null $extra
     *
     * @return $this
     */
    public function setExtra($extra)
    {
        $this->extra = $extra;

        return $this->setModified(true);
    }

    /** @return string|null */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * @param string|null $comment
     *
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this->setModified(true);
    }

    /** @return string|null */
    public function getComment()
    {
        return $this->comment;
    }

    /** @return bool */
    public function equals(self $column)
    {
        return
            $this->name === $column->name
            && $this->type === $column->type
            && $this->nullable === $column->nullable
            && $this->default === $column->default
            && $this->extra === $column->extra
            && $this->comment === $column->comment;
    }
}
