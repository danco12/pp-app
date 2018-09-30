<?php
namespace App\Model\CronTasks;

use Nette;
use Nette\Database\Helpers;

/**
 * LogQueries for Nette\Database.
 */
class LogQueries
{
	use Nette\SmartObject;

	/** @var int */
	public $maxQueries = 100;

	/** @var string */
	public $name;

	/** @var bool|string explain queries? */
	public $explain = true;

	/** @var bool */
	public $disabled = false;

	/** @var int logged time */
	private $totalTime = 0;

	/** @var int */
	private $count = 0;

	/** @var array */
	private $queries = [];


	public function __construct(Nette\Database\Connection $connection)
	{
		$connection->onQuery[] = [$this, 'logQuery'];
	}


	public function logQuery(Nette\Database\Connection $connection, $result)
	{
		if ($this->disabled) {
			return;
		}
		$this->count++;

		$source = null;
		$trace = $result instanceof \PDOException ? $result->getTrace() : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($trace as $row) {
			if (isset($row['file']) && is_file($row['file'])) {
				if ((isset($row['function']) && strpos($row['function'], 'call_user_func') === 0)
					|| (isset($row['class']) && is_subclass_of($row['class'], '\\Nette\\Database\\Connection'))
				) {
					continue;
				}
				$source = [$row['file'], (int) $row['line']];
				break;
			}
		}
		if ($result instanceof Nette\Database\ResultSet) {
			$this->totalTime += $result->getTime();
			if ($this->count < $this->maxQueries) {
				$this->queries[] = [$connection, $result->getQueryString(), $result->getParameters(), $source, $result->getTime(), $result->getRowCount(), null];
			}

		} elseif ($result instanceof \PDOException && $this->count < $this->maxQueries) {
			$this->queries[] = [$connection, $result->queryString, null, $source, null, null, $result->getMessage()];
		}
	}

	public function getCount()
	{
		return $this->count;
	}

	public function getTotalTime()
	{
		return $this->totalTime;
	}

	public function getQueries()
	{
		return $this->queries;
	}

}
