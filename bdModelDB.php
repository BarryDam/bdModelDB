<?php
	/**
	 *	bdModelDB
 	 *	@author 	Barry Dam
 	 *	@copyright  BIC Multimedia 2013 - 2014
 	 *	@version	2.0.1
 	 *	@uses		\LW\DB
 	 *
	 *	Note:
	 *		Since it is an abstract class, you can only extend
	 *
	 *	In your child class set a constant TABLE (string tablename)
	 */
	abstract class bdModelDB 
	{	
	/**
	 * Variables
	 */
		// database table primary key
		public static $strPrimaryKey = 'intID'; // DEFAULT BIC
		/**
		 * All Childs DB entries will be stored as a stdClass in $arrDBRowObjects
		 * And can be called by getDBRowObject()  
		 * @var array
		 */
		protected static $arrDBRowObjects 	= array(); // key = $intDBRowInstanceID value stdClass with dbrows
		protected $intDBRowInstanceID 		= 0; // the current DBRowInstance according to $arrDbRowInstance
		/**
		 * @return stdCls object of database row
		 */
		protected function getDBRowObject() {
			$strClassName = get_class($this);
			if (
				array_key_exists($strClassName, static::$arrDBRowObjects) &&
				array_key_exists($this->intDBRowInstanceID, static::$arrDBRowObjects[$strClassName])
			) return  static::$arrDBRowObjects[$strClassName][$this->intDBRowInstanceID];
		}
		// for debugging:
		public function printInstances($boolAllbdModelDBs=false)
		{
			$arrInstances = ($boolAll) 
				? static::$arrDBRowObjects
				: static::$arrDBRowObjects[get_class($this)];
			echo 'bdModelDB.php Line 22:<br /><pre>'.print_r($arrInstances,true).'</pre>';
		}		

	/**
	 * MAGIC METHODS
	 */
		
		/**
		 * @param mixed $getIntIDorDbRow int ID or array() db row
		 */
		public function __construct($getIntIDorDbRow = false) 
		{
			$strTable = static::getTable();
			// regular construct using primary key id
			if (is_numeric($getIntIDorDbRow)) {
				$this->intDBRowInstanceID = $getIntIDorDbRow;
				// Check if there is allready a $arrDBRowObjects with the same ID
				if ($this->getDBRowObject())
					return;
				$row = \LW\DB::selectOneRow($strTable, static::$strPrimaryKey.' = ?', $this->intDBRowInstanceID) ;				
				if (! $row) 
					throw new Exception('Row with id '.$this->intDBRowInstanceID.' in table '.$strTable.' does not exist.');
			} else if (is_array($getIntIDorDbRow) && count($getIntIDorDbRow)) {
				$row = $getIntIDorDbRow;
				if (empty($row[static::$strPrimaryKey]))
					throw new Exception('No "'.static::$strPrimaryKey.'" key passed in db row for '.get_class($this), 1);					
				else 
					$this->intDBRowInstanceID = $row[static::$strPrimaryKey];
				// Check if there is allready a $arrDBRowObjects with the same ID
				if ($this->getDBRowObject())
					return;
			} else {
				throw new Exception(
					($getIntIDorDbRow) 
						? 'Wrong argument type 1 for '.get_class($this).': '.gettype($getIntIDorDbRow).' passed'
						: 'Missing argument 1 for '.get_class($this) 
				);
			}
			// save to $arrDBRowObjects when not existing
			if (! $this->getDBRowObject()) {
				$obj = new stdClass();
				foreach ($row as $key => $val)
					$obj->$key = $val;
				static::$arrDBRowObjects[get_class($this)][$this->intDBRowInstanceID] = $obj;			
			}
		}
		
		/**
		 * toSting for debugging!
		 */
		public function __toString()
		{
			$class	= get_called_class();
			return $class. ' '.$this->getId();
			/*
			OLD:
			// get the file + linenr
			$strFileLine	= '';
			$arrDebug		= debug_backtrace();
			foreach($arrDebug as $arr)  {
				if ($arr['function'] == '__toString') {
					$strFileLine = ' - '.$arr['file'].' line '.$arr['line'];
					break;
				}
			}
			// parse the arrModelDBdata trough __get();
			$arrData = (array) $this->getDBRowObject();					
			foreach($arrData as $key => $val)
				$arrData[$key] = $this->$key;
			// return
			return '
				<div style="margin:10px;padding:10px;border:solid 5px blue;background-color:#FFF">
					'.get_class($this).$strFileLine.'
					<br /><br />
					<strong>DB values:</strong>
					<pre>'.print_r($arrData, true).'</pre>'.'
					<strong>Object</strong> <br />
					<pre>'.print_r($this,true).'</pre>
				</div>
			';
			*/
		}

		public function __set($getKey, $getValue)
		{
			/// check valid column
			if (! array_key_exists($getKey, (array) $this->getDBRowObject()))
				throw new Exception('Cannot set "'.$getKey.'", not a valid DB column of table '.static::getTable());
			// check if it's not the primary key
			if ($getKey === static::$strPrimaryKey) 
				throw new Exception('Cannot overwrite primary key "'.$getKey.'"');
			// fallback for bdModel < v2
			if ($getKey == 'arrModelDBdata' && is_array($getValue)) {
				static::$arrDBRowObjects[get_class($this)][$this->intDBRowInstanceID] = new stdClass();
				foreach ($getValue as $key => $val)
					static::$arrDBRowObjects[get_class($this)][$this->intDBRowInstanceID]->$key = $val;
			} else {
				// json encode when it is an array
				if (is_array($getValue))
					$getValue = json_encode($getValue);
				// set the val
				$this->getDBRowObject()->$getKey = $getValue ;
			}
		}

		public function __get($getKey)
		{
			// get the DB data
			$arrDBData = (array) $this->getDBRowObject();
			if (! count($arrDBData))
				return false;
			// fallback for bdModel < v2
			if ($getKey == 'arrModelDBdata') 
				return $arrDBData;	
			// check valid column
			if (! array_key_exists($getKey, $arrDBData))
				return false;
			// set the value
			$value = $arrDBData[$getKey];
			// check if it is json encoded
			if (is_string($value)) {
				$arrFromJson = json_decode($value, true);
				if (is_array($arrFromJson))
					$value = $arrFromJson;
			}
			// return value
			return $value;
		}

		public function __isset($getKey)
		{
			return ($this->$getKey) ? true : false;
		}

		public function __unset($getKey)
		{
			$arr = (array) $this->getDBRowObject();
			unset($arr[$getKey]);
			static::$arrDBRowObjects[get_class($this)][$this->intDBRowInstanceID] = new stdClass();
			foreach ($arr as $key => $val)
				static::$arrDBRowObjects[get_class($this)][$this->intDBRowInstanceID]->$key = $val;
		}

		public function __clone()
		{
			// force a copy of $this->object, otherwise it will point to same object
			return $this->duplicate();
		}

	/**
	 * Convenience Functions
	 */
		
		/**
		 * Checks an returns the table name
		 * @return string database table name
		 */
		public static function getTable()
		{
			$strTableName = false;
			if(defined('static::TABLE'))
				$strTableName = static::TABLE;
			// fallback for bdModelDB < v2
			if(method_exists(get_called_class(),'getTableName')) {
				// make sure to override the error message
				$strTableName =  @static::getTableName();				
			}
			// trhow error when not found
			if (! $strTableName)
				throw new Exception('contstant TABLE not set in class '.get_called_class());
			// return table name
			return $strTableName;
		}

		
	/**
	 * DB functions
	 */
		/**
		 * Database functions update
		 * @return (bool) true on success
		 */
		public function update()
		{
			// Get the model data and unset the primary key
			$arrUpdate  = (array) $this->getDBRowObject();
			$intID 		= $arrUpdate[static::$strPrimaryKey];
			unset($arrUpdate[static::$strPrimaryKey]);
			/* update the database */
			return  \LW\DB::update(
				static::getTable(),
				$arrUpdate,
				static::$strPrimaryKey.' = ?',
				$intID
			);
		}

		/**
		 *  Database function delete
		 *  @return (bool) true on success
		 */
		public function delete()
		{
			$arr 	= (array) $this->getDBRowObject();
			$intID 	= $arr[static::$strPrimaryKey];
			// first delete from singletons
			if (isset(static::$arrDBRowObjects[get_class($this)][$this->__get(static::$strPrimaryKey)])) 
				unset(static::$arrDBRowObjects[get_class($this)][$this->__get(static::$strPrimaryKey)]);
			// then delete from db
			return  \LW\DB::delete(
				static::getTable(),
				static::$strPrimaryKey.' = ?',
				$intID
			);
		}

		/**
		 * Duplicates the current model in DB
		 * @return child object of bdModelDB - new db entry
		 */
		public function duplicate()
		{
			// create the new db array
			$arrNewDBEntry[$strColumn] = (array) $this->getDBRowObject();
			unset($arrNewDBEntry[static::$strPrimaryKey]);
			// insert in db
			if (! count($arrNewDBEntry)) 
				return;
			$query = \LW\DB::insert(static::getTable(), $arrNewDBEntry);
			if ($query) {
				$strClass = get_class($this);
				return new $strClass(\LW\DB::getInsertId());
			}				
		}

	/**
	 * Static Convinience functions
	 */		
		/**
		 * Create a new DB row AND return a BDModel object!
		 * @params should be in order of db presence
		 * @return new childobject with the last DB entry
		 * when the first @param is an array, the array will be interpreted as an
		 * associative DB array @example array('column' => 'value', 'column2' => 'value2');
		 * @important If you want to overwrite this function in your childobject:
		 * Make sure to return static::fetchLast();
		 */
		static function insert(){
			// Get args
			$arguments = func_get_args();
			if (! count($arguments)) return;
			// get table name
			$strTableName 	= static::getTable();
			if (! $strTableName) return;
			// get database columns
			$arrColumns 	= \LW\DB::customQuery('SHOW COLUMNS FROM '.$strTableName);
			if(! $arrColumns) return ;
			// create new db entry
			$arrNewDBentry = array();
			// if first argument is an associative array
			if (is_array($arguments[0])) {
				foreach ($arrColumns as $arrColumn) {
					if (
						$arrColumn['Key'] !== 'PRI' && // skip the primary key
						array_key_exists($arrColumn['Field'], $arguments[0])
					) {
						$arrNewDBentry[$arrColumn['Field']] = (is_array($arguments[0][$arrColumn['Field']])) 
							? json_encode($arguments[0][$arrColumn['Field']]) 
							: $arguments[0][$arrColumn['Field']] ;
					}
				}
			} else {
				foreach ($arguments as $key => $val) {
					$intDBKey = $key+1; // first key = allways the primary key
					if (! array_key_exists($intDBKey, $arrColumns))
						continue;
					$arrNewDBentry[$arrColumns[$intDBKey]['Field']] = (is_array($val)) ? json_encode($val) :  $val ;
				}
			}
			// insert in db
			if (! count($arrNewDBentry)) return;
			$query = \LW\DB::insert($strTableName, $arrNewDBentry);
			// return now this object
			if ($query) return new static(\LW\DB::getInsertId());
		}
	

	/**
	 * Static fetchers
	 */
		
		/**
		 *	fetchByPrimaryKey, fetchByID are simular
		 *	@return new object
		 */
		public static function fetchByPrimaryKey($getIntID = false)
		{
			try {
				return new static($getIntID);
			} catch (Exception $e) {
				// do nothing
			}
		}
		public static function fetchByID($getIntID = false)
		{
			return static::fetchByPrimaryKey($getIntID);
		}

		/**
		 * @return childobject first in db
		 * @param $getOrderByColumn the column to order.. when false.. the primarykey will be used
		 */
		public static function fetchFirst($getOrderByColumn = false)
		{
			$strColumn	= ($getOrderByColumn) ? $getOrderByColumn : static::$strPrimaryKey;
			$row 		= \LW\DB::selectOneRow(
				static::getTable(),
				'1=1 ORDER BY '.$strColumn.' ASC'
			);
			if ($row) return new static($row);
		}

		/**
		 * Returns a new child object by the last inserted db entry
		 * duplicate func of fetchLast .. keep this for backwardcompatibi
		 * @return childobject
		 */
		public static function fetchLast($getOrderByColumn = false)
		{
			$strColumn  = ($getOrderByColumn) ? $getOrderByColumn : static::$strPrimaryKey;
			$row 		= \LW\DB::selectOneRow(
				static::getTable(),
				'1=1 ORDER BY '.$strColumn.' DESC'
			);
			if (! $row) 
				return array();
			//
			return new static($row);
		}

		/**
		 * @param  string  $getOrderByColumn   Optional
		 * @param  string  $getOrderDirection  Only used when $getOrderByColumn is set.
		 * @return [type]  array               array with bdModelDB childobjects
		 */
		public static function fetchAll($getOrderByColumn = false, $getOrderDirection = "ASC")
		{
			$rows = ($getOrderByColumn) 
				? \LW\DB::select(static::getTable(), '*', '1=1 ORDER BY '.$getOrderByColumn.' '.$getOrderDirection)
				: \LW\DB::select(static::getTable(), '*');
			return static::getArrObjectsByDbRows($rows);
		}

		/**
		 *	@param (string) $getColumnName
		 */
		public static function fetchByColumn($getColumnName = false, $getValue = false, $getOrderBy = false, $getOrderDirection = "ASC")
		{
			if (! $getColumnName && ! $getValue) 
				throw new Exception('Check your passed params', 1);;
			$rows = ($getOrderBy) 
			 	? \LW\DB::select(static::getTable(),'*',$getColumnName.' = ? ORDER BY '.$getOrderBy.' '.$getOrderDirection.'',$getValue)
			 	: \LW\DB::select(static::getTable(),'*',$getColumnName.' = ?',$getValue);			
			return static::getArrObjectsByDbRows($rows);
		}

		/**
		*	@param (string) mysql WHERE # voorbeeld = 'test' AND id="1"
		**/
		public static function fetchByWhere($getStrWhere = false)
		{
			if (! $getStrWhere) 
				throw new Exception('First param not set', 1);
			$rows = \LW\DB::customQuery('SELECT * FROM '.static::getTable().' WHERE '.$getStrWhere);
			return static::getArrObjectsByDbRows($rows);			
		}
		

		/**
		 * return objects by search
		 * @param  string $getStrSearch  one word or sentence to search 
		 * @param  string  $getOperator default is OR  search operator (OR, AND, EXACT)
		 * @params > 2 are the field to search (caught by func_get_arguments)
		 * @return array with objects
		 */
		public static function fetchBySearch($getStrSearch = false, $getOperator = 'OR')
		{
			// default return value;
			$arrReturn = array();
			// No search passed return
			if (! $getStrSearch)
				return $arrReturn;
			// Check passed the operator
			if (! in_array($getOperator, array('OR', 'AND', 'EXACT')))
				throw new Exception('Unvalid operator "'.$getOperator.'"', 1);
			// Check the arguments for the Search Fields
			$arrAguments = func_get_args();
			if (count($arrAguments) < 3)
				throw new Exception("No fields passed", 1);
			// Build the array with field to search
			$arrFields = array();
			if (is_array(func_get_arg(2))) {
				$arrFields = func_get_arg(2);
			} else {
				foreach ($arrAguments as $k => $v) {
					if ($k >= 2)
						$arrFields[] = $v;
				}
			}
			// Convert the $getStrSearch string to array words
			$arrQueryWords = array_unique(preg_split('/[\ \n\,]+/', $getStrSearch));

			// prepare query
			$arrWhere  = array();
			$arrValues = array();
			foreach($arrFields as $field) {
				$arrWhereIn = array();
				if ($getOperator == 'EXACT') {
					$arrWhere[]  = $field." LIKE ?";
					$arrValues[] = '%'.$getStrSearch.'%';					
				} else { // AND OR
					foreach($arrQueryWords as $strWord) {
						$arrWhereIn[] = $field." LIKE ?";
						$arrValues[]  = '%'.$strWord.'%';
					}
					$arrWhere[] = '('.implode(' '.$getOperator.' ', $arrWhereIn).')';
				}
			}
			// final where clause
			$arrWhere = implode(" OR ", $arrWhere);
			// exec the db call
			$rows = call_user_func_array(
				array('\LW\DB', 'select'), 
				array_merge(
					array(static::TABLE, '*', $arrWhere), 
					$arrValues
				)
			);
			return static::getArrObjectsByDbRows($rows);
		}

		/**
		 * HELPER METHODS
		 */

		/**
		 * This helper method is used in multiple fetch Functions
		 * It creates an array with new Model objects and suppresses the Exception thrown in _construct on error
		 * @param array $getDBRows array with rows from db
		 * @return array $arrObjects array with new ModelObjects generated by @param $getDBRows
		 */
		final protected static function getArrObjectsByDbRows($getDBRows = array()) 
		{
			if (! is_array($getDBRows) || ! count($getDBRows))
				return array();
			$arrObjects	= array();
			foreach ($getDBRows as $row) {
				try {
					$objNew = new static($row);
					if ($objNew) 
						$arrObjects[] = $objNew ;
				} catch (Exception $e) {
					// do nothing
				}				
			}
			if (count($arrObjects)) 
				return $arrObjects;
			else
				return array();
		}

		/**
		 * @return array An array of values by provided $property
		 * @param array $objects An array of objects to extract the values from
		 * @param string $property The methodname or key for the value to extract
		 * @param array $method_arguments Optional arguments to pass to the method if $property is a method
		 */
		final public static function extractValues($objects, $property, $method_arguments = array())
		{
			// check if $objects is an array
			if( !is_array($objects) )
				throw new Exception('first parameter is not an array');

			if(empty($objects))
				return array();

			$objFirst = array_slice($objects, 0,1);
			$objFirst = $objFirst[0];

			// if property is a method ...
			$r = new ReflectionClass( get_class($objFirst) );
			if($r->hasMethod($property))
			{
				// some methods are forbidden (the methods in bdModelDB), so disallow access to those
				$r = new ReflectionClass('bdModelDB');
				$forbidden_methods = array_map(function($method){
					return $method->name;
				}, $r->getMethods());

				if( in_array($property, $forbidden_methods) )
					throw new Exception('forbidden property');

				// the optional arguments to pass to the method on the object
				$method_arguments = array_slice(func_get_args(), 2);
				$callback_arguments = array('property'=>$property, 'args'=> $method_arguments);
				$callback = function($object) use ($callback_arguments) {
					return call_user_func_array( array($object, $callback_arguments['property']), $callback_arguments['args']);
				};

				// call the method on the objects
				$return_values = array_map($callback, $objects);
				return $return_values;
			}

			// if property is not a method (but a key for a mixed value) ...
			// check if property is valid
			$arrDBData = (array) $objFirst->getDBRowObject();
			if (! array_key_exists($property, $arrDBData))
				throw new Exception('property "'.$property.'" does not exists');
			
			// get return values
			$return_values = array();
			foreach($objects as $object)
				$return_values[] = $object->$property;

			return $return_values;
		}
	}
?>