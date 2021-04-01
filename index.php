<?php
include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";
dbset();
session_start();

logincheck(0,0,"lindows.kr/CorpESI/Event/login.php");

if(!isset($_SESSION['PublicESI_userid'])){
	echo "<script language=javascript>window.location.href='./login.php'</script>";
		
}

else{
	echo "<script language=javascript>window.location.href='./mystat.php'</script>";
}




?>