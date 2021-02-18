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


//WARNING: this script is standalone and can be deployed alone.
// It should not have any outside dependency except iTop core.

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
    $sPathPrefix .= "/..";
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

ini_set('max_execution_time', max(3600, ini_get('max_execution_time')));

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
        PrintWithDateAndTime("ERROR: Missing argument '$sParam'");
        UsageAndExit($oP);
    }

    return trim($sValue);
}

function HandleLog($sMsg, $bDebug){
	if ($bDebug){
		PrintWithDateAndTime("$sMsg");
	}
}

function UsageAndExit($oP) {
    $bModeCLI = ($oP instanceof CLIPage);

    if ($bModeCLI) {
        PrintWithDateAndTime("USAGE:");
        PrintWithDateAndTime("php change.bulkcleaner.php --auth_user=<login> --auth_pwd=<password>  [--param_file=<file>] [--debug=true] --bulk_size=<bulk_size> [--count_lines_limit=<count_lines_limit>]");
    } else {
        PrintWithDateAndTime("Optional parameters: count_lines_limit, param_file, debug");
    }
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

function PrintWithDateAndTime($sText)
{
    echo sprintf("[%s] %s\n",
        date('Y-m-d H:i:s'),
        $sText);
}

function BulkDelete($iBulkSize, $bDebug)
{
    if ($iBulkSize == 0){
        return "Nothing to do";
    }

    $sPrefix = \MetaModel::GetConfig()->Get('db_subname');

    $aIds= GetIds($sPrefix, $iBulkSize, $bDebug);

    if (count($aIds) == 0){
        return "No CMDBChange row to delete.";
    }

    $sBulkDelete = sprintf("DELETE FROM %spriv_change WHERE id IN (%s)",
        $sPrefix,
        implode(',', $aIds)
    );


	$fStartTime = microtime(true);
	ExecuteQuery($sBulkDelete, "Bulk deletion query of $iBulkSize row(s)", $bDebug);
	$fElapsed = microtime(true) - $fStartTime;

	$sMsg= sprintf("%d CMDBChange row(s) deleted in %.3f s.", count($aIds), $fElapsed);
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
function ExecuteQuery($sSqlQuery, $sLogMessage, $bDebug){
	$fStartTime = microtime(true);
    HandleLog(sprintf("%s : ongoing step", $sLogMessage), $bDebug);

	$sSqlQueryMsg = str_replace(PHP_EOL, '', $sSqlQuery);
	HandleLog("Query to execute: $sSqlQueryMsg", $bDebug);

    /** @var \mysqli_result $oQueryResult */
    $oQueryResult = CMDBSource::Query($sSqlQuery);
    $fElapsed = microtime(true) - $fStartTime;
	HandleLog(sprintf("%s : executed in %.3f s", $sLogMessage, $fElapsed), $bDebug);
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
function GetIds($sPrefix, $iBulkSize, $bDebug){
    $sSqlQuery = <<<SQL
SELECT c.id as id FROM ${sPrefix}priv_change AS c 
LEFT JOIN ${sPrefix}priv_changeop AS co ON co.changeid = c.id 
WHERE co.id IS NULL
ORDER BY id DESC
LIMIT {$iBulkSize};
SQL;

    $oQueryResult = ExecuteQuery($sSqlQuery, "Get CMDBChange $iBulkSize ID(s) to remove", $bDebug);

    $aIds = [];
    while($aRow = $oQueryResult->fetch_array()){
        $aIds[] = $aRow['id'];
    }

    return $aIds;
}

/**
 * @param int $iLimit
 * @param $bDebug
 *
 * @return int
 * @throws \CoreException
 * @throws \MySQLException
 * @throws \MySQLHasGoneAwayException
 */
function CountLinesToRemove($iLimit=0, $bDebug){
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

    $oQueryResult = ExecuteQuery($sSqlQuery, "Count Lines to remove (max: $iLimit)", $bDebug);

    while($aRow = $oQueryResult->fetch_array()){
        return (int) $aRow['count'];
    }

    return 0;
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
        PrintWithDateAndTime("Access wrong credentials ('$sAuthUser')");
        $oP->output();
        exit(EXIT_CODE_ERROR);
    }

    if (!UserRights::IsAdministrator())
    {
        PrintWithDateAndTime("Access restricted to administrators");
        $oP->Output();
        exit(EXIT_CODE_ERROR);
    }
}
catch (Exception $e)
{
    PrintWithDateAndTime("Error: ".$e->GetMessage());
    $oP->output();
    exit(EXIT_CODE_FATAL);
}

try
{
    $oMutex = new iTopMutex('dbcleanup');
    if (!MetaModel::DBHasAccess(ACCESS_ADMIN_WRITE))
    {
        PrintWithDateAndTime("A maintenance is ongoing");
    }
    else
    {
        if ($oMutex->TryLock())
        {
            $iBulkDelete = ReadMandatoryParam($oP, 'bulk_size', 'integer');
            if ($iBulkDelete <0) {
                PrintWithDateAndTime("Error: invalid value ($iBulkDelete) for bulk_size parameter.");
                $oP->output();
                exit(EXIT_CODE_FATAL);
            }

            $iCountLimit = utils::ReadParam('count_lines_limit', 0, true, 'integer');
            if ($iBulkDelete < -1) {
                PrintWithDateAndTime("Error: invalid value ($iCountLimit) for count_lines_limit parameter.");
                $oP->output();
                exit(EXIT_CODE_FATAL);
            }

            $iCount = CountLinesToRemove($iCountLimit, $bDebug);

            if ($iCountLimit !== 0){
                if (($iCountLimit == -1) || ($iCount < $iCountLimit)){
                    PrintWithDateAndTime("Found exactly $iCount CMDBChange row(s) to delete");
                }else{
                    PrintWithDateAndTime("Found at least $iCount CMDBChange row(s) to delete");
                }
            }

            PrintWithDateAndTime(BulkDelete($iBulkDelete, $bDebug));
        }
        else
        {
            // Exit silently
            PrintWithDateAndTime("Already running...");
        }
    }
}
catch (Exception $e)
{
    PrintWithDateAndTime("ERROR: '".$e->getMessage()."'");
    if ($bDebug)
    {
        // Might contain verb parameters such a password...
        PrintWithDateAndTime($e->getTraceAsString());
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
        PrintWithDateAndTime("ERROR: '".$e->getMessage()."'");
        if ($bDebug)
        {
            // Might contain verb parameters such a password...
            PrintWithDateAndTime($e->getTraceAsString());
        }
    }
}

$oP->Output();