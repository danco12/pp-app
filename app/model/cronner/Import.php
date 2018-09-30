<?php 
namespace App\Model\CronTasks;

class Import
{

    /**
     * @var \App\Model\Import
     */
    public $model;

    private $presenter;

    public function __construct(\App\Model\ImportService $model, $presenter)
    {
        $this->model = $model;
        $this->presenter = $presenter;
    }

    /**
     * @cronner-task Import jobs
     * @cronner-period 1 hour
     */
    public function importJobsFromJobangels()
    {
        $this->model->import;
    }

    /**
     * @cronner-task Import exchange rates
     * @cronner-period 1 day
     */
    public function exchangeRates()
    {
        $this->presenter->main->updateExchangeRates();
    }

}