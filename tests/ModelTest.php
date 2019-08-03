<?php 

use aphp\XPDO\Database;
use aphp\XPDO\Model;
use aphp\XPDO\Utils;

class user extends Model {
	public $id;
	public $name;
	public $email;
	public $gender;
	public $age;
	public $binary;
}

/*
name
lastname
*/

class user_object_uniq extends Model {
	static function tableName() {
		return 'user_uniq';
	}
	static function keyField() {
		return 'name';
	}
	static function keyFieldAutoIncrement() {
		return false;
	}
}

class user_object_nokey extends Model {
	static function tableName() {
		return 'user_uniq'; // table name must exists
	}
	static function keyField() {
		return null; // not keyfield used
	}
	// dynamic properties, not implemented
}

class user_json extends Model {
	static function tableName() {
		return 'user';
	}
	static function jsonFields() {
		return [ 'email' ];
	}
}

// ---

class ModelTest extends Base_TestCase {
	// STATIC
	public static function setUpBeforeClass() {
		Utils::$_jsonBindDetection = true;
	}

	public static function tearDownAfterClass() {

	}
	
	static $jsonExample = 
'{
    "glossary": {
        "title": "example glossary",
        "GlossDiv": {
            "title": "S",
            "GlossList": {
                "GlossEntry": {
                    "ID": "SGML",
                    "SortAs": "SGML",
                    "Unicode": "Thíś íś ṕŕéttӳ fúń tőő. Dő śőḿéthíńǵ főŕ ӳőúŕ ǵŕőúṕ táǵ",
                    "Acronym": "SGML",
                    "Abbrev": "ISO 8879:1986",
                    "GlossDef": {
                        "para": "A meta-markup language, used to create markup languages such as DocBook.",
                        "GlossSeeAlso": [
                            "GML",
                            "XML"
                        ]
                    },
                    "GlossSee": "markup"
                }
            }
        }
    }
}';

	// tests
	public function test_newModel() 
	{
		$obj2 = user::newModel();

		$obj2->name = 'name 01';
		$obj2->email = 'email 01';

		$obj2->save();
		$obj2_read = user::loadWithId( $obj2->id );
		$this->assertTrue(is_a($obj2_read, user::class));
		$this->assertEquals( 'email 01', $obj2_read->email );
	}

	public function test_newModel2() 
	{
		$obj1 = new user_object_uniq();
		$obj1->name = 'obj_name1';
		$obj1->lastname = 'obj_lastname1';

		$obj1->save();

		$obj1_read = user_object_uniq::loadWithId( $obj1->name );
		$this->assertTrue(is_a($obj1_read, user_object_uniq::class));
		$this->assertEquals( 'obj_lastname1', $obj1_read->lastname );
	}

	public function test_update() 
	{
		$obj = user::loadWithId('1');
		$this->assertTrue(is_a($obj, user::class));
		
		$oldName = $obj->name;
		$obj->name = 'new name';
		$obj->email = 'new email';

		$obj->save(['email']);

		$obj1_read = user::loadWithId( '1' );
		$this->assertTrue(is_a($obj1_read, user::class));
		$this->assertEquals( 'new email', $obj1_read->email );
		$this->assertEquals( $oldName, $obj1_read->name );
	}

	public function test_nokeyObj() 
	{
		$obj = user_object_nokey::newModel();
		$this->assertTrue(is_a($obj, user_object_nokey::class));

		$obj->name = 'user_object_nokey name';
		$obj->lastname = 'user_object_nokey lastname';

		$obj->save();

		$obj1_read = user_object_nokey::loadWithField('name', 'user_object_nokey name');
		$this->assertTrue(is_a($obj1_read, user_object_nokey::class));
		$this->assertEquals( 'user_object_nokey lastname', $obj1_read->lastname );

		$obj->lastname = 'hello world';
		try {
			$obj->save();
			$this->assertTrue( false );
		} catch (aphp\XPDO\Model_Exception $ex) {
			$this->assertTrue( true );
		}
	}
	
	public function test_nokeyObj2() 
	{
		$obj = user_object_uniq::newModel();
		$this->assertTrue(is_a($obj, user_object_uniq::class));

		$id = 'user2_object_nokey name';

		$obj->name = $id;
		$obj->lastname = 'user2_object_nokey lastname';

		$obj->save();

		$obj1_read = user_object_uniq::loadWithField('name', $id);
		$this->assertTrue(is_a($obj1_read, user_object_uniq::class));
		$this->assertEquals( 'user2_object_nokey lastname', $obj1_read->lastname );

		$obj->lastname = 'hello world';
		$obj->save();

		$obj2_read = user_object_uniq::loadWithField('name', $id);
		$this->assertTrue(is_a($obj2_read, user_object_uniq::class));
		$this->assertEquals( 'hello world', $obj2_read->lastname);
	}

	public function test_fetch() 
	{
		$db = Database::getInstance();
		$statement = $db->prepare('SELECT * FROM user_uniq');
		$objects = user_object_nokey::loadAllWithStatement($statement);
		$this->assertTrue(is_a($objects[1], user_object_nokey::class));

		$statement2 = $db->prepare('SELECT * FROM user_uniq');
		$object = user_object_nokey::loadWithStatement($statement2);
		$this->assertTrue(is_a($object, user_object_nokey::class));
	}

	public function test_delete() 
	{
		$obj = user_object_uniq::loadWithId('obj_name1');
		$this->assertTrue(is_a($obj, user_object_uniq::class));

		$obj->delete();
		$obj1_read = user_object_uniq::loadWithId('obj_name1');
		$this->assertTrue( $obj1_read === null );
	}
	
	public function test_fieldsLoad() 
	{
		$obj = user_object_uniq::newModel();
		$obj->name = 'test_fieldsLoad-name';
		$obj->lastname = 'test_fieldsLoad-lastname';
		$obj->save();
		
		$obj2 = user_object_uniq::loadWithId('test_fieldsLoad-name', ['name']);
		
		$this->assertEquals( ['name'], $obj2->_model_loadedFields );
		
		$obj2->lastname = 'test_fieldsLoad-lastname 2';
		
		try {
			$obj2->save();
			$this->assertTrue( false );
		} catch (aphp\XPDO\Model_Exception $e) {
			$this->assertTrue( true );
		}
	}
	
	public function test_JSON() {
		$obj = user_json::newModel();
		$obj->name = 'user_json01';
		
		$json = Utils::jsonDecode(self::$jsonExample);
		
		$obj->email = $json;
		$obj->save();
		
		$obj2 = user_json::loadWithField('name', 'user_json01');
		
		$this->assertEquals( $json, $obj2->email);
	}
}
