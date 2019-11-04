<?php

/*
 * ADOBE SYSTEMS INCORPORATED
 * Copyright 2002 Adobe Systems Incorporated. All Rights Reserved.
 * 
 * NOTICE:  Notwithstanding the terms of the Adobe GoLive End User 
 * License Agreement, Adobe permits you to reproduce and distribute this 
 * file only as an integrated part of a web site created with Adobe 
 * GoLive software and only for the purpose of enabling your client to 
 * display their web site. All other terms of the Adobe license 
 * agreement remain in effect.
 */

// *****************************************************************************
// Multibyte Function support
// To use multibyte functions, see
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

// mixed gl_str_replace (mixed search, mixed replace, mixed subject)
function gl_str_replace($search, $replace, $subject)
{
	for($temp = "", $offset = 0;;) {
		if (($pointer = mb_strpos($subject, $search, $offset)) === false) {
			$temp .= mb_substr($subject, $offset);
			break;
		}
		$temp .= mb_substr($subject, $offset, $pointer-$offset);
		$temp .= $replace;
		$offset = $pointer + mb_strlen($search);
	}
	return $temp;
}


/*
 * Set a Content-Type: header using $content_type.
 * $content_type is *only* the type (e.g., text/xml) and not
 * the charset.  Use $GLparam['output_encoding'] to adjust
 * the charset.
 */
function setContentTypeHeader($content_type)
{
	$cs = function_exists('mb_http_output') ? mb_http_output() : '';
	if (!$cs)
		$cs = 'iso-8859-1';
	header("Content-Type: {$content_type}; charset={$cs}");
}

/*
 * Adjust encodings, before we send any output.
 */
if (@$GLparam['internal_encoding']) {
	if (function_exists('mb_internal_encoding')) {
		mb_internal_encoding($GLparam['internal_encoding']);
	}
}
if (@$GLparam['output_encoding']) {
	if (function_exists('mb_http_output')) {
		ob_start('mb_output_handler');
		mb_http_output($GLparam['output_encoding']);
	}
}

/*
 * Set a content-type header only if the page asked for one.
 */
if (@$GLparam['content_type'])
	setContentTypeHeader($GLparam['content_type']);


// *****************************************************************************
// SESSION HANDLING
//
// Note: session_register sends cookie information in the response header, and
// so MUST appear before any output is set.

if (!@$GLparam['no_session']) {
	session_start();  // this must be before any output is generated

	if (isset($HTTP_SESSION_VARS["obscureData"])) {
		// in case register_globals isn't set...
		$obscureData = $HTTP_SESSION_VARS["obscureData"];
		$newSession = false;
	}
	else {
		$obscureData = array();
		session_register("obscureData");
		$newSession = true;
	}
}

error_reporting(E_ALL);

/**
 ** STRING SUBROUTINES
 **/

/*
 * Like the standard strpos() routine, but returns -1 for not found
 * (instead of "false", which in a weakly typed language equates too
 * easily to "starts at 0").
 */
function indexof($haystack, $needle) {
	global $GL_strpos;
	$pos = $GL_strpos($haystack, $needle);
	/* Note ===: it's how PHP spells (eq ) */
	return $pos === false ? -1 : $pos;
}

/*
 * Return a copy of $text with XML syntax characters replaced.
 */
function xmlEncode($text) {
	global $GL_str_replace;
	$text = $GL_str_replace('&', '&amp;', $text);
	$text = $GL_str_replace('"', '&quot;', $text);
	$text = $GL_str_replace("'", '&apos;', $text);
	$text = $GL_str_replace('<', '&lt;', $text);
	$text = $GL_str_replace('>', '&gt;', $text);
	return $text;
}

/*
 * Return a copy of $text with -- replaced by -&#45;
 * for safe inclusion in XML comments.
 */
function xmlUndash($text) {
	global $GL_str_replace;
	return $GL_str_replace('--', '-&#45', $text);
}

function xmlComment($text) {
	return '<!-' . '- ' . xmlUndash($text) . ' -' . '->';
}


// *****************************************************************************
// DEBUGGING

$RuntimeDebug = false;

// -----------------------------------------------------------------------------
// RuntimeDebugMessage

function RuntimeDebugMessage($msg)
{
	echo "<b><font color=\"red\">Runtime Debug Message: " . $msg . "</font></b><br>\n";
}


// -----------------------------------------------------------------------------
// Print HTML head part for debugging on actions page

function debugAddHtmlHead() {
	$cs = !function_exists('mb_http_output') ? '' : mb_http_output();
	$cs = (strtoupper($cs) == 'SJIS') ? 'Shift_JIS' : $cs;
	echo "<html><head>\r\n";
	if ($cs) {
		echo "<meta http-equiv=\"content-type\" content=\"text/html;charset=$cs\">\r\n";
	}
	echo "</head><body>\r\n";
}


// -----------------------------------------------------------------------------
// Print query string in debug message

function debugPrintQueryString()
{
	global $HTTP_GET_VARS;
	$msg = "Query String<br>\n";
	$msg .= "<table border=\"1\">\n";

	reset ($HTTP_GET_VARS);
	while (list($key, $value) = each($HTTP_GET_VARS)) {
		$msg .= "<tr><td>" . xmlEncode($key)
		    . "</td><td>" . xmlEncode($value)
		    . "</td></tr>\r\n";
	}

	$msg .= "</table>\n";
	RuntimeDebugMessage($msg);
}


// -----------------------------------------------------------------------------
// Print form data in debug message

function debugPrintFormData()
{
	global $HTTP_POST_VARS;
	$msg = "Form Data<br>\n";
	$msg .= "<table border=\"1\">\n";

	reset ($HTTP_POST_VARS);
	while (list($key, $value) = each($HTTP_POST_VARS)) {
		$msg .= "<tr><td>" . xmlEncode($key)
		    . "</td><td>" . xmlEncode($value)
		    . "</td></tr>\r\n";
	}

	$msg .= "</table>\n";
	RuntimeDebugMessage($msg);
}


// -----------------------------------------------------------------------------
// Print obscured data in debug message

function debugPrintObscuredData()
{
	$database = unobscure(GetFormValue("_database"));
	$sql = unobscure(GetFormValue("_sql"));
	$cswName = unobscure(GetFormValue('_cswName'));
	$datatypes = queryStringToArray(unobscure(GetFormValue("_datatypes")));

	$msg = "Obscured Data<br>\r\n";

	$msg .= "<table border=\"1\">\r\n";
	$msg .= "<tr><td>_database</td><td>"
		. xmlEncode($database) . "</td></tr>\r\n";
	$msg .= "<tr><td>_sql</td><td>"
		. xmlEncode($sql) . "</td></tr>\n";
	$msg .= "<tr><td>_cswName</td><td>"
		. xmlEncode($cswName) . "</td></tr>\r\n";
	reset($datatypes);
	while (list($key, $value) = each($datatypes)) {
		$msg .= "<tr><td>_datatypes["
			. xmlEncode($key) . "]</td><td>"
			. xmlEncode($value) . "</td></tr>\r\n";
	}
	$msg .= "</table>\n";
	RuntimeDebugMessage($msg);
}


// *****************************************************************************
// UTILITY FUNCTIONS
//

/*
 * Return the platform-specific absolute pathname of the script
 * being executed.
 */
function GetCurrentFile() {
	global $PATH_TRANSLATED;
	$r = @$PATH_TRANSLATED;
	if (isset($r))
		return $r;
	global $HTTP_SERVER_VARS;
	$r = @$HTTP_SERVER_VARS['PATH_TRANSLATED'];
	if (isset($r))
		return $r;
	$r = @$HTTP_SERVER_VARS['SCRIPT_FILENAME'];
	if (isset($r))
		return $r;
	return 'BAD FILE ROOT';
}

/*
 * Return the rooted URL of the script being executed.
 */
function GetCurrentURL() {
	global $REQUEST_URI;
	global $GL_substr;

	$r = @$REQUEST_URI;
	if (isset($r)) {
		$ix = indexof($r, '?');
		return $ix < 0 ? $r : $GL_substr($r, 0, $ix);
	}
	global $HTTP_SERVER_VARS;
	$r = @$HTTP_SERVER_VARS['REQUEST_URI'];
	if (isset($r))
		return $r;
	$r = @$HTTP_SERVER_VARS['PATH_INFO'];
	if (isset($r))
		return $r;
	global $PHP_SELF;
	$r = @$PHP_SELF;
	if (isset($r))
		return $r;
	return 'BAD URL ROOT';
}

function GetRemoteAddress() {
	global $HTTP_SERVER_VARS, $HTTP_ENV_VARS;
	$r = @$HTTP_SERVER_VARS['REMOTE_ADDR'];
	if (!isset($r))
		$r = @$HTTP_ENV_VARS['REMOTE_ADDR'];
	if (!isset($r))
		$r = '255.255.255.255';	/* bogus host */
	return $r;
}

// -----------------------------------------------------------------------------
// Return the path to the config folder.

function GetConfigPath()
{
	$configPath = "";
	$requiredFiles = get_required_files();
	while (list(, $file) = each($requiredFiles)) {
		if (ereg("(.*)include[\/]utils.runtime6.php", $file, $regs)) {
			$configPath = $regs[1];
			break;
		}
	}
	if ($configPath == "") {
		die("caller must use require_once() or include_once() to include this file");
	}
	return $configPath;
}

// -----------------------------------------------------------------------------
// Return the path to the datasources folder.
//
// If you'd like to place the datasources outside the config folder, you can
// change the body of this routine to return the path to the datasources.  For
// instance:
//	{
//		return "C:\\datasourcesfolder\\";
//	}

function GetDataSourcePath()
{
	return GetConfigPath() . "datasources/";
}

function GetFormValue($name)
{
	global $HTTP_GET_VARS;
	global $HTTP_POST_VARS;

	if (isset($HTTP_GET_VARS[$name])) {
		return stripslashes($HTTP_GET_VARS[$name]);
	} else if (isset($HTTP_POST_VARS[$name])) {
		return stripslashes($HTTP_POST_VARS[$name]);
	} else if (isset($HTTP_GET_VARS["~$name"])) {
		return stripslashes($HTTP_GET_VARS["~$name"]);
	} else if (isset($HTTP_POST_VARS["~$name"])) {
		return stripslashes($HTTP_POST_VARS["~$name"]);
	} else if (isset($HTTP_GET_VARS[$name . "_x"])) {
		return "image button hit";
	} else if (isset($HTTP_POST_VARS[$name . "_y"])) {
		return "image button hit";
	} else {
		return "_no_form_value";
	}
}

/*
 * Read a file and break it up into lines.
 * Recognize UNIX (\n), MacOS (\r), and Windows/HTTP (\r\n)
 * conventions for line terminators.
 * In the result array, each element has a canonical line ending (\r\n).
 */
function GL_file($p, $useIncludePath=0) {
	$fp = fopen($p, "r", $useIncludePath);
	$text = fread($fp, filesize($p));
	fclose($fp);
	$text = ereg_replace("\r\n?|\n", "\r\n\1", $text);
	$a = split("\1", $text);
	if (count($a) > 0 && $a[count($a)-1]=='')
		array_pop($a);	/* spurious last element */
	return $a;
}


// *****************************************************************************
// DYNAMIC LINKING

// -----------------------------------------------------------------------------
// It's easier to know these exist than to check for them each time:

if (!isset($QUERY_STRING)) {
	$QUERY_STRING = "";
}
if (!isset($HTTP_GET_VARS)) {
	$HTTP_GET_VARS = array();
}
if (!isset($HTTP_POST_VARS)) {
	$HTTP_POST_VARS = array();
}

// -----------------------------------------------------------------------------
// Add query parameters to a href.	The first set comes from known parameters
// that must be passed by application servers.	The second is specified by
// the arguments to the function (which must be name/value pairs).

function URLArgs($argsToAdd)
{
	global $GL_strlen;
	$args = array();
	for ($i = 0; $i < sizeof($argsToAdd); $i += 2) {
		$args[urlencode($argsToAdd[$i])] = urlencode($argsToAdd[$i+1]);
	}

	//
	// Session ID
	//
	if ($GL_strlen(SID) > 0) {
		$args["PHPSESSID"] = session_id();
	}

	return queryArrayToString($args);
}

// -----------------------------------------------------------------------------
// Split up a string of the form "arg1=val1&...&argN=valN" into an array
// of argument value pairs.

function queryStringToArray($queryString)
{
	global $GL_strlen;
	global $GL_strpos;
	if ($GL_strlen($queryString) == 0) {
		return array();
	}
	$queryArray = array();
	$args = explode("&", $queryString);
	for ($i = 0; $i < sizeof($args); $i++) {
		if ($GL_strpos($args[$i], "=")) {
			list($param, $value) = explode("=", $args[$i]);
			$queryArray[$param] = $value;
		} else {
			$queryArray[$args[$i]] = "";
		}
	}
	return $queryArray;
}

// -----------------------------------------------------------------------------
// Construct a query string from an array of key value pairs.

function queryArrayToString($queryArray)
{
	$paramArray = array();
	reset($queryArray);
	while (list($param, $value) = each($queryArray)) {
		if (isset($value)) {
			$paramArray[] = $param . "=" . $value;
		} else {
			$paramArray[] = $param;
		}
	}
	return join("&", $paramArray);
}

/*
 * Construct a query string from an array of key-value pairs.
 * urlencode the keys and values.
 */
function qatostr($a) {
	reset($a);
	$sep = '';
	$s = '';
	while (list($k,$v) = each($a)) {
		$s .= $sep; $sep = '&';
		$s .= urlencode($k);
		$s .= '=';
		$s .= urlencode($v);
	}
	return $s;
}

/*
 * Construct an array of key-value pairs from a query string.
 * urldecode the keys and values.
 */
function qstoa($s) {
	$a = explode('&', $s);
	$args = array();
	while (list(,$arg) = each($a)) {
		if (ereg("(^=*)=(.*)", $arg, $m)) {
			$k = $m[1];
			$v = $m[2];
			$args[urldecode($k)] = urldecode($v);
		}
	}
	return $args;
}

// -----------------------------------------------------------------------------
// Remove all record index and key information from a query array.

function resetRecordNavigation($queryArray, $cswName) {
	$remove      = array();
	$cswParam    = "[" + $cswName + "]";
	$cswParamEsc = urlencode($cswName);

	reset($queryArray);
	while (list($k, $v) = each($queryArray)) {
		if ((indexof($k, $cswParam) != -1)||(indexof($k, $cswParamEsc) != -1)) {
			$remove[$k] = 1;
		}
	}

	$a = array();
	reset($queryArray);
	while (list($k, $v) = each($queryArray)) {
		$r = @$remove[$k];
		if (!isset($r))
			$a[$k] = $v;
	}

	reset($a);
	return $a;
}

// -----------------------------------------------------------------------------
// Turn an application-relative path into a file-relative path

function mapPath($path, $relativePathToApplicationRoot)
{
	if ($path[0] != "/" && $path[0] != "\\") {
		$path = "/" . $path;
	}
	return $relativePathToApplicationRoot . $path;
}

// -----------------------------------------------------------------------------
// Turn an relative path into an absolute path

function absolutePath($basePath, $relativePath)
{
	global $GL_strlen;
	if ($relativePath[0] == '/') {
		return $relativePath;
	}

	$origurl = explode("?", $basePath);
	$newurl = explode("?", $relativePath);

	$origparts = explode("/", $origurl[0]);
	$newparts = explode("/", $newurl[0]);

	if (sizeof($origurl) > 1) {
		$origargs = explode("&", $origurl[1]);
	} else {
		$origargs = array();
	}
	if (sizeof($newurl) > 1) {
		$newargs = explode("&", $newurl[1]);
	} else {
		$newargs = array();
	}

	$firstpart = $newparts[0];
	if ($firstpart[$GL_strlen($firstpart)-1] == ":") {	// absolute href
		$finalparts = $newparts;
	} else {
		$keeporig = sizeof($origparts) - 1;			// first we'll get rid of the last part
		$startnew = 0;
		while ($newparts[$startnew] == "..") {
			$keeporig--;							// get rid of the last part
			$startnew++;							// get rid of the ".."
		}
		$finalparts = array();
		for ($i = 0; $i < $keeporig; $i++) {
			$finalparts[sizeof($finalparts)] = $origparts[$i];
		}
		for ($i = $startnew; $i < sizeof($newparts); $i++) {
			$finalparts[sizeof($finalparts)] = $newparts[$i];
		}
	}

	$finalargs = $origargs;
	for ($i = 0; $i < sizeof($newargs); $i++) {
		$finalargs[sizeof($finalargs)] = $newargs[$i];
	}

	$abspath = join("/", $finalparts);
	if (sizeof($finalargs) > 0) {
		$abspath .= "?" . join("&", $finalargs);
	}
	return $abspath;
}

function fullURL($rootedURL) {
	global $HTTPS;
	global $SERVER_NAME;
	global $SERVER_PORT;

	$scheme = $HTTPS=='ON' ? 'https://' : 'http://';
	return "{$scheme}{$SERVER_NAME}:{$SERVER_PORT}" . $rootedURL;
}

// -----------------------------------------------------------------------------
// Returns the base URL to this page (excluding the query string).

function thisPageURL()
{
	return fullURL(GetCurrentURL());
}

function absoluteURL($relativePath)
{
	return fullURL(absolutePath(GetCurrentURL(), $relativePath));
}

// -----------------------------------------------------------------------------
// Add arguments to a url.	New values replace old values.

function addArgs($url, $newArgs)
{
	global $GL_strlen;
	if ($GL_strlen($newArgs) == 0) {
		return $url;
	}

	$urlparts = explode("?", $url);
	if (sizeof($urlparts) == 1) {
		return $url . "?" . $newArgs;
	}

	$finalArgs = queryStringToArray($urlparts[1]);
	$addArgs = queryStringToArray($newArgs);
	while (list($param, $value) = each($addArgs)) {
		$finalArgs[$param] = $value;
	}
	return $urlparts[0] . "?" . queryArrayToString($finalArgs);
}


// *****************************************************************************
// RECORD NAVIGATION
//
// These functions support "prev" and "next" links on record-oriented content
// sources.
//


/*
 * Create a link to the current page with the specified record $index,
 * preserving all other query parameters.
 */
function linkToRecord($csw, $index, $path="", $force="") {
	/*
	 * If it's a navigator, do something special.
	 */
	while (isset($csw->viewed_csw)) {
		$csw = $csw->viewed_csw;
		$index = ($index-1) * $csw->BlockSize() + 1;
		$path = '';
	}

	if (!$force) {
		if ($index < 1 || $csw->RecordCount() < $index
		 || $csw->FirstRecordOnPage() <= $index
			 && $index <= $csw->LastRecordOnPage())
			return '';
	}

	global $HTTP_GET_VARS;
	$newArray = resetRecordNavigation($HTTP_GET_VARS, $csw->GetName());
	$newArray["RECORD_INDEX(".$csw->GetName().")"] = $index;
	return " href='" . GetCurrentURL() . "?"
		. qatostr($newArray) . "'";
}

/*
 * Create a link to the previous record in a record-oriented content source.
 */
function LinkToPreviousRecord($csw, $path = "")
{
	$decr = $csw->BlockSize($path);
	if ($decr < 1) $decr = 1;
	return linkToRecord($csw, $csw->FirstRecordOnPage($path)-$decr, $path);
}

/*
 * Create a link to the next record in a record-oriented content source.
 */
function LinkToNextRecord($csw, $path = "")
{
	$incr = $csw->BlockSize($path);
	if ($incr < 1) $incr = 1;
	return linkToRecord($csw, $csw->FirstRecordOnPage($path)+$incr, $path);
}

/*
 * Create a link to a new record in a record-oriented content source.
 */
function LinkToNewRecord($csw, $path = "")
{
	// return linkToRecord($csw, $csw->RecordCount($path) + 1, $path);
	return '';	/* XXX s.b. url?RECORD_KEY(csw)=_newRecord */
}

/*
 * Create a link to the first record in a record-oriented content source.
 */
function LinkToFirstRecord($csw, $path = "")
{
	return linkToRecord($csw, 1, $path);
}

/*
 * Create a link to the last record in a record-oriented content source.
 */
function LinkToLastRecord($csw, $path = "")
{
	$bs = $csw->BlockSize($path);
	$bs = $bs < 1 ? 1 : $bs;
	$n = ceil($csw->RecordCount($path) / $bs);
	return linkToRecord($csw, ($n-1)*$bs + 1, $path);
}

/*
 * Emit the record key URL parameters for the specified content source.
 * Used for Show Details of Current Record.
 */
function AddRecordKey($csw, $path = "")
{
	$k = urlencode("RECORD_KEY(" . $csw->GetName() . ")");
	return $k.'='.$csw->Key();
}

/*
 * Emit the "new record" URL parameters for the specified content source.
 * Used for Show Details of Empty Record.
 */
function AddNewRecordKey($csw, $path="") {
	$k = urlencode("RECORD_KEY(" . $csw->GetName() . ")");
	return $k.'=_newRecord';
}


// *****************************************************************************
// CONDITIONS

function FirstRecord($csw)
{
	return $csw->FirstRecordOnPage() == 1;
}

function IsEmpty($field)
{
	return !isset($field) || $field==null || $field=='';
}

function LastRecord($csw)
{
	return $csw->LastRecordOnPage() == $csw->RecordCount();
}

function NoRecords($csw)
{
	return $csw->RecordCount() == 0;
}

function RecordCountGreaterThan($csw, $cmpValue)
{
	return $csw->RecordCount() > $cmpValue;
}

function HasError($errorField)
{
	global $GL_strlen;
	return $GL_strlen($errorField) > 0;
}

function ReturnTrue()
{
	return true;
}

function MatchField($field, $cond, $value)
{
	if ($cond == '<')
		return $field < $value;
	if ($cond == '<=')
		return $field <= $value;
	if ($cond == '==')
		return $field == $value;
	if ($cond == '!=')
		return $field != $value;
	if ($cond == '>=')
		return $field >= $value;

	if ($cond == 'like')
		return indexof("$field", "$value") >= 0;
	if ($cond == 'in')
		return indexof("$value", "$field") >= 0;
	return false;
}

function IsTrue($expression)
{
	return $expression;
}


// *****************************************************************************
// MISCELLANEOUS

// -----------------------------------------------------------------------------
// Used to conditionally generate "selected" and "checked" attributes for <form>
// controls:

function selected($condition)
{
	if ($condition) {
		return "selected";
	} else {
		return "";
	}
}

function checked($condition)
{
	if ($condition) {
		return "checked";
	} else {
		return "";
	}
}

function truth($fieldValue)
{
	if ($fieldValue == "no" || $fieldValue == "false" || $fieldValue == "off") {
		return false;
	} else {
		return $fieldValue;
	}
}


// -----------------------------------------------------------------------------
// Used to show <form> errors:

function showError($msg)
{
	if (HasError($msg)) {
		return "class='error'";
	} else {
		return "";
	}
}


// -----------------------------------------------------------------------------
// Generates alternating attributes for table cells.

function Alternate($recordNumber, $attrName, $attrSpec)
{
	$attrRuns = explode(",", $attrSpec);
	$totalCount = 0;
	for ($i = 0; $i < sizeof($attrRuns); $i++) {
		$attrRuns[$i] = explode(":", $attrRuns[$i]);
		$totalCount += $attrRuns[$i][0];
	}

	$rowIndex = ($recordNumber - 1) % $totalCount;
	$runIndex = 0;
	$runCount = 0 + $attrRuns[$runIndex][0];
	while ($runCount <= $rowIndex) {
		$runIndex++;
		if ($runIndex == sizeof($attrRuns)) {
			$runIndex = 0;
		}
		$runCount += $attrRuns[$runIndex][0];
	}
	$value = $attrRuns[$runIndex][1];

	if ($value == '_no_attr')
		return '';
	return xmlEncode($attrName) . '="' . xmlEncode($value) . '"';
}

// -----------------------------------------------------------------------------
// ConvertLineEndings text filter.

function ConvertLineEndings($text, $useNBSP, $useBR, $useP, $encodeHTML)
{
	global $GL_str_replace;
	$x_NL = "\1";
	if ($encodeHTML)
		$text = htmlspecialchars($text);
	if ($useBR || $useP)    /* canonicalize line endings */
		$text = ereg_replace("\r\n?|\n", $x_NL, $text);
	if ($useP)
		$text = ereg_replace($x_NL.$x_NL, "\r\n<P>", $text);
	if ($useBR)
		$text = ereg_replace($x_NL, "<BR>\r\n", $text);
	if ($useNBSP) {
		$text = $GL_str_replace("\t", "    ", $text);
		$text = $GL_str_replace("   ", "&nbsp;&nbsp; ", $text);
		$text = $GL_str_replace("  ", "&nbsp; ", $text);
		$text = $GL_str_replace(" &nbsp;", "&nbsp;&nbsp;", $text);
	}
	return $text;
}

/*
 * Redirect to URL, perhaps appending (as query parameters) the
 * session ID and an indication that errors occurred.
 */
function Redirect($url, $cswerr=null)
{
	$sessId = session_id();
	if ($sessId) {
		$url .= indexof($url, '?') > 0 ? '&' : '?';
		$url .= 'PHPSESSID=';
		$url .= $sessId;
	}

	if ($cswerr) {
		StoreError($cswerr);
		$url .= indexof($url, '?') > 0 ? '&' : '?';
		$url .= 'FORMERR=';
	}

	global $RuntimeDebug;
	if ($RuntimeDebug) {	//	no redirection in debug mode.
		$u = xmlEncode($url);
		RuntimeDebugMessage(
			"Redirect URL = [<a href=\"$u\">$u</a>]<p>\n");
	} else {
		header("Location: $url");
	}
	exit();
}

/*
 * Echo an error message containing $num and $desc.
 */
function writeErrorMessage($num, $desc) {
	echo '<ERROR Number="', xmlEncode($num), '" Description="',
		xmlEncode($desc), '"/>', "\r\n";
}

function sprintErrorMessage($num, $desc) {
	return '<ERROR Number="' . xmlEncode($num) . '" Description="'
		. xmlEncode($desc) . '"/>' . "\r\n";
}

function userErrorHandler ($errno, $errmsg, $filename, $linenum, $vars) {
    if (!($errno & error_reporting())) return;

    $msg = '<ERROR Number="' . xmlEncode($errno) . '" Description="Error: &quot;'
        . xmlEncode($errmsg) . '&quot; on line ' . xmlEncode($linenum) . ' of '
        . xmlEncode($filename) . '."/>' . "\r\n";
    die($msg);
}

// we will do our own error handling
set_error_handler("userErrorHandler");


/*
 * Write a header comment containing $text, suitably escaped.
 */
function writeHeaderComment($text) {
	echo "<!-", "-\r\n",
		"\t", xmlUndash($text), "\r\n",
		"  -->\r\n";
}

/*
 * Write an inline comment (no leading or trailing newlines) containing $text,
 * suitably escaped.
 */
function writeInlineComment($text) {
	echo "<!-", "- ", xmlUndash($text), " -->";
}

// -----------------------------------------------------------------------------
// Escape quote marks in attribute values.
function fixHTMLquotes($text)
{
	global $GL_strlen;
	global $GL_str_replace;
	if ($GL_strlen($text) != 0) {
		$text = $GL_str_replace("\"", "&#34;", $text);
		$text = $GL_str_replace("'",  "&#39;", $text);
	}
	return $text;
}

// -----------------------------------------------------------------------------
// Position a content source at the end.  Designed for hand-coders.
function MoveToEnd(&$cs)
{
	$cs->MoveTo($cs->RecordCount()+1);
}

function pageParameter($name, $defaultValue)
{
	if (@$GLOBALS[$name] <> "") {
		return @$GLOBALS[$name];
	} else {
		return $defaultValue;
	}
}

// *****************************************************************************
// SECURITY

function RejectUnauthorizedCallers()
{
	$r = GetRemoteAddress();
	$caller = explode(".", $r);

	$friendsPath = GetConfigPath() . "include/friends.php";
	if (@is_file($friendsPath)) {
		$friends = GL_file($friendsPath);
		for ($line = 0; $line < sizeof($friends); $line++) {
			if (!$friends[$line] || $friends[$line][0] == ";" || $friends[$line][0] == "<") {
				continue;
			}
			$parts = preg_split('/\s+/', trim($friends[$line]));

			$kosherAddr = explode(".", $parts[0]);
			$kosherMask = sizeof($parts) > 1 ? explode(".", $parts[1]) : array(255, 255, 255, 255);
			$kosher = 0;
			for ($i = 0; $i < sizeof($caller) && $i < sizeof($kosherAddr); $i++) {
				if ((int)$kosherAddr[$i] == ((int)$caller[$i] & (int)$kosherMask[$i])) {
					$kosher++;
				}
			}
			if ($kosher == 4) {
				return;
			}
		}
	} else {
		// no friends file, local machine access only:
		if (join(".", $caller) == "127.0.0.1") {
			return;
		}
	}

	// XXX apache 1.3.20 complains about malformed header (perhaps
	// because php has jumped the gun and sent a 200 header already?)
	// header("HTTP/1.1 403 Forbidden");
	echo("<p>\r\n");
	echo "You are not authorized to access this page. Check with your Web administrator to make sure your machine is listed in the include/friends.php file.";
	echo("</p>\r\n");
	exit();
}


// -----------------------------------------------------------------------------
// These functions allow the passing of sensitive information over a URL.  They
// store the infomation in the session, and pass a randomly-generated key to it.

function obscure($plaintext)
{
	global $obscureData;

	reset($obscureData);
	while (list($key, $value) = each($obscureData)) {
		if ($value == $plaintext) {
			return $key;
		}
	}

	//
	// Not found; need to add...
	//
	$key = rand();
	$obscureData[$key] = urlencode($plaintext);
	return $key;
}

function unobscure($key)
{
	global $obscureData;
	return urldecode($obscureData[$key]);
}


// *****************************************************************************
// FILTERS

function FormatCurrency($float, $decimals, $dec_point, $thousands_sep)
{
	$locale_info = localeconv();
	return $locale_info["currency_symbol"] . number_format($float, $decimals, $dec_point, $thousands_sep);
}


function FormatPercent($float, $decimals, $dec_point, $thousands_sep)
{
	return number_format($float, $decimals, $dec_point, $thousands_sep) . '%';
}

/*
 * Make a best effort at decoding a timestamp string, and return
 * the result as a UNIX epoch value (seconds since 1970/01/01 00:00:00Z),
 * or -1 if nothing worked.
 */
function parseTime($tt) {
	if (is_numeric($tt))    /* already a UNIX timestamp */
		return $tt;
	/*
	 * Try date-time, date, time as returned by MySQL
	 */
	list($YY, $MM, $DD, $hh, $mm, $ss) =
		sscanf($tt, "%d-%d-%d %d:%d:%d");
	if (isset($ss))
		return mktime($hh, $mm, $ss, $MM, $DD, $YY);
	list($YY, $MM, $DD) = sscanf($tt, "%d-%d-%d");
	if (isset($MM))
		return mktime(0, 0, 0, $MM, $DD, $YY);
	list($hh, $mm, $ss) = sscanf($tt, "%d:%d:%d");
	if (isset($mm))
		return mktime($hh, $mm, $ss);
	return -1;	/* 1969/12/31 11:59:59Z */
}
function FormatDateTime($datetime, $formatString) {
	return date($formatString, parseTime($datetime));
}
function FormatDate($date, $formatString) {
	return date($formatString, parseTime($date));
}
function FormatTime($time, $formatString) {
	return date($formatString, parseTime($time));
}

/**
 ** ERROR CONTENT SOURCE
 **/

function GetError() {
	global $obscureData, $HTTP_GET_VARS, $HTTP_POST_VARS;
	if (!isset($HTTP_GET_VARS['FORMERR'])
	 && !isset($HTTP_POST_VARS['FORMERR']))
		return null;
	$r = @$obscureData['FORMERR'];
	return !isset($r) ? null : $r;
}

function StoreError($cswerr) {
	global $obscureData;
	$obscureData['FORMERR'] = $cswerr;
}

class CSWError {
	var $ix, $count, $firstRec, $lastRec;
	var $dollar, $data, $error;

	function CSWError() {
	    $this->name = "unknown";
		$this->ix = $this->firstRec = 700514;
		$this->count = $this->lastRec = -700514;
		$this->dollar = array();
		$this->data = array();
		$this->error = array();
	}

	/*
	 * Implement the content source wrapper interface.
	 */
	function AbsolutePosition() {
		return $this->ix;
	}
	function BlockSize() {
		return $this->lastRec - $this->firstRec + 1;
	}
	function EOB() {
		return $this->ix > $this->lastRec;
	}
	function EOF() {
		return $this->ix > $this->count;
	}
	function Error($fieldName) {
		$r = @$this->error[$this->ix][$fieldName];
		return isset($r) ? $r : '';
	}
	function FirstRecordOnPage() {
		return $this->firstRec;
	}
	function Key() {
		die("Key() in CSWError.");
	}
	function LastRecordOnPage() {
		return $this->lastRec;
	}
	function Move($off) {
		$this->ix += $off;
	}
	function MoveFirst() {
		$this->ix = 1;
	}
	function MoveNext() {
		++$this->ix;
	}
	function MoveTo($ix) {
		$this->ix = $ix;
	}
	function MoveToKey() {
		die("MoveToKey() in CSWError.");
	}
	function RecordCount() {
		return $this->count;
	}
	function Value($fieldName) {
		return @$this->data[$this->ix][$fieldName];
	}
	function GetName() {
		return $this->name;
	}

	/* Other functions. */

	function ChangeValue($fieldName, $val) {
		$ix = $this->ix;
		if ($ix < $this->firstRec || $this->lastRec < $i
		 || !isset($this->data[$ix]))
			return;
		$this->data[$ix][$fieldName] = $val;
	}

	function DollarData() {
		reset($this->dollar);
		return $this->dollar;
	}

	function loadDollarDatum($k) {
		if (indexof($k, '_') == 0)
			$this->dollar[$k] = GetFormValue($k);
	}

	function LoadDollarDataFromForm() {
		global $HTTP_GET_VARS, $HTTP_POST_VARS;
		reset($HTTP_GET_VARS);
		while (list($k,) = each($HTTP_GET_VARS))
			$this->loadDollarDatum($k);
		reset($HTTP_POST_VARS);
		while (list($k,) = each($HTTP_POST_VARS))
			$this->loadDollarDatum($k);
	}

	function loadFormDatum($k) {
		if (ereg('^(.*)\(([0-9]+)\)$', $k, $m)) {
			$ix = $m[2]; $field = $m[1];
			if (!isset($this->data[$ix]))
				$this->data[$ix] = array();
			$this->data[$ix][$field] = GetFormValue($k);
			if ($this->firstRec > $ix)
				$this->firstRec = $ix;
			if ($this->lastRec < $ix)
				$this->lastRec = $ix;
			if ($this->count < $ix)
				$this->count = $ix;
		}
	}

	function LoadFormData() {
		global $HTTP_GET_VARS, $HTTP_POST_VARS;
		reset($HTTP_GET_VARS);
		while (list($k,) = each($HTTP_GET_VARS))
			$this->loadFormDatum($k);
		reset($HTTP_POST_VARS);
		while (list($k,) = each($HTTP_POST_VARS))
			$this->loadFormDatum($k);
		if ($this->firstRec == 700514) {
			$this->firstRec = 1;
			$this->lastRec = 0;
			$this->count = 0;
		}
	}

	function SetError($fieldName, $msg) {
		$ix = $this->ix;
		if ($ix < $this->firstRec || $this->lastRec < $i)
			return;
		if (!isset($this->error[$ix]))
			$this->error[$ix] = array();
		$this->error[$ix][$fieldName] = $msg;
	}
}

function NewError() {
	$cswerr = new CSWError;
	$cswerr->LoadDollarDataFromForm();
	$cswerr->LoadFormData();
	/* ignore RECORDS parameter */
	$cswerr->MoveFirst();
	return $cswerr;
}


/**
 ** HTTP, HTTPS
 **/

/**
 * $GL_pxa is searched by GL_proxyInfo() to determine a proxy setting
 * for use by GL_curl_get().
 */
$GL_pxa = array(
	/*
	 * No Proxy:
	 * This pattern selects any http or https URL and gives an
	 * empty string as the proxy.
	 * If you're running a site at an ISP, your site it likely
	 * will not have a firewall, so this entry is most likely
	 * what you want.
	 * Alternatively, if you are only calling to sites within
	 * your firewall, you typically won't use a proxy server to
	 * contact them, and this entry again is sufficient.
	 *
	 * Note that since this entry matches any http or https URL,
	 * you need to remove it (or move it to the end of the
	 * $GL_pxa array) in order for any of the other entries
	 * to take effect.
	 */
	array(
		'pattern' => '^https?://.*',
		'proxy-url' => '',
	),
	/*
	 * No proxy for internal hosts:
	 * This pattern selects particular http or https URLs
	 * that identify sites within your firewall.
	 * Edit the pattern string so that it matches your
	 * company or organization.
	 */
	array(
		'pattern' => '^https?://.*\.yourcompany.com',
		'proxy-url' => '',
	),
	/*
	 * Proxy for all hosts:
	 * This pattern selects all http and https URLs and returns
	 * the URL of your proxy server.  Edit the proxy server URL
	 * so that it identifies your company or organization's proxy
	 * server.
	 *
	 * Inside a firewall, you typically want $GL_pxa to contain
	 * the two entries "No proxy for internal hosts:" and
	 * "Proxy for all hosts:" (in that order).  Since we search
	 * $GL_pxa from top to bottom, we first check for an internal host.
	 * If the URL matches the internal host pattern, we return
	 * that entry.
	 * If the URL doesn't match the internal host pattern, we look
	 * at the all-hosts entry, which will match.
	 */
	array(
		'pattern' => 'https?://.*',
		'proxy-url' => 'http://your-http-relay.yourcompany.com:80',
	),
);

/**
 * Searches $GL_pxa from top to bottom looking for the first entry
 * that matches $URL; returns the entry if found, else an empty array.
 */
function GL_proxyInfo($url) {
	global $GL_pxa;
	for ($i = 0; $i < count($GL_pxa); ++$i)
		if (ereg($GL_pxa[$i]['pattern'], $url))
			return $GL_pxa[$i];
	return array();
}

/*
 * Sends an http or https GET request to $url.
 * Returns an array: [0] is the response, if any.
 * If [1] is nonemtpy, then the request failed: [1] is a curl error number
 * and [2] is a curl error string.
 */
function GL_curl_get($url) {
	/*
	 * If CURL is not configured in, we still can try using
	 * the builtin PHP facilities.  We can't use https, or
	 * proxies, or set/receive headers.  But we can use the
	 * simple HTTP GET method, which works in many cases.
	 */
	if (!function_exists("curl_init")) {
		$f = @fopen($url, "rb");
		if (!$f)
			return array('', 42, "Cannot open $url");
		$data = '';
		while ($buf = @fgets($f, 4096))
			$data .= $buf;
		@fclose($f);
		return array($data, '', '');
	}

	global $RuntimeDebug;
	$proxyInfo = GL_proxyInfo($url);
	$proxy = @$proxyInfo['proxy-url'];
	if ($RuntimeDebug) {
		echo xmlComment("GL_curl_get url $url proxy $proxy");
		flush();
	}
	$ch = @curl_init($url);
	if (!$ch)
		return array('', curl_errno($ch), curl_error($ch));

	if (indexof($url, 'https://')==0)
		@curl_setopt($ch, CURLOPT_SSLVERSION, 2);
	@curl_setopt($ch, CURLOPT_HEADER, 1);
	@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	if ($proxy)
		@curl_setopt($ch, CURLOPT_PROXY, $proxy);

	$data = @curl_exec($ch);
	list($header, $data) = explode("\r\n\r\n", $data, 2);
	if (ereg("^HTTP/1.. 2..", $header)) {
		$r = array($data, '', '');
	} else {
		$r = array($data, curl_errno($ch), curl_error($ch) . $data);
	}
	curl_close($ch);
	return $r;
}

?>
