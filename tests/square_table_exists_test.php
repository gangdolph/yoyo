<?php

define('YOYO_SKIP_DB_BOOTSTRAP', true);
require __DIR__ . '/../includes/square-migrations.php';

class SquareMigrationsTestResult
{
    public int $num_rows;

    public function __construct(int $numRows)
    {
        $this->num_rows = $numRows;
    }

    public function free(): void
    {
        // no-op for test doubles
    }
}

class SquareMigrationsTestMysqli extends mysqli
{
    /** @var string[] */
    private array $existingTables;

    /** @var string[] */
    public array $queries = [];

    /**
     * @param string[] $existingTables
     */
    public function __construct(array $existingTables = [])
    {
        $this->existingTables = $existingTables;
    }

    public function real_escape_string($string): string
    {
        return addslashes($string);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        $this->queries[] = $query;

        if (preg_match("/^SHOW TABLES LIKE '(.+)'/i", $query, $match)) {
            $tableName = stripslashes($match[1]);
            $tableName = str_replace(['\\_', '\\%'], ['_', '%'], $tableName);
            $exists = in_array($tableName, $this->existingTables, true);

            return new SquareMigrationsTestResult($exists ? 1 : 0);
        }

        throw new Exception('Unexpected query: ' . $query);
    }
}

$conn = new SquareMigrationsTestMysqli(['square_orders', 'user_shipping_profiles']);

if (!square_table_exists($conn, 'square_orders')) {
    throw new Exception('square_table_exists should return true for known tables.');
}

if (square_table_exists($conn, 'square_missing')) {
    throw new Exception('square_table_exists should return false for missing tables.');
}

if (count($conn->queries) !== 2) {
    throw new Exception('square_table_exists should only issue a single SHOW TABLES query per invocation.');
}
