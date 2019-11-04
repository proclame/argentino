<?php

require_once("../include/utils.runtime6.php");
require_once("../include/mysql.runtime6.php");

// *****************************************************************************
//
// actions/mysql.actions6.php
//
// GoLive actions for MySQL datasources.
//
// ADOBE SYSTEMS INCORPORATED
// Copyright 2000-2002 Adobe Systems Incorporated. All Rights Reserved.
// 
// NOTICE:  Notwithstanding the terms of the Adobe GoLive End User 
// License Agreement, Adobe permits you to reproduce and distribute this 
// file only as an integrated part of a web site created with Adobe 
// GoLive software and only for the purpose of enabling your client to 
// display their web site. All other terms of the Adobe license 
// agreement remain in effect.
//


// *****************************************************************************
// UTILITIES
//

// -----------------------------------------------------------------------------
// Data collection from <form>

function getDataFromForm($firstRecord, $lastRecord)
{
	global $datatypes;
	$data = array();


	for ($i = $firstRecord; $i <= $lastRecord; $i++) {
		$record = array();

		reset($datatypes);
		while (list ($fieldName,) = each($datatypes)) {
			$value = GetFormValue("$fieldName($i)");
			if ($value != "_no_form_value") {
				$record[$fieldName] = $value;
			}
		}

		$data[] = $record;
	}

	global $RuntimeDebug;
	if ($RuntimeDebug) {
		$msg = "Data from form\n";
		$msg .= "<table border=\"1\">\n";
		while (list($k, $dataArray) = each($data)) {
			while (list($key, $value) = each($dataArray)) {
		    	$msg .= "<tr><td>$k</td><td>$key</td><td>$value</td></tr>\n";
		    }
		}
		$msg .= "</table>\n";
		RuntimeDebugMessage($msg);
	}
	return $data;
}

function getKeysFromForm($firstRecord, $lastRecord)
{
	global $cswName;
	$keys = array();

	for ($i = $firstRecord; $i <= $lastRecord; $i++) {
		$keyString = "RECORD_KEY({$cswName})=" . GetFormValue("_key($i)");
		$keys[] = queryStringToArray($keyString);
	}

	global $RuntimeDebug;
	if ($RuntimeDebug) {
		$msg = "Keys from form\n";
		$msg .= "<table border=\"1\">\n";
		while (list($k, $keyArray) = each($keys)) {
			while (list($key, $value) = each($keyArray)) {
		    	$msg .= "<tr><td>$k</td><td>$key</td><td>$value</td></tr>\n";
		    }
		}
		$msg .= "</table>\n";
		RuntimeDebugMessage($msg);
	}

	return $keys;
}

// -----------------------------------------------------------------------------
// SQL utilities

function getTableNameFromSQL($sql)
{
	$tableName = "";
	$tokens = explode(" ", $sql);
	for ($i = 0; $i < sizeof($tokens); $i++) {
		if (strtoupper($tokens[$i]) == "FROM") {
			$tableName = $tokens[$i+1];
			break;
		}
	}
	return $tableName;
}

// -----------------------------------------------------------------------------
// wrapSQLValue
//
// Inserts provider-specific annotations (quotes, brackets, etc.) around values
// based on their type and use.

function wrapSQLValue($fieldName, $value)
{
	global $datatypes;
	global $GL_str_replace;
	$type = $datatypes[$fieldName];

	if ($type == "string" || $type == "blob"
	 || $type == "text" || $type == "date")
	{
		if (function_exists('mb_internal_encoding')) {
			if (strtoupper(mb_internal_encoding()) == 'SJIS') {
				$value = addslashes($value);
			}
		}

		$value = $GL_str_replace("'", "''", $value);
		return "'$value'";
	}
	return $value;
}

function setClause($data)
{
	$fields = array();
	while (list($fieldName, $value) = each($data)) {
		$fields[] = "$fieldName = " . wrapSQLValue($fieldName, $value);
	}
	return " SET " . join(", ", $fields);
}

function whereClause($key)
{
	global $cswName;
	global $GL_strlen;

	$fields = array();
	reset($key);
	while (list($fieldName, $value) = each($key)) {
		if ($fieldName != "RECORD_KEY(".$cswName.")" && $GL_strlen($value) > 0) {
			// need to remove CSN from field name here
			$simpleField = explode ("(", $fieldName);
			$simpleField = $simpleField[0];
			$fields[] = "$simpleField = "
				. wrapSQLValue($simpleField, $value);
		}
	}
	return " WHERE " . join(" AND ", $fields);
}

function columnList($data)
{
	$fields = array();
	reset($data);
	while (list($fieldName,) = each($data)) {
		$fields[] = $fieldName;
	}
	return " (" . join(", ", $fields) . ") ";
}

function valuesClause($data)
{
	$fields = array();
	reset($data);
	while (list($fieldName, $value) = each($data)) {
		$fields[] = wrapSQLValue($fieldName, $value);
	}
	return " VALUES (" . join(", ", $fields) . ") ";
}

// -----------------------------------------------------------------------------
// Record navigation utilities

function join_url($parts)
{
	return $parts["scheme"] . "://" . $parts["host"] . ":" . $parts["port"]
				. $parts["path"] . "?" . $parts["query"];
}

function addParams($url, $params)
{
	global $cswName;
	$parts = parse_url($url);

	$args = queryStringToArray(@$parts["query"]);
	if ((isset($args["RECORD_INDEX({$cswName})"]))||
	    (isset($args["RECORD_INDEX%28{$cswName}%29"]))) {
		$args = resetRecordNavigation($args, $cswName);
	}
	while (list($key, $value) = each($params)) {
		$args[$key] = $value;
	}
	$parts["query"] = queryArrayToString($args);

	return join_url($parts);
}

function deleteParams($url)
{
	$urlparts = explode("?", $url);
	return $urlparts[0];
}

function decrementRecordIndex($url)
{
	global $cswName;
	global $GL_strlen;

	$parts = parse_url($url);
	$rx =  "RECORD_INDEX({$cswName})";
	$rx2 = "RECORD_INDEX%28{$cswName}%29";
	if (!isset($parts["query"])) {return $url;}
	$query_part = $parts["query"];
	if ($GL_strlen($query_part) == 0) {return $url;}
	$args = queryStringToArray($query_part);
	if (isset($args[$rx])) {
		$recordIndex = $args[$rx];
		$args = resetRecordNavigation($args, $cswName);
		$args[$rx] = max(1, $recordIndex - 1);
	} else if (isset($args[$rx2])) {
		$recordIndex = $args[$rx2];
		$args = resetRecordNavigation($args, $cswName);
		$args[$rx2] = max(1, $recordIndex - 1);
	}

	$parts["query"] = queryArrayToString($args);

	return join_url($parts);
}


// *****************************************************************************
// SERVER SIDE ACTIONS
//

// -----------------------------------------------------------------------------
// Action handler functions

function SubmitChanges($tableName)
{
	global $cswerr;
	global $RuntimeDebug;
	global $cswName;

    $firstRecord = GetFormValue("_firstRecord");
    $lastRecord = GetFormValue("_lastRecord");
    $position = GetFormValue ("_position");
	$keys = getKeysFromForm($firstRecord, $lastRecord);
	$data = getDataFromForm($firstRecord, $lastRecord);
	$firstInserted = -1;

	if (count($keys) > 0 && indexof($keys[0]["RECORD_KEY(".$cswName.")"], "_newRecord") == 0) {
		// new record case - do a SQL insert
		$keys = getKeysFromForm($position, $position);
		$data = getDataFromForm($position, $position);
		for ($i = 0; $i < sizeof($data); $i++) {
			$sql = "INSERT INTO $tableName "
				. columnList($data[$i])
				. valuesClause($data[$i]);
			if ($RuntimeDebug) {
				RuntimeDebugMessage("SQL = [$sql]<p>\n");
			}
			mysql_query($sql);

			if (mysql_errno()) {
				$m = mysql_errno(); $s = mysql_error();
				$cswerr->dollar['__ERROR'] =
					"Database error $m [$s]";
				Redirect(GetFormValue("_update_failure"),
					$cswerr);
			}
			else
				$firstInserted = mysql_insert_id();
		}
	}
	else {
		for ($i = 0; $i < sizeof($data); $i++) {
			$sql = "UPDATE $tableName"
				. setClause($data[$i])
				. whereClause($keys[$i]);
			if ($RuntimeDebug) {
				RuntimeDebugMessage("SQL = [$sql]<p>\n");
			}
			mysql_query($sql);

			if (mysql_errno()) {
				$m = mysql_errno(); $s = mysql_error();
				$cswerr->dollar['__ERROR'] =
					"Database error $m [$s]";
				Redirect(GetFormValue("_update_failure"),
					$cswerr);
			}
		}
	}

	$url = GetFormValue("_update_success");
	if ($firstInserted >= 0) {
		global $cswName;

		$keys = array();
		$keys["RECORD_KEY({$cswName})"] = "ID";
		$keys["ID({$cswName})"] = $firstInserted;
		Redirect(addParams($url, $keys));
	} else {
		Redirect($url);
	}
}


function AddRecord($tableName)
{
	global $cswerr;
	global $RuntimeDebug;

    $position = GetFormValue ("_position");
	$keys = getKeysFromForm($position, $position);
	$data = getDataFromForm($position, $position);
	$firstInserted = -1;

	if (sizeof($keys) != 1) {	// block data is not supported
		$n = sizeof($keys);
		$cswerr->dollar['__ERROR'] =
			"$n items were selected;
AddRecord $tablename can handle only one item at a time.";

		Redirect(GetFormValue("_add_failure"), $cswerr);
	}

	for ($i = 0; $i < sizeof($data); $i++) {
		$sql = "INSERT INTO $tableName "
			. columnList($data[$i])
			. valuesClause($data[$i]);
		if ($RuntimeDebug) {
			RuntimeDebugMessage("SQL = [$sql]<p>\n");
		}
		mysql_query($sql);

		if (mysql_errno()) {
			$m = mysql_errno(); $s = mysql_error();
			$cswerr->dollar['__ERROR'] =
				"Database error $m [$s]";
			Redirect(GetFormValue("_add_failure"),
				$cswerr);
		}
		else
			$firstInserted = mysql_insert_id();
	}

	$url = GetFormValue("_add_success");
	if ($firstInserted >= 0) {
		global $cswName;
		$keys = array();
		$keys["RECORD_KEY({$cswName})"] = "_lastRecord";
		Redirect(addParams($url, $keys));
	} else {
		Redirect($url);
	}
}


function DeleteRecord($tableName)
{
	global $cswerr;
	global $RuntimeDebug;

	$keys = getKeysFromForm(GetFormValue("_firstRecord"),GetFormValue("_lastRecord"));
	if (sizeof($keys) != 1) {	// block data is not supported
		$n = sizeof($keys);
		$cswerr->dollar['__ERROR'] =
			"$n items were selected;
_DeleteRecord $tablename can handle only one item at a time.";

		Redirect(GetFormValue("_delete_failure"), $cswerr);
	}

	// delete last record in a block
	$i = sizeof($keys) - 1;
	$sql = "DELETE FROM $tableName " . whereClause($keys[$i]);
	if ($RuntimeDebug) {
		RuntimeDebugMessage("SQL = [$sql]<p>\n");
	}
	mysql_query($sql);

	if (mysql_errno()) {
		$m = mysql_errno(); $s = mysql_error();
		$cswerr->dollar['__ERROR'] = "Database error $m [$s]";
		// error_log($cswerr->dollar['__ERROR'], 0);
		Redirect(GetFormValue("_delete_failure"), $cswerr);
	}
	else if (sizeof($keys) > 1) {
		// Records are still visible in this block: don't move cursor
		Redirect(GetFormValue("_delete_success"));
	}
	else {
		$url = GetFormValue("_delete_success");
		Redirect(decrementRecordIndex($url));
	}
}

function ClearFormData($tableName)
{
	global $cswerr;
	global $RuntimeDebug;

	$data = getDataFromForm(GetFormValue("_firstRecord"),GetFormValue("_lastRecord"));
	if (sizeof($data) != 1) {	// block data is not supported
		$n = sizeof($keys);
		$cswerr->dollar['__ERROR'] =
			"$n items were selected;
ClearFormData $tablename can handle only one item at a time.";

		if ($RuntimeDebug) {
			RuntimeDebugMessage("SQL = [$sql]<p>\n");
		}
		Redirect(GetFormValue("_clear_failure"), $cswerr);
	}
	else {
		global $cswName;
		$url = GetFormValue("_clear_success");
		$url = deleteParams($url) . "?RECORD_KEY({$cswName})=_newRecord";	//TODO: use AddParams after it's fixed.
		Redirect($url);
	}
}

function CopyFormData($tableName)
{
	global $cswerr;

	$keys = getKeysFromForm(GetFormValue("_firstRecord"),GetFormValue("_lastRecord"));
	if (sizeof($keys) != 1) {	// block data is not supported
		$n = sizeof($keys);
		$cswerr->dollar['__ERROR'] =
			"$n items were selected;
CopyFormData $tablename can handle only one item at a time.";

		Redirect(GetFormValue("_copy_failure"), $cswerr);
	}
	else {
		global $cswName;
		$rk = "RECORD_KEY({$cswName})";
		$rk2 = "RECORD_KEY%28{$cswName}%29";
		$url = GetFormValue("_copy_success");
		$url = deleteParams($url);
		if (isset($keys[0][$rk])) {
			$keys[0][$rk] = "_copyRecord," . $keys[0][$rk];
		}
		if (isset($keys[0][$rk2])) {
			$keys[0][$rk2] = "_copyRecord," . $keys[0][$rk2];
		}
		Redirect(addParams($url, $keys[0]));
	}
}


// -----------------------------------------------------------------------------
// Dispatch

$r = GetFormValue("_database");
$ds = unobscure($r);	// it is datasource name
GL_mysql_connect($ds);

$sql = unobscure(GetFormValue("_sql"));
$tableName = getTableNameFromSQL($sql);
$cswerr = NewError();

$datatypes = queryStringToArray(unobscure(GetFormValue("_datatypes")));
$cswName = unobscure(GetFormValue('_cswName'));

global $RuntimeDebug;
if ($RuntimeDebug) {
	debugAddHtmlHead();
	debugPrintQueryString();
	debugPrintFormData();
	debugPrintObscuredData();
}

if (GetFormValue("_SubmitChanges") != "_no_form_value") {
	SubmitChanges($tableName);
} elseif (GetFormValue("_AddRecord") != "_no_form_value") {
	AddRecord($tableName);
} elseif (GetFormValue("_DeleteRecord") != "_no_form_value") {
	DeleteRecord($tableName);
} elseif (GetFormValue("_ClearFormData") != "_no_form_value") {
	ClearFormData($tableName);
} elseif (GetFormValue("_CopyFormData") != "_no_form_value") {
	CopyFormData($tableName);
} else {
	// default action
	SubmitChanges($tableName);
}

?>
