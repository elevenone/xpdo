<?php 

/*
https://phpunit.de/getting-started/phpunit-5.html

PHP 5.6

All tests
phpunit --bootstrap tests/bootstrap_autoload.php -c tests/phpunit.xml tests --debug

One file
phpunit --bootstrap tests/bootstrap_autoload.php -c tests/phpunit.xml tests/%file%Test.php --debug

One test
phpunit --bootstrap tests/bootstrap_autoload.php -c tests/phpunit.xml --filter %method% tests/%file%Test.php --debug
*/

require __DIR__ . '/../vendor/autoload.php';

use aphp\XPDO\Database;

@unlink(__DIR__ . '/db/sampleBase-temp.sqlite');
@unlink(__DIR__ . '/db/sampleBase-temp01.sqlite');
@unlink(__DIR__ . '/db/sampleBase-temp02.sqlite');
copy(__DIR__ . '/db/sampleBase.sqlite', __DIR__ . '/db/sampleBase-temp.sqlite');
copy(__DIR__ . '/db/sampleBase01.sqlite', __DIR__ . '/db/sampleBase-temp01.sqlite');
copy(__DIR__ . '/db/sampleBase02.sqlite', __DIR__ . '/db/sampleBase-temp02.sqlite');
@mkdir(__DIR__ . '/logs');

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/db/sampleBase-temp.sqlite');

$logger = aphp\logger\FileLogger::getInstance();
$logger->configure(__DIR__ . '/logs/log');
$logger->startLog();

$db->setLogger( $logger );

class Base_TestCase extends PHPUnit_Framework_TestCase {
	// override
	protected function setUp() {
		aphp\logger\FileLogger::getInstance()->debug( get_class($this) . '::' . $this->getName() );
	}
}


