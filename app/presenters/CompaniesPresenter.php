<?php

namespace App\Presenters;

use Nette;
use App;


class CompaniesPresenter extends BasePresenter
{
	private $company;

	
	public function actionDefault()
	{
		$this->template->companies = $this->main->getCompanies()->where([
			":jobs.publication_date IS NOT NULL",
			":jobs.expiration_date IS NULL"
		])->having("COUNT(:jobs.id) > 0")->group("companies.id")->order("name ASC");
	}

	
	protected function createComponentCompanyListItem()
	{
		return new Nette\Application\UI\Multiplier(function ($id)
		{
			return new App\Components\CompanyListItem($this->main->getCompanies()->get($id));
		});
	}

	
	public function actionDetail($id)
	{
		$this->company = $this->template->company = $this->main->getCompanies()->get($id);
		$this->template->jobs = $this->template->company->related("jobs")->where([
			"publication_date IS NOT NULL",
			"expiration_date IS NULL"
		])->order("name ASC");

		$this->template->img = $this->company->related("jobs")->where([
			"title_image IS NOT NULL",
			"expiration_date IS NULL"
		])->order("created DESC")->limit(1)->fetch();
	}


	protected function createComponentSharingButtons()
	{
		$url = $this->getHttpRequest()->getUrl();
		$text = $this->company->name;
		$image = $this->company->logo;
		return new App\Components\SharingButtons($url, $text, $image);
	}
}