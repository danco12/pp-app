<?php

namespace App\Presenters;


use Nette;


class BasePresenter extends Nette\Application\UI\Presenter
{
	/** 
	 * @persistent
	 */
	public $locale;

	
	/**
	 * @var \Kdyby\Translation\Translator
	 * @inject
	 */
	public $translator;

	
	/**
     * @var \App\Model\Main
     * @inject
     */
    public $main;

    
    /**
     * @var \App\Model\AccessLog
     * @inject
     */
    public $access_log;

    
    /**
     * @var \App\Model\Mailer
     * @inject
     */
    public $mailer;

	
	/**
     * @var \App\Model\ImportService
     * @inject
     */
    public $importService;


	public function startup()
	{
		parent::startup();

		$this->template->acclogId = $this->access_log->init($this);
		// $this->importService->import; exit;

		$less = new \lessc;
		$less->checkedCompile(__DIR__ . "/../compile/css/style.less", __DIR__ . "/../../www/css/style.css");
	}

	
	public function handleVerifyAcc($acc_id)
	{
		if ($this->isAjax()) {
			$this->access_log->setVerified($acc_id);
		}

		$this->terminate();
	}

	
	protected function createComponentJobListItem()
	{
		return new Nette\Application\UI\Multiplier(function ($jobId)
		{
			$job = new \App\Entity\Job($this->main->jobs->get($jobId), $this->translator);
			return new \App\Components\JobListItem($job);
		});
	}
}