<?php

// *****************************************************************************
//
// include/extra.runtime6.php
//
// GoLive extra filters for PHP.
//
// ADOBE SYSTEMS INCORPORATED
// Copyright 2001-2002 Adobe Systems Incorporated. All Rights Reserved.
// 
// NOTICE:  Notwithstanding the terms of the Adobe GoLive End User 
// License Agreement, Adobe permits you to reproduce and distribute this 
// file only as an integrated part of a web site created with Adobe 
// GoLive software and only for the purpose of enabling your client to 
// display their web site. All other terms of the Adobe license 
// agreement remain in effect.


//  Truncate string and add truncatedString 
//  when the length is longer than cutOffLength
function Truncate($source, $cutOffLength, $truncatedString)
{
	global $GL_substr;
	global $GL_strlen;
	if ((!isset($truncatedString))||($GL_strlen($truncatedString) == 0)||($GL_strlen($source) <= $cutOffLength)) {
		return $GL_substr($source, 0, $cutOffLength);
	} else {
		return $GL_substr($source, 0, $cutOffLength) . $truncatedString;
	}
}

//  Do RegExp.match
//	If it matched, it returns match string.
//  If match string iclude $1-$9, it will be replaced.
//	If it was not matched, it returns notMatch string.
function RegExpMatch($source, $regExp, $ignoreCase, $match, $notMatch)
{
	global $GL_strlen;
	global $GL_strpos;
	$matches = "";
	$result = false;
	if ($ignoreCase) {
		$result = eregi($regExp, $source, $matches);
	} else {
		$result = ereg($regExp, $source, $matches);
	}
	if ($result == false) {	// not matched
		return (($notMatch == "") ? $source : $notMatch);
	} else {
		if ($match == "") {
			return $source;
		}
		if (!$GL_strpos($match, '$')) {
			return $match;
		}
		$replaced = "";
		for ($i = 0; $i < $GL_strlen($match); $i++) {
			if ($match[$i] != '$') {
				$replaced .= $match[$i];
			} else {	// with '$'
				$i++;
				if ($i >= $GL_strlen($match)) {
					break;
				}
				switch($match[$i]) {
					case '1':	$replaced .= $matches[1];	break;
					case '2':	$replaced .= $matches[2];	break;
					case '3':	$replaced .= $matches[3];	break;
					case '4':	$replaced .= $matches[4];	break;
					case '5':	$replaced .= $matches[5];	break;
					case '6':	$replaced .= $matches[6];	break;
					case '7':	$replaced .= $matches[7];	break;
					case '8':	$replaced .= $matches[8];	break;
					case '9':	$replaced .= $matches[9];	break;
					default:	$replaced .= $match[$i];	break;
				}
			}
		}
		return $replaced;
	}
}

//  Do RegExp.replace
function RegExpReplace($source, $regExp, $ignoreCase, $replace)
{
	if ($ignoreCase) {
		return eregi_replace($regExp, $replace, $source);
	} else {
		return ereg_replace($regExp, $replace, $source);
	}
}


?>
