<?php

namespace App\Components;

use Nette;

class CompanyListItem extends Nette\Application\UI\Control
{
	private $company;

	public function __construct($company)
	{
		$this->company = $company;
	}

	public function render()
	{
		$this->template->link = $this->presenter->link("Companies:detail", $this->company->id);
		$this->template->company = $this->company;
		$this->template->setFile(__DIR__ . "/templates/CompanyListItem.latte");
		$this->template->render();
	}
}