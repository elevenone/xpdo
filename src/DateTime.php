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
	public function __construct($text = null) {
		$this->setText($text);
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
	public function setText($text) {
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
	public function getText() {
		if ($this->date && $this->time) return $this->date . ' ' . $this->time;
		if ($this->date) return $this->date;
		if ($this->time) return $this->time;
		return null;
	}
// set timestamp
	public function setNow($dt = null) {
		$this->setTimestamp(time(), $dt);
	}
	public function setTimestamp($timestamp, $dt = null) {
		if (is_int($timestamp)) {
			$text = date('Y-m-d H:i:s', $timestamp);
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
	public function getPHPDateTime() {
		// dateTime is supported only 
		if ($this->getDT() != 'dt') { return null; }
		$date = \DateTime::createFromFormat('Y-m-d H:i:s', $this->getText() );
		return $date;
	}
	public function getTimestamp() {
		// dateTime is supported only
		if ($this->getDT() != 'dt') { return null; }
		return $this->getPHPDateTime()->getTimestamp();
	}
}