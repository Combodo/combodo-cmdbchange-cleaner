<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;


/**
 * @since 1.0.2 N°4486 factorize module parameters default values to this class
 */
class CMDBChangeCleanerBackgroundProcessesDefaults
{
	const DEBUG = false; // debug

	// consts for {@see \Combodo\iTop\Extension\CMDBChangeCleaner\Service\CMDBChangePeriodicCleaner}
	const PERIODIC_PERIODICITY        = 60 * 60; // cleaning_periodicity
	const PERIODIC_CLEANING_BULK_SIZE = 5000; // periodic_cleaning_bulk_size

	// consts for {@see \Combodo\iTop\Extension\CMDBChangeCleaner\Service\CMDBChangeScheduledCleaner}
	const SCHEDULED_TIME      = '00:00'; // scheduled_cleaning_time
	const SCHEDULED_BULK_SIZE = 0; // scheduled_cleaning_bulk_size
}