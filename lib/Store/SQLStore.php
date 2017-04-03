<?php

/**
 * SQL Attributes Collector
 *
 * This class implements a collector that retrieves attributes from a database.
 * It shoud word against both MySQL and PostgreSQL
 *
 * It has the following options:
 * - dsn: The DSN which should be used to connect to the database server. Check the various
 *		  database drivers in http://php.net/manual/en/pdo.drivers.php for a description of
 *		  the various DSN formats.
 * - username: The username which should be used when connecting to the database server.
 * - password: The password which should be used when connecting to the database server.
 * - query: The sql query for retrieve attributes. You can use the special :uidfield string
 *			to refer the value of the field especified as an uidfield in the processor.
 *
 *
 * Example - with PostgreSQL database:
 * <code>
 * 'collector' => array(
 *		 'class' => 'accesslogger:SQLStore',
 *		 'dsn' => 'pgsql:host=localhost;dbname=simplesaml',
 *		 'username' => 'simplesaml',
 *		 'password' => 'secretpassword',
 *		 'query' => 'select address, phone, country from extraattributes where uid=:uidfield',
 *		 ),
 *	   ),
 *	 ),
 * </code>
 *
 * SQLCollector allows to specify several database connections which will
 * be used sequentially when a connection fails. This can be done
 * by defining each parameter by using an array.
 *
 * Example:
 *  'collector' => array(
 *          'class' => 'accesslogger:SQLStore',
 *          'dsn' => array('oci:dbname=first',
 *                  'mysql:host=localhost;dbname=second'),
 *          'username' => array('first', 'second'),
 *          'password' => array('first', 'second'),
 *          'table'      =>
 *          'mapping'    => array("field_1" => "mail", "filed_2" => "eduperson);
 *          'uid'        => array(edupersonprincipalname)
 *                  ),
 *          ),
 *  ),
 */

class sspmod_accesslog_Store_SQLStore extends sspmod_accesslog_SimpleStore {
/*	 * DSN for the database.
	 */
	private $dsn;


	/**
	 * Username for the database.
	 */
	private $username;


	/**
	 * Password for the database;
	 */
	private $password;


	/**
	 * Query for retrieving attributes
	 */
	private $table;

	/**
	 * Query for retrieving attributes
	 */
	private $mapping;

	/**
	 * Database handle.
	 *
	 * This variable can't be serialized.
	 */
	private $db;


	/**
	 * Attribute name case.
	 *
	 * This is optional and by default is "natural"
	 */
	private $attrcase;


	/* Initialize this collector.
	 *
	 * @param array $config  Configuration information about this collector.
	 */
	public function __construct($config) {
		$this->total = /*0*/ 1;
		$this->current = /*0*/ 1;

		foreach (array('dsn', 'username', 'password', 'table', 'mapping') as $id) {
			if (!array_key_exists($id, $config)) {
				throw new Exception('accesslogger:SQLStore - Missing required option \'' . $id . '\'.');
			}
/*
			if (is_array($config[$id])) {

				// Check array size
				if ($this->total == 0) {
					$this->total = count($config[$id]);
				} elseif (count($config[$id]) != $this->total) {
					throw new Exception('accesslogger:SQLStore - \'' . $id . '\' size != ' . $this->total);
				}

			} elseif (is_string($config[$id])) {
				// TODO: allow single values
				// when using arrays on previous fields?
				if ($this->total > 1) {
					throw new Exception('accesslogger:SQLStore - \'' . $id . '\' is supposed to be an array.');
				}

				$config[$id] = array($config[$id]);
				$this->total = 1;
			} else {
				throw new Exception('accesslogger:SQLStore - \'' . $id . '\' is supposed to be a string or array.');
			}*/
		}

		$this->dsn = $config['dsn'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->table = $config['table'];
		$this->mapping = $config['mapping'];

		$this->sql = "insert into " . $this->table . "  ( " . implode (",",array_keys($this->mapping)). ")";
		$this->sql .= " values (:".  implode (",:",array_values($this->mapping)) .")";

		$case_options = array ("lower" => PDO::CASE_LOWER,
			"natural" => PDO::CASE_NATURAL,
			"upper" => PDO::CASE_UPPER);
		// Default is 'natural'
		$this->attrcase = $case_options["natural"];
		if (array_key_exists("attrcase", $config)) {
			$attrcase = $config["attrcase"];
			if (in_array($attrcase, array_keys($case_options))) {
				$this->attrcase = $case_options[$attrcase];
			} else {
				throw new Exception("accesslogger:SQLStore - Wrong case value: '" . $attrcase . "'");
			}
		}
	}

	/* Get collected attributes
	 *
	 * @param array $originalAttributes      Original attributes existing before this collector has been called
	 * @param string $uidfield      Name of the field used as uid
	 * @return array  Attributes collected
	 */
	public function storeAttributes($attributes) {
		//assert('array_key_exists($uidfield, $originalAttributes)');
		$db = $this->getDB();

		$st = $db->prepare($this->sql);
		if (FALSE === $st) {
			$err = $st->errorInfo();
			$err_msg = 'accesslogger:SQLStore - invalid query' . $this->sql;
			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			throw new SimpleSAML_Error_Exception('accesslogger:SQLStore - invalid query: '.$err[2]);
		}

		foreach ($attributes as $key => $value)
		{
			if ($st->bindValue(":".$key, $value, PDO::PARAM_STR) == FALSE)
				throw new SimpleSAML_Error_Exception($this->sql . " .. ".print_r($attributes,true));

		}

		//throw new SimpleSAML_Error_Exception($this->sql . " .. ".print_r($attributes,true));

		//TODO dar la posibilidad de sólo almacenar los último n registros??
		$res = $st->execute();

		if (FALSE === $res){
			$err = $st->errorInfo();
			$err_msg = 'accesslogger:SQLStore - invalid query execution' . $this->sql . "-->" . print_r($attributes,true);

			if (isset($err[2])) {
				$err_msg .= ': '.$err[2];
			}
			else if (isset($err[0])) {
				$err_msg .= ': SQLSTATE['.$err[0].']';
			}
			throw new SimpleSAML_Error_Exception($err_msg);
		}
	}


	/**
	 * Get database handle.
	 *
	 * @return PDO|FALSE  Database handle, or FALSE if we fail to connect.
	 */
	public function getDB() {
		if ($this->db !== NULL) {
			return $this->db;
		}

		try {
				$this->db = new PDO($this->dsn, $this->username, $this->password);
		} catch (PDOException $e) {
			SimpleSAML_Logger::error('accesslogger:SQLStore - skipping ' . $this->dsn . ': ' . $e->getMessage());
				// Error connecting to i-th database
		}
		if ($this->db == NULL) {
			throw new SimpleSAML_Error_Exception('accesslogger:SQLStore - cannot connect to any database');
		}
		$this->db->setAttribute(PDO::ATTR_CASE, $this->attrcase);
		return $this->db;
	}
}

?>
