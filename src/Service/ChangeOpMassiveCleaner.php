<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

use CMDBSource;
use DateTime;
use IssueLog;

class ChangeOpMassiveCleaner extends \AbstractWeeklyScheduledProcess implements \iScheduledProcess {
    public function Process($iUnixTimeLimit)
    {
        return $this->BulkDelete($this->GetBulkSize());
    }

    protected function GetModuleName() {
        return 'combodo-cmdbchange-cleaner';
    }

    /**
     * @return string default value for {@link MODULE_SETTING_TIME} config param.
     *         example '23:30'
     */
    protected function GetDefaultModuleSettingTime() {
        return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'massive_bulk_delete_time', '00:00');
    }

	/**
	 * @return int
	 */
	public function GetBulkSize()
	{
		return (int) \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'massive_bulk_delete_size', 0);
	}
}