<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

class CMDBChangePeriodicCleaner implements \iBackgroundProcess
{
	use CMDBChangeCleaner;

	public function GetPeriodicity()
	{
		return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'cleaning_periodicity',
			CMDBChangeCleanerBackgroundProcessesDefaults::PERIODIC_PERIODICITY);
	}

	/**
	 * @return int
	 */
	public function GetBulkSize()
	{
		return (int)\MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'periodic_cleaning_bulk_size',
			CMDBChangeCleanerBackgroundProcessesDefaults::PERIODIC_CLEANING_BULK_SIZE);
	}

	public function IsDebug()
	{
		return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'debug',
			CMDBChangeCleanerBackgroundProcessesDefaults::DEBUG);
	}

	public function Process($iUnixTimeLimit)
	{
		return $this->BulkDelete($this->GetBulkSize(), $this->IsDebug());
	}
}