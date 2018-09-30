<?php

namespace App\Presenters;

use Nette;
use App;

class JobsPresenter extends BasePresenter
{
	private $filter;
	private $page;
	private $perPage;
	private $jobs;
	private $filterInputsValues;
	private $radioLists;
	private $job;

	
	public function startup()
	{
		parent::startup();

		$this->radioLists = [
			(object)[
				"name" => "location",
				"function" => "getLocationsOfJobs"
			],
			(object)[
				"name" => "work_type",
				"function" => "getWorkTypesOfJobs",
				"translate" => (object)[
					"path" => "messages.contractType."
				]
			],
			(object)[
				"name" => "field_of_work",
				"function" => "getFieldOfWorkOfJobs",
				"translate" => (object)[
					"path" => "messages.fieldOfWorks."
				]
			]
		];
	}


	public function actionDefault($perPage = 20)
	{
		$this->perPage = $perPage;
		self::setFormItems($this->main->openedJobs);
	}


	public function renderDefault()
	{
		list($jobs, $this->template->templateFilter) = self::getFilteredJobs($this->filter, true);

		// paginator
		$jobsCount = $jobs->count();
		$paginator = new Nette\Utils\Paginator;
		$paginator->setItemCount($jobsCount);
		$paginator->setItemsPerPage($this->perPage);
		$paginator->setPage($this->page);

		// form values
		if ($this->isAjax()) {
			self::setFormItems($jobs);
		}

		// template variables
		$this->template->jobs = $jobs->limit($paginator->getLength(), $paginator->getOffset());
		$this->template->jobsCount = $jobsCount;
		$this->template->paginator = $paginator;
		$this->template->filter = $this->filter;

		if (isset($this->filter["salary"])) {
			$this->template->templateFilter["salary"] = "od " . $this->filter["salary"] . "€";
		}
	}


	private function getFilteredJobs($filter, $setTemplateFilter = false)
	{
		$jobs = $this->main->openedJobs->order("publication_date DESC");
		$templateFilter = [];

		// filtrovanie		
		if (isset($filter["location"])) {
			$jobs->where([
				":job_location.location" => $filter["location"]
			]);

			if ($setTemplateFilter) {
				$templateFilter["location"] = $filter["location"];
			}
		}

		if (isset($filter["work_type"])) {
			$jobs->where([
				"contract_type_id" => $filter["work_type"]
			]);

			if ($setTemplateFilter) {
				$templateFilter["work_type"] = $this->translator->translate("messages.contractType." . $filter["work_type"]);
			}
		}

		if (isset($filter["field_of_work"])) {
			$jobs->where([
				"field_of_work_id" => $filter["field_of_work"]
			]);

			if ($setTemplateFilter) {
				$templateFilter["field_of_work"] = $this->translator->translate("messages.fieldOfWorks." . $filter["field_of_work"]);
			}
		}

		if (isset($filter["salary"])) {
			$jobs->where([
				"salary_order_min >= ?" => $filter["salary"]
			]);
		}

		return [$jobs, $templateFilter];
	}


	private function setFormItems($jobs)
	{
		$jobs_id_array = $jobs->fetchPairs("id", "id");

		foreach($this->radioLists as $object) {
			$fnc_name = $object->function;

			if (isset($this->filter[$object->name])) {
				$newFilter = $this->filter;
				unset($newFilter[$object->name]);

				$jia = self::getFilteredJobs($newFilter)[0]->fetchPairs("id", "id");
			} else {
				$jia = $jobs_id_array;
			}

			$this->filterInputsValues[$object->name] = $this->main->$fnc_name($jia);

			if (isset($object->translate)) {
				foreach ($this->filterInputsValues[$object->name] as $key => $value) {
					$this->filterInputsValues[$object->name][$key] = $this->translator->translate($object->translate->path . $value);
				}
			}
		}

		// mzda
		$newFilter = $this->filter;

		if (isset($newFilter["salary"])) {
			unset($newFilter["salary"]);
			$jia = self::getFilteredJobs($newFilter)[0]->fetchPairs("id", "id");
		} else {
			$jia = $jobs_id_array;
		}

		if ($this->isAjax()) {
			foreach($this->filterInputsValues as $key => $value) {
				$this['filterForm'][$key]->setItems($value);
			}
		}
		
		if ($this->isAjax() && !empty($this->filter)) {
			$this['filterForm']->setDefaults($this->filter);
		}
	}


	protected function createComponentFilterForm()
	{
		$form = new Nette\Application\UI\Form;

		$form->getElementPrototype()->class = "ajax";

		$locations = $this->filterInputsValues["location"];
		$form->addRadioList("location", "", $locations);

		$work_types = $this->filterInputsValues["work_type"];
		$form->addRadioList("work_type", "", $work_types);

		$fields_of_work = $this->filterInputsValues["field_of_work"];
		$form->addRadioList("field_of_work", "", $fields_of_work);

		$form->addText("salary", "")
			->setAttribute("class", "form-control");

		$form->onSuccess[] = function($form, $values)
		{
			$this->filter = [];
			foreach ($values as $key => $value) {
				if (!empty($values->$key)) {
					$this->filter[$key] = $value;
				}
			}

			if ($this->isAjax()) {
				$this->redrawControl("jobs");
			}
		};

		return $form;
	}


	public function handlePage($page = 1, $filter = [])
	{
		$this->page = $page;
		$this->filter = $filter;

		if ($this->isAjax()) {
			$this->redrawControl("jobs");
		}
	}


	public function handleDeleteFromFilter($value = "", $filter = [])
	{
		unset($filter[$value]);
		$this->filter = $filter;

		if ($this->isAjax()) {
			$this->redrawControl("jobs");
		}
	}


	public function actionDetail($id = null)
	{
		$job_row = $this->main->jobs->get($id);

		if (!$job_row) {
			$this->redirect("Jobs:default");
		}

		$job = new App\Entity\Job($job_row, $this->translator);
		$this->template->job = $this->job = $job;
		$this->template->company = $job->company;
		$this->template->locations_string = implode(",",$job->locations->fetchPairs("location", "location"));

		$this->template->url = $this->getHttpRequest()->getUrl();
	}


	public function renderDetail($id = null)
	{
		if (!is_null($this->job->expiration_date)) {
			$this->flashMessage("Táto pracovná príležitosť je už uzavretá", "info");
		}
	}


	protected function createComponentSharingButtons()
	{
		$url = $this->getHttpRequest()->getUrl();
		$text = $this->job->name . " @ " . $this->job->company->name;
		$image = $this->job->title_image;
		return new App\Components\SharingButtons($url, $text, $image);
	}
}