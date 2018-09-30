<?php
namespace App\Model;

use Nette;

class AccessLog
{
	use Nette\SmartObject;
    
    /**
     * @var Nette\Database\Context
     */
    private $database;

    private $presenter;
    private $isAjax;
    private $referer;
    private $visitorId;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


    public function getAccessLog()
    {
    	return $this->database->table("access_log");
    }


    public function getAccessLogUrls()
    {
    	return $this->database->table("access_log_urls");
    }


	public function init($presenter)
	{
		$this->presenter = $presenter;
		$this->isAjax = $presenter->isAjax();
		self::setVisitorId();

		if ($presenter->getParameter("do") == "verifyAcc") {
			return false;
		}

		if ($presenter->name == "Cron") {
			return false;
		}

		self::setReferer();

		$page_row = self::getUrlRow($this->presenter->getHttpRequest()->getUrl());

		if ($this->referer) {
			$referer_row = self::getUrlRow($this->referer);
		}

		$job_id = null;

		if ($this->presenter->name == "Jobs" && $this->presenter->action == "detail") {
			$job_id = $presenter->getParameter("id");

			if (!$this->database->table("jobs")->get($job_id)) {
				$job_id = null;
			}
		}

		$data = [
			"ip" => $presenter->getHttpRequest()->getRemoteAddress(),
			"page" => $page_row->id,
			"referer" => !empty($referer_row) ? $referer_row->id : null,
			"job_id" => $job_id,
			"is_ajax" => $this->isAjax ? 1 : 0,
			"verified" => $this->isAjax ? 1 : 0,
			"visitor_id"  => $this->visitorId
		];

		$acc_row = self::getAccessLog()->insert($data);

		return $acc_row->id;
	}


	public function setVerified($id)
	{
		if ($this->isAjax) {
			$acc_row = self::getAccessLog()->where("id", $id)->fetch();
			if ($acc_row && $acc_row->ip == $this->presenter->getHttpRequest()->getRemoteAddress()) {
				$acc_row->update([
					"verified" => 1
				]);
			}
		}
	}


	private function setReferer()
	{
		if(empty($this->presenter->getHttpRequest()->referer)) {
			$this->referer = null;
		} else {
			$this->referer = $this->presenter->getHttpRequest()->referer->absoluteUrl;
		}
	}


	private function getUrlRow($url)
	{
		$page_row = self::getAccessLogUrls()->where("url", $url)->fetch();
		if (!$page_row) {
			$page_row = self::getAccessLogUrls()->insert([
				"url" => $url
			]);
		}

		return $page_row;
	}


	public function setVisitorId($id = null)
	{
		if($id) {
			$this->visitorId = $id;
		}

		$this->visitorId = $this->presenter->getHttpRequest()->getCookie('visitor_id');

		if (empty($this->visitorId)) {
			$this->visitorId = hash("crc32b", uniqid());
			$this->setCookie('visitor_id', $this->visitorId);
		}
	}


	public function setCookie($name, $value, $duration = '+10 years', $path = '/')
	{
		$this->presenter->getHttpResponse()->setCookie($name, $value, $duration, $path, null);
	}


	public function updateViews()
	{
		$before_1_hour = date('Y-m-d H:i:s', strtotime('-1 hour', strtotime(date("Y-m-d H:i:s"))));

		$new_views_array = self::getAccessLog()->select("job_id, COUNT(id) AS pocet")->where([
			"created > ?" => $before_1_hour,
			"job_id IS NOT NULL",
			"is_ajax" => 0
		])->group("job_id")->fetchPairs("job_id", "pocet");

		foreach ($new_views_array as $job_id => $views) {
			$job = $this->database->table("jobs")->where("id", $job_id)->fetch();
			$job->update([
				"views" => $job->views + $views
			]);
		}
	}
}