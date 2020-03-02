<?php
namespace aphp\XPDO;

// https://www.sqlite.org/lang_datefunc.html
// YYYY-MM-DD
// YYYY-MM-DD HH:MM:SS
// HH:MM:SS

abstract class DateTimeH 
{
	protected $date = null;
	protected $time = null;
	
	abstract public function isTimeText($text);
	abstract public function isDateText($text);
	abstract public function isDateTimeText($text);

	abstract public function setText($text);
	abstract public function getText();
	
	abstract public function setNow($dt = null);
	abstract public function setTimestamp(/* int */ $timestamp, $dt = null); // dt = dateTime, d = date, t = time

	public function getDate() { return $this->date; }
	public function getTime() { return $this->time; }
	public function getDT() { return ($this->date ? 'd' : '') . ($this->time ? 't' : ''); }
	
	abstract public function getPHPDateTime(); // \DateTime
	abstract public function getTimestamp(); // int
}

class DateTime extends DateTimeH
{
	const DATE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * DateTime constructor.
	 * @param string $text 'now_dt', 'now_t', 'now_d' or '1970-01-01 00:00:00' format
	 */

	public function __construct($text = null)
	{
		if ($text == 'now_dt') {
			$this->setNow('dt');
		}
		elseif ($text == 'now_d') {
			$this->setNow('d');
		}
		elseif ($text == 'now_t') {
			$this->setNow('t');
		} else {
			$this->setText($text);
		}
	}
	
	public function isTimeText($text) 
	{
		return 
			is_string($text) && 
			strlen($text) == 8 && 
			preg_match('~^[0-2]\d:[0-5]\d:[0-5]\d$~', $text);
	}
	public function isDateText($text) 
	{
		return 
			is_string($text) && 
			strlen($text) == 10 && 
			preg_match('~^\d{4}-[0-1]\d-[0-3]\d$~', $text);
	}
	public function isDateTimeText($text) 
	{
		return 
			is_string($text) && 
			strlen($text) == 19 &&
			$this->isDateText(substr($text, 0, 10)) && $this->isTimeText(substr($text, 11));
	}
// set Date
	public function setText($text)
	{
		if ($this->isDateText($text)) {
			$this->date = $text;
			$this->time = null;
		} elseif($this->isTimeText($text)) {
			$this->time = $text;
			$this->date = null;
		} elseif($this->isDateTimeText($text)) {
			$this->date = substr($text, 0, 10);
			$this->time = substr($text, 11);
		} else {
			$this->date = null;
			$this->time = null;
		}
	}
	public function getText()
	{
		if ($this->date && $this->time) return $this->date . ' ' . $this->time;
		if ($this->date) return $this->date;
		if ($this->time) return $this->time;
		return null;
	}

	// set timestamp
	public function setNow($dt = null)
	{
		$this->setTimestamp(time(), $dt);
	}

	public function setTimestamp($timestamp, $dt = null)
	{
		if (is_int($timestamp)) {
			$text = date(self::DATE_FORMAT, $timestamp);
			$this->date = null;
			$this->time = null;
			if (!$dt) $dt = $this->getDT();
			// --
			if ($dt == 'dt' || $dt == 'd') $this->date = substr($text, 0, 10);
			if ($dt == 'dt' || $dt == 't') $this->time = substr($text, 11);
		} else {
			throw XPDOException::invalidTimestamp($timestamp);
		}
	}
// get timestamp
	public function getPHPDateTime()
	{
		if ($this->getDT() == 'dt') {
			$date = \DateTime::createFromFormat(self::DATE_FORMAT, $this->getText() );
		}
		elseif ($this->getDT() == 'd') {
			$date = \DateTime::createFromFormat(self::DATE_FORMAT, $this->date . ' 00:00:00' );
		}
		elseif ($this->getDT() == 't') {
			$date = \DateTime::createFromFormat(self::DATE_FORMAT, '1970-01-01 ' . $this->time );
		} else {
			return new \DateTime('@0');
		}
		return $date;
	}

	public function getTimestamp()
	{
		return $this->getPHPDateTime()->getTimestamp();
	}
}