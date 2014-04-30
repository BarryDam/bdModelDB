# bdModelDB #

*Note: bdModelDB uses \LW\DB which is an mysql db framework.*


##Extending this class##
```php
class modelExample extends bdModelDB {
	/* Abstracts from bdModelDB */
		public function getTableName(){
			return 'table_name';
		}
		public static function insert(){
			// run mysql with ur data then return
			// then return a self::fetchByPrimaryKey(MYSQL_INSERT_ID);
		}
	
	
	
	public function __set($getName,$getVal){
		if($getName=="something") $dosome;
		parent::__set($getName,$getVal);
	}
	
	public function yourNewFunction(){
		/* Do your own shizzle here */
		
	}
}
```

##Using this class##
- Create one object by calling it's ID
```php
$objExample = modelExample::fetchByID(1); || getByPrimaryKey # returns modelExample object
```

- Create multiple objects by get them all from DB
```php
$arrExampleObjects = modelExample::fetchAll(); #returns array(modelExample object,modelExample object,modelExample object,...)
```

	- Get the vars (equal to db column names)
```php
$objExample->intID 				# inside the class use $this->intID
$objExample->strName 
$objExample->strSomeColumnName
```

- (re) Set the vars 
```php
$objExample->strName = 'new value';
```	

- Update the database
```php
$objExample->update(); # return (boolean) on success
```
- getter functions
```php
$objExample->getTableName(); #the db table name
$objExample->getConfigPrimaryKey();
```
