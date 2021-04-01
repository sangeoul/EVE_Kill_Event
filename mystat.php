<?php


$LINKS_PER_ROW=4;



include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";

dbset();

session_start();

logincheck(0,0,"lindows.kr/CorpESI/Event/login.php");


$loginurl="https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=https://".$serveraddr."/CorpESI/Event/getesi.php&scope=".$ESI_scope["KillEvent"]."&client_id=".$client_id["KillEvent"];

if(!isset($_GET["character_id"])) {
    $_GET["character_id"]=$_SESSION['PublicESI_userid'];

}



//유저의 캐릭터들을 불러온다.
$qr="select * from PublicESI_keys where userid=".$_SESSION['PublicESI_userid']." and service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

if($result->num_rows==0){
    echo "<script language=javascript>window.location.href='./login.php'</script>";
}

echo("<br><br>");
for($i=0;$i<$result->num_rows;$i++){
    $data=$result->fetch_array();
    echo("<a class=\"stat-link\" href=\"./mystat.php?character_id=".$data["characterid"]."\">".$data["charactername"]."</a> ");
    if($i%$LINKS_PER_ROW==($LINKS_PER_ROW-1)){
        echo("\n<br>\n");
    }
    
}
echo("<br>");
echo("<a href=\"".$loginurl."\">캐릭터 추가</a>");


echo("<br><hr><br>");
echo(getCharacterName($_GET["character_id"])."\n<br>\n<br>");

//현재 페이지에서 불러오는 character 가 유저의 소유인지 확인한다.
$qr="select * from PublicESI_keys where userid=".$_SESSION['PublicESI_userid']." and characterid=".$_GET["character_id"]." and service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

//소유가 확인되면 정보를 출력함.
if($result->num_rows>0){
    $character_access_token=refresh_token($_GET["character_id"],"KillEvent");

    $character_data=$result->fetch_array();

    $header_type= "Content-Type:application/json";
    $apiurl="https://esi.evetech.net/latest/characters/".$_GET["character_id"]."/killmails/recent/";
    $curl= curl_init();
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, $SSLauth); 
    curl_setopt($curl,CURLOPT_HTTPGET,true);
    curl_setopt($curl,CURLOPT_HTTPHEADER,array($header_type,"Authorization: Bearer ".$character_access_token));
    curl_setopt($curl,CURLOPT_URL,$apiurl);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    
    $curl_response=curl_exec($curl);
    //var_dump($curl_response);
    curl_close($curl);

    $killdata=json_decode($curl_response,true);

    if(array_key_exists("error", $killdata) && ($killdata["error"]=="unexpected end of JSON input" || $killdata["error"]=="authorization not provided")){
        echo($character_data["charactername"]." 의 토큰이 만료되었습니다. 다시 <a href=\"".$loginurl."\">로그인</a> 해 주세요.<br><br>");
    }
    else{
        //킬메일 업데이트도 동시에 진행해 준다(비동기)
        echo("<script>\nsetTimeout(()=>{update_killmail(".$_GET["character_id"].");},100);\n</script>\n");

        
        //전체 킬포인트/로스포인트 정리
        $qr="select sum(value/killers_number) from Event_killmails where killer_id=".$_GET["character_id"];
        $result=$dbcon->query($qr);
        $killpoint=$result->fetch_row();
        $killpoint=ceil($killpoint[0]);
        
        //                                                                                                                  인셉은 1/4                                                                              딕터는 1/2
        $qr="select sum(value/killers_number) from Event_killmails where victim_id=".$_GET["character_id"]." and (victim_ship!=11176 and victim_ship!=11198 and victim_ship!=11202 and victim_ship!=11186) and (victim_ship!=22456 and victim_ship!=22460 and victim_ship!=22464 and victim_ship!=22452)";
        $result=$dbcon->query($qr);
        $losspoint=$result->fetch_row();
        $losspoint=ceil($losspoint[0]);

        //인셉 추가 (1/2)
        $qr="select sum(value/(2*killers_number)) from Event_killmails where victim_id=".$_GET["character_id"]." and (victim_ship=11176 or victim_ship=11198 or victim_ship=11202 or victim_ship=11186)";
        $result=$dbcon->query($qr);
        $losspoint_cept=$result->fetch_row();
        $losspoint+=ceil($losspoint_cept[0]);


        //딕터 추가(1/24)
        $qr="select sum(value/(3*killers_number)) from Event_killmails where victim_id=".$_GET["character_id"]." and (victim_ship=22456 or victim_ship=22460 or victim_ship=22464 or victim_ship=22452)";
        $result=$dbcon->query($qr);
        $losspoint_dict=$result->fetch_row();
        $losspoint+=ceil($losspoint_dict[0]);   

        echo("Kill point : ".number_format($killpoint)."\n<br>");
        echo("Loss point : ".number_format($losspoint)."\n<br>");
        echo("<span class=score>Effective point : ".number_format($killpoint-$losspoint)."</span>\n<br><br>");

        echo("<a href=\"./killlogs.php?character_id=".$_GET["character_id"]."\" target=_blank>상세 기록 보기</a><br>");
        //쉽별 킬포인트/로스포인트 정리(추가해야함)


    }
}


?>
<a href="./submit_killmail.html">킬메일 수동으로 등록하기</a><br>
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
a.stat-link{
    margin:10px;
}
span.score{
    font-size:30px;
}
</style>

