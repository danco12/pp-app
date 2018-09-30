<?php

namespace App\Components;

use Nette;

class JobListItem extends Nette\Application\UI\Control
{
	private $job;
	private $company;

	public function __construct($job)
	{
		$this->job = $job;
		$this->company = $this->job->company;
	}

	public function render()
	{
		$this->template->job = $this->job;
		$this->template->company = $this->company;
		$this->template->locations_string = implode(",",$this->job->locations->fetchPairs("location", "location"));
		$this->template->setFile(__DIR__ . "/templates/JobListItem.latte");
		$this->template->render();
	}
}