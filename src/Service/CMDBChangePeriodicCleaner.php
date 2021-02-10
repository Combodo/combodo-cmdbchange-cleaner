<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

use CMDBSource;
use IssueLog;

class CMDBChangePeriodicCleaner implements \iBackgroundProcess{
    use CMDBChangeCleaner;

    public function GetPeriodicity()
	{
		return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'date_update_interval', 1);
	}

	/**
	 * @return int
	 */
	public function GetBulkSize()
	{
		return (int) \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'progressive_bulk_delete_size', 0);
	}

	public function Process($iUnixTimeLimit)
	{
		return $this->BulkDelete($this->GetBulkSize());
	}
}