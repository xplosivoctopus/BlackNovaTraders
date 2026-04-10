<?php
// Minimal ADOdb compatibility layer backed by PDO for BlackNova Traders.

if (!defined('ADODB_FETCH_ASSOC')) {
    define('ADODB_FETCH_ASSOC', 2);
}

if (!defined('ADODB_FETCH_NUM')) {
    define('ADODB_FETCH_NUM', 1);
}

if (!defined('ADODB_FETCH_BOTH')) {
    define('ADODB_FETCH_BOTH', 3);
}

class ADORecordSet
{
    public $fields = false;
    public $EOF = true;
    public $_numOfRows = 0;
    public $_currentRow = 0;
    public $_queryID = null;

    private array $rows = [];

    public function __construct(array $rows = [], ?PDOStatement $statement = null)
    {
        $this->rows = array_values($rows);
        $this->_numOfRows = count($this->rows);
        $this->_queryID = $statement;
        $this->syncRow();
    }

    public function RecordCount(): int
    {
        return $this->_numOfRows;
    }

    public function MoveNext(): bool
    {
        $this->_currentRow++;
        $this->syncRow();

        return !$this->EOF;
    }

    public function FetchRow()
    {
        if ($this->EOF) {
            return false;
        }

        $row = $this->fields;
        $this->MoveNext();

        return $row;
    }

    public function Fields($column = null)
    {
        if ($column === null) {
            return $this->fields;
        }

        return $this->fields[$column] ?? null;
    }

    public function Close(): void
    {
        $this->rows = [];
        $this->fields = false;
        $this->EOF = true;
        $this->_numOfRows = 0;
        $this->_currentRow = 0;
        $this->_queryID = null;
    }

    private function syncRow(): void
    {
        if (isset($this->rows[$this->_currentRow])) {
            $this->fields = $this->rows[$this->_currentRow];
            $this->EOF = false;
        } else {
            $this->fields = false;
            $this->EOF = true;
        }
    }
}

class ADOConnection
{
    public $databaseType = 'pdo_mysql';
    public $prefix = '';
    public $logging = false;
    public $debug = false;
    public $_connectionID = null;
    public $_errorMsg = '';
    public $_errorCode = 0;
    public $fetchMode = ADODB_FETCH_ASSOC;

    private bool $persistent = false;
    private string $host = '';
    private string $username = '';
    private string $password = '';
    private string $database = '';

    public function __construct(string $driver = 'pdo_mysql')
    {
        $this->databaseType = $driver;
    }

    public function Connect($host, $user = '', $password = '', $database = ''): bool
    {
        $this->persistent = false;

        return $this->doConnect((string)$host, (string)$user, (string)$password, (string)$database);
    }

    public function PConnect($host, $user = '', $password = '', $database = ''): bool
    {
        $this->persistent = true;

        return $this->doConnect((string)$host, (string)$user, (string)$password, (string)$database);
    }

    public function IsConnected(): bool
    {
        return $this->_connectionID instanceof PDO;
    }

    public function Close(): void
    {
        $this->_connectionID = null;
    }

    public function Execute($sql, $inputarr = false)
    {
        $this->resetError();

        try {
            $params = is_array($inputarr) ? array_values($inputarr) : [];

            if ($params) {
                $stmt = $this->_connectionID->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $this->_connectionID->query($sql);
            }

            if ($stmt instanceof PDOStatement && $stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll($this->pdoFetchMode());
                return new ADORecordSet($rows, $stmt);
            }

            return true;
        } catch (Throwable $e) {
            $this->captureError($e);
            return false;
        }
    }

    public function CacheExecute($secs2cache, $sql = false, $inputarr = false)
    {
        return $this->Execute($sql, $inputarr);
    }

    public function GetOne($sql, $inputarr = false)
    {
        $result = $this->Execute($sql, $inputarr);
        if (!$result instanceof ADORecordSet || $result->EOF) {
            return false;
        }

        $row = $result->fields;
        if (!is_array($row) || !$row) {
            return false;
        }

        return reset($row);
    }

    public function GetRow($sql, $inputarr = false)
    {
        $result = $this->Execute($sql, $inputarr);
        if (!$result instanceof ADORecordSet || $result->EOF) {
            return false;
        }

        return $result->fields;
    }

    public function GetArray($sql, $inputarr = false): array
    {
        $result = $this->Execute($sql, $inputarr);
        if (!$result instanceof ADORecordSet) {
            return [];
        }

        $rows = [];
        while (!$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }

        return $rows;
    }

    public function SelectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs2cache = 0)
    {
        $limitSql = rtrim($sql);

        if ($nrows >= 0) {
            $limitSql .= ' LIMIT ' . (int)$nrows;
            if ($offset >= 0) {
                $limitSql .= ' OFFSET ' . (int)$offset;
            }
        } elseif ($offset >= 0) {
            $limitSql .= ' LIMIT 18446744073709551615 OFFSET ' . (int)$offset;
        }

        return $this->Execute($limitSql, $inputarr);
    }

    public function BeginTrans(): bool
    {
        $this->resetError();

        try {
            return $this->_connectionID->beginTransaction();
        } catch (Throwable $e) {
            $this->captureError($e);
            return false;
        }
    }

    public function CommitTrans($ok = true): bool
    {
        if (!$ok) {
            return $this->RollbackTrans();
        }

        $this->resetError();

        try {
            if ($this->_connectionID->inTransaction()) {
                return $this->_connectionID->commit();
            }

            return true;
        } catch (Throwable $e) {
            $this->captureError($e);
            return false;
        }
    }

    public function RollbackTrans(): bool
    {
        $this->resetError();

        try {
            if ($this->_connectionID->inTransaction()) {
                return $this->_connectionID->rollBack();
            }

            return true;
        } catch (Throwable $e) {
            $this->captureError($e);
            return false;
        }
    }

    public function Insert_ID()
    {
        $this->resetError();

        try {
            return $this->_connectionID->lastInsertId();
        } catch (Throwable $e) {
            $this->captureError($e);
            return false;
        }
    }

    public function SetFetchMode($mode)
    {
        $previous = $this->fetchMode;
        if (in_array($mode, [ADODB_FETCH_ASSOC, ADODB_FETCH_NUM, ADODB_FETCH_BOTH], true)) {
            $this->fetchMode = $mode;
        }

        return $previous;
    }

    public function qstr($value, $magic_quotes = false): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $this->_connectionID->quote((string)$value);
    }

    public function Param($name = null, $type = 'C'): string
    {
        return '?';
    }

    public function LogSQL(): void
    {
        // No-op. The legacy app only toggles this in development mode.
    }

    public function StartTrans(): bool
    {
        return $this->BeginTrans();
    }

    public function CompleteTrans($ok = true): bool
    {
        return $this->CommitTrans($ok);
    }

    public function ServerInfo(): array
    {
        $version = (string)$this->GetOne('SELECT VERSION()');

        return [
            'description' => 'PDO MySQL',
            'version' => $version,
        ];
    }

    public function ErrorMsg(): string
    {
        return $this->_errorMsg;
    }

    public function ErrorNo(): int
    {
        return $this->_errorCode;
    }

    private function doConnect(string $host, string $user, string $password, string $database): bool
    {
        $this->resetError();
        $this->host = $host;
        $this->username = $user;
        $this->password = $password;
        $this->database = $database;

        try {
            [$hostname, $port] = $this->splitHostAndPort($host);
            $dsn = 'mysql:host=' . $hostname;
            if ($port !== null) {
                $dsn .= ';port=' . $port;
            }
            if ($database !== '') {
                $dsn .= ';dbname=' . $database;
            }
            $dsn .= ';charset=utf8mb4';

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => $this->pdoFetchMode(),
                PDO::ATTR_PERSISTENT => $this->persistent,
            ];

            $this->_connectionID = new PDO($dsn, $user, $password, $options);

            return true;
        } catch (Throwable $e) {
            $this->captureError($e);
            $this->_connectionID = null;
            return false;
        }
    }

    private function splitHostAndPort(string $host): array
    {
        if (str_contains($host, ':')) {
            [$hostname, $port] = explode(':', $host, 2);
            return [$hostname, (int)$port];
        }

        return [$host, null];
    }

    private function pdoFetchMode(): int
    {
        return match ($this->fetchMode) {
            ADODB_FETCH_NUM => PDO::FETCH_NUM,
            ADODB_FETCH_BOTH => PDO::FETCH_BOTH,
            default => PDO::FETCH_ASSOC,
        };
    }

    private function resetError(): void
    {
        $this->_errorMsg = '';
        $this->_errorCode = 0;
    }

    private function captureError(Throwable $e): void
    {
        $code = $e->getCode();
        $this->_errorCode = is_numeric($code) ? (int)$code : 1;
        $this->_errorMsg = $e->getMessage();
    }
}

function NewADOConnection($driver = 'pdo_mysql'): ADOConnection
{
    return new ADOConnection((string)$driver);
}

class adodb_perf
{
    public static function table($table = null)
    {
        return $table;
    }
}

class ADODB_Session
{
    public static function encryptionKey($key): void
    {
        // Native PHP sessions are used instead of the legacy ADOdb session layer.
    }

    public static function dataFieldName($name): void
    {
        // Native PHP sessions are used instead of the legacy ADOdb session layer.
    }
}
