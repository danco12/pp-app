<?php
namespace App\Entity;

use Nette;

class Company
{
	use Nette\SmartObject;

	/**
	 * @var Nette\Database\Table\ActiveRow
	 */
	private $row;

	/**
	 * Class Constructor
	 */
	public function __construct(Nette\Database\Table\ActiveRow $row)
	{
		$this->row = $row;
	}

	public function __get($name)
	{
		$item = strtolower(str_replace('get_', '', preg_replace('/([A-Z])/','_$1', $name)));

		return $this->row->$item;
	}

}