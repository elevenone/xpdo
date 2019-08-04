<?php 

require __DIR__ . '/../vendor/autoload.php';

use aphp\XPDO\Database;
use aphp\XPDO\Model;
use aphp\XPDO\DateTime;

class timeTable extends Model {
	static function dateFields() {
		return ['v_dateTime', 'v_date', 'v_time'];
	}
	/*
	public $id;
	public $name;
	public $v_dateTime;
	public $v_date;
	public $v_time;
	*/
}

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/sampleBase.sqlite');

// Delete db

$db->exec('DELETE FROM `timeTable`');

// --

$t1 = timeTable::newModel();
$t1->name = 'name00001';
// datetime mode
$t1->v_dateTime = new DateTime('2019-11-22 14:55:59');
// date mode
$t1->v_date = new DateTime('2019-11-22');
// time mode
$t1->v_time = new DateTime('14:55:59');

$t2 = timeTable::newModel();
$t2->name = 'name00002';
// valid string format mode
$t2->v_dateTime = '2019-11-22 14:55:59';
// date mode
$t2->v_date = '2019-11-22';
// time mode
$t2->v_time = '14:55:59';

// SAVE
$t1->save();
$t2->save();

// Load
$objs = timeTable::loadAll();
print_r($objs);