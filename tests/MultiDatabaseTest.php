<?php

use aphp\XPDO\Database;
use aphp\XPDO\Model;

class User_db01 extends Model {
	static function tableName() {
		return 'user';
	}
	static function database() {
		return MultiDatabaseTest::$db01;
	}
}

class User_db02 extends Model {
	static function tableName() {
		return 'user';
	}
	static function database() {
		return MultiDatabaseTest::$db02;
	}
}

class MultiDatabaseTest extends Base_TestCase {

	// STATIC
	static $db01 = null;
	static $db02 = null;

	public static function setUpBeforeClass() {
		MultiDatabaseTest::$db01 = new Database;
		MultiDatabaseTest::$db01->SQLiteInit(__DIR__ . '/db/sampleBase-temp01.sqlite');

		MultiDatabaseTest::$db01->setLogger( aphp\Logger\FileLogger::getInstance() );

		MultiDatabaseTest::$db02 = new Database;
		MultiDatabaseTest::$db02->SQLiteInit(__DIR__ . '/db/sampleBase-temp02.sqlite');

		MultiDatabaseTest::$db02->setLogger( aphp\Logger\FileLogger::getInstance() );
	}

	public static function tearDownAfterClass() {

	}

	protected function setUp() {

    }

	// tests
	public function test_fetch()
	{
		$userDb1 = User_db01::loadAll();
		$userDb2 = User_db02::loadAll();

		$this->assertEquals( 'User_db01', get_class($userDb1[0]) );
		$this->assertEquals( 'User_db02', get_class($userDb2[0]) );

		$this->assertEquals( 'user1_db01', $userDb1[0]->name );
		$this->assertEquals( 'user1_db02', $userDb2[0]->name );
	}

	public function test_save_update()
	{
		$userDb1 = User_db01::loadAll();
		$userDb2 = User_db02::loadAll();

		$userDb1[0]->email = 'hello world 01';
		$userDb2[0]->email = 'hello world 02';

		$userDb1[0]->save();
		$userDb2[0]->save();

		$userDb1_1 = User_db01::loadAll();
		$userDb2_2 = User_db02::loadAll();

		$this->assertEquals( 'User_db01',  get_class($userDb1_1[0]) );
		$this->assertEquals( 'User_db02',  get_class($userDb2_2[0]) );

		$this->assertEquals( 'hello world 01', $userDb1_1[0]->email );
		$this->assertEquals( 'hello world 02', $userDb2_2[0]->email );
	}

	public function test_save_insert()
	{
		$userDb1 = User_db01::newModel();
		$userDb2 = User_db02::newModel();

		$time = time() . '';

		$userDb1->name = $time;
		$userDb2->name = $time;
		$userDb1->email = 'db email1';
		$userDb2->email = 'db email2';

		$userDb1->save();
		$userDb2->save();

		$userDb1_1 = User_db01::loadWithField('name', $time);
		$userDb2_2 = User_db02::loadWithField('name', $time);

		$this->assertEquals( 'db email1', $userDb1_1->email );
		$this->assertEquals( 'db email2', $userDb2_2->email );
	}
}
