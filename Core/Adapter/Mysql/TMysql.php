<?php 

namespace Sharding\Core\Adapter\Mysql;

use Core\Logger;

trait TMysql
{
	
	public function getDriver()
	{
		return 'mysql';
	}
	
	public function connect()
	{
		try {
			$this -> connection = new \PDO('mysql:host=' . $this -> host . ';port=' . $this -> port . ';dbname=' . $this -> database . ';charset=utf8', $this -> user, $this -> password, array(\PDO::ATTR_PERSISTENT => true));
			$this -> connection -> setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this -> connection -> exec('SET NAMES utf8');
		} catch(\PDOException $e) {
			Logger::log($e -> getMessage());
			$this -> errors = $e -> getMessage();
		}
		
		return $this;
	}

	
	public function disconnect()
	{
		$this -> connection = null;
	}
	
	
	public function tableExists($tblName)
	{
		$query = $this -> connection -> prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = :databse 
													AND table_name = :tblName');
		$query -> execute([':database' => $this -> database, 
						   ':tblName' =>  $tblName]);									
		$tblExists = $query -> fetch(\PDO::FETCH_OBJ);
		
		return $tblExists;
	}
	
	
	public function getTableScheme()
	{
		$structure = false;
		
		if ($this -> queryTable) {
			$query = $this -> connection -> prepare('SHOW CREATE TABLE :queryTable');
		
			try {
				$query -> execute([':queryTable' => $this -> queryTable]);
				$scheme = $query -> fetchAll(\PDO::FETCH_ASSOC);
			} catch (\PDOException $e) {
				Logger::log($e -> getMessage());
				$this -> errors = $e -> getMessage();
			}
		} 

		return $scheme;
	}
	
	
	public function getTableStructure()
	{
		$fields = [];
	
		if ($this -> queryTable) {
			$query = $this -> connection -> prepare('DESCRIBE :queryTable');

			try {
				$query -> execute([':queryTable' => $this -> queryTable]);
			} catch (\PDOException $e) {
				$this -> errors = $e -> getMessage();
			}
		
			if ($structure = $query -> fetchAll(\PDO::FETCH_ASSOC)) {
				foreach ($structure as $key => $meta) {
					if (strpos($meta['Type'], 'int') !== false || strpos($meta['Type'], 'decimal') !== false || strpos($meta['Type'], 'timestamp') !== false) {
						$fields[$meta['Field']]['type'] = 'int';
					} else {
						$fields[$meta['Field']]['type'] = 'string';
					}
					
					if (strtolower($meta['Null']) == 'yes') {
						$fields[$meta['Field']]['isnull'] = true;
					} else {
						$fields[$meta['Field']]['isnull'] = false;
					}
				}
			} 
		}
	
		return $fields;
	}
	
	
	
	public function setTable($table)
	{
		if (!is_null($table)) {
			$this -> queryTable = $table;
		} else {
			throw new \Exception('Table can\'t be NULL');
		}
		
		return $this;
	}
	
	
	public function setFetchClass($class)
	{
		$this -> fetchClass = $class;
		return $this;
	}
	
	
	public function addCondition($condition)
	{
		$this -> conditions[] = $condition;
		return $this; 
	}
	
	
	public function addField($field)
	{
		$this -> fields[] = $field;
	}
	 
	
	public function setLimit($limit, $offset = false)
	{
		$this -> offset = $offset;
		$this -> limit = $limit;
		
		return $this;
	}

	
	public function getRowsCount()
	{
		$query = $this -> connection -> prepare('SELECT COUNT(*) FROM :queryTable');
		$query -> execute([':queryTable' => $this -> queryTable]);
		$result = $query -> fetchColumn();
		
		return $result;
	}
	
	
	public function fetchOne()
	{
		$this -> composeQuery();
		$fetch = $this -> connection -> query($this -> queryExpr);
 		
		if ($fetch -> rowCount() == 0) {
			$result = false;
		} else {
			if ($this -> fetchClass) {
				$fetch -> setFetchMode(\PDO::FETCH_CLASS, $this -> fetchClass);
				$result = $fetch -> fetch();
			} elseif ($this -> fetchFormat == 'OBJECT') {
				$result = $fetch -> fetch(\PDO::FETCH_OBJ);
			} else {
				$result = $fetch -> fetch(\PDO::FETCH_ASSOC);
			}
		}
		
		$this -> clearQuery();

		return $result;
	}
	
	
	public function fetch()
	{
		$this -> composeQuery();
		
		$fetch = $this -> connection -> query($this -> queryExpr);

		if ($this -> fetchClass) {
			$fetch -> setFetchMode(\PDO::FETCH_CLASS, $this -> fetchClass);
			$result = $fetch -> fetchAll();
		} else {
			$result = $fetch -> fetchAll();
		}
		$this -> clearQuery();
		
		return $result;
	}
	
	public function fetchAffected()
	{
		$this -> composeCountQuery();
		$result = $this -> connection -> query($this -> queryExpr) -> fetchColumn();

		return $result;
	}
	
	
	public function getLastId()
	{
		$primaryKey = $this -> getPrimaryKey();
		$this -> queryExpr = 'SELECT ' . $primaryKey . ' FROM ' . $this -> queryTable
								. ' ORDER BY (' . $primaryKey . '+0) DESC LIMIT 1';
		$lastId = $this -> connection -> query($this -> queryExpr) -> fetch(\PDO::FETCH_LAZY);
		if ($lastId) {
			return ['key' => $primaryKey, 'lastId' => $lastId[$primaryKey]];
		} else {
			return ['key' => $primaryKey, 'lastId' => false];
		}
		
		$this -> clearQuery();
	}
	
	
	public function getPrimaryKey()
	{
		$this -> queryExpr = 'SHOW KEYS FROM ' . $this -> queryTable . ' WHERE Key_name = "PRIMARY"';
		$keys = $this -> connection -> query($this -> queryExpr) -> fetch(\PDO::FETCH_LAZY);

		return $keys -> Column_name;					
	}
	

	public function execute($query)
	{
		try {
			$result = $this -> connection -> query($query);
			return $result;			
		} catch(\Exception $e) {
			throw new \Exception('Unable to create mapping table');
		}
	}

	
	private function clearQuery()
	{
		$this -> queryTable = false;
		$this -> limit = false;
		$this -> offset = false;
		$this -> fields = [];
		$this -> conditions = [];
		$this -> queryExpr = '';
		$this -> fetchClass = false;
		
		return;
	}
	
	
	private function processFields()
	{
		if (!empty($this -> fields)) {
			$this -> processFields();
			foreach ($this -> fields as $index => $field) {
				$this -> queryExpr .= $this -> queryTable . '.' . $field . ',';
			}
			$this -> queryExpr = substr($this -> queryExpr, 0, strlen($this -> queryExpr) - 1);
		} else {
			$this -> queryExpr .= '*';
		}
		
		return;
	}
	
	
	private function processConditions()
	{
		if (!empty($this -> conditions)) {
			$this -> queryExpr .= ' WHERE ';
			$conds = count($this -> conditions);
				
			for ($i = 0; $i < $conds; $i++) {
				$this -> queryExpr .= $this -> conditions[$i] . ' ';
				if ($i < $conds - 1) {
					$this -> queryExpr .= 'AND ';
				}
			}
		}
		
		return;
	}
	
	
	private function processLimits()
	{
		if ($this -> offset && $this -> limit) {
			$this -> queryExpr .= ' LIMIT ' . (int)$this -> offset . ',' . (int)$this -> limit;
		} elseif ($this -> limit) {
			$this -> queryExpr .= ' LIMIT ' . (int)$this -> limit;
		}	
	}
	
	
	private function composeQuery()
	{
		$this -> queryExpr = 'SELECT ';
		$this -> processFields();
		$this -> queryExpr .= ' FROM ' . $this -> queryTable;
		$this -> processConditions();
		$this -> processLimits(); 
	}
	
	
	private function composeCountQuery()
	{
		$this -> queryExpr = 'SELECT count(*) FROM ' . $this -> queryTable;
		$this -> processConditions();
	}
}