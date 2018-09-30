<?php
namespace App\Model;

use Nette;

/**
 * @property-read Nette\Database\ResultSet $openedJobs
 * @property-read Nette\Database\ResultSet $jobs
 */
class Main
{
	use Nette\SmartObject;
    
    /**
     * @var Nette\Database\Context
     */
    private $database;

 
    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


	public static function getIp()
	{
		if (getenv('HTTP_CLIENT_IP'))
			$ip = getenv('HTTP_CLIENT_IP');
		else if(getenv('HTTP_X_FORWARDED_FOR'))
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		else if(getenv('HTTP_X_FORWARDED'))
			$ip = getenv('HTTP_X_FORWARDED');
		else if(getenv('HTTP_FORWARDED_FOR'))
			$ip = getenv('HTTP_FORWARDED_FOR');
		else if(getenv('HTTP_FORWARDED'))
		   $ip = getenv('HTTP_FORWARDED');
		else if(getenv('REMOTE_ADDR'))
			$ip = getenv('REMOTE_ADDR');
		else
			$ip = null;

		return $ip;
	}


	public function getDatabase()
	{
		return $this->database;
	}


	public function getJobs()
	{
		return $this->database->table("jobs");
	}


	public function getCompanies()
	{
		return $this->database->table("companies");
	}


	public function getOpenedJobs()
	{
		return $this->database->table("jobs")->where([
			"publication_date IS NOT NULL",
			"expiration_date IS NULL"
		]);
	}


	public function getLocationsOfJobs($jobs)
	{
		return $this->database->table("jobs")->select(":job_location.location AS location, COUNT(:job_location.location) AS pocet")->where([
			"jobs.id IN ?" => $jobs,
			":job_location.closed" => 0
		])->order("pocet DESC")->group("location")->fetchPairs("location", "location");
	}


	public function getWorkTypesOfJobs($jobs)
	{
		return $this->database->table("jobs")->select("contract_type_id, COUNT(contract_type_id) AS pocet")->where([
			"jobs.id IN ?" => $jobs,
		])->order("pocet DESC")->group("contract_type_id")->fetchPairs("contract_type_id", "contract_type_id");
	}


	public function getFieldOfWorkOfJobs($jobs)
	{
		return $this->database->table("jobs")->select("field_of_work_id, COUNT(field_of_work_id) AS pocet")->where([
			"jobs.id IN ?" => $jobs,
		])->order("pocet DESC")->group("field_of_work_id")->fetchPairs("field_of_work_id", "field_of_work_id");
	}


	public static function initVersion($src)
	{
		$file = __DIR__ . '/../../www' . $src;
		if(file_exists($file)) {
			return $src . '?v=' . filemtime($file);
		} else {
			return $src;
		}
	}


	public function updateExchangeRates()
	{
		$xml = simplexml_load_file("http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");

		if (isset($xml->Cube->Cube->Cube)){
			$data = [];
			foreach ( $xml->Cube->Cube->Cube as $fx ) {
				$data[] = [
					"rate" => $fx["rate"],
					"currency" => $fx["currency"]
				];
			}
			$this->database->query('INSERT INTO exchange_rate ? ON DUPLICATE KEY UPDATE rate = VALUES(rate)', $data);
		}
	}


	public function cron_share($translator)
	{
		$x = new \Group\PonukyPraceJA($this->database, $translator);
		$x->start();
	}


	public function makeFacebookPostToGroup($translator)
	{
		$job = self::getJobToPost();

		if (!empty($job)) {
			$job_class = new \App\Entity\Job($job, $translator);

			$company_name = $job_class->company->name;
			$job_salary = $job_class->getSalaryString();
			$job_locations = implode(",",$job_class->locations->fetchPairs("location", "location"));
			$job_link = $job_class->getLink();

			$mailer = Mailer::getMailer();

			$mail = new Nette\Mail\Message;

			$mail->setFrom('vanco.dano@gmail.com');
			$mail->addTo('najkrajsieponukyprace@groups.facebook.com');
			$mail->addTo('vanco.dano@gmail.com');
			$mail->setHtmlBody("<b>" . $job_class->name . "</b><br>$company_name <br> 🌎 $job_locations ".($job_class->contract_type_id != 3 ? "<br> " . $translator->translate("messages.contractType." . $job_class->contract_type_id) : "") . " <br> 💰 $job_salary <br> Viac informácií nájdete tu: $job_link");

			$mailer->send($mail);

			$this->database->table("fb_group_posting")->insert([
				"job_id" => $job->id,
				"fb_group_email_id" => 1
			]);
		}
	}


	public function getJobToPost()
	{
		$before_7_days = date('Y-m-d H:i:s', strtotime('-7 day', strtotime(date("Y-m-d H:i:s"))));
		$posted_jobs = $this->database->table("fb_group_posting")->where([
			"created >= ?" => $before_7_days
		])->fetchPairs("job_id", "job_id");

		$jobs = self::getOpenedJobs()->where([
			"expiration_date IS NULL",
			"title_image IS NOT NULL",
			"title_image != ?" => ""
		]);

		$last_field_of_work_row = $this->database->table("fb_group_posting")->order("created DESC")->fetch();

		if ($last_field_of_work_row) {
			$tmp_job = $last_field_of_work_row->ref("job_id");
			
			if ($tmp_job) {
				$jobs = $jobs->where("field_of_work_id != ?", $tmp_job->field_of_work_id);
			}
		}

		if (!empty($posted_jobs)) {
			$jobs = $jobs->where("id NOT IN (?)", $posted_jobs);
		}

		$jobs_array = [];
		foreach($jobs as $j) {
			for($i = 0; $i < $j->percentil; $i++) {
				$jobs_array[] = $j->id;
			}
		}

		$job = self::getOpenedJobs()->where("id", $jobs_array[array_rand ($jobs_array)])->fetch();

		return $job;
	}

}