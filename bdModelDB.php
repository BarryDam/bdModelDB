<?php
	/**  
	*	bdModelDB 
	*	@author 	Barry Dam
	*	@copyright  BIC Multimedia 2013 - 2014
	*	@version	1.3.0
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
	*		- insert new db row
	*			$objnew = ModelExample::insert('valColumn1', 'valColumn2');
	*			// OR associative 
	*			$objnew = ModelExample::insert(
	*				array(
	*					'column1' = 'valColumn1'
	*					'column2' = 'valColumn2'
	*				);
	*			);
	*			// now the row entry is made
	*			// and the new object is returned
	*			// your can do some like
	*			// $objnew->column1 will return 'valColumn1'
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
			private	$arrModelDBColumns		= array(), # will set in construct_setDbData and used in __set
					$arrModelDBCalledFN		= array(), # array to check if a function is allready called
					$arrModelDBSettings 	= array(
						'strTableName'			=> false,
						'strColumnPrimaryKey' 	=> 'intID' 	#default BIC //TODO SHOW KEYS FROM tblPAG__Textbins WHERE Key_name = 'PRIMARY'
						)
					;

		/* Singletons for performance bound to bdModelDB */
			private static $arrSingletons = array(); // key = classname , value = array (key = id, value = object)		 
			// for debug purposes > prints all singletons
			public static function printSingletons()
			{
				echo 'bdModelDB.php Line 97:<br /><pre>'.print_r(self::$arrSingletons,true).'</pre>';
			}

		/**
		 * toSting for debugging!
		 */
		public function __toString()
		{
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
			$arrData = array();
			foreach($this->arrModelDBdata as $key => $val)
				$arrData[$key] = $this->$key;
			// return
			return '
				<div style="margin:10px;padding:10px;border:solid 5px blue;background-color:#FFF">
					'.get_class($this).$strFileLine.'<pre>'.print_r($arrData, true).'</pre>'.'
				</div>
			';
		}

		/* abstracts */

			/**
			* 	@return (string) tablename
			*	make sure that this function can called static and non static
			**/
			abstract function getTableName();

		/* Magic methods */
			public function __construct(){}

			public function __set($getName, $getValue)
			{
				// check valid column
				if (! in_array($getName, $this->arrModelDBColumns)) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Cannot set '.$getName.', not a valid DB column in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line']
					);
					return false;
				}
				// check if it's not the primary key
				if ($getName === $this->arrModelDBSettings['strColumnPrimaryKey']) {
					$arrBacktrace = debug_backtrace();
					self::triggerError(
						'Cannot overwrite '.$getName.', used as primary key in '.$arrBacktrace[0]['file'].' on line '.$arrBacktrace[0]['line']
					);
					return false;
				}
				// json encode when it is an array
				if (is_array($getValue)) 
					$getValue = json_encode($getValue);
				// add to arrmodelDBdata
				$this->arrModelDBdata[$getName] = $getValue ;
			}

			public function __get($getName)
			{
				// check valid column
				if (! array_key_exists($getName, $this->arrModelDBdata)) 
					return false;
				$return = $this->arrModelDBdata[$getName];
				// check if it is json encoded
				if (is_string($return)) {
					$arrFromJson = json_decode($return, true);
					if (is_array($arrFromJson)) 
						$return = $arrFromJson;
				}
				// return value
				return $return;
			}

			public function __isset($getName)
			{
				return ($this->$getName) ? true : false;							
			}

			public function __unset($getName)
			{
				$this->arrModelDBdata[$getName] = false;
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
				$o = new static();
				$strTableName 	= $o->getTableName();
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
							$arrNewDBentry[$arrColumn['Field']] = $arguments[0][$arrColumn['Field']];
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
				if ($query) return static::fetchByPrimaryKey(\LW\DB::getInsertId());
			}


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
			 *	@return new childobject
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
			 * @return childobject first in db
			 * @param $getOrderByColumn the column to order.. when false.. the primarykey will be used
			 */
			final public static function fetchFirst($getOrderByColumn = false)
			{
				$objChildTemp		= new static();
				$strTableName 		= $objChildTemp->getTableName();
				$strColumn			= ($getOrderByColumn) ? $getOrderByColumn : $objChildTemp->getConfigPrimaryKey();
				$row 				= \LW\DB::selectOneRow(
					$strTableName,
					'1=1 ORDER BY '.$strColumn.' ASC'
				);
				if ($row) return self::construct_fromChildObject($row);
			}

			/**
			 * Returns a new child object by the last inserted db entry
			 * duplicate func of fetchLast .. keep this for backwardcompatibi
			 * @return childobject
			 */
			final public static function fetchLast($getOrderByColumn = false) 
			{
				$objChildTemp		= new static();
				$strTableName 		= $objChildTemp->getTableName();
				$strColumn			= ($getOrderByColumn) ? $getOrderByColumn : $objChildTemp->getConfigPrimaryKey();
				$row 				= \LW\DB::selectOneRow(
					$strTableName,
					'1=1 ORDER BY '.$strColumn.' DESC'
				);
				if ($row) return self::construct_fromChildObject($row);
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
				if (count($arrObjects)) return $arrObjects;
			}

			/**
			 *	@param (string) $getColumnName
			 */
			final public static function fetchByColumn($getColumnName = false, $getValue = false, $getOrderBy = false, $getOrderDirection = "ASC")
			{
				if (! $getColumnName && ! $getValue) return false ;
				$objChildTemp		= new static();
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
				if (count($arrObjects)) return $arrObjects;
			}

			/**
			*	@param (string) mysql WHERE # voorbeeld = 'test' AND id="1" 
			**/
			public static function fetchByWhere($getStrWhere = false)
			{
				if (! $getStrWhere) return false;
				$objChildTemp		= new static();
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
				if (count($arrObjects)) return $arrObjects;
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
				$objChild 		= new static();
				$strTableName = $objChild->getTableName();
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

			/**
			 * get exsisting object from cache or create a new object // and store it in cache
			 */
			private static function construct_fromChildObject($getIntOrDbRow){
			//	$strCalledClassName = get_called_class();
				$calledClass 	= get_called_class();
				$objChild 		= new static;
				/* Check for singletons */				
				if (
					is_numeric($getIntOrDbRow) 
					&& array_key_exists($calledClass, self::$arrSingletons)
					&& array_key_exists($getIntOrDbRow, self::$arrSingletons[$calledClass])
				) {
					return self::$arrSingletons[$calledClass][$getIntOrDbRow];
				} elseif (
					is_array($getIntOrDbRow)
					&& array_key_exists($calledClass, self::$arrSingletons)
					&& array_key_exists($objChild->getConfigPrimaryKey(), self::$arrSingletons[$calledClass])
				) {
					return self::$arrSingletons[$calledClass][$getIntOrDbRow];
				}
				/* else create a new one */				
				
				try { 
					$objChild->construct($getIntOrDbRow);
				} catch(Exception $e) {
					$objChild = null;
				}
				/* add to singletons */
				$strPrimaryKey = $objChild->getConfigPrimaryKey();
				self::$arrSingletons[$calledClass][$objChild->$strPrimaryKey] = $objChild;
				//if (is_numeric($getIntOrDbRow)) self::$arrSingletons[$calledClass][$getIntOrDbRow] = $objChild;
				return $objChild;
			}

			private static function triggerError($getStrError = false, $boolFatalError = false)
			{
				if(!$getStrError) return false ;
				trigger_error($getStrError, (($boolFatalError)? E_USER_ERROR : E_USER_WARNING) );
			}
	};
?>