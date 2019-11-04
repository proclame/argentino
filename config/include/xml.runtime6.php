<?php

// *****************************************************************************
//
// include/xml.runtime6.php
//
// GoLive runtime support for XML
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
// Multi Byte Function support
// To use multi byte funcions, refer 
//   http://www.php.net/manual/en/ref.mbstring.php
// 

if (function_exists('mb_strlen')) {
	$GL_strlen      = 'mb_strlen';
	$GL_substr      = 'mb_substr';
	$GL_strpos      = 'mb_strpos';
	$GL_str_replace = 'gl_str_replace';
    $to_utf8        = 'mb_utf8_encode';
    $un_utf8        = 'mb_utf8_decode';
} else {
	$GL_strlen      = 'strlen';
	$GL_substr      = 'substr';
	$GL_strpos      = 'strpos';
	$GL_str_replace = 'str_replace';
    $to_utf8        = 'utf8_encode';
    $un_utf8        = 'utf8_decode';
}

function mb_utf8_encode($text)
{
    return mb_convert_encoding($text, 'UTF-8', mb_internal_encoding());
}

function mb_utf8_decode($text)
{
    return mb_convert_encoding($text, mb_internal_encoding(), 'UTF-8');
}

// -----------------------------------------------------------------------------
// Instantiate Sablotron once for the page

global $xh;
$xh = xslt_create();
xslt_set_scheme_handlers($xh, array('get_all' => 'get_all'));
register_shutdown_function("free_xh");

// -----------------------------------------------------------------------------
// Clean up Sablotron

function free_xh()
{
	global $xh;
	xslt_free($xh);
}

// -----------------------------------------------------------------------------
// Scheme handler for Sablotron

function get_all($xh, $scheme, $rest)
{
	if (preg_match('/^http[s]?$/i', $scheme)) {
		list($data, $errno, $errstr) = @GL_curl_get("$scheme:$rest");
		if ($errstr == '')
			return $data;
		// $errstr returns the complete server error msg
		// which is too long to be useful in this context
		die(sprintErrorMessage($errno, "The URL $scheme:$rest could not be retrieved. Perhaps it is invalid."));
	}
	
	die(sprintErrorMessage(E_USER_ERROR, "The URL $scheme:$rest does not begin with http:, https:, or file:."));
}

// -----------------------------------------------------------------------------
// Wrapper for various means of opening URLs.

function get_url($url)
{
	global $GL_substr;
	global $GL_str_replace;
	
	if (preg_match('/^http[s]?:\/\//i', $url)) {
		list($data, $errno, $errstr) = @GL_curl_get($url);
		if ($errstr == '')
			return $data;
		// $errstr returns the complete server error msg
		// which is too long to be useful in this context
		die(sprintErrorMessage($errno, "The URL $url could not be retrieved. Perhaps it is invalid."));
	}
	
	if (strncasecmp($url, 'file://', 7) == 0) {
		$fileName = $GL_str_replace('/', '\\', $GL_substr($url, 7));
		$fd = fopen($fileName, "r"); 
		$data = fread($fd, filesize($fileName)); 
		fclose($fd); 
		return $data;
	}
	
	die(sprintErrorMessage(E_USER_ERROR, "The URL $url does not begin with http:, https:, or file:."));
}

// -----------------------------------------------------------------------------
// Get canonical url for xds file.

function XDSURL($dstype)
{
	global $GL_str_replace;
    $dsFilename = GetDataSourcePath() . $dstype . '.xds';
    return 'file://' . $GL_str_replace('\\', '/', realpath($dsFilename));
}

// -----------------------------------------------------------------------------
// Get count of nodes returned by a given XPath expression.

function node_count($xml, $xpath)
{
	global $xh;
    global $to_utf8;

	$xslCount = '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output omit-xml-declaration="yes" />
<xsl:template match="/"><xsl:value-of select="count(%s)" /></xsl:template>
</xsl:stylesheet>';

	$args['/_xml'] = $xml;
	$args['/_xsl'] = sprintf($xslCount, $to_utf8($xpath));
	$count = xslt_process($xh, 'arg:/_xml', 'arg:/_xsl', NULL, $args);
	return (int)$count;
}

// *****************************************************************************
// constructor

function GetXMLSource($records, $dstype, $xmlurl, $csname)
{
	global $xh;
	
    // get the datasource file
    $dsFilename = XDSURL($dstype);

	$xslDSInfo = '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output omit-xml-declaration="yes" />
	<xsl:template match="/">$xslurl=\'<xsl:value-of select="GL6XMLSOURCE/@xslurl" disable-output-escaping="yes" />\';$single=\'<xsl:value-of select="GL6XMLSOURCE/@single" disable-output-escaping="yes" />\';</xsl:template>
	</xsl:stylesheet>';

	$variables = xslt_process($xh, $dsFilename, 'arg:/_xsl', NULL, array('/_xsl' => $xslDSInfo));
	if (!$variables) {
		return new XMLSource('<xml />', '/*');
	}
	eval($variables); // sets $xslurl and $single
	
	if (indexof($xmlurl, ":") == -1) {
		$xmlurl = absoluteURL($xmlurl);
	}
	
    // get the xml data, transforming it if necessary
	if ($xslurl == "") {
		$xmlData = get_url($xmlurl);
	} else {
		$xmlData = xslt_process($xh, $xmlurl, $xslurl);
		if(!$xmlData) {
			return new XMLSource('<xml />', '/*');
		}
	}

	if ($block = explode("=", $records)) {
		$blockSize = (integer)$block[1];
	} else {	// old version compatibility
		if ($records == "single") {
			$blockSize = 1;
		}
		if ($records == "all") {
			$blockSize = 0;
		}
	}

	return new XMLSource($xmlData, ($single == 'true') ? '/*' : '/*/*', $blockSize, $csname);
}

// *****************************************************************************
// class

class XMLSource {
	var $xmlstring;
	var $state;
	var $root;
	var $blockSize;
	var $firstIndex;
	var $lastIndex;
	var $name;
	
	function XMLSource($xmlsrc, $root, $blockSize = 0, $csname = "")
	{
		global $HTTP_GET_VARS;

		$this->xmlstring = $xmlsrc;
		$this->root = $root;
		$this->name = $csname;
		$this->state["position"] = 1;
		$this->state["count"] = node_count($xmlsrc, $root);

		$this->blockSize = $blockSize;
		if ($blockSize == 0) { // "all" case
			$this->firstIndex = 1;
			$this->lastIndex = $this->state["count"];
		} else {
			$k = "RECORD_KEY({$csname})";
			$r = @$HTTP_GET_VARS[$k];
			if (isset($r)) {
				$this->MoveToKey($r);
			}
			$k = "RECORD_INDEX({$csname})";
			$r = @$HTTP_GET_VARS[$k];
			if (isset($r)) {
				$this->MoveTo($r);
			}

			$this->firstIndex = $this->state["position"];
			if ($blockSize == 1) { // "single" case
				$this->lastIndex = $this->firstIndex;
			} else {	//	"block=N" case
				$this->lastIndex = Min($this->state["count"], $this->firstIndex + $blockSize - 1);
			}
		}
	}
	
	function &_walk($pathArray, $value, &$selector)
	{
		global $GL_substr;

		$node =& $this->state;
		$selector = $this->root . "[{$node['position']}]";
		foreach ($pathArray as $step) {
			$selector .= "/*[name() = '$step']";
			if(!isset($node['/'][$step])) {
				$node['/'][$step]["position"] = 1;
				$node['/'][$step]["count"] = node_count($this->xmlstring, $selector);
			}
			$node =& $node['/'][$step];
			$selector .= "[{$node['position']}]";
		}
		if ($value == ".") {
			// do nothing
		} elseif ($GL_substr($value, 0, 1) == "@") {
			$value = $GL_substr($value, 1);
			$selector .= "/@*[name() = '$value']";
		} else {
			$selector .= "/*[name() = '$value']";
		}
		return $node;
	}
	
	function &_node($path)
	{
		global $GL_strlen;
		return $this->_walk($GL_strlen($path) ? explode('/', $path) : array(), '.', $selector);
	}
	
    function Move($delta, $path = "")
    {
		$node =& $this->_node($path);
		$node["position"] += $delta;
		
		if ($node["position"] < 1) {
			$node["position"] = 1;
		} elseif ($node["position"] > ($node["count"] + 1)) {
			$node["position"] = $node["count"] + 1;
		}
		
		unset($node["/"]);
    }
    
    function MoveFirst($path = "")
    {
		$node =& $this->_node($path);
		$node["position"] = 1;
		unset($node["/"]);
	}
	
    function MoveNext($path = "")
    {
		$node =& $this->_node($path);
		if ($node["position"] <= $node["count"]) {
			$node["position"]++;
		}
		unset($node["/"]);
	}
	
    function MoveTo($ix, $path = "")
    {
		$node =& $this->_node($path);
		$node["position"] = $ix;
		
		if ($node["position"] < 1) {
			$node["position"] = 1;
		} elseif ($node["position"] > ($node["count"] + 1)) {
			$node["position"] = $node["count"] + 1;
		}
		
		unset($node["/"]);
	}

    function MoveToKey($key)
    {
		while(!$this->EOF() && ($key != $this->Key()))
			$this->MoveNext();
    }

	function NestedCSW($path = "")
	{
		return new XMLSource($this->xmlstring, $this->root . $path);
	}
	
	function EOF($path = "")
	{
		$node =& $this->_node($path);
		return $node["position"] > $node["count"];
	}
    
	function EOB() // in version 6.0, blocking is only allowed on the top level
	{
		return ($this->state["position"] > $this->state["count"])
			|| ($this->state["position"] > $this->lastIndex);
	}
    
    function AbsolutePosition($path = "")
    {
		$node =& $this->_node($path);
		return $node["position"];
    }
    
    function RecordCount($path = "")
    {
		$node =& $this->_node($path);
		return $node["count"];
    }

    function BlockSize() // in version 6.0, blocking is only allowed on the top level
    {
		return $this->blockSize;
    }
    
    function FirstRecordOnPage() // in version 6.0, blocking is only allowed on the top level
    {
		return $this->firstIndex;
    }
    
    function LastRecordOnPage() // in version 6.0, blocking is only allowed on the top level
    {
		return $this->lastIndex;
    }
    
    function GetName()
    {
		return $this->name;
	}
	
    function Value($name)
    {
		global $xh;
        global $to_utf8;
        global $un_utf8;

		$pathArray = explode('/', $name);
		$value = array_pop($pathArray);
		$node =& $this->_walk($pathArray, $value, $selector);

		if ($node["position"] > $node["count"]) {
			return "";
		}
		
		$xslValue = '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output omit-xml-declaration="yes" />
<xsl:template match="*"><xsl:for-each select="node()"><xsl:copy-of select="." /></xsl:for-each></xsl:template>
<xsl:template match="@*"><xsl:value-of select="." /></xsl:template>
<xsl:template match="/"><xsl:apply-templates select="%s" /></xsl:template>
</xsl:stylesheet>';

		$args['/_xml'] = $this->xmlstring;
		$args['/_xsl'] = sprintf($xslValue, $to_utf8($selector));
		$result = xslt_process($xh, 'arg:/_xml', 'arg:/_xsl', NULL, $args);
		return $result ? $un_utf8($result) : "";
    }

    function Key()
    {
		return (string)crc32($this->Value('.'));
    }

    function Error($name)
    {
		return $this->Value($name . '/@error');
    }
}

?>
