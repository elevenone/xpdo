<?php
require __DIR__ . '/../vendor/autoload.php';
use aphp\XPDO\Database;
use aphp\XPDO\Model;
use aphp\XPDO\ModelConfig;
use aphp\Foundation\SystemService;

class Category extends Model {
	static function tableName() {
		return 'category';
	}
	static function relations() {
		return [
			'books' => 'this->id *-** Book->category_id'
		];
	}
}

class Book extends Model {
	static function tableName() {
		return 'book';
	}
}

@unlink(__DIR__ . '/db/sampleBase-temp.sqlite');
copy(__DIR__ . '/db/sampleBase.sqlite', __DIR__ . '/db/sampleBase-temp.sqlite');

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/db/sampleBase-temp.sqlite');

file_put_contents(__DIR__ . '/logs/log000.log', ''); // clear logs

$logger = aphp\logger\FileLogger::getInstance();
$logger->configure(__DIR__ . '/logs/log', true, 10102500);
$logger->startLog();

for ($i = 0; $i<100000; $i++) {
	$category = Category::loadWithField('id', 1);
	//print_r($category);
	$books = $category->relation()->books;
	$logger->info( SystemService::memoryUsageText(true) . ' ---- '. count($books) );
	//unset($category, $books);
}