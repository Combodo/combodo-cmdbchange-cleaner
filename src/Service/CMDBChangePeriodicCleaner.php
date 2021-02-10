<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

use CMDBSource;
use IssueLog;

class CMDBChangePeriodicCleaner implements \iBackgroundProcess{
    use CMDBChangeCleaner;

    public function GetPeriodicity()
	{
		return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'cleaning_periodicity', 60);
	}

	/**
	 * @return int
	 */
	public function GetBulkSize()
	{
		return (int) \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'periodic_cleaning_bulk_size', 5000);
	}

	public function Process($iUnixTimeLimit)
	{
		return $this->BulkDelete($this->GetBulkSize());
	}
}