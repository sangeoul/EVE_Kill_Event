<?php


$LINKS_PER_ROW=4;



include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";

dbset();

session_start();

logincheck();


$loginurl="https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=https://".$serveraddr."/CorpESI/Event/getesi.php&scope=".$ESI_scope["KillEvent"]."&client_id=".$client_id["KillEvent"];


if(!isset($_GET["character_id"])) {
    $_GET["character_id"]=$_SESSION['PublicESI_userid'];

}



//유저의 캐릭터들을 불러온다.
$qr="select * from PublicESI_keys where userid=".$_SESSION['PublicESI_userid']." and service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

echo("<br><br>");
for($i=0;$i<$result->num_rows;$i++){
    $data=$result->fetch_array();
    echo("<a class=\"stat-link\" href=\".mystat.php?character_id=".$data["characterid"]."\">".$data["charactername"]."</a> ");
    if($i%$LINKS_PER_ROW==($LINKS_PER_ROW-1)){
        echo("\n<br>\n");
    }
    
}

echo("<br><hr><br>");
echo(getCharacterName($_GET["character_id"])."\n<br>\n<br>");

//현재 페이지에서 불러오는 character 가 유저의 소유인지 확인한다.
$qr="select * from PublicESI_keys where userid=".$_SESSION['PublicESI_userid']." and characterid=".$_GET["character_id"]." and service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

//소유가 확인되면 정보를 출력함.
if($result->num_rows>0){
    refresh_token($data["characterid"],"KillEvent");
    $character_data=$result->fetch_array();

    $header_type= "Content-Type:application/json";
    $apiurl="https://esi.evetech.net/latest/characters/1535234/killmails/recent/";
    $curl= curl_init();
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, $SSLauth); 
    curl_setopt($curl,CURLOPT_HTTPGET,true);
    curl_setopt($curl,CURLOPT_HTTPHEADER,array($header_type,"Authorization: Bearer ".$_SESSION["PublicESI_access_token"]));
    curl_setopt($curl,CURLOPT_URL,$apiurl);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    
    $curl_response=curl_exec($curl);
    //var_dump($curl_response);
    curl_close($curl);
    
    $killdata=json_decode($curl_response,true);

    if(array_key_exists("error", $killdata) && $killdata["error"]=="unexpected end of JSON input"){
        echo($character_data["charactername"]." 의 토큰이 만료되었습니다. 다시 <a href=\"".$loginurl."\">로그인</a> 해 주세요.");
    }
    else{
        //킬메일 업데이트도 동시에 진행해 준다(비동기)
        echo("<script>\nsetTimeout(()=>{update_killmail(".$data["characterid"].");},100);\n</script>\n");

        
        //전체 킬포인트/로스포인트 정리
        $qr="select sum(value/killers_number) from Event_killmails where killer_id=".$data["characterid"];
        $result=$dbcon->query($qr);
        $killpoint=$result->fetch_row();
        $killpoint=ceil($killpoint[0]);

        $qr="select sum(value) from Event_killmails where victim_id=".$data["characterid"]." and (victim_ship!=11176 and victim_ship!=11198 and victim_ship!=11202 and victim_ship!=11186)";
        $result=$dbcon->query($qr);
        $losspoint=$result->fetch_row();
        $losspoint=ceil($losspoint[0]);

        echo("Kill point : ".number_format($killpoint)."\n<br>");
        echo("Loss point : ".number_format($losspoint)."\n<br>");
        echo("Effective point : ".number_format($killpoint-$losspoint)."\n<br>");

    }
}


?>
<script>
function update_killmail(character_id){
    var DBxhr=new XMLHttpRequest();

    DBxhr.open("GET","./load_character_killmail.php?character_id="+character_id,true);
    DBxhr.send();

    //alert("update");

}
</script>
<style>

span.tax{
    font-size:26px;
}
</style>

