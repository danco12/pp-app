<?php
namespace Group;

class Group
{
	private const EMAILS_TABLE = "fb_group_emails";
	private const POSTING_TABLE = "fb_group_posting";

	private $database;
	private $translator;
	private $group_row;


	/**
	 * Class Constructor
	 * @param    $database   
	 */
	public function __construct($database, $translator)
	{
		$this->database = $database;
		$this->translator = $translator;
	}


	private function getEmailsTable()
	{
		return $this->database->table(self::EMAILS_TABLE);
	}


	private function getPostingTable()
	{
		return $this->database->table(self::POSTING_TABLE)->where([
			"fb_group_email_id" => $this->id
		]);
	}


	private function getOpenedJobs()
	{
		return $this->database->table("jobs")->where([
			"publication_date IS NOT NULL",
			"expiration_date IS NULL"
		]);
	}


	protected function initGroup()
	{
		$this->group_row = self::getEmailsTable()->get($this->id);

		if (!$this->group_row) {
			\Tracy\Debugger::log("Neexistuje riadok s ID: " . $this->id . " v tabulke " . self::EMAILS_TABLE, "fbGroupShare");
			throw new \Exception("Neexistuje riadok s ID: " . $this->id . " v tabulke " . self::EMAILS_TABLE, 1);
			
		}
	}


	public function start()
	{
		$can_share = self::canShare();

		if ($can_share === false) {
			return;
		}

		$count_posts = rand(1, $this->getPostLimit());

		for ($i=0; $i < $count_posts; $i++) { 
			$job = self::getJobForPosting();
			self::makePost($job);
		}

		return true;
	}

	
	public function canShare()
	{
		$last_post = $this->group_row->related(self::POSTING_TABLE)->order("created DESC")->fetch();

		if (!$last_post) {
			return true;
		}

		$now = date("Y-m-d H:i:s");
		$before_interval_string = date("Y-m-d H:i:s", strtotime($this->getTimeIntervalBetweenPosts(), strtotime($now)));
		$before_interval = new \DateTime($before_interval_string);
		if ($last_post->created > $before_interval) {
			return false;
		}

		$rand_number = rand (1,10);
		if ($rand_number !== 3) {
			return false;
		}

		return true;
	}

	private function makePost($job)
	{
		if (!$job) {
			return false;
		}

		$job_class = new \App\Entity\Job($job, $this->translator);

		$company_name = $job_class->company->name;
		$job_salary = $job_class->getSalaryString();
		$job_locations = implode(",",$job_class->locations->fetchPairs("location", "location"));
		$job_link = $job_class->getLink();

		$other_salary = "";
		$job_other_salary = $job_class->salary_info;
		if (!empty($job_other_salary)) {
			$other_salary = "<br>" . $job_other_salary;
		}

		$mailer = \App\Model\Mailer::getMailer();
		$mail = new \Nette\Mail\Message;

		$mail->setFrom('vanco.dano@gmail.com');
		$mail->addTo($this->group_row->email);
		$mail->addTo('vanco.dano@gmail.com');
		$mail->setHtmlBody("<b>" . $job_class->name . "</b><br>$company_name <br> 🌎 $job_locations ".($job_class->contract_type_id != 3 ? "<br> " . $this->translator->translate("messages.contractType." . $job_class->contract_type_id) : "") . " <br> 💰 $job_salary $other_salary <br> Viac informácií nájdete tu: $job_link");

		$mailer->send($mail);

		self::getPostingTable()->insert([
			"job_id" => $job->id,
			"fb_group_email_id" => $this->id
		]);
	}


	private function getJobForPosting()
	{
		$before_7_days = date('Y-m-d H:i:s', strtotime('-7 day', strtotime(date("Y-m-d H:i:s"))));

		$posted_jobs = self::getPostingTable()->where([
			"created >= ?" => $before_7_days
		])->fetchPairs("job_id", "job_id");

		$jobs = self::getOpenedJobs()->where([
			"title_image IS NOT NULL",
			"title_image != ?" => ""
		]);

		$last_field_of_work_row = self::getPostingTable()->order("created DESC")->fetch();

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