<?php

declare(strict_types=1);

namespace Doctrine\ORM\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Exec\AbstractSqlExecutor;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use LogicException;

use function sprintf;

/**
 * Encapsulates the resulting components from a DQL query parsing process that
 * can be serialized.
 *
 * @link        http://www.doctrine-project.org
 */
class ParserResult
{
    private const LEGACY_PROPERTY_MAPPING = [
        'sqlExecutor' => '_sqlExecutor',
        'resultSetMapping' => '_resultSetMapping',
        'parameterMappings' => '_parameterMappings',
        'sqlFinalizer' => 'sqlFinalizer',
    ];

    /**
     * The SQL executor used for executing the SQL.
     *
     * @var ?AbstractSqlExecutor
     */
    private $sqlExecutor;

    /**
     * The SQL executor used for executing the SQL.
     *
     * @var ?SqlFinalizer
     */
    private $sqlFinalizer;

    /**
     * The ResultSetMapping that describes how to map the SQL result set.
     *
     * @var ResultSetMapping
     */
    private $resultSetMapping;

    /**
     * The mappings of DQL parameter names/positions to SQL parameter positions.
     *
     * @phpstan-var array<string|int, list<int>>
     */
    private $parameterMappings = [];

    /**
     * Initializes a new instance of the <tt>ParserResult</tt> class.
     * The new instance is initialized with an empty <tt>ResultSetMapping</tt>.
     */
    public function __construct()
    {
        $this->resultSetMapping = new ResultSetMapping();
    }

    /**
     * Gets the ResultSetMapping for the parsed query.
     *
     * @return ResultSetMapping The result set mapping of the parsed query
     */
    public function getResultSetMapping()
    {
        return $this->resultSetMapping;
    }

    /**
     * Sets the ResultSetMapping of the parsed query.
     *
     * @return void
     */
    public function setResultSetMapping(ResultSetMapping $rsm)
    {
        $this->resultSetMapping = $rsm;
    }

    /**
     * Sets the SQL executor that should be used for this ParserResult.
     *
     * @deprecated
     *
     * @param AbstractSqlExecutor $executor
     *
     * @return void
     */
    public function setSqlExecutor($executor)
    {
        $this->sqlExecutor = $executor;
    }

    /**
     * Gets the SQL executor used by this ParserResult.
     *
     * @deprecated
     *
     * @return ?AbstractSqlExecutor
     */
    public function getSqlExecutor()
    {
        return $this->sqlExecutor;
    }

    public function setSqlFinalizer(SqlFinalizer $finalizer): void
    {
        $this->sqlFinalizer = $finalizer;
    }

    public function prepareSqlExecutor(Query $query): AbstractSqlExecutor
    {
        if ($this->sqlFinalizer !== null) {
            return $this->sqlFinalizer->createExecutor($query);
        }

        if ($this->sqlExecutor !== null) {
            return $this->sqlExecutor;
        }

        throw new LogicException('This ParserResult lacks both the SqlFinalizer as well as the (legacy) SqlExecutor');
    }

    /**
     * Adds a DQL to SQL parameter mapping. One DQL parameter name/position can map to
     * several SQL parameter positions.
     *
     * @param string|int $dqlPosition
     * @param int        $sqlPosition
     *
     * @return void
     */
    public function addParameterMapping($dqlPosition, $sqlPosition)
    {
        $this->parameterMappings[$dqlPosition][] = $sqlPosition;
    }

    /**
     * Gets all DQL to SQL parameter mappings.
     *
     * @phpstan-return array<int|string, list<int>> The parameter mappings.
     */
    public function getParameterMappings()
    {
        return $this->parameterMappings;
    }

    /**
     * Gets the SQL parameter positions for a DQL parameter name/position.
     *
     * @param string|int $dqlPosition The name or position of the DQL parameter.
     *
     * @return int[] The positions of the corresponding SQL parameters.
     * @phpstan-return list<int>
     */
    public function getSqlParameterPositions($dqlPosition)
    {
        return $this->parameterMappings[$dqlPosition];
    }

    public function __wakeup(): void
    {
        $this->__unserialize((array) $this);
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        foreach (self::LEGACY_PROPERTY_MAPPING as $property => $legacyProperty) {
            $this->$property = $data[sprintf("\0%s\0%s", self::class, $legacyProperty)]
                ?? $data[self::class][$legacyProperty]
                ?? $data[sprintf("\0%s\0%s", self::class, $property)]
                ?? $data[self::class][$property]
                ?? $this->$property
                ?? null;
        }
    }
}
