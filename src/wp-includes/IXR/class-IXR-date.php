<?php

/**
 * IXR_Date
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Date {
    var $year;
    var $month;
    var $day;
    var $hour;
    var $minute;
    var $second;
    var $timezone;

	/**
	 * PHP5 constructor.
	 */
    function __construct( $time )
    {
        // $time can be a PHP timestamp or an ISO one
        if (is_numeric($time)) {
            $this->parseTimestamp($time);
        } else {
            $this->parseIso($time);
        }
    }

	/**
	 * PHP4 constructor.
	 */
	public function IXR_Date( $time ) {
		self::__construct( $time );
	}

    function parseTimestamp($timestamp)
    {
        $this->year = gmdate('Y', $timestamp);
        $this->month = gmdate('m', $timestamp);
        $this->day = gmdate('d', $timestamp);
        $this->hour = gmdate('H', $timestamp);
        $this->minute = gmdate('i', $timestamp);
        $this->second = gmdate('s', $timestamp);
        $this->timezone = '';
    }

    function parseIso($iso)
    {
        $this->year = substr($iso, 0, 4);
        $this->month = substr($iso, 4, 2);
        $this->day = substr($iso, 6, 2);
        $this->hour = substr($iso, 9, 2);
        $this->minute = substr($iso, 12, 2);
        $this->second = substr($iso, 15, 2);
        $this->timezone = substr($iso, 17);
    }

    /**
     * Gets the datetime in ISO format.
     *
     * @return string ISO datetime.
     * @phpstan-return non-falsy-string
     */
    function getIso(): string
    {
        return $this->year.$this->month.$this->day.'T'.$this->hour.':'.$this->minute.':'.$this->second.$this->timezone;
    }

    /**
     * Gets the `dateTime.iso8601` XML tag.
     *
     * @return string A dateTime.iso8601 XML tag.
     * @phpstan-return non-falsy-string
     */
    function getXml(): string
    {
        return '<dateTime.iso8601>'.$this->getIso().'</dateTime.iso8601>';
    }

    /**
     * Gets the timestamp.
     *
     * @return int|false Timestamp, or false on error.
     */
    function getTimestamp()
    {
        return mktime($this->hour, $this->minute, $this->second, $this->month, $this->day, $this->year);
    }
}
