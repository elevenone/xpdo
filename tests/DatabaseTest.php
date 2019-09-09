<?php 

use aphp\XPDO\Database;
use aphp\XPDO\Utils;
use aphp\XPDO\DateTime;

class User_object {
	public $id;
	public $name;
	public $email;
	public $gender;
	public $age;
	public $binary;

	public $param1_v;
	public $param2_v;

	function __construct($param1 = null, $param2 = null) {
		$this->param1_v = $param1;
		$this->param2_v = $param2;
	}
}

class Time_object {
	public $id;
	public $name;
	public $v_dateTime;
	public $v_date;
	public $v_time;
}

class DatabaseTest extends Base_TestCase {
	
	// STATIC
	static $blobFile1 = '';
	static $blobFile2 = '';
	
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

	public static function setUpBeforeClass() {
		self::$blobFile1 = __DIR__ . '/blobData1.png';
		self::$blobFile2 = __DIR__ . '/blobData2.png';

		@unlink(self::$blobFile1);
		@unlink(self::$blobFile2);
	}

	public static function tearDownAfterClass() {
		@unlink(self::$blobFile1);
		@unlink(self::$blobFile2);
	}
	
	protected function setUp()
    {
        Utils::$_jsonBindDetection = false;
        parent::setUp();
    }
	
	// tests
	
	public function test_fetchBlob() 
	{
		$db = Database::getInstance();

		$statement = $db->prepare('SELECT * FROM user WHERE id = 1');
		$blobData = null;
		$this->assertTrue( $statement->fetchBlob('binary', $blobData) );
		$this->assertTrue( file_put_contents(self::$blobFile1, $blobData) !== false );
		$this->assertFileEquals(self::$blobFile1 , __DIR__ . '/db/blobImage1.png');
	}

	public function test_insertRow() {
		$db = Database::getInstance();
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user2', 'email2', 2, 1.5 )");
		$statement->execute();

		$lastId = $statement->fetchLastId('user', 'id');

		$statement = $db->prepare('SELECT `id`, `name`, `email`, `gender`, `age` FROM user WHERE id = ?');
		$statement->bindValues([ $lastId ]);
		$data = $statement->fetchLine();

		$this->assertSame(
			[ 'id' => $lastId, 'name' => 'user2', 'email' => 'email2', 'gender' => '2', 'age' => '1.5' ], $data
		);
	}

	public function test_insertBlob() {
		$db = Database::getInstance();
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user3', 'email3', 3, 2.5 )");
		$statement->execute();

		$lastId = $statement->fetchLastId('user', 'id');

		$statement = $db->prepare("UPDATE user SET `binary` = :blob WHERE id = $lastId");
		$statement->bindNamedBlobAsFilename('blob', __DIR__ . '/db/blobImage2.png');
		$statement->execute();

		$statement = $db->prepare("SELECT * FROM user WHERE id = $lastId");
		$blobData = null;
		$this->assertTrue( $statement->fetchBlob('binary', $blobData) );
		$this->assertTrue( file_put_contents(self::$blobFile2, $blobData) !== false );
		$this->assertFileEquals(self::$blobFile2 , __DIR__ . '/db/blobImage2.png');
	}

	public function test_bindValues() {
		$db = Database::getInstance();
		// bindNamedValue
		$statement = $db->prepare("UPDATE user SET `name` = :value WHERE id = 1");
		$statement->bindNamedValue('value', 'user_bindNamedValue1');
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( 'user_bindNamedValue1', $statement->fetchOne() );
		// bindNamedValues
		$statement = $db->prepare("UPDATE user SET `name` = :value WHERE id = 1");
		$statement->bindNamedValues([ 'value' => 'user_bindNamedValue2' ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( 'user_bindNamedValue2', $statement->fetchOne() );
		// bindValues
		$statement = $db->prepare("UPDATE user SET `name` = ? WHERE id = 1");
		$statement->bindValues([ 'user_bindNamedValue3' ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( 'user_bindNamedValue3', $statement->fetchOne() );
	}

	public function test_bindValues_types() {
		$db = Database::getInstance();
		// bindNamedValues
		$statement = $db->prepare("UPDATE user SET `name` = :value, age = :age WHERE id = 1");
		$statement->bindNamedValues([ 'value' => 'test_bindValues_types', 'age' => 2.3 ]);
		$statement->execute();
		$statement = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		$this->assertEquals( 'test_bindValues_types',  $statement->fetchOne() );
		$statement = $db->prepare("SELECT `age` FROM user WHERE id = 1");
		$this->assertEquals( '2.3', $statement->fetchOne() );
	}

	public function test_objects() {
		$db = Database::getInstance();
		$statement = $db->prepare("SELECT * FROM user WHERE id = 1");
		$obj = $statement->fetchObject(User_object::class, [ 'p1', 'p2' ]);
		$this->assertTrue( is_a($obj, User_object::class) );
		$this->assertEquals( '1', $obj->id);
		$this->assertEquals( 'p2', $obj->param2_v);
		
		$statement = $db->prepare("SELECT * FROM user LIMIT 2");
		$objList = $statement->fetchAllObjects(User_object::class, [ 'p3', 'p4' ]);
		$this->assertTrue( is_a($objList[1], User_object::class) );
		$this->assertEquals( 'p3', $objList[1]->param1_v );
		$this->assertEquals( 'p4', $objList[1]->param2_v );
	}

	// Exceptions

	public function test_Database_Exception() {
		$db = new Database;
		try {
			$db->prepare("SELECT `name` FROM user WHERE id = 1");
			$this->assertTrue(false);
		} catch (aphp\XPDO\XPDOException $ex) {
			$this->assertContains('pdoIsNull', $ex->getMessage());
		}
	}

	public function test_bindNamedValue_Exception() {
		$db = Database::getInstance();
		$st = $db->prepare("SELECT `name` FROM user WHERE id = 1");
		try {
			$st->bindNamedValue('invalid', [ 'hello world' ]);
			$this->assertTrue(false);
		} catch (aphp\XPDO\XPDOException $ex) {
			$this->assertContains('bindInvalidType', $ex->getMessage());
		}
	}

	public function test_bindNamedBlobAsFilename_Exception() {
		$db = Database::getInstance();
		try {
			$statement = $db->prepare("UPDATE user SET `binary` = :blob WHERE id = 2");
			$statement->bindNamedBlobAsFilename('blob', __DIR__ . '/db/invalid.png');
			$this->assertTrue(false);
		} catch (aphp\XPDO\XPDOException $ex) {
			$this->assertContains('bindNamedBlobAsFilenameException', $ex->getMessage());
		}
	}

	public function test_executeException() {
		$db = Database::getInstance();
		try {
			$statement = $db->prepare("SELEC * FROM user WHERE id = 1");
			$data = $statement->fetchLine();
			$this->assertTrue(false);
		} catch (\PDOException $ex) {
			$this->assertTrue(true);
		}
	}
	
	public function test_JSON() {
		Utils::$_jsonBindDetection = true;
		$json = Utils::jsonDecode(self::$jsonExample);
		
		// fetchLine
		$db = Database::getInstance();
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user_json01', ?, 3, 2.5 )");
		$statement->bindValues([
			$json
		]);
		$statement->execute();

		$statement = $db->prepare("SELECT * FROM user WHERE name = ?");
		$statement->bindValues([
			'user_json01'
		]);
		
		$user = $statement->fetchLine();
		
		// windows line ending handling
		$json_db = str_replace("\r", "", $user['email']);
		$json_text = str_replace("\r", "", self::$jsonExample);
		
		$this->assertEquals( $json_text, $json_db );
		
		$statement = $db->prepare("SELECT * FROM user WHERE name = ?");
		$statement->bindValues([
			'user_json01'
		]);
		$statement->setJSONColumns([ 'email' ]);
		$user = $statement->fetchLine();
		
		$this->assertEquals( $json, $user['email'] );
		// bindNamedValue
		
		$statement = $db->prepare("INSERT INTO user ( `name`, `email`, `gender`, `age` ) VALUES ( 'user_json02', :json, 3, 2.5 )");
		$statement->bindNamedValue( 'json', $json );
		$statement->execute();
		
		$statement = $db->prepare("SELECT * FROM user WHERE name = :name");
		$statement->bindNamedValue( 'name', 'user_json02' );
		$user2 = $statement->fetchLine();
		
		$json_db = str_replace("\r", "", $user2['email']);
		
		$this->assertEquals( $json_text, $json_db );
		
		// fetchAllObjects
		$statement = $db->prepare("SELECT * FROM user WHERE name = ? OR name = ?");
		$statement->bindValues([
			'user_json01', 'user_json02'
		]);
		$statement->setJSONColumns([ 'email' ]);
		
		$users = $statement->fetchAllObjects(User_object::class);
		
		$this->assertEquals( $json, $users[0]->email );
		$this->assertEquals( $json, $users[1]->email );
		
		// fetchAll
		$statement = $db->prepare("SELECT * FROM user WHERE name = ? OR name = ?");
		$statement->bindValues([
			'user_json01', 'user_json02'
		]);
		$statement->setJSONColumns([ 'email' ]);
		$users = $statement->fetchAll();
		
		$this->assertEquals( $json, $users[0]['email'] );
		$this->assertEquals( $json, $users[1]['email'] );
		
		// fetchOne
		$statement = $db->prepare("SELECT email FROM user WHERE name = ?");
		$statement->bindValues([
			'user_json01'
		]);
		$statement->setJSONColumns([ 'email' ]);
		$user = $statement->fetchOne();
		$this->assertEquals( $json, $user );
	}

	public function test_dateType() {
		$db = Database::getInstance();
		$date = new DateTime('2019-10-22');
		$time = new DateTime('13:55:59');
		$dateTime = new DateTime('2019-10-22 13:55:59');
		$dateTime2 = new DateTime('2019-11-22 14:55:59');
		
		// insert
		$statement = $db->prepare("INSERT INTO timeTable ( `name`, `v_dateTime`, `v_date`, `v_time` ) VALUES ( 'name001', ?, ?, ? )");
		$statement->bindValues([
			$dateTime, $date, $time
		]);
		$statement->execute();

		// insert named
		$statement = $db->prepare("INSERT INTO timeTable ( `name`, `v_dateTime`, `v_date`, `v_time` ) VALUES ( 'name002', :p1, :p2, :p3 )");
		$statement->bindNamedValues([
			'p1' => $dateTime2, 'p2' => $date, 'p3' => $time
		]);
		$statement->execute();

		// fetchLine - strings
		$statement = $db->prepare("SELECT * FROM timeTable WHERE name = ?");
		$statement->bindValues([
			'name001'
		]);
		
		$timeValue = $statement->fetchLine();
		$this->assertTrue( $timeValue['v_dateTime'] == '2019-10-22 13:55:59' );
		$this->assertTrue( $timeValue['v_date'] == '2019-10-22' );
		$this->assertTrue( $timeValue['v_time'] == '13:55:59' );

		// closeCursor - fetchAll - DateTime
		$statement->_pdoStatement->closeCursor();
		$statement->setDateColumns(['v_dateTime', 'v_date', 'v_time']);
		$statement->bindValues([
			'name002'
		]);
		
		$timeValue = $statement->fetchAll();
		$this->assertTrue( is_a($timeValue[0]['v_date'], DateTime::class) );
		$this->assertTrue( $timeValue[0]['v_dateTime']->getText() == '2019-11-22 14:55:59' );

		// fetchOne - DateTime
		$statement = $db->prepare("SELECT v_dateTime FROM timeTable WHERE name = ?");
		$statement->setDateColumns(['v_dateTime']);
		$statement->bindValues([
			'name002'
		]);
		
		$timeValue = $statement->fetchOne();
		$this->assertTrue( is_a($timeValue, DateTime::class) );
		$this->assertTrue( $timeValue->getText() == '2019-11-22 14:55:59' );

		// fetchObject - DateTime
		$statement = $db->prepare("SELECT * FROM timeTable WHERE name = ?");
		$statement->setDateColumns(['v_dateTime', 'v_date', 'v_time']);
		$statement->bindValues([
			'name001'
		]);
		$obj = $statement->fetchObject(Time_object::class);
		$this->assertTrue( is_a($obj->v_time, DateTime::class) );
		$this->assertTrue( $obj->v_dateTime->getText() == '2019-10-22 13:55:59' );
	}
}
	