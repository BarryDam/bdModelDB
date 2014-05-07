<?php
	/**  
	*	bdModelDB 
	*	@author 	Barry Dam
	*	@copyright  BIC Multimedia 2013 - 2014
	*	@version	1.1.3
	*	@uses		\LW\DB
	*
	*	Note:
	*		Since it is an abstract class, you can only extend
	*	
	*	Examples:
	*	
	*	Extending this class:
	*		class modelExample extends bdModelDB {
	*
	*			public function getTableName(){
	*				return 'table_name';
	*			}
	*
	*			public static function insert(){
	*				// run mysql with ur data then return
	*				
	*			}
	*			
	* 			// __construct is optional! only needed when primary key is not intID
	*			public function __construct(){
	*				$this->setConfigPrimaryKey('intID'); 	#Optional > default = intID
	*			}
	*			
	*			public function __set($getName,$getVal){
	*				if($getName=="something") $dosome;
	*				parent::__set($getName,$getVal);
	*			}
	*
	*			
	*			public function yourNewFunction(){
	*				if($something==$wrong){
	*					self::triggerError('There is something wrong!');
	*				}
	*				
	*			}
	*
	*		}
	*	
	*	Using this class :
	*		- Create one object by calling it's ID
	*			$objExample = modelExample::fetchByID(1); || getByPrimaryKey # returns modelExample object
	*		
	*		- Create multiple objects by get them all from DB
	*			$arrExampleObjects = modelExample::fetchAll(); #returns array(modelExample object,modelExample object,modelExample object,...)
	*		
	*		- Get the vars (equal to db column names)
	*			$objExample->intID 				# inside the class use $this->intID
	*			$objExample->strName 
	*			$objExample->strSomeColumnName
	*	
	*		- (re) Set the vars 
	*			$objExample->strName = 'Some new name'
	*			
	*		- Update the database
	*			$objExample->update(); # return (boolean)
	*
	*		- getter functions
	*			$objExample->getTableName(); #the db table name
	*			$objExample->getConfigPrimaryKey();
	*
	*
	**/
	abstract class bdModelDB {
		/* data from db */
			public 	$arrModelDBdata			= array(); # ONLY contains the DB row

		/* Config working data */
			private	$arrModelDBColumns		= array(), # will be set in parseData and used in __set
					$arrModelDBCalledFN		= array(), # array to check if a function is allready called
					$arrModelDBSettings 	= array(
						'strTableName'			=> false,
						'strColumnPrimaryKey' 	=> 'intID'  #default BIC //TODO SHOW KEYS FROM tblPAG__Textbins WHERE Key_name = 'PRIMARY'
						)
					;

		/* Singletons for performance */
			public static $arrSingletons = array(); // key = primarykey , value = object		 
					
		/* abstracts */

			/**
			* 	@return (string) tablename
			*	make sure that this function can called static and non static
			**/
			abstract function getTableName();

			/**
			 * Create a new DB row!
			 * IMPORTANT!!! Make sure to return self::fetchByPrimaryKey(INSERT_ID);
			 * @return (object) this
			 */
			abstract static function insert();
			

		/* Magic methods */
			public function __construct(){}

			/**
			 *	@param (string) $getName can only be a column name! 
			 *  the primary key can not be set
			 */
			public function __set($getName, $getValue)
			{
				if (! in_array($getName, $this->arrModelDBColumns)) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Cannot set '.$getName.', not a valid DB column in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line']
					);
					return false;
				}
				if ($getName === $this->arrModelDBSettings['strColumnPrimaryKey']) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Cannot overwrite '.$getName.', used as primary key in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line']
					);
					return false;
				}
				$this->arrModelDBdata[$getName] = $getValue ;
			}

			public function __get($getName)
			{
				if (array_key_exists($getName, $this->arrModelDBdata)) 
					return $this->arrModelDBdata[$getName];
			}

			public function __isset($getName)
			{
				return isset($this->arrModelDBdata[$getName]);
			}

		/* Config setters */
			
			/**
			*	Set the Primary key name of the DB
			*	This function can only be called once, second time a fatal error will occur
			**/
			final public function setConfigPrimaryKey($getStrName = false)
			{
				if (! $getStrName || ! is_string($getStrName) ) return false ;
				/* Check if function allready called */
					if (in_array(__FUNCTION__,$this->arrModelDBCalledFN)) {
						$arrBacktrace = debug_backtrace();
						self::triggerError(
							'Function '.__FUNCTION__.' allready called in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line'],
							true
						);
						return false;
					}
					$this->arrModelDBCalledFN[] = __FUNCTION__;
				/* Set the db Table */
					$this->arrModelDBSettings['strColumnPrimaryKey'] = $getStrName ;
			}

		/* Config getters */
			public function getConfigPrimaryKey()
			{
				return $this->arrModelDBSettings['strColumnPrimaryKey'];
			}

		/* DB Functions */
			
			/**
			 *	Update by POST data
			 *	@param (bool) $boolSaveDB default is TRUE
			 *  When set to true the DB row will be updated with post data immediately.
			 * 	When set to false, you will have to tigger a $this->update() manually to update the DB
			 */
			public function updateByPostData($boolSaveDB = true)
			{
				if (isset($_POST)) {
					foreach ($_POST as $key => $val) {
						/* Filter out the primary key else an error will be thrown by magic method __set*/
						if (in_array($key, $this->arrModelDBColumns) && $key != $this->getConfigPrimaryKey()) {
							$this->$key = $val;
						}
					}
					if ($boolSaveDB) $this->update();
					return true ;
				}
			}


			/**
			 * Database functions update
			 * @return (bool) true on success
			 */
			public function update()
			{
				/* Get the model data and unset the primary key */
				$arrUpdate  = $this->arrModelDBdata;
				unset($arrUpdate[$this->getConfigPrimaryKey()]);
				/* update the database */
				return  \LW\DB::update(
					$this->getTableName(), 
					$arrUpdate, 
					$this->getConfigPrimaryKey().' = ?',
					$this->arrModelDBdata[$this->getConfigPrimaryKey()]
				);
			}

			/**
			 *  Database function delete
			 *  @return (bool) true on success
			 */
			public function delete()
			{
				return  \LW\DB::delete(
					$this->getTableName(), 
					$this->getConfigPrimaryKey().' = ?',
					$this->arrModelDBdata[$this->getConfigPrimaryKey()]
				);				
			}

			/**
			 * Duplicates the current model in DB
			 * @return child object of bdModelDB - new db entry
			 */
			public function duplicate()
			{
				// create the new db array
				$arrNewDBEntry = array();
				foreach ($this->arrModelDBColumns as $strColumn)
					if ($strColumn != $this->getConfigPrimaryKey())
						$arrNewDBEntry[$strColumn] = $this->$strColumn;
				// insert in db
				if (! count($arrNewDBEntry)) return;
				$query = \LW\DB::insert($this->getTableName(), $arrNewDBEntry);
				if (! $query) return;
				return static::fetchByPrimaryKey(\LW\DB::getInsertId());
			}

		/* Static create / fetch functions */

			/**
			 *	fetchByPrimaryKey, fetchByintID, fetchByID are simular
			 */
			final public static function fetchByPrimaryKey($getIntID = false)
			{
				if (! $getIntID || ! is_numeric($getIntID)) return null ;
				return self::construct_fromChildObject($getIntID);				
			}
			final public static function fetchByintID($getIntID) 
			{
				return self::fetchByPrimaryKey($getIntID);
			}
			final public static function fetchByID($getIntID) 
			{
				return self::fetchByPrimaryKey($getIntID);
			}

			/**
			 * @param  string  $getOrderByColumn   Optional
			 * @param  string  $getOrderDirection  Only used when $getOrderByColumn is set.
			 * @return [type]  array               array with bdModelDB childobjects
			 */
			final public static function fetchAll($getOrderByColumn = false, $getOrderDirection = "ASC")
			{
				$objChildTemp		= new static();
				$strTableName 		= $objChildTemp->getTableName();
				if (! $strTableName) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Table name is not set!',
						true
					);
					return false ;
				}
				if ($getOrderByColumn) {
					// 1 = 1 (needed)
					$rows = \LW\DB::select($strTableName, '*', '1=1 ORDER BY '.$getOrderByColumn.' '.$getOrderDirection);	
				} else {
					$rows = \LW\DB::select($strTableName, '*');	
				}				
				if (! $rows) return false ;
				$arrObjects	= array();
				foreach ($rows as $row) {
					$objNew = self::construct_fromChildObject($row);
					if ($objNew) $arrObjects[] = $objNew ;
				}
				return $arrObjects;
			}

			/**
			 *	@param (string) $getColumnName
			 */
			final public static function fetchByColumn($getColumnName = false, $getValue = false, $getOrderBy = false, $getOrderDirection = "ASC")
			{
				if (! $getColumnName && ! $getValue) return false ;
				$strCalledClassName = get_called_class();
				$objChildTemp		= new $strCalledClassName();
				$strTableName 		= $objChildTemp->getTableName();
				if (! $strTableName) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Table name is not set! use $this->setDbTableName() in constructor in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line'],
						true
					);
					return false ;
				}
				if ($getOrderBy) {
					$rows = \LW\DB::select($strTableName,'*',$getColumnName.' = ? ORDER BY '.$getOrderBy.' '.$getOrderDirection.'',$getValue);
				} else{
					$rows = \LW\DB::select($strTableName,'*',$getColumnName.' = ?',$getValue);
				}
				if (! $rows) return false ;
				$arrObjects			= array();
				foreach ($rows as $row) {
					$objNew = self::construct_fromChildObject($row);
					if ($objNew) $arrObjects[] = $objNew ;
				}
				return $arrObjects;
			}

			/**
			*	@param (string) mysql WHERE # voorbeeld = 'test' AND id="1" 
			**/
			public static function fetchByWhere($getStrWhere = false)
			{
				if (! $getStrWhere) return false;
				$strCalledClassName = get_called_class();
				$objChildTemp		= new $strCalledClassName();
				$strTableName 		= $objChildTemp->getTableName();
				if (! $strTableName) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Table name is not set! use $this->setDbTableName() in constructor in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line'],
						true
					);
					return false ;
				}
				$rows = \LW\DB::customQuery('SELECT * FROM '.$strTableName.' WHERE '.$getStrWhere);				
				if (! $rows) return false ;
				$arrObjects			= array();
				foreach ($rows as $row) {
					$objNew = self::construct_fromChildObject($row);
					if ($objNew) $arrObjects[] = $objNew ;
				}
				return $arrObjects;
			}

			/**
			 *	$param $getArrDbRow = row from DB
			 */
			final public static function createByDbRow($getArrDbRow = false)
			{
				if (! $getArrDbRow || ! is_array($getArrDbRow)) return false ;
				return self::construct_fromChildObject($getArrDbRow);
			}



		/**
		 * !!! Functions BELOW THIS LINE ONLY NEEDED IN THIS ABSTRACT!! only needed in this abstract class 
		 */ 
			/**
			 * Constructor called from static function 
			 */
			final private function construct($getIntIDorDbArray = false)
			{
				/* Regular consturctor using intID */
					if (is_numeric($getIntIDorDbArray))
						$this->construct_byPrimaryKey($getIntIDorDbArray);
				/* constructor using a db row, for performance */
					if (is_array($getIntIDorDbArray)) 
						$this->construct_setDbData($getIntIDorDbArray);
				/* check if data is set else throw exception */
					if (! $this->arrModelDBdata) throw new Exception("Empty object", 1);					
				/**/
			}
			final private function construct_byPrimaryKey($getIntID = false)
			{
				if (! $getIntID) return false ;
				$strTableName = $this->getTableName();
				if (! $strTableName) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Table name is not set!',
						true
					);
					return false ;
				}
				$row = \LW\DB::selectOneRow($strTableName, $this->getConfigPrimaryKey().' = ?', $getIntID);
				if (! $row) return false ;
				$this->construct_setDbData($row);
			}
			/**
			 * Only be called once! 
			 */
			final private function construct_setDbData($getArrDbData = false)
			{
				if (! $getArrDbData || $this->arrModelDBdata) return false ;
				foreach ($getArrDbData as $key => $val) {
					if (! in_array($key, $this->arrModelDBColumns)) {
						$this->arrModelDBColumns[] = $key;
					}
					/* bypass the __set() */
					$this->arrModelDBdata[$key] = $val;
				}				
			}
			private static function construct_fromChildObject($getIntOrDbRow){
				$calledClass = get_called_class();
				/* Check for singletons */				
				if (
					is_numeric($getIntOrDbRow) 
					&& array_key_exists($calledClass, self::$arrSingletons)
					&& array_key_exists($getIntOrDbRow, self::$arrSingletons[$calledClass])
				)
					return self::$arrSingletons[$calledClass][$getIntOrDbRow];
				/* else create a new one */	
				$objChild 			= new static;
				try { 
					$objChild->construct($getIntOrDbRow);
				} catch(Exception $e) {
					$objChild = null;
				}
				/* add to singletons */
				if (is_numeric($getIntOrDbRow)) self::$arrSingletons[$calledClass][$getIntOrDbRow] = $objChild;
				return $objChild;
			}

			private static function triggerError($getStrError = false, $boolFatalError = false)
			{
				if(!$getStrError) return false ;
				trigger_error($getStrError, (($boolFatalError)? E_USER_ERROR : E_USER_WARNING) );
			}
	};
?>