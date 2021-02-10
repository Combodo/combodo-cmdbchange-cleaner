<?php
/**
 * Copyright (C) 2013-2019 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

const EXIT_CODE_ERROR = -1;
const EXIT_CODE_FATAL = -2;

if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));

$sPathPrefix="";
$bFound = false;
for($i=0;$i<=4; $i++){
    $sFilename = __DIR__ . "$sPathPrefix/approot.inc.php";
    if (file_exists($sFilename)){
        require_once($sFilename);
        $bFound = true;
        break;
    }
    $sPathPrefix .= "../";
}
if (! $bFound){
    echo "iTop file approot.inc.php not found. Please install this script in iTop directory. Exiting...\n";
    exit(EXIT_CODE_FATAL);
}

// early exit
if (file_exists(READONLY_MODE_FILE)) {
    echo "iTop is read-only. Exiting...\n";
    exit(EXIT_CODE_ERROR);
}

require_once(APPROOT . '/application/application.inc.php');
$sConfigFile = APPCONF . ITOP_DEFAULT_ENV . '/' . ITOP_CONFIG_FILE;
if (!file_exists($sConfigFile)) {
    echo "iTop is not yet installed. Exiting...\n";
    exit(EXIT_CODE_ERROR);
}

require_once(APPROOT . '/application/startup.inc.php');

function ReadMandatoryParam($oP, $sParam, $sSanitizationFilter = 'parameter') {
    $sValue = utils::ReadParam($sParam, null, true, $sSanitizationFilter);
    if (is_null($sValue)) {
        $oP->p("ERROR: Missing argument '$sParam'\n");
        UsageAndExit($oP);
    }

    return trim($sValue);
}

function UsageAndExit($oP) {
    $bModeCLI = ($oP instanceof CLIPage);

    if ($bModeCLI) {
        $oP->p("USAGE:\n");
        $oP->p("php change.bulkcleaner.php --auth_user=<login> --auth_pwd=<password> --bulk_size=<bulk_size> [-count_lines_limit=<count_lines_limit>] [--param_file=<file>]\n");
    } else {
        $oP->p("Optional parameters: verbose, param_file, status_only\n");
    }
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

function BulkDelete($iBulkSize)
{
    if ($iBulkSize == 0){
        return "Nothing to do";
    }

    $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

    $aIds= GetIds($sPrefix, $iBulkSize);

    if (count($aIds) == 0){
        return "No CMDBChange row to delete.";
    }

    $sBulkDelete = sprintf("DELETE FROM %spriv_change WHERE id IN (%s)",
        $sPrefix,
        implode(',', $aIds)
    );

    ExecuteQuery($sBulkDelete);

    $sMsg= sprintf("%d CMDBChange row(s) deleted.", count($aIds));
    IssueLog::Info($sMsg);
    return $sMsg;
}

/**
 * @param $sSqlQuery
 *
 * @return \mysqli_result
 * @throws \CoreException
 * @throws \MySQLException
 * @throws \MySQLHasGoneAwayException
 */
function ExecuteQuery($sSqlQuery){
    IssueLog::Info($sSqlQuery);
    $fStartTime = microtime(true);
    /** @var \mysqli_result $oQueryResult */
    $oQueryResult = CMDBSource::Query($sSqlQuery);
    $fElapsed = microtime(true) - $fStartTime;
    IssueLog::Info(sprintf("Query executed in %.3f s",  $fElapsed));
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

    $oQueryResult = ExecuteQuery($sSqlQuery);

    $aIds = [];
    while($aRow = $oQueryResult->fetch_array()){
        $aIds[] = $aRow['id'];
    }

    return $aIds;
}

/**
 * @param $sPrefix
 * @param int $iLimit
 * @return int
 * @throws CoreException
 * @throws MySQLException
 * @throws MySQLHasGoneAwayException
 */
function CountLinesToRemove($iLimit=0){
    if ($iLimit==0){
        return -1;
    }

    $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

    $sSqlQuery = <<<SQL
SELECT count(*) AS count FROM 
(SELECT co.id FROM ${sPrefix}priv_change AS c 
LEFT JOIN ${sPrefix}priv_changeop AS co 
ON co.changeid = c.id 
WHERE co.id IS NULL
LIMIT
) AS t;
SQL;

    if ($iLimit != -1){
        $sSqlQuery = str_replace("LIMIT", " LIMIT {$iLimit} ", $sSqlQuery);
    } else {
        $sSqlQuery = str_replace("LIMIT", "", $sSqlQuery);
    }

    $oQueryResult = ExecuteQuery($sSqlQuery);

    while($aRow = $oQueryResult->fetch_array()){
        return (int) $aRow['count'];
    }

    return 0;
}

function ProvisionDb($iMax){
    $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

    for ($i=0;$i<$iMax;$i++){
        $sSqlQuery = <<<SQL
	INSERT INTO {$sPrefix}priv_change (`userinfo`, `date`, `origin`) 
	values
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing'),
	("test{$i}", NOW(), 'email-processing');
SQL;

        ExecuteQuery($sSqlQuery);
    }

    return $iMax * 10;
}

if (!utils::IsModeCLI()){
    echo "Not CLI mode. Exiting...\n";
    exit(EXIT_CODE_ERROR);
}

$oP = new CLIPage("iTop - change.bulkcleaner");

try
{
    utils::UseParamFile();

    $bVerbose = utils::ReadParam('verbose', false, true /* Allow CLI */);
    $bDebug = utils::ReadParam('debug', false, true /* Allow CLI */);

    $sAuthUser = ReadMandatoryParam($oP, 'auth_user', 'raw_data');
    $sAuthPwd = ReadMandatoryParam($oP, 'auth_pwd', 'raw_data');
    if (UserRights::CheckCredentials($sAuthUser, $sAuthPwd))
    {
        UserRights::Login($sAuthUser); // Login & set the user's language
    }
    else
    {
        $oP->p("Access wrong credentials ('$sAuthUser')");
        $oP->output();
        exit(EXIT_CODE_ERROR);
    }

    if (!UserRights::IsAdministrator())
    {
        $oP->p("Access restricted to administrators");
        $oP->Output();
        exit(EXIT_CODE_ERROR);
    }
}
catch (Exception $e)
{
    $oP->p("Error: ".$e->GetMessage());
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

try
{
    $oMutex = new iTopMutex('dbcleanup');
    if (!MetaModel::DBHasAccess(ACCESS_ADMIN_WRITE))
    {
        $oP->p("A maintenance is ongoing");
    }
    else
    {
        if ($oMutex->TryLock())
        {
            $iBulkDelete = ReadMandatoryParam($oP, 'bulk_size', 'integer');

            $iCountLimit = utils::ReadParam('count_lines_limit', 0, true, 'integer');

            //$iCreatedCount = ProvisionDb(100);
            //return "ProvisionDb $iCreatedCount added lines";

            $iCount = CountLinesToRemove($iCountLimit);
            if ($iCount !== -1){
                if ($iCount < $iCountLimit){
                    $oP->p("Found exactly $iCount CMDBChange row(s) to delete");
                }else{
                    $oP->p("Found at least $iCount CMDBChange row(s) to delete");
                }
            }

            $oP->p(BulkDelete($iBulkDelete));
        }
        else
        {
            // Exit silently
            $oP->p("Already running...");
        }
    }
}
catch (Exception $e)
{
    $oP->p("ERROR: '".$e->getMessage()."'");
    if ($bDebug)
    {
        // Might contain verb parameters such a password...
        $oP->p($e->getTraceAsString());
    }
}
finally
{
    try
    {
        $oMutex->Unlock();
    }
    catch (Exception $e)
    {
        $oP->p("ERROR: '".$e->getMessage()."'");
        if ($bDebug)
        {
            // Might contain verb parameters such a password...
            $oP->p($e->getTraceAsString());
        }
    }
}

$oP->p("Exiting: ".time().' ('.date('Y-m-d H:i:s').')');
$oP->Output();