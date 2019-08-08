<?php 

require __DIR__ . '/../vendor/autoload.php';

use aphp\XPDO\Database;
use aphp\XPDO\Model;

class user extends Model {
	/*
	public $id;
	public $name;
	public $email;
	*/
}

if (!file_exists(__DIR__ . '/sampleBase.sqlite')) {
	copy(__DIR__ . '/../tests/db/sampleBase.sqlite', __DIR__ . '/sampleBase.sqlite');
}

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/sampleBase.sqlite');

$user = user::newModel();

$user->name = 'name00001';
$user->email = 'email00001';

$user->save();

$user2 = user::loadWithId($user->id, ['name']);

print_r($user2);

