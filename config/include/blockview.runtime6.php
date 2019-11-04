<?php

/*
 * GoLive
 *
 * ADOBE SYSTEMS INCORPORATED
 * Copyright 2000-2002 Adobe Systems Incorporated. All Rights Reserved.
 * 
 * NOTICE:  Notwithstanding the terms of the Adobe GoLive End User 
 * License Agreement, Adobe permits you to reproduce and distribute this 
 * file only as an integrated part of a web site created with Adobe 
 * GoLive software and only for the purpose of enabling your client to 
 * display their web site. All other terms of the Adobe license 
 * agreement remain in effect.
 */

function NewCSWBlockView($records, $viewed_csw, $name) {
	$csn = new CSWBlockView($records, $viewed_csw, $name);
	return $csn;
}

class CSWBlockView {
	var $viewed_csw, $name;
	var $ix, $nrow, $ncol, $bs;
	var $firstRec, $lastRec;
	var $colname;

	function CSWBlockView($records, $viewed_csw, $name) {
		$this->name = $name;
		$this->colname = array(
			null,
			'Block_Size',
			'First',
			'Last',
			'Link_URL',
			'Link_URL_checker',
			'Total_Records',
			);
		$this->ncol = count($this->colname)-1;

		$this->viewed_csw = $viewed_csw;
		$vbs = $viewed_csw->BlockSize();
		if ($vbs == 0) {
			$vbs = 1000000;
		}
		$this->nrow = ceil($viewed_csw->RecordCount() / $vbs);
		$this->firstRec = 1;
		$this->lastRec = $this->nrow;
		$t = ceil($viewed_csw->FirstRecordOnPage() / $vbs);
		$this->MoveTo($t);

		$m = array();
		$n = $this->nrow;
		if ($records=='all')
			{$n = $this->nrow;RuntimeDebugMessage("all");}
		else if ($records=='single')
			$n = 1;
		else if (ereg('^block=([0-9]+)', $records, $m))
			$n = (integer) $m[1];
		if ($n == 0) $n = $this->nrow; // block=0 means all records
		$this->bs = $n;
		if ($n == 0) $this->firstRec = 1;
		else $this->firstRec = $this->ix - ($this->ix - 1) % $n;
		$this->lastRec = $this->firstRec + $n - 1;
		if ($this->lastRec > $this->nrow)
			$this->lastRec = $this->nrow;

	global $RuntimeDebug;
	if ($RuntimeDebug) {
		$msg = "CSW Info: <br>\n";
		$msg .= "<table border=\"1\">\n";
		$msg .= "<tr><td>NAME</td><td>"	                 . $name . "</td></tr>\n";
		$msg .= "<tr><td>CSW.ix</td><td>"                . $this->ix . "</td></tr>\n";
		$msg .= "<tr><td>CSW.RecordCount</td><td>"       . $this->RecordCount() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.AbsolutePosition</td><td>"  . $this->AbsolutePosition() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.FirstRecordOnPage</td><td>" . $this->FirstRecordOnPage() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.LastRecordOnPage</td><td>"  . $this->LastRecordOnPage() . "</td></tr>\n";
		$msg .= "<tr><td>CSW.BlockSize</td><td>"         . $this->BlockSize() . "</td></tr>\n";
		$msg .= "</table>\n";
		RuntimeDebugMessage($msg);
	}

	}

	/*
	 * Implement the ContentSourceWrapper interface.
	 */
	function AbsolutePosition() {
		return $this->ix;
	}
	function BlockSize() {
		return $this->bs;
	}
	function EOB() {
		return $this->ix > $this->nrow
			|| $this->ix > $this->lastRec;
	}
	function EOF() {
		return $this->ix > $this->nrow;
	}
	function Error($field) {
		return '';
	}
	function FirstRecordOnPage() {
		return $this->firstRec;
	}
	function Key() {
		return $this->viewed_csw->GetName();
	}
	function LastRecordOnPage() {
		return $this->lastRec;
	}
	function Move($n) {
		$this->ix += $n;
	}
	function MoveFirst() {
		$this->ix = 1;
	}
	function MoveNext() {
		++$this->ix;
	}
	function MoveTo($n) {
		$this->ix = $n;
	}
	/* TODO
	function MoveToKey($key) {
	}
	*/
	function GetName() {
		return $this->name;
	}
	function RecordCount() {
		return $this->nrow;
	}
	function Value($field) {
		$c = $this->viewed_csw;
		$vbs = $c->BlockSize();
		$vn = $c->RecordCount();
		$ix = $this->ix;
		if ($field=='Block_Size')
			return $vbs;
		if ($field=='First')
			return ($ix-1) * $vbs + 1;
		if ($field=='Last') {
			$n = $ix*$vbs;
			return $n < $vn ? $n : $vn;
		}
		if ($field=='Link_URL') {
			$link = linkToRecord($this, $this->ix, '', 1);
			$m = array();
			if (ereg("href='(.*)'", $link, $m))
				$link = $m[1];
			return $link;
		}
		if ($field=='Link_URL_checker') {
			$link = linkToRecord($this, $this->ix);
			$m = array();
			if (ereg("href='(.*)'", $link, $m))
				$link = 'OK';
			return $link;
		}
		if ($field=='Total_Records')
			return $vn;
		return '';
	}

	function printme() {
		echo "<p>CSW name ", $this->name, " ix ", $this->ix,
		" nrow ", $this->nrow, " ncol ", $this->ncol,
		" bs ", $this->bs, " firstRec ", $this->firstRec,
		" lastRec ", $this->lastRec, "<p>\r\n";
	}
}

?>
