<?php

namespace Combodo\iTop\Extension\ChangeCleaner\Service;

use CMDBSource;
use IssueLog;

abstract class AbstractChangeOpCleaner {
    /**
     * @param $iBulkSize
     * @return string
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    public function BulkDelete($iBulkSize)
    {
        $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

        $aIds= $this->GetIds($sPrefix, $iBulkSize);

        if (count($aIds) == 0){
            return "No CMDBChange row to delete.";
        }

        $sBulkDelete = sprintf("DELETE FROM %spriv_change WHERE id IN (%s)",
            $sPrefix,
            implode(',', $aIds)
        );

        $this->ExecuteQuery($sBulkDelete, "Bulk deletion query of $iBulkSize row(s)");

        $sMsg= sprintf("%d CMDBChange row(s) deleted.", count($aIds));
        IssueLog::Info($sMsg);
        return $sMsg;
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
    function ExecuteQuery($sSqlQuery, $sLogMessage){
        IssueLog::Debug($sSqlQuery);
        $fStartTime = microtime(true);
        /** @var \mysqli_result $oQueryResult */
        $oQueryResult = CMDBSource::Query($sSqlQuery);
        $fElapsed = microtime(true) - $fStartTime;
        IssueLog::Info(sprintf("%s : executed in %.3f s", $sLogMessage, $fElapsed));
        return $oQueryResult;
    }

    /**
     * @param string $sPrefix
     * @param int $iBulkSize
     *
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    function GetIds($sPrefix, $iBulkSize){
        $sSqlQuery = <<<SQL
SELECT c.id as id FROM ${sPrefix}priv_change AS c 
LEFT JOIN ${sPrefix}priv_changeop AS co ON co.changeid = c.id 
WHERE co.id IS NULL
ORDER BY id DESC
LIMIT {$iBulkSize};
SQL;

        $oQueryResult = $this->ExecuteQuery($sSqlQuery, "Get CMDBChange $iBulkSize ID(s) to remove");

        $aIds = [];
        while($aRow = $oQueryResult->fetch_array()){
            $aIds[] = $aRow['id'];
        }

        return $aIds;
    }

}