<?php

class Model 
{

	protected ?MySQLiDB $_db = null;

	protected string $_prefix = '';
	protected string $tableName = '';
	protected string $connection = '';
	protected string $table = ''; 

	protected static array $instances = [];

	/**
     * Init database connection (singleton per connection)
     */
	protected function mysqlDriver(mixed $options) 
	{
		if (isset(self::$instances[$this->connection])) {
            return $this->_db = self::$instances[$this->connection];
        }

		$conn = [
			'host' => $options['host'],
            'username' => $options['username'], 
            'password' => $options['password'],
            'db'=> $options['db'],
            'port' => $options['port'] ?? 3306,
            'prefix' => $options['prefix'] ?? '',
            'charset' => $options['charset'] ?? 'utf8mb4'
		];
		
		$db = new MySQLiDB($conn);
		$db->addConnection($this->connection, $conn);
		$db->defConnectionName = $this->connection;
        $db->connection($this->connection);
        $db->connect($this->connection);
        $db->setPrefix($conn['prefix']);
		$db->setTableName($this->table);

		$this->_prefix = $conn['prefix'];

		self::$instances[$this->connection] = $db;

		return $this->_db = $db;
	}

	public function __construct()
	{
		// construct
		$defConnection = config('connection.'.$this->connection);

		if (!$defConnection) {
            throw new \Exception("Connection {$this->connection} not found");
        }

		$this->mysqlDriver($defConnection);
	}

	public function __call(string $name, array $args) 
	{
		if ($this->_db === null) {
			throw new \Exception('Database not initialized');
		}
		if (!method_exists($this->_db, $name)) {
            throw new \BadMethodCallException(
                "Method {$name} not found in " . get_class($this->_db)
            );
        }

		$result = $this->_db->$name(...$args);

        return $result === $this->_db ? $this : $result;
    }

	public static function __callstatic(string $name, array $args)
	{
		$instance = new static();
		if (!method_exists($instance->_db, $name)) {
            throw new \BadMethodCallException(
                "Method {$name} not found in " . get_class($instance->_db)
            );
        }
		$result = $instance->_db->$name(...$args);
		return $result === $instance->_db ? $instance : $result;
	}
}