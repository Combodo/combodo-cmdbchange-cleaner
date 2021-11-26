<?php

namespace Combodo\iTop\Extension\CMDBChangeCleaner\Service;

use CMDBSource;
use IssueLog;

trait CMDBChangeCleaner {
	/**
	 * @param $iBulkSize
	 * @param $bDebug
	 *
	 * @return string
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
    public function BulkDelete($iBulkSize, $bDebug)
    {
        $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

        if ($iBulkSize === 0){
            return "Task configured to avoid cleaning (bulk_size=0).";
        }

        $aIds= $this->GetIds($sPrefix, $iBulkSize, $bDebug);

        if (count($aIds) == 0){
            return "No CMDBChange row to delete.";
        }

        $sBulkDelete = sprintf("DELETE FROM %spriv_change WHERE id IN (%s)",
            $sPrefix,
            implode(',', $aIds)
        );

	    $fStartTime = microtime(true);
        $this->ExecuteQuery($sBulkDelete, "Bulk deletion query of $iBulkSize row(s)", $bDebug);
	    $fElapsed = microtime(true) - $fStartTime;

        $sMsg= sprintf("%d CMDBChange row(s) deleted in %.3f s.", count($aIds), $fElapsed);
        $this->HandleLog($sMsg, $bDebug);
        return $sMsg;
    }

	function HandleLog($sMsg, $bDebug){
		if ($bDebug){
			IssueLog::Info($sMsg);
		}else{
			IssueLog::Debug($sMsg);
		}
	}

    /**
     * @param string $sSqlQuery
     * @param string $sLogMessage
     *
     * @return \mysqli_result
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    function ExecuteQuery($sSqlQuery, $sLogMessage, $bDebug){
        $fStartTime = microtime(true);
        /** @var \mysqli_result $oQueryResult */
        $oQueryResult = CMDBSource::Query($sSqlQuery);
        $fElapsed = microtime(true) - $fStartTime;
        $sMsg = sprintf("[%s] %s : executed in %.3f s",
            (new \ReflectionClass($this))->getShortName(),
            $sLogMessage,
            $fElapsed);
        $this->HandleLog($sMsg, $bDebug);

        return $oQueryResult;
    }

	/**
	 * @param string $sPrefix
	 * @param int $iBulkSize
	 *
	 * @param $bDebug
	 *
	 * @return array
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
    function GetIds($sPrefix, $iBulkSize, $bDebug){
	    // N°4486 don't clean very recent CMDBChange as they might still be ongoing operations creating corresponding CMDBChangeOp
	    $oAnHourAgo = new \DateTime('-1 hour');
	    $sAnHourAgo = $oAnHourAgo->format('Y-m-d H:i:s');

	    $sSqlQuery = <<<SQL
SELECT c.id as id 
FROM ${sPrefix}priv_change AS c 
	LEFT JOIN ${sPrefix}priv_changeop AS co ON co.changeid = c.id 
WHERE co.id IS NULL 
	AND c.date < '{$sAnHourAgo}'
ORDER BY id DESC
LIMIT {$iBulkSize};
SQL;

	    $oQueryResult = $this->ExecuteQuery($sSqlQuery, "Get CMDBChange $iBulkSize ID(s) to remove", $bDebug);

	    $aIds = [];
	    while ($aRow = $oQueryResult->fetch_array()) {
		    $aIds[] = $aRow['id'];
	    }

        return $aIds;
    }

}