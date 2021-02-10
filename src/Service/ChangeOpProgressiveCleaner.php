<?php

namespace Combodo\iTop\Extension\ChangeCleaner\Service;

use CMDBSource;
use IssueLog;

class ChangeOpProgressiveCleaner extends AbstractChangeOpCleaner implements \iBackgroundProcess{
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