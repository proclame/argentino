<?php require_once('Connections/argentino.php'); ?>
<?php
if (!function_exists("GetSQLValueString")) {
function GetSQLValueString($theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "") 
{
  if (PHP_VERSION < 6) {
    $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;
  }

  $theValue = function_exists("mysql_real_escape_string") ? mysql_real_escape_string($theValue) : mysql_escape_string($theValue);

  switch ($theType) {
    case "text":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;    
    case "long":
    case "int":
      $theValue = ($theValue != "") ? intval($theValue) : "NULL";
      break;
    case "double":
      $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
      break;
    case "date":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;
    case "defined":
      $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
      break;
  }
  return $theValue;
}
}

mysql_select_db($database_argentino, $argentino);
$query_argentino16 = "SELECT omschrijving FROM weekmenu";
$argentino16 = mysql_query($query_argentino16, $argentino) or die(mysql_error());
$row_argentino16 = mysql_fetch_assoc($argentino16);
$totalRows_argentino16 = mysql_num_rows($argentino16);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>

	<head>
		<meta http-equiv="content-type" content="text/html;charset=iso-8859-1">
		 <META NAME="DESCRIPTION" CONTENT="Argentino Argentijns Grillrestaurant - Argentijns specialiteiten-restaurant - Turnhout. Sfeervol, ongedwongen, romantisch en exclusief dat is wat u in Turnhout bij restaurant Argentino kunt verwachten ">
		 <META NAME="KEYWORDS" CONTENT="Turnhout, restaurant, grillrestaurant, dineren, restaurants, Belgie, argentijns, grillrestaurants, eetgelegenheid, kobe, Wagyu , Wagyu Kobe, menu, weekmenu, diner, Argentino,  grillrestaurant Argentino, specialiteitenrestaurant"> 
<META NAME="ROBOTS" CONTENT="INDEX, FOLLOW">
  <META NAME="REVISIT-AFTER" CONTENT="1 WEEK">
<META NAME="copyright" content="FDM Studio 2005 - www.fdmstudio.nl">
<META NAME="author" content="FDM Studio Internet Services www.fdmstudio.nl , voor webdesign, domeinregistraties en hosting">
	<title>Weekmenu</title>
  <!--Restaurant, argentijns, Argentino -->
					<style type="text/css">
     <!--
     body {font-size:12px} 
     .s1 {font-size:14px}
     .s2 {font-size:13px}
 .s3 {font-size:13px}
 .s4 {font-size:11px}
  .s5 {font-size:10px}
          -->
     </style>
		<STYLE TYPE='text/css'>
<!-- TextRollover-1 -->
a:link { color:#6F6F6F; text-decoration:none}
a:visited { color:#000000; text-decoration:none}
a:hover { color:#BE0F2F; text-decoration:none; cursor:hand}
a:active { color:#6F6F6F; text-decoration:none}
</STYLE>
		<STYLE type="text/css"><!--body { scrollbar-face-color: #FFCC4D; scrollbar-track-color: #FEA056; scrollbar-arrow-color: #CE3C3D; scrollbar-3dlight-color: #FFCC4D; scrollbar-shadow-color: #FEA056; scrollbar-highlight-color: #FEA056; scrollbar-darkshadow-color: #FFCC4D}//--></STYLE>
		
	</head>


	<body background="images/backnaam400.jpg" bgproperties="fixed"  bgcolor="#ffa904" leftmargin="0" marginheight="0" marginwidth="0" topmargin="0">
		<table width="470" border="0" cellspacing="0" cellpadding="0" cool gridx="10" gridy="10" height="423" showgridx showgridy usegridx usegridy>
			<tr height="10">
				<td width="10" height="422" rowspan="2"></td>
				<td width="459" height="10"></td>
				<td width="1" height="10"><spacer type="block" width="1" height="10"></td>
			</tr>
			<tr height="412">
				<td content csheight="160" width="459" height="412" valign="top" xpos="10"><font face="Arial,Helvetica,Geneva,Swiss,SunSans-Regular"><font size="2"><?php echo $row_argentino16['omschrijving']; ?></font></font></td>
				<td width="1" height="412"><spacer type="block" width="1" height="412"></td>
			</tr>
			<tr height="1" cntrlrow>
				<td width="10" height="1"><spacer type="block" width="10" height="1"></td>
				<td width="459" height="1"><spacer type="block" width="459" height="1"></td>
				<td width="1" height="1"></td>
			</tr>
		</table>
		<p></p>
	</body>

</html>

<?php
mysql_free_result($argentino16);
 
if (false) { ?><!-- Mock Content
   "FDM/omschrijving" html {Menu}
--><?php } ?>









