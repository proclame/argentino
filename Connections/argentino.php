<?php
# FileName="Connection_php_mysql.htm"
# Type="MYSQL"
# HTTP="true"
$hostname_argentino = "localhost";
$database_argentino = "agentino";
$username_argentino = "argentino";
$password_argentino = "KB4538t";
$argentino = mysql_pconnect($hostname_argentino, $username_argentino, $password_argentino) or trigger_error(mysql_error(),E_USER_ERROR); 
?>