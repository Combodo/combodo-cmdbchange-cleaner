<?php

namespace Combodo\iTop\Extension\ChangeCleaner\Service;

use CMDBSource;
use DateTime;
use IssueLog;

class ChangeOpMassiveCleaner extends \AbstractWeeklyScheduledProcess implements \iScheduledProcess {
    public function Process($iUnixTimeLimit)
    {
        return $this->BulkDelete($this->GetBulkSize());
    }

    protected function GetModuleName() {
        return 'combodo-change-cleaner';
    }

    /**
     * @return string default value for {@link MODULE_SETTING_TIME} config param.
     *         example '23:30'
     */
    protected function GetDefaultModuleSettingTime() {
        return \MetaModel::GetModuleSetting('combodo-change-cleaner', 'massive_bulk_delete_time', '16:50to remove orphan records');
    }

	/**
	 * @return int
	 */
	public function GetBulkSize()
	{
		return (int) \MetaModel::GetModuleSetting('combodo-change-cleaner', 'massive_bulk_delete_size', 1);
	}
}