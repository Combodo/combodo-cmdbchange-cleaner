<?php /** @noinspection ClassReImplementsParentInterfaceInspection */

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

if (class_exists("AbstractWeeklyScheduledProcess")) {
	class CMDBChangeScheduledCleaner extends \AbstractWeeklyScheduledProcess implements \iScheduledProcess
	{
		use CMDBChangeCleaner;

		const MODULE_SETTING_WEEKDAYS = 'scheduled_cleaning_week_days';

		public function Process($iUnixTimeLimit)
		{
			ini_set('max_execution_time', max(3600, ini_get('max_execution_time')));

			return $this->BulkDelete($this->GetBulkSize(), $this->IsDebug());
		}

		protected function GetModuleName()
		{
			return 'combodo-cmdbchange-cleaner';
		}

		/**
		 * @return string default value for {@link MODULE_SETTING_TIME} config param.
		 *         example '23:30'
		 */
		protected function GetDefaultModuleSettingTime()
		{
			return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'scheduled_cleaning_time',
				CMDBChangeCleanerBackgroundProcessesDefaults::SCHEDULED_TIME);
		}

		/**
		 * @return int
		 */
		public function GetBulkSize()
		{
			return (int)\MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'scheduled_cleaning_bulk_size',
				CMDBChangeCleanerBackgroundProcessesDefaults::SCHEDULED_BULK_SIZE);
		}

		public function IsDebug()
		{
			return \MetaModel::GetModuleSetting('combodo-cmdbchange-cleaner', 'debug',
				CMDBChangeCleanerBackgroundProcessesDefaults::DEBUG);
		}
	}
}