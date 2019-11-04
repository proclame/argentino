<?php

include("../include/utils.runtime6.php");
include("../include/custommerchant.runtime6.php");

//*****************************************************************************
//
// actions/custommerchant.actions6.php
//
// GoLive actions for CustomMerchant.
//
// ADOBE SYSTEMS INCORPORATED
// Copyright 1999-2002 Adobe Systems Incorporated. All Rights Reserved.
//
// NOTICE:  Notwithstanding the terms of the Adobe GoLive End User
// License Agreement, Adobe permits you to reproduce and distribute this
// file only as an integrated part of a web site created with Adobe
// GoLive software and only for the purpose of enabling your client to
// display their web site. All other terms of the Adobe license
// agreement remain in effect.
//


// *****************************************************************************
// CART ACTIONS
//

// -----------------------------------------------------------------------------
// Dispatch a CustomMerchant action over http to the provider.

function DoCMAction($providerURL, $action, $merchantId, $shopperId, $args)
{
	global $RuntimeDebug;
	$queryString = "action=$action&merchant-id=$merchantId&shopper-id=$shopperId";

	if (isset($args)) {
		while (list($key, $value) = each($args)) {
			$args[$key] = urlencode($value);
		}
		$queryString .= "&" . queryArrayToString($args);
	}
	if ($RuntimeDebug) {
		RuntimeDebugMessage("DoCMAction: provider = $providerURL, request= $queryString\n");
	}

	return GetHTTPResultsAsText($providerURL, $queryString);
}

// -----------------------------------------------------------------------------

function AddToCart($providerURL, $merchantId, $shopperId)
{
	//
	// Collect the known parameters:
	//
	$args = array();
	$args["sku"] = GetFormValue("_sku");
	$args["description"] = GetFormValue("_description");
	$args["unit-price"] = GetFormValue("_unit-price");

	//
	//	Collect anything else that appeared in the form:
	//
	global $HTTP_POST_VARS;
	reset($HTTP_POST_VARS);
	while (list($param, $value) = each($HTTP_POST_VARS)) {
		if ($param[0] == '_') {
			continue;
		}
		if (ereg("(.*)\(([0-9]+)\)", $param, $regs)) {
			// convert index from (i) to [i]
			$name = $regs[1] . "[" . $regs[2] . "]";
			$args[$name] = $value;
		} else {
			$args[$param] = $value;
		}
	}

	$result = DoCMAction($providerURL, "add-to-cart", $merchantId, $shopperId, $args);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_add-to-cart_failure"));
	} else if (indexof($result, '<add-error>') > 0) {
		Redirect(GetFormValue("_add-to-cart_failure"));
	}

	Redirect(GetFormValue("_add-to-cart_success"));
}

// -----------------------------------------------------------------------------

function UpdateCart($providerURL, $merchantId, $shopperId)
{
	global $HTTP_POST_VARS;

	//
	// Collect form arguments:
	//
	$args = array();
	reset($HTTP_POST_VARS);
	while (list($param, $value) = each($HTTP_POST_VARS)) {
		if (ereg("(.*)\(([0-9]+)\)", $param, $regs)) {
			$name = $regs[1];
			if ($name == "_key") {	// convert "_key" to "sku"
				$name = "sku";
			}
			// convert index from (i) to [i]
			$name .= "[" . $regs[2] . "]";
			$args[$name] = $value;
		}
	}

	$result = DoCMAction($providerURL, "change-cart-props", $merchantId, $shopperId, $args);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_change-cart-props_failure"));
	} else if (indexof($result, '<change-error>') > 0) {
		Redirect(GetFormValue("_change-cart-props_failure"));
	}
	Redirect(GetFormValue("_change-cart-props_success"));
}

// -----------------------------------------------------------------------------

function ClearCart($providerURL, $merchantId, $shopperId)
{
	$result = DoCMAction($providerURL, "clear-cart", $merchantId, $shopperId, NULL);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_clear-cart_failure"));
	}
	Redirect(GetFormValue("_clear-cart_success"));
}


// *****************************************************************************
// ORDER FORM ACTIONS
//

// -----------------------------------------------------------------------------

function UpdateOrder($providerURL, $merchantId, $shopperId)
{
	global $HTTP_POST_VARS;

	//
	// Collect form arguments:
	//
	$args = array();
	reset($HTTP_POST_VARS);
	while (list($param, $value) = each($HTTP_POST_VARS)) {
		if ($param[0] == '_') {
			continue;
		}
		if (ereg("(.*)\(([0-9]+)\)", $param, $regs)) {
			$name = $regs[1];	// strip index
			$args[$name] = $value;
		} else {
			$args[$param] = $value;
		}
	}

	$result = DoCMAction($providerURL, "change-order-form-props", $merchantId, $shopperId, $args);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_change-order-form-props_failure"));
	} else if (indexof($result, '<change-error>') > 0) {
		Redirect(GetFormValue("_change-order-form-props_failure"));
	}

	Redirect(GetFormValue("_change-order-form-props_success"));
}

// -----------------------------------------------------------------------------

function PurchaseNow($providerURL, $merchantId, $shopperId)
{
	global $HTTP_POST_VARS;

	//
	// Collect form arguments:
	//
	$args = array();
	reset($HTTP_POST_VARS);
	while (list($param, $value) = each($HTTP_POST_VARS)) {
		if ($param[0] == '_') {
			continue;
		}
		if (ereg("(.*)\(([0-9]+)\)", $param, $regs)) {
			$name = $regs[1];	// strip index
			$args[$name] = $value;
		} else {
			$args[$param] = $value;
		}
	}

	$result = DoCMAction($providerURL, "change-order-form-props", $merchantId, $shopperId, $args);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_purchase_failure"));
	} else if (indexof($result, '<change-error>') > 0) {
		Redirect(GetFormValue("_purchase_failure"));
	}

	$result = DoCMAction($providerURL, "purchase", $merchantId, $shopperId, NULL);

	if (indexof($result, '$error') == 0) {
		Redirect(GetFormValue("_purchase_failure"));
	} else if (indexof($result, '<purchase-error>') > 0) {
		Redirect(GetFormValue("_purchase_failure"));
	}

	Redirect(GetFormValue("_purchase_success"));
}

//-----------------------------------------------------------------------------
// Dispatch

$providerURL = GetFormValue("_provider-url");
$merchantId = GetFormValue("_merchant-id");
$shopperId = GetFormValue("_shopper-id");

if (GetFormValue("_AddToCart") != "_no_form_value") {
	AddToCart($providerURL, $merchantId, $shopperId);

} else if (GetFormValue("_UpdateTotals") != "_no_form_value") {
	UpdateCart($providerURL, $merchantId, $shopperId);

} else if (GetFormValue("_ClearCart") != "_no_form_value") {
	ClearCart($providerURL, $merchantId, $shopperId);

} else if (GetFormValue("_UpdateOrder") != "_no_form_value") {
	UpdateOrder($providerURL, $merchantId, $shopperId);

} else if (GetFormValue("_PurchaseNow") != "_no_form_value") {
	PurchaseNow($providerURL, $merchantId, $shopperId);

} else {	// no default action
	exit();
}

</SCRIPT>

