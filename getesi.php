<html>
	<head>
		<title>SSO Authorization</title>
	</head>
	<body>
<?php


include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";
dbset();


$SERVICE_TYPE="KillEvent";

$authcurl= curl_init();

$header_type= "Content-Type:application/json";
$curl_body="{'grant_type':'authorization_code','code':'".$_GET['code']."'}";


curl_setopt($authcurl,CURLOPT_URL,"https://login.eveonline.com/oauth/token");
curl_setopt($authcurl,CURLOPT_SSL_VERIFYPEER, $SSLauth);
curl_setopt($authcurl,CURLOPT_HTTPHEADER,array($header_type,$header_auth[$SERVICE_TYPE]));
curl_setopt($authcurl,CURLOPT_POSTFIELDS,$curl_body);
curl_setopt($authcurl,CURLOPT_POST,1);
curl_setopt($authcurl,CURLOPT_RETURNTRANSFER,true);


$redirect_uri = "https://".$serveraddr."/CorpESI/Event/getesi.php"; 

$curl_response=curl_exec($authcurl);
 

curl_close($authcurl);

$token_data=json_decode($curl_response,true);


//var_dump($token_data);

/*//DEBUG
echo "access_token : ".$token_data["access_token"]."<br>";
echo "token_type : ".$token_data['token_type']."<br>";
echo "expires_in : ".$token_data['expires_in']."<br>";
echo "refresh_token : ".$token_data['refresh_token']."<br>";
*/
session_start();

if(isset($token_data["access_token"])){

	echo "<br><br> =============Character Data============ <br><br>";
	$authcurl= curl_init();
	curl_setopt($authcurl, CURLOPT_SSL_VERIFYPEER, $SSLauth); 
	curl_setopt($authcurl,CURLOPT_HTTPGET,true);
	curl_setopt($authcurl,CURLOPT_HTTPHEADER,array($header_type,"Authorization: Bearer ".$token_data['access_token']));
	curl_setopt($authcurl,CURLOPT_URL,"https://login.eveonline.com/oauth/verify");
	curl_setopt($authcurl,CURLOPT_RETURNTRANSFER,true);

	$curl_response=curl_exec($authcurl);
	curl_close($authcurl);

	$character_data=json_decode($curl_response,true);

	echo "<br>character name : ".$character_data['CharacterName'];


	//https://esi.evetech.net/latest/characters/93695932/?datasource=tranquility
	
	$authcurl= curl_init();
	curl_setopt($authcurl, CURLOPT_SSL_VERIFYPEER, $SSLauth); 
	curl_setopt($authcurl,CURLOPT_HTTPGET,true);
	curl_setopt($authcurl,CURLOPT_URL,"https://esi.evetech.net/latest/characters/".$character_data['CharacterID']."/?datasource=tranquility");
	curl_setopt($authcurl,CURLOPT_RETURNTRANSFER,true);

	$curl_response=curl_exec($authcurl);
	curl_close($authcurl);

	$personal_data=json_decode($curl_response,true);

	//이미 등록되어있는지 검사
	$qr="select * from PublicESI_keys where characterid=".$character_data['CharacterID']." and service_type=\"".$SERVICE_TYPE."\" and active>=1";
	$result=$dbcon->query($qr);


	//등록이 되어 있으면 바로 로그인.
	if($result->num_rows == 1){
		
		if(isset($_SESSION["PublicESI_addchar"]) && $_SESSION["PublicESI_addchar"]==$_SESSION["PublicESI_userid"]){
			
			$qr="select userid from PublicESI_keys where characterid=".$character_data['CharacterID']." and userid!=".$_SESSION["PublicESI_userid"]." and active>=1;";
			$result=$dbcon->query($qr);
			if($result->num_rows>0){
				$delete_userid=$result->fetch_row();
				$qr="update PublicESI_keys set userid=".$_SESSION["PublicESI_userid"]." where characterid=".$character_data['CharacterID'].";";
				$dbcon->query($qr);
				$qr="delete from PublicESI_accounts where userid=".$character_data['CharacterID'].";";
				$dbcon->query($qr);
			}
			unset($_SESSION["PublicESI_addchar"]);
		}
		

		$qr1= "update PublicESI_accounts set latest_ip='".$_SERVER['REMOTE_ADDR']."' where userid=".getMainId($character_data['CharacterID']).";";
		$qr2= "update PublicESI_keys set refresh_token='".$token_data['refresh_token']."' , access_token=\"".$token_data['access_token']."\" , corpid=".$personal_data["corporation_id"]." where characterid=".$character_data['CharacterID']." and service_type=\"".$SERVICE_TYPE."\" and active>=1;";
		
		if($dbcon->query($qr1)&&$dbcon->query($qr2)){
		
		$_SESSION["PublicESI_userid"]=getMainId($character_data['CharacterID']);
		$_SESSION['PublicESI_charactername']=$character_data['CharacterName'];
		$_SESSION['PublicESI_characterid']=$character_data['CharacterID'];
		$_SESSION['PublicESI_refresh_token']=$token_data['refresh_token'];
		$_SESSION['PublicESI_access_token']=$token_data['access_token'];
		echo "<script language=javascript>alert('Login success.');location.replace('./index.php');</script>";
		}

		else{
			errorhome('Login Failed. Login DB Error');
		}

	}

	//등록이 안되어있으면 등록
	else if($result->num_rows == 0){

		//이미 사용자가 있는지 검사한다.
		$qr="select userid from PublicESI_keys where characterid=".$character_data['CharacterID']." and active>=1;";
		$result=$dbcon->query($qr);
		
		//기존 사용자가 있었다면 (Key 만 등록하면 됨)
		if($result->num_rows>0){
			$userid=$result->fetch_row();
			//Key 등록
			$qr="insert into PublicESI_keys 
			(userid,
			characterid,
			charactername,
			registered_date,
			access_token,
			refresh_token,
			corpid,
			service_type,
			permission_date) 
			values 
			(".$userid[0].",
			".$character_data['CharacterID'].",
			\"".$character_data['CharacterName']."\",
			UTC_TIMESTAMP,
			\"".$token_data["access_token"]."\",
			\"".$token_data['refresh_token']."\",
			".$personal_data["corporation_id"].",
			\"".$SERVICE_TYPE."\",
			DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3650 DAY));";

			if($dbcon->query($qr)){
				echo "<script>alert('Register success. ".$character_data['CharacterName']."');\n";
				$_SESSION["PublicESI_userid"]=getMainId($character_data['CharacterID']);
				$_SESSION['PublicESI_charactername']=$character_data['CharacterName'];
				$_SESSION['PublicESI_characterid']=$character_data['CharacterID'];
				$_SESSION['PublicESI_corporationid']=$personal_data["corporation_id"];
				$_SESSION['PublicESI_refresh_token']=$token_data['refresh_token'];
				$_SESSION['PublicESI_access_token']=$token_data['access_token'];
				echo "location.replace('./index.php');</script>";
			}
			else{
				echo "<script>alert('ESI Register Failed. DB Error 1');\n";
				echo "location.replace('https://lindows.kr/PublicESI/logout.php');</script>\n";
				
			}
			
		}
		//혹은 이 캐릭터는 처음 등록이지만 다른 캐릭터에 추가하는 것인지 확인한다
		else if(isset($_SESSION["PublicESI_addchar"]) && $_SESSION["PublicESI_addchar"]==$_SESSION["PublicESI_userid"]){
			unset($_SESSION["PublicESI_addchar"]);
			$qr1="insert into PublicESI_keys 
			(userid,
			characterid,
			charactername,
			registered_date,
			access_token,
			refresh_token,
			corpid,
			service_type,
			permission_date) 
			values
			(".$_SESSION["PublicESI_userid"].",
			".$character_data['CharacterID'].",
			\"".$character_data['CharacterName']."\",
			UTC_TIMESTAMP,
			\"".$token_data["access_token"]."\",
			'".$token_data['refresh_token']."',
			".$personal_data["corporation_id"].",
			\"".$SERVICE_TYPE."\",
			DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3650 DAY));";

			if($dbcon->query($qr1)){
				echo "<script>alert('캐릭터가 등록되었습니다. ".$character_data['CharacterName']." 화이팅');\n";
				//$_SESSION['PublicESI_userid']=$_SESSION["PublicESI_userid"];
				$_SESSION['PublicESI_charactername']=$character_data['CharacterName'];
				$_SESSION['PublicESI_characterid']=$character_data['CharacterID'];
				$_SESSION['PublicESI_corporationid']=$personal_data["corporation_id"];
				$_SESSION['PublicESI_refresh_token']=$token_data['refresh_token'];
				$_SESSION['PublicESI_access_token']=$token_data['access_token'];
				echo "location.replace('../charactersheet.php');</script>";
			}
			else{
				echo "<script>alert('ESI 등록에 실패했습니다.');\n";
				echo "location.replace('https://".$serveraddr."/PublicESI/logout.php');</script>\n";
				
			}
		}
		//기존 사용자가 아예 등록되어 있지 않으면 user 도 등록해야한다.
		else{
			$qr1="insert into PublicESI_keys 
			(userid,
			characterid,
			charactername,
			registered_date,
			access_token,
			refresh_token,
			corpid,
			service_type,
			permission_date) 
			values
			(".$character_data['CharacterID'].",
			".$character_data['CharacterID'].",
			\"".$character_data['CharacterName']."\",
			UTC_TIMESTAMP,
			\"".$token_data["access_token"]."\",
			'".$token_data['refresh_token']."',
			".$personal_data["corporation_id"].",
			\"".$SERVICE_TYPE."\",
			DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3650 DAY));";

			$qr2="insert into PublicESI_accounts 
			(userid,
			registered_date,
			latest_ip) 
			values 
			(".$character_data['CharacterID'].",
			UTC_TIMESTAMP,
			'".$_SERVER['REMOTE_ADDR']."');";
	
			if($dbcon->query($qr1)&& $dbcon->query($qr2)){
				echo "<script>alert('First Login. ".$character_data['CharacterName']."');\n";
				$_SESSION['PublicESI_userid']=getMainId($character_data['CharacterID']);
				$_SESSION['PublicESI_charactername']=$character_data['CharacterName'];
				$_SESSION['PublicESI_characterid']=$character_data['CharacterID'];
				$_SESSION['PublicESI_corporationid']=$personal_data["corporation_id"];
				$_SESSION['PublicESI_refresh_token']=$token_data['refresh_token'];
				$_SESSION['PublicESI_access_token']=$token_data['access_token'];
				echo "location.replace('./index.php');</script>";
			}
			else{
				echo "<script>alert('ESI register failed. DB Error2');\n";
				echo "location.replace('https://".$serveraddr."/PublicESI/logout.php');</script>\n";
				
			}
		}


	}

}
else{

	errorlogout("세션이 변경되어 로그아웃됩니다. 다시 로그인 해 주세요",$serveraddr."/PublicESI/");
		
}

?>
</body>
</html>