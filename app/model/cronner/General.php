<?php 
namespace App\Model\CronTasks;

class General
{
    private $presenter;

    public function __construct($presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @cronner-task Update views
     * @cronner-period 1 hour
     */
    public function updateViews()
    {
        $this->presenter->access_log->updateViews();
    }

    /**
     * @cronner-task Post to facebook group
     * @cronner-period 1 minute
     * @cronner-time 05:00 - 23:00
     */
    public function postTogroup()
    {
        return;
        $this->presenter->main->cron_share($this->presenter->translator);
    }

}