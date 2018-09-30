<?php

namespace App\Components;

use Nette;

class SharingButtons extends Nette\Application\UI\Control
{
	private $url;
	private $text;
	private $image;


	public function __construct($url, $text, $image)
	{
		$this->url = $url;
		$this->text = $text;
		$this->image = $image;
	}


	public function render()
	{
		$this->template->text = $this->text;
		$this->template->url = $this->url;
		$this->template->image = $this->image;
		$this->template->setFile(__DIR__ . "/templates/SharingButtons.latte");
		$this->template->render();
	}
}