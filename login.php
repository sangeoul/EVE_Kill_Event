

<?php

include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";
dbset();

if($dbcon->connect_error){
	die("Connection Failed<br>".$dbcon->connect_error);
}
unset($_SESSSION["PublicESI_addchar"]);
//else echo "Connected MariaDB Successfully.<br><br>";


$loginurl="https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=https://".$serveraddr."/CorpESI/Event/getesi.php&scope=".$ESI_scope["KillEvent"]."&client_id=".$client_id["KillEvent"];

if(isset($_GET["redirect"]) && $_GET["redirect"]==1){

	echo("<script >\n");
	echo "window.location.replace('".$loginurl."');\n";

	echo ("</script>\n");

}
else{
	
	echo("<div >");
	echo "<a href='".$loginurl."'><img style=\"margin-left:80px;margin-top:50px;\" src='https://lindows.kr/PublicESI/images/loginbutton.jpg'></a><br>\n";

	echo ("</div>");
}


?>