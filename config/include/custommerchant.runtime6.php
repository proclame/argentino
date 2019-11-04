<?php
//
// require_once("xml.runtime6.php")
//
// Looks simple enough, but it won't work because require() paths are relative
// to the requested file, not the file doing the require().  The longer way
// appears below:
//
{
	$includePath = "";
	$requiredFiles = get_required_files();
	while (list(, $file) = each($requiredFiles)) {
		if (ereg("(.*)custommerchant.runtime6.php", $file, $regs)) {
			$includePath = $regs[1];
			break;
		}
	}
	if ($includePath == "") {
		die("caller must use require_once() or include_once() to include this file");
	}
	require_once($includePath . "xml.runtime6.php");
}


// *****************************************************************************
//
// include/custommerchant.runtime6.php
//
// GoLive runtime support for CustomMerchant
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
//

// *****************************************************************************
// SESSION HANDLING
//
// Note: session_start sends cookie information in the response header, and
// so MUST appear before any output is set.

session_start();
if (isset($HTTP_SESSION_VARS["sesShopperId"])) {
	// in case register_globals isn't set...
	$sesShopperId = $HTTP_SESSION_VARS["sesShopperId"];
} else {
	$sesShopperId = "";
	session_register("sesShopperId");
}


// *****************************************************************************
// HTTP UTILITIES

// -----------------------------------------------------------------------------
// Get the results of an HTTP query as a string.

function GetHTTPResultsAsText($providerURL, $query) {
	list($data, $errno, $errstr) = @GL_curl_get($providerURL . '?' . $query);

	//
	// Egregious hack: Sablotron seems to get confused with a
	// "standalone" attribute on the XML PI.  So we whack it.
	//
	$data = ereg_replace("standalone=([\"][^\"]*[\"]|['][^']*['])", "", $data);

	if ($errstr == '')
		return $data;
	return sprintErrorMessage($errno, $errstr);
}


// *****************************************************************************
// CUSTOM MERCHANT UTILITIES

// -----------------------------------------------------------------------------
// Get the provider info from the e-commerce provider datasource.

function GetProviderInfo($provider)
{
	$filePath = GetDataSourcePath() . $provider . '.ecp';
	if (!file_exists($filePath)) {
		user_error("$filePath was not found.", E_USER_ERROR);
	}

	$file = GL_file($filePath);
	for ($i = 0; $i < sizeof($file); $i++) {
		if (preg_match('/(\S+)\s+(.*)/', $file[$i], $regs))
			$providerInfo[$regs[1]] = trim($regs[2]);
	}

	global $RuntimeDebug;
	if ($RuntimeDebug) {
		$msg = "Provider Info:\n";
		reset($providerInfo);
		while (list($k,$v) = each($providerInfo)) {
			$msg .= "$k $v\r\n";
		}
		echo xmlComment($msg);
		flush();
	}

	$keys = array_keys($providerInfo);
	if (!in_array("url", $keys)) {
		die("ERROR: \"url\" is not specified in $filePath.");
	}
	if (!in_array("merchant-id", $keys)) {
		die("ERROR: \"merchant-id\" is not specified in $filePath.");
	}
	if ($providerInfo["url"] == "") {
		die("ERROR: \"url\" has invalid setting in $filePath.");
	}
	if ($providerInfo["merchant-id"] == "") {
		die("ERROR: \"merchant-id\" has invalid setting in $filePath.");
	}

	return $providerInfo;
}

// -----------------------------------------------------------------------------
// Return the current shopper-id from the session, or create a new one.

function GetShopperId($provider)
{
	global $sesShopperId;
	global $GL_strlen;

	if ($GL_strlen($sesShopperId) == 0) {
		$providerInfo = GetProviderInfo($provider);
		$query = "action=new-cart&merchant-id=" . $providerInfo['merchant-id'];
		$xml = GetHTTPResultsAsText($providerInfo['url'], $query);
		if (ereg("<shopper-id>(.*)</shopper-id>", $xml, $regs)) {
			$sesShopperId = trim($regs[1]);
		}
	}

	return $sesShopperId;
}


// *****************************************************************************
// CONTENT SOURCE WRAPPERS: CART AND ORDERFORM
//
// Items are accessed through the wrappers as follows:
//
//	  $cart = CustomMerchantCart("block=1", "ecommerce_provider", "cart")
//	  $cart->MoveFirst("item");
//	  $cart->Value("item/description");
//
//	  $orderForm = CustomMerchantOrderForm("block=1", "ecommerce_provider", "orderForm")
//	  $orderForm->Value("ship_to_name");
//	  $orderForm->Value("cc-number");

function CustomMerchantCart($records, $provider, $name)
{
	return new CSWCustomMerchant($provider, true, $name);
}

function CustomMerchantOrderForm($records, $provider, $name)
{
	return new CSWCustomMerchant($provider, false, $name);
}

// -----------------------------------------------------------------------------
// Content source wrapper for shopping cart and order form.

class CSWCustomMerchant
{
	var $providerURL;
	var $merchantId;
	var $shopperId;
	var $xmlWrapper;

	function CSWCustomMerchant($provider, $isCart, $name)
	{
		global $RuntimeDebug;
		$this->name = $name;

		$providerInfo = GetProviderInfo($provider);
		$this->providerURL = $providerInfo['url'];
		$this->merchantId = $providerInfo['merchant-id'];
		$this->shopperId = GetShopperId($provider);

		$args = Array();
		$args["action"] = $isCart ? "get-cart" : "get-order-form";
		$args["merchant-id"] = $this->merchantId;
		$args["shopper-id"] = $this->shopperId;
		if ($RuntimeDebug) {
			RuntimeDebugMessage ("Custom Merchant Source ($name): request: " . queryArrayToString($args) . "\n");
		}
		$data = GetHTTPResultsAsText($this->providerURL, queryArrayToString($args));
		$this->xmlWrapper = new XMLSource($data, '/*/*');
		if ($RuntimeDebug) {
			RuntimeDebugMessage ("Custom Merchant Source ($name): $data\n");
		}
	}

	function RecordCount($path = "")
	{
		return $this->xmlWrapper->RecordCount($path);
	}
	function AbsolutePosition($path = "")
	{
		return $this->xmlWrapper->AbsolutePosition($path);
	}
	function FirstRecordOnPage($path = "")
	{
		return $this->xmlWrapper->FirstRecordOnPage($path);
	}
	function LastRecordOnPage($path = "")
	{
		return $this->xmlWrapper->LastRecordOnPage($path);
	}
	function BlockSize($path = "")
	{
		return $this->xmlWrapper->BlockSize($path);
	}
	function EOB($path = "")
	{
		return $this->xmlWrapper->EOB($path);
	}
	function EOF($path = "")
	{
		return $this->xmlWrapper->EOF($path);
	}
	function GetName()
	{
		return $this->name;
	}

	// -------------------------------------------------------------------------
	// Cursor positioning:

	function Move($delta, $path = "")
	{
		return $this->xmlWrapper->Move($delta, $path);
	}

	function MoveFirst($path = "")
	{
		return $this->xmlWrapper->MoveFirst($path);
	}

	function MoveNext($path = "")
	{
		return $this->xmlWrapper->MoveNext($path);
	}

	// -------------------------------------------------------------------------
	// Data access:

	function Value($fieldName)
	{
		return $this->xmlWrapper->Value($fieldName);
	}

	function Error($fieldName)
	{
		return $this->xmlWrapper->Error($fieldName);
	}

	function Key($path = "")
	{
		// Only items have keys (their skus)
		if ($path == "item") {
			return $this->xmlWrapper->Value("item/sku");
		} else {
			return "";
		}
	}

	function NestedCSW($path = "")
	{
		return $this->xmlWrapper->NestedCSW($path);
	}
};


// *****************************************************************************
// ACTION SETUP ROUTINES

$setupArgs = array();

// -----------------------------------------------------------------------------

function MakeCMReturnURL($url)
{
	global $QUERY_STRING;

	if ($url == "\$return") {
		return thisPageURL() . "?$QUERY_STRING";
	} else {
		return absolutePath(thisPageURL(), $url);
	}
}

// -----------------------------------------------------------------------------

function SetupAddToCart($successLink, $failureLink, $provider, $sku, $description, $unitPrice)
{
	global $setupArgs;
	$setupArgs["add-to-cart_success"] = MakeCMReturnURL($successLink);
	$setupArgs["add-to-cart_failure"] = MakeCMReturnURL($failureLink);
	$setupArgs["provider"] = $provider;
	$setupArgs["sku"] = $sku;
	$setupArgs["description"] = $description;
	$setupArgs["unit-price"] = $unitPrice;
}

// -----------------------------------------------------------------------------

function SetupUpdateTotals($successLink, $failureLink, $cart)
{
	global $setupArgs;
	$setupArgs["change-cart-props_success"] = MakeCMReturnURL($successLink);
	$setupArgs["change-cart-props_failure"] = MakeCMReturnURL($failureLink);

	$cart->MoveFirst("item");
	while (!$cart->EOF("item")) {
		$keyName = "key(" . $cart->AbsolutePosition("item") . ")";
		$setupArgs[$keyName] = $cart->Key("item");
		$cart->MoveNext("item");
	}
	$cart->MoveFirst("item");
}

// -----------------------------------------------------------------------------

function SetupClearCart($successLink, $failureLink, $cart)
{
	global $setupArgs;
	$setupArgs["clear-cart_success"] = MakeCMReturnURL($successLink);
	$setupArgs["clear-cart_failure"] = MakeCMReturnURL($failureLink);
}

// -----------------------------------------------------------------------------

function SetupUpdateOrder($successLink, $failureLink, $orderForm)
{
	global $setupArgs;
	$setupArgs["change-order-form-props_success"] = MakeCMReturnURL($successLink);
	$setupArgs["change-order-form-props_failure"] = MakeCMReturnURL($failureLink);
}

// -----------------------------------------------------------------------------

function SetupPurchaseNow($successLink, $failureLink, $orderform)
{
	global $setupArgs;
	$setupArgs["purchase_success"] = MakeCMReturnURL($successLink);
	$setupArgs["purchase_failure"] = MakeCMReturnURL($failureLink);
}

// -----------------------------------------------------------------------------
// Setup the form.  This function must at least write out the setup arguments
// added by the individual action setup functions.

function SetupCustomMerchantForm($contentSource)
{
	global $setupArgs;

	//
	// Write out the standard info:
	//
	if (gettype($contentSource) == "object") {
		echo "<input type='hidden' name='_provider-url' value='$contentSource->providerURL'>";
		echo "<input type='hidden' name='_merchant-id' value='$contentSource->merchantId'>";
		echo "<input type='hidden' name='_shopper-id' value='$contentSource->shopperId'>";
	} else {
		$provider = $setupArgs["provider"];
		$providerInfo = GetProviderInfo($provider);
		$providerURL = $providerInfo["url"];
		$merchantId = $providerInfo["merchant-id"];
		$shopperId = GetShopperId($provider);

		echo "<input type='hidden' name='_provider-url' value='$providerURL'>";
		echo "<input type='hidden' name='_merchant-id' value='$merchantId'>";
		echo "<input type='hidden' name='_shopper-id' value='$shopperId'>";
	}

	//
	// Write out all the args collected from the SetupAction calls:
	//
	while (list($key, $value) = each($setupArgs)) {
		echo "<input type='hidden' name='_$key' value='$value'>";
	}
	$setupArgs = array();
}

?>
