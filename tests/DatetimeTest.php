<?php
use aphp\XPDO\DateTime;

class DatetimeTest extends Base_TestCase {

	public static function setUpBeforeClass() {
		date_default_timezone_set('UTC');
	}

	public static function tearDownAfterClass() {
		
	}
	
	// tests

	public function test_dateCreate() 
	{
		$t = '2019-08-13';
		$d = new DateTime($t);
		$this->assertTrue( $d->isDateText($t) );
		$this->assertEquals('2019-08-13', $d->getDate() );
		$this->assertEquals('2019-08-13', $d->getText() );

		$t = '14:30:33';
		$d = new DateTime($t);
		$this->assertTrue( $d->isTimeText($t) );
		$this->assertEquals('14:30:33', $d->getTime() );
		$this->assertEquals('14:30:33', $d->getText() );

		$t = '2019-12-04 09:30:01';
		$d = new DateTime($t);
		$this->assertTrue( $d->isDateTimeText($t) );
		$this->assertEquals('2019-12-04 09:30:01', $d->getText() );
	}
	
	public function test_dateTimestamp() 
	{
		$s = 1486139884;
		$d = new DateTime();
		$this->assertTrue( $d->getText() == null );
		
		$d->setTimestamp($s, 'd');
		$this->assertEquals('2017-02-03', $d->getText() );

		$d->setTimestamp($s, 't');
		$this->assertEquals('16:38:04', $d->getText() );

		$d->setTimestamp($s, 'dt');
		$this->assertEquals('2017-02-03 16:38:04', $d->getText() );

		try {
			$d->setTimestamp('string', 'dt');
			$this->assertTrue( false );
		} catch (aphp\XPDO\DateTime_Exception $ex) {
			$this->assertTrue( true );
		}
	}

	public function test_dateValidation() 
	{
		$d = new DateTime();
		$this->assertTrue( $d->isTimeText('00:00:00') );
		$this->assertTrue( $d->isTimeText('23:59:59') );
		$this->assertTrue( false == $d->isTimeText('33:59:59') );
		$this->assertTrue( false == $d->isTimeText('24:69:59') );
		$this->assertTrue( false == $d->isTimeText('24:69:69') );

		$this->assertTrue( $d->isDateText('0000-01-01') );
		$this->assertTrue( $d->isDateText('9999-12-31') );
		$this->assertTrue( false == $d->isDateText('9999-22-31') );
		$this->assertTrue( false == $d->isDateText('9999-12-41') );
	}

	public function test_phpdate() 
	{
		$s = 1486139884;
		$d = new DateTime();
		$d->setTimestamp($s, 'dt');
		$p = $d->getPHPDateTime();
		$t = $p->getTimestamp();
		$this->assertTrue( $s == $t );
	}
}