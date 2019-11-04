<?php
require_once("../include/utils.runtime6.php");
?>

<html>
<!--
  - ADOBE SYSTEMS INCORPORATED
  - Copyright 2002 Adobe Systems Incorporated. All Rights Reserved.
  - 
  - NOTICE:  Notwithstanding the terms of the Adobe GoLive End User 
  - License Agreement, Adobe permits you to reproduce and distribute this 
  - file only as an integrated part of a web site created with Adobe 
  - GoLive software and only for the purpose of enabling your client to 
  - display their web site. All other terms of the Adobe license 
  - agreement remain in effect.
  -->
	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		<title>DynamicLink server error</title>
	</head>
	<body bgcolor="#ffffff">
		<h1>DynamicLink server error</h1>
		<p>Error caused by illegal or unsupported operation.</p>
<?php
	$cswerr = GetError();
	if ($cswerr) {
		$msg = @$cswerr->dollar['__ERROR'];
		$msg = htmlspecialchars($msg);
		echo("<h2>Specific error message</h2>");
		echo("<p>$msg</p>");
	}
?>
	</body>
</html>
