<?php
namespace In2code\In2publishCore\Record\Query;

class BulkSelectQuery
{
    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var array
     */
    protected $identifiers = [];

    /**
     * BulkSelectQuery constructor.
     *
     * @param string $table
     * @param array $identifiers
     */
    public function __construct($table, array $identifiers)
    {
        $this->table = $table;
        $this->identifiers = $identifiers;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
}
