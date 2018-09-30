<?php

namespace App\Presenters;

use Nette;

class HomepagePresenter extends BasePresenter
{
	
	public function actionDefault()
	{
		$this->redirect("Jobs:");
	}

	
	public function actionSitemap()
	{
		$this->template->jobs = $this->main->openedJobs;

		$urls = [
			"http://pracovne-prilezitosti.sk",
			"http://www.pracovne-prilezitosti.sk"
		];
		$this->template->urls = $urls;
	}
}
