<?php

namespace App\Presenters;

use App\Model\CronTasks as CT;
use stekycz\Cronner\Cronner;
use stekycz\Cronner\Tasks\Task;

class CronPresenter extends BasePresenter
{
	/**
	 * @var \stekycz\Cronner\Cronner
	 * @inject
	*/
	public $cronner;

	private $beginTime;
	private $data;
	private $pom_db_requests = 0;
	private $pom_db_time = 0;
	private $dbLogger;

	
	public function startup()
	{
		$server_ip = "185.33.146.173";
		$actual_ip = \App\Model\Main::getIp();

		\Tracy\Debugger::enable(\Tracy\Debugger::PRODUCTION);

		if($actual_ip != $server_ip && $actual_ip != '127.0.0.1') {
			\Tracy\Debugger::log("access denied!", "error");
			echo 'Access Denied!';
			$this->terminate();
		}
		
		$this->dbLogger = new CT\LogQueries($this->main->getDatabase()->getConnection());
		parent::startup();
	}

	
	public function actionDefault()
	{
		$this->cronner->addTasks(new CT\Import($this->importService, $this));
		$this->cronner->addTasks(new CT\General($this));

		$this->cronner->onTaskBegin[] = [$this, 'onBegin'];
		$this->cronner->onTaskFinished[] = [$this, 'onFinished'];
		$this->cronner->onTaskError[] = [$this, 'onError'];

		$this->cronner->run();
	}

	
	public function onBegin(Cronner $cronner, Task $task)
	{
		$this->beginTime = microtime(true);
	}

	
	public function onFinished(Cronner $cronner, Task $task)
	{
		$this->data = [
			"name" => $task->getName(),
			"time" => microtime(true) - $this->beginTime,
			"db_time" => $this->dbLogger->getTotalTime() - $this->pom_db_time,
			"db_requests" => $this->dbLogger->getCount() - $this->pom_db_requests,
		];

		$this->pom_db_time += $this->data['db_time'];
		$this->pom_db_requests += $this->data['db_requests'] + 2; // 2 - kvoli db insertu do logu;

		$this->main->getDatabase()->table("cron_duration")->insert($this->data);

		if($this->data['time'] > 20 || $this->data['db_time'] > 2) {
			\Tracy\Debugger::log("Skontroluj tasks " . $task->getName() .", pretože trval príliš dlho:<br>ex: " . $this->data['time'] . '<br>db: ' . $this->data['db_time'] . '<br>req: ' . $this->data["db_requests"], 'cronner');
		}
	}

	
	public function onError(Cronner $cronner, \Exception $e, Task $task)
	{
		\Tracy\Debugger::log($e, 'cronner');
	}

	
	public function beforeRender()
	{
		$this->terminate();
	}
}
