<?php

// *****************************************************************************
// Multi Byte Function support
// To use multi byte funcions, refer
//   http://www.php.net/manual/en/ref.mbstring.php
//

if (function_exists('mb_strlen')) {
	$GL_strlen      = 'mb_strlen';
	$GL_substr      = 'mb_substr';
	$GL_strpos      = 'mb_strpos';
	$GL_str_replace = 'gl_str_replace';
} else {
	$GL_strlen      = 'strlen';
	$GL_substr      = 'substr';
	$GL_strpos      = 'strpos';
	$GL_str_replace = 'str_replace';
}

// *****************************************************************************
//
// include/mysql.runtime6.php
//
// GoLive runtime support for MySQL
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
// CONTENT SOURCE WRAPPERS
//
// GoLive supports a standard interface definition for a content source.  Other
// objects are wrapped in this interface to allow access.
//
// Content Source Wrapper Methods:
//
//	  RecordCount()
//		 the number of records in the recordset
//	  AbsolutePosition()
//		 the (1-based) index of the current record (the cursor position)
//	  FirstRecordOnPage()
//		 the (1-based) index of the first record on the current page
//    LastRecordOnPage()
//		 the (1-based) index of the last record on the current page
//	  BlockSize()
//       the number of records in a page
//	  EOB()
//       true if the cursor is after the last record on the current page
//	  EOF()
//		 true if the cursor is after the last record
//
//	  Move(delta)
//		 moves the cursor by the specified amount
//	  MoveFirst()
//		 moves the cursor to the first record
//	  MoveNext()
//		 moves the cursor to the next record
//	  GetName()
//		 returns the content source name
//
//	  Value(fieldName)
//		 returns the value of a field of the current record
//	  Error(fieldName)
//       returns any error message associated with a field of the current record
//
//	  Key()
//		 returns a key indentifying the current record
//


// *****************************************************************************
// CONTENT SOURCE WRAPPER: MySQL
//
// Content source wrapper for MySQL queries.
//
// Items are accessed through the wrapper as follows:
//
//	  $results = WrapMySQLDatabaseResults($database, $sql, $records)
//	  $results->MoveFirst();
//	  $results->Value("SKU");

function WrapMySQLDatabaseResults($database, $sql, $records, $name) {
	global $HTTP_GET_VARS;

	$link_id = GL_mysql_connect($database);
	$recordSet = mysql_query($sql, $link_id);
	if ($recordSet == null)
		return null;
	$wrapper = new mysql_CSW($database, $sql, $recordSet, $name);

	if ($block = explode("=", $records)) {
		$wrapper->blockSize = (integer)$block[1];
	} else {	// old version compatibility
		if ($records == "single") {
			$wrapper->blockSize = 1;
		}
		if ($records == "all") {
			$wrapper->blockSize = 0;
		}
	}

	if ($wrapper->blockSize == 0) { // "all" case
		$wrapper->MoveFirst();
		$wrapper->firstix = $wrapper->ix;
		$wrapper->lastix = $wrapper->RecordCount() - 1;
	} else {
		$k = "RECORD_KEY({$name})";
		$r = @$HTTP_GET_VARS[$k];
		if (isset($r)) {
			$wrapper->MoveToKey($r);
		}
		$k = "RECORD_INDEX({$name})";
		$r = @$HTTP_GET_VARS[$k];
		if (isset($r)) {
			$wrapper->MoveTo($r);
		}

		$wrapper->firstix = $wrapper->ix;
		if ($wrapper->blockSize == 1) { // "single" case
			$wrapper->lastix = $wrapper->ix;
		} else {	//	"block=N" case
			$wrapper->lastix = Min($wrapper->recordCount - 1, ($wrapper->ix + $wrapper->blockSize - 1));
		}
	}

	global $RuntimeDebug;
	if ($RuntimeDebug) {
		$msg = "CSW Info: <br>\n";
		$msg .= "<table border=\"1\">\n";
		$msg .= "<tr><td>NAME</td><td>"	                 . $name . "</td></tr>\n";
		$msg .= "<tr><td>DATABASE</td><td>"	             . $database . "</td></tr>\n";
		$msg .= "<tr><td>SQL</td><td>"	                 . $sql . "</td></tr>\n";
		$msg .= "<tr><td>CSW.ix</td><td>"                . $wrapper->ix . "</td></tr>\n";
		$msg .= "<tr><td>CSW.firstix</td><td>"           . $wrapper->firstix . "</td></tr>\n";
		$msg .= "<tr><td>CSW.lastix</td><td>"            . $wrapper->lastix . "</td></tr>\n";
		$msg .= "<tr><td>CSW.RecordCount</td><td>"       . $wrapper->RecordCount() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.AbsolutePosition</td><td>"  . $wrapper->AbsolutePosition() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.FirstRecordOnPage</td><td>" . $wrapper->FirstRecordOnPage() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.LastRecordOnPage</td><td>"  . $wrapper->LastRecordOnPage() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.BlockSize</td><td>"         . $wrapper->BlockSize() . "</td></tr>\n";
		$msg .= "</table>\n";
		RuntimeDebugMessage($msg);
	}
	return $wrapper;
}

class mysql_CSW
{
	var $recordCount;
	var $blockSize;
	var $name;

	var $recordSet;
	var $primaryKeySet;
	var $dataTypes;
	var $database;
	var $sql;

	var $ix;		// cursor
	var $dup_hack;	// duplicate record hack
	var $firstix;	// first record on page
	var $lastix;	// last record on page

	var $cswerr;	// error information returned from action, if any
	var $cname;

	function mysql_CSW($database, $sql, $mysqlRecordSet, $name)
	{
		$this->recordCount = 0;
		$this->blockSize = 0;

		$this->name = $name;

		$this->recordSet = array();
		$this->primaryKeySet = array();
		$this->dataTypes = array();
		$this->cname = array();
		$this->database = $database;
		$this->sql = $sql;

		$this->ix = 0;
		$this->firstix = 0;
		$this->lastix = 0;
		$this->dup_hack = -1;

		if ($mysqlRecordSet) {
			$this->LoadRecordSet($mysqlRecordSet);
			$this->DeterminePrimaryKeySet($mysqlRecordSet);
			$this->LoadDataTypes($mysqlRecordSet);
		}

		$this->cswerr = GetError();
	}

	function GetColumnNames() {
		return $this->cname;
	}

	function LoadRecordSet($mysqlRecordSet)
	{
		while ($row = mysql_fetch_array($mysqlRecordSet)) {
			$this->recordSet[$this->recordCount++] = $row;
		}
	}

	function DeterminePrimaryKeySet($mysqlRecordSet)
	{
		mysql_field_seek($mysqlRecordSet, 0);
		while ($field = mysql_fetch_field($mysqlRecordSet)) {
			if ($field->primary_key) {
				$this->primaryKeySet[] = $field->name;
			}
		}
		if (sizeof($this->primaryKeySet) == 0) {
			mysql_field_seek($mysqlRecordSet, 0);
			while ($field = mysql_fetch_field($mysqlRecordSet)) {
				if ($field->unique_key) {
					$this->primaryKeySet[] = $field->name;
				}
			}
		}
	}

	function LoadDataTypes($mysqlRecordSet)
	{
		mysql_field_seek($mysqlRecordSet, 0);
		while ($field = mysql_fetch_field($mysqlRecordSet)) {
			$this->cname[] = $field->name;
			$this->dataTypes[$field->name] = $field->type;
		}
	}

	function RecordCount()
	{
		return $this->recordCount;
	}
	function AbsolutePosition()
	{
		return $this->ix + 1;	// CSW uses [1..n]
	}
	function FirstRecordOnPage()
	{
		return $this->firstix + 1;	// CSW uses [1..n]
	}
	function LastRecordOnPage()
	{
		return $this->lastix + 1;	// CSW uses [1..n]
	}
	function BlockSize()
	{
		return $this->blockSize;
	}
	function EOB()
	{
		return $this->EOF() || $this->ix > $this->lastix;
	}
	function EOF()
	{
		return $this->ix >= $this->recordCount;
	}
	function GetName() {
		return $this->name;
	}

	// -------------------------------------------------------------------------
	// Cursor positioning:

	function MoveTo($i)
	{
		$this->ix = $i - 1;		// CSW uses [1..n]
		$this->dup_hack = -1;
	}

	function Move($i)
	{
		$this->ix += $i;
		$this->dup_hack = -1;
	}

	function MoveFirst()
	{
		$this->ix = 0;
		$this->dup_hack = -1;
	}

	function MoveNext()
	{
		++$this->ix;
		$this->dup_hack = -1;
	}

	// -------------------------------------------------------------------------
	// Data access:

	function Value($fieldName)
	{
		if ($cswerr = $this->cswerr) {
			$cswerr->MoveTo($this->ix);  // Error information is at current record
			$r = $cswerr->Value($fieldName);
			if (isset($r))
				return $r;
		}

		if ($this->dup_hack >= 0) {
			$i = $this->dup_hack;      // Handle copy form
		} else {
			$i = $this->ix;
		}

		if (isset($this->recordSet[$i][$fieldName])) {
			return $this->recordSet[$i][$fieldName];
		} else {
			return "";
		}
	}

	function Error($fieldName)
	{
		if ($cswerr = $this->cswerr) {
			$cswerr->MoveTo($this->ix);
			$r = $cswerr->Error($fieldName);
			if ($r != "")
				return $r;
		}
		return "";
	}

	// -------------------------------------------------------------------------
	// Key-based data access:
	//
	// The primary key specification is stored as an array of column names.	 An
	// instance of a key is of the format:
	//    colName1,colName2,...&colName1=value&colName2=value....

	function Key()
	{
		global $PATH_INFO;
		global $RuntimeDebug;
		global $GL_substr;
		global $GL_strlen;
		global $GL_strpos;


		if ($this->EOF() || $GL_strpos($PATH_INFO, "_copyRecord")) {
			return "_newRecord";
		}

		$keyLengthLimit = 255 - $GL_strlen($PATH_INFO) - 50;  // 50 is extra margin for server name
		if (sizeof($this->primaryKeySet) == 0) {	// primary key was not found
			$fieldLengthLimit = 10;	// default
		} else {	//	 Min 4 is for "null". 6 is worst case for 2 byte char encoded.
			$fieldLengthLimit = max(4, ($keyLengthLimit / sizeof($this->primaryKeySet)) / 6);
		}

		$key = "";
		$keyFields = "";
		for ($i = 0; $i < sizeof($this->primaryKeySet); $i++) {
			$keyName = $this->primaryKeySet[$i];
			if ($GL_strlen($key) + $GL_strlen($keyName) + $fieldLengthLimit + 1 > $keyLengthLimit) {
				break;
			}
			if ($i > 0) {
				$key .= "&";
				$keyFields .= ",";
			}
			$keyValue = $GL_substr($this->Value($keyName), 0, $fieldLengthLimit);
			$key .= urlencode($keyName) . "(" . $this->name .")=" . urlencode($keyValue);
			$keyFields .= urlencode($keyName);
		}

		if ($RuntimeDebug) {
			if ($GL_strlen($keyFields) + $GL_strlen("&") + $GL_strlen($key) > $keyLengthLimit) {
				RuntimeDebugMessage("Error: Generated key is too long.");
			}
		}
		return $keyFields . "&" . $key;
	}

	function MoveToKey($key)
	{
		if ($key == "_newRecord") {
			$this->Move($this->RecordCount());
			return;
		}

		if ($key == "_lastRecord") {
			$this->Move($this->RecordCount() -1);
			return;
		}

		global $HTTP_GET_VARS;
		$keys = array();
		$keyNames = explode(",", $key);
		reset($keyNames);
		while (list(, $keyName) = each($keyNames)) {
			if ($keyName == "_copyRecord") {
				continue;
			}
			$keys[$keyName] = stripslashes($HTTP_GET_VARS[$keyName."(".$this->name.")"]);
		}

		$this->MoveFirst();
		while (!$this->EOF()) {
			$match = true;
			reset($keys);
			while (list($keyName, $keyValue) = each($keys)) {
				if (indexof($this->Value($keyName), $keyValue)<>0) {
					$match = false;
				}
			}
			if ($match) {
				if (indexof($key, "_copyRecord") == 0) {
					$this->dup_hack = $this->ix;
					$this->ix = $this->recordCount;
				}
				break;
			}
			$this->MoveNext();
		}
	}
};

// -----------------------------------------------------------------------------

$GL_mysql_dsnames = array();

// To avoid file I/O fetching database info, you can bake the names into the
// program.  E.g., uncomment the following statement:
//		$GL_mysql_dsnames['Magazine'] = array('host'=>'localhost',
//			'user'=>'magazine', 'password'=>'madcow',
//			'date'=>0);

// -----------------------------------------------------------------------------
// Find a datasource description file named $dataSource in our datasources folder.
// If it exists, read it into an array indexed by keys
//	'host'		host and port number
//	'database'	database name / if there's no setting, it considers $dataSource as database name
//	'user'		database user name
//	'password'	database user password
//	'date'		when did we read in this file?
// The format of the file follows that of java.utils.Properties, but
//	- doesn't let you use \ to escape syntax chars
//	- doesn't dig Unicode

function GL_mysql_get_dsinfo($dataSource)
{
	//
	// Read in $dataSource
	//
	global $GL_strlen;
	$f = GetDataSourcePath() . "$dataSource.mysql.sbs";
	if (!file_exists($f) || !is_file($f))
		die("ERROR: Could not find datasource file [$dataSource.mysql.sbs].");
	$a = GL_file($f);	/* array: 1 line per entry */
	$ret = array();
	$ret['date'] = filemtime($f);
	$m = array();
	for ($i = 0; $i < count($a); ++$i) {
		if (ereg("^[#!]", $a[$i]))	/* comment line */
			continue;
		if (ereg("[ \t]*([^ \t:=]+)[ \t]*[:=]?[ \t]*(.*)", $a[$i], $m))
		{
			$name = trim($m[1]);
			$value = trim($m[2]);
			$ret[$name] = $value;
		}
	}
	// If no database parameter is set, use filename body as database name.
	$keys = array_keys($ret);
	if (in_array('database', $keys)) {
		$tmp = $ret['database'];
		if ($GL_strlen($tmp) == 0) {
			$ret['database'] = $dataSource;	// over write
		}
	} else {
		$ret['database'] = $dataSource;	// add
	}
	return $ret;
}


/*
 * Map the $dataSource to mysql_connect() args, and run mysql_connect().
 * It returns $link_id info.
 * If we don't find a database name, connect anyway since that seems to be
 * MySQL standard behavior.  (XXX probably wrong!)
 */
function GL_mysql_connect($dataSource)
{
	global $GL_mysql_dsnames;
	global $GL_mysql_dsnames_result;
	global $REQUEST_URI;

	$r = @$GL_mysql_dsnames[$dataSource];
	if (!isset($r)) {
		$r = GL_mysql_get_dsinfo($dataSource);
		$GL_mysql_dsnames[$dataSource] = $r;
	}

	$GL_mysql_dsnames_result = $r;
	if (isset($r) && $r != null && is_array($r)) {
		$link_id = mysql_connect(@$r['host'], @$r['user'], @$r['password']) or
			die('ERROR: Could not connect to host [' . @$r['host'] . '].');
	} else {
		// error_log("{$REQUEST_URI}: {$dataSource} not found", 0);
		$link_id = mysql_connect('localhost') or
			die('ERROR: Could not connect host [localhost].');
	}
	if (!mysql_select_db(@$r['database'], $link_id)) {
		die('ERROR: Could not find database [' . $r['database'] . '].');
	}

	return $link_id;
}


// *****************************************************************************
// ACTION SETUP ROUTINES

$setupArgs = array();


function MakeMySQLReturnURL($csw, $url)
{
	global $QUERY_STRING;

	if ($url != '$return')
		return absolutePath(thisPageURL(), $url);

	$oldArgs = qstoa($QUERY_STRING);
	$newArgs = array();
	while (list($k,$v) = each($oldArgs)) {
		/*
		 * Remove the keys since they won't work after an
		 * update/delete/&c.
		 */
		if (indexof($k, '_key('/*)*/) == 0
		 || indexof($k, 'RECORD_KEY('.$csw->GetName().')') == 0)
			continue;
		$newArgs[$k] = $v;
	}
	$newArgs["RECORD_INDEX(".$csw->GetName().")"] =
		$csw->FirstRecordOnPage();
	return thisPageURL() . '?' . qatostr($newArgs);
}

// -----------------------------------------------------------------------------

function SetupMySQLUpdate($successLink, $failureLink, $contentSource)
{
	global $setupArgs;
	$setupArgs["update_success"] = MakeMySQLReturnURL($contentSource, $successLink);
	$setupArgs["update_failure"] = MakeMySQLReturnURL($contentSource, $failureLink);
}

// -----------------------------------------------------------------------------

function SetupMySQLAdd($successLink, $failureLink, $contentSource)
{
	global $setupArgs;
	$setupArgs["add_success"] = MakeMySQLReturnURL($contentSource, $successLink);
	$setupArgs["add_failure"] = MakeMySQLReturnURL($contentSource, $failureLink);
}

// -----------------------------------------------------------------------------

function SetupMySQLDelete($successLink, $failureLink, $contentSource)
{
	global $setupArgs;
	$setupArgs["delete_success"] = MakeMySQLReturnURL($contentSource, $successLink);
	$setupArgs["delete_failure"] = MakeMySQLReturnURL($contentSource, $failureLink);
}

// -----------------------------------------------------------------------------

function SetupMySQLClear($successLink, $failureLink, $contentSource)
{
	global $setupArgs;
	$setupArgs["clear_success"] = MakeMySQLReturnURL($contentSource, $successLink);
	$setupArgs["clear_failure"] = MakeMySQLReturnURL($contentSource, $failureLink);
}

// -----------------------------------------------------------------------------

function SetupMySQLCopy($successLink, $failureLink, $contentSource)
{
	global $setupArgs;
	$setupArgs["copy_success"] = MakeMySQLReturnURL($contentSource, $successLink);
	$setupArgs["copy_failure"] = MakeMySQLReturnURL($contentSource, $failureLink);
}

// -----------------------------------------------------------------------------
// Setup the form.  This function must at least write out the setup arguments
// added by the individual action setup functions.

function SetupMySQLForm($contentSource)
{
	global $setupArgs;

	if (!is_object($contentSource)) {
		return;
	}

	//
	// If we got here from a form error redirect, then all the setup info has
	// been saved for us in the CSWFormError.
	//
	if (isset($contentSource->isFormError)) {
		$dollarData = $contentSource->DollarData();
		reset($dollarData);
		while (list($key, $value) = each($dollarData)) {
			echo "<input type='hidden' name='$key' value='$value'>",
				"\r\n";
		}
		return;
	}

	//
	// Write out the database info:
	//
	echo "<input type='hidden' name='_cswName' value='",
		obscure($contentSource->GetName()), "'>",
		// "<!-- ", $contentSource->GetName(), " -->",
		"\r\n";
	echo "<input type='hidden' name='_database' value='",
		obscure($contentSource->database), "'>",
		// "<!-- ", $contentSource->database, " -->",
		"\r\n";
	echo "<input type='hidden' name='_sql' value='",
		obscure($contentSource->sql), "'>",
		// "<!-- ", $contentSource->sql, " -->",
		"\r\n";
	echo "<input type='hidden' name='_datatypes' value='",
		obscure(queryArrayToString($contentSource->dataTypes)),
		"'>",
		// "<!-- ", queryArrayToString($contentSource->dataTypes),
		// " -->",
		"\r\n";

	//
	// Write out the record key(s):
	//
	$first = $contentSource->FirstRecordOnPage();
	$last = $contentSource->LastRecordOnPage();
	$index = $contentSource->AbsolutePosition();
	echo "<input type='hidden' name='_firstRecord' value='$first' >\r\n";
	echo "<input type='hidden' name='_lastRecord' value='$last' >\r\n";
	echo "<input type='hidden' name='_position' value='$index' >\r\n";

	$contentSource->MoveTo($first);
	while ($contentSource->AbsolutePosition() <= $last) {
		$keyName = '_key(' . $contentSource->AbsolutePosition() . ')';
		$key = $contentSource->Key();
		echo "<input type='hidden' name='$keyName' value='$key' >\r\n";
		$contentSource->MoveNext();
	}
	// Reset
	$contentSource->MoveFirst();
	$contentSource->Move($first - 1);

	//
	// Write out all the args collected from the SetupAction calls:
	//
	while (list($key, $value) = each($setupArgs)) {
		echo "<input type='hidden' name='_$key' value='$value'>\r\n";
	}
	$setupArgs = array();
}

?>
