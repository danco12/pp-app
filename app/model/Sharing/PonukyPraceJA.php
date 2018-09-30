<?php
namespace Group;

class PonukyPraceJA extends Group implements iGroup
{
	public $id = 1;

	/**
	 * Class Constructor
	 */
	public function __construct($database, $translator)
	{
		parent::__construct($database, $translator);
		self::initGroup();
	}

	public function getPostLimit()
	{
		return 2;
	}

	public function getTimeIntervalBetweenPosts()
	{
		return "-5 hours";
	}

	public function getCasualnessNumber()
	{
		return 5;
	}

}