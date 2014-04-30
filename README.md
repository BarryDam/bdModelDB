Private use,,, uses \LW\DB


Examples:

Extending this class:
	class modelExample extends bdModelDB {
		public function getTableName(){
			return 'table_name';
		}
		public static function insert(){
			// run mysql with ur data then return
			
		}
		
		
		
		public function __set($getName,$getVal){
			if($getName=="something") $dosome;
			parent::__set($getName,$getVal);
		}
		
		public function yourNewFunction(){
			if($something==$wrong){
				self::triggerError('There is something wrong!');
			}
			
		}
	}

Using this class :
	- Create one object by calling it's ID
		$objExample = modelExample::fetchByID(1); || getByPrimaryKey # returns modelExample object
	
	- Create multiple objects by get them all from DB
		$arrExampleObjects = modelExample::fetchAll(); #returns array(modelExample object,modelExample object,modelExample object,...)
	
	- Get the vars (equal to db column names)
		$objExample->intID 				# inside the class use $this->intID
		$objExample->strName 
		$objExample->strSomeColumnName

	- (re) Set the vars 
		$objExample->strName = 'Some new name'
		
	- Update the database
		$objExample->update(); # return (boolean)
	- getter functions
		$objExample->getTableName(); #the db table name
		$objExample->getConfigPrimaryKey();
