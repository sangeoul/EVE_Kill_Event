<?php

include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";
dbset();
session_start();
header("Content-Type: application/json");


//1페이지당 1000개의 킬메일만 불러올 수 있는데. 상식적으로 1000개의 킬메일을 단시간 내에 쌓는 것은 불가능하므로 1페이지만 불러오도록 프로그래밍함.

$header_type= "Content-Type:application/json";
$apiurl="https://esi.evetech.net/latest/characters/".$_GET["character_id"]."/killmails/recent/?datasource=tranquility&page=1";
$curl= curl_init();
curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, $SSLauth); 
curl_setopt($curl,CURLOPT_HTTPGET,true);
curl_setopt($curl,CURLOPT_HTTPHEADER,array($header_type,"Authorization: Bearer ".refresh_token($_GET["character_id"],"KillEvent")));
curl_setopt($curl,CURLOPT_URL,$apiurl);
curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);

$curl_response=curl_exec($curl);
//var_dump($curl_response);
curl_close($curl);
$killdata=json_decode($curl_response,true);

for($i=0;$i<sizeof($killdata);$i++){
    $qr="select killmail_id from Event_killmails where killmail_id=".$killdata[$i];
    $result=$dbcon->query($qr);

    if($result->num_rows==0){
        $curl= curl_init();
        $killapiurl="https://lindows.kr/CorpESI/Event/submit_killmail.php?killmail_id=".$killdata[$i]["killmail_id"]."&killmail_hash=".$killdata[$i]["killmail_hash"];
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, $SSLauth); 
        curl_setopt($curl,CURLOPT_HTTPGET,true);
        curl_setopt($curl,CURLOPT_URL,$killapiurl);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);

        $curl_response=curl_exec($curl);
        //var_dump($curl_response);
        curl_close($curl);
        echo($killapiurl."\n".$curl_response."\n\n");
    }
}

?>