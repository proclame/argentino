<?php require_once('../Connections/argentino.php'); ?>
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

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
  $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
}

if ((isset($_POST["MM_update"])) && ($_POST["MM_update"] == "form1")) {
  $updateSQL = sprintf("UPDATE alacarte SET omschrijving=%s WHERE id=%s",
                       GetSQLValueString($_POST['omschrijving'], "text"),
                       GetSQLValueString($_POST['id'], "int"));

  mysql_select_db($database_argentino, $argentino);
  $Result1 = mysql_query($updateSQL, $argentino) or die(mysql_error());

  $updateGoTo = "isgewijzigd.html";
  if (isset($_SERVER['QUERY_STRING'])) {
    $updateGoTo .= (strpos($updateGoTo, '?')) ? "&" : "?";
    $updateGoTo .= $_SERVER['QUERY_STRING'];
  }
  header(sprintf("Location: %s", $updateGoTo));
}

if ((isset($_POST["MM_update"])) && ($_POST["MM_update"] == "form1")) {
  $updateSQL = sprintf("UPDATE weekmenu SET omschrijving=%s WHERE id=%s",
                       GetSQLValueString($_POST['omschrijving'], "text"),
                       GetSQLValueString($_POST['id'], "int"));

  mysql_select_db($database_argentino, $argentino);
  $Result1 = mysql_query($updateSQL, $argentino) or die(mysql_error());
}

mysql_select_db($database_argentino, $argentino);
$query_argentinoweek = "SELECT * FROM weekmenu";
$argentinoweek = mysql_query($query_argentinoweek, $argentino) or die(mysql_error());
$row_argentinoweek = mysql_fetch_assoc($argentinoweek);
$totalRows_argentinoweek = mysql_num_rows($argentinoweek);
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Weekmenu</title>
<script type="text/javascript" src="/db/ckeditor/ckeditor.js"></script></head>

<body>
<form method="POST" name="form1" action="<?php echo $editFormAction; ?>">
  <table width="800" align="center">
    <tr valign="baseline">
      <td><?php echo $row_argentinoweek['id']; ?></td>
    </tr>
    <tr valign="baseline">
      <td><textarea id="editor8" name="omschrijving" cols="50" rows="5"><?php echo $row_argentinoweek['omschrijving']; ?></textarea><script type="text/javascript">
				CKEDITOR.replace( 'editor8' );	</script></td>
    </tr>
    <tr valign="baseline">
      <td><input type="submit" value="Record bijwerken"></td>
    </tr>
  </table>
  <input type="hidden" name="MM_update" value="form1">
  <input type="hidden" name="id" value="<?php echo $row_argentinoweek['id']; ?>">
</form>
<p>&nbsp;</p>
</body>
</html>
<?php
mysql_free_result($argentinoweek);
?>
