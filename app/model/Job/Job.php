<?php
namespace App\Entity;

use Nette;

class Job
{
	use Nette\SmartObject;

	/**
	 * @var Nette\Database\Table\ActiveRow
	 */
	private $row;

	private $translator;

	/**
	 * Class Constructor
	 */
	public function __construct(Nette\Database\Table\ActiveRow $row, $translator)
	{
		$this->row = $row;
		$this->translator = $translator;
	}

	public function __get($name)
	{
		$item = strtolower(str_replace('get_', '', preg_replace('/([A-Z])/','_$1', $name)));

		$result = null;

		if ($item == "company") {
			$result = new Company($this->row->ref("company_id"));
		} elseif ($item == "locations"){
			$result = $this->row->related("job_location")->where("closed", 0);
		} elseif ($item == "prax") {
			$prax = $this->row->related("job_years_of_prax")->fetch();
			if ($prax) {
				$result = $prax->years;
			} else {
				$result = -1;
			}
		} elseif($item == "responsibilities") {
			return $this->row->related("job_responsibility");
		} elseif ($item == "fields_of_study") {
			return $this->row->related("job_field_of_study")->where("type", "required");
		} elseif ($item == "fields_of_work") {
			return $this->row->related("job_field_of_work")->where("type", "required");
		} elseif ($item == "languages") {
			return $this->row->related("job_language")->where("type", "required");
		} elseif ($item == "skills") {
			return $this->row->related("job_skill")->where("type", "required");
		} elseif ($item == "qualif_other") {
			return $this->row->related("job_qualification_other")->where("type", "required");
		} elseif ($item == "top_benefit") {
			return $this->row->related("job_top_benefit")->order("order ASC");
		} elseif($item == "gallery") {
			return $this->row->related("job_gallery");
		} else {
			$result = $this->row->$item;
		}

		return $result;
	}

	public function getEducation($type)
	{
		return $this->row->related("job_education")->where("type", $type)->fetch();
	}

	public function getSalaryString()
	{
		$string = $this->row->salary_min;
		if ($this->row->salary_max && $this->row->salary_max != $this->row->salary_min) {
			$string .= " - " . $this->row->salary_max;
		} else {
			$string = "od " . $string;
		}

		$string .= " " . $this->row->currency . " / " . $this->translator->translate("messages.timeUnit.".$this->row->salary_time_unit);

		return $string;
	}

	public function getLink()
	{
		return "http://pracovne-prilezitosti.sk/jobs/detail/" . $this->row->id;
	}

}