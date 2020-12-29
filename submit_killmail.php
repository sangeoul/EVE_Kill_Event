<?php

include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";
dbset();
session_start();
header("Content-Type: application/json");

//제외해야 할 목록. 주로 스트럭쳐 및 디플로이어블.
$SHIP_EXCEPTION=array(33475,33700,33702,56701,12198,12199,12200,26849,26888,26890,26892,28770,28772,28774,33474,33520,33522,33477,33478,33479,33581,33583,33476,33589,36523,33591,33990,34120,48899,35825,35826,35827,35835,35836,35837,35841,35832,35833,35834,40340,47512,47513,47514,47515,47516,37534,35840);


//꼽 아이디를 찾아낸다.
$qr="select killmail_id from Event_killmails where killmail_id=".$_GET["killmail_id"];
$result=$dbcon->query($qr);

if($result->num_rows>0){
    echo("{\"error\":\"killmail_exist\",\n\"message\":\"이미 등록이 되어 있는 킬메일입니다.\"}");
}

else{
    $error_occured=false;

    $header_type= "Content-Type:application/json";
    $apiurl="https://esi.evetech.net/latest/killmails/".$_GET["killmail_id"]."/".$_GET["killmail_hash"]."/?datasource=tranquility";
    $curl= curl_init();
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, $SSLauth); 
    curl_setopt($curl,CURLOPT_HTTPGET,true);
    //curl_setopt($curl,CURLOPT_HTTPHEADER,array($header_type,"Authorization: Bearer ".$_SESSION["PublicESI_access_token"]));
    curl_setopt($curl,CURLOPT_URL,$apiurl);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    
    $curl_response=curl_exec($curl);
    //var_dump($curl_response);
    curl_close($curl);
    
    $killdata=json_decode($curl_response,true);

    if(array_key_exists("error", $killdata)){
        echo("{\"error\":\"esi_error\",\n\"message\":\"ESI 에러 발생.\"}");
    }
    //특정 기간의 킬메일만 정상적으로 저장해야 함. 그 외에는 가짜 정보 저장.
    else if(strtotime($killdata["killmail_time"])<strtotime("2020-12-01T00:00:00Z")){
        $qr="insert into Event_killmails (
            killmail_id,
            killmail_hash,
            killtime,
            victim_id,victim_name,victim_ship,
            value,killers_number,
            killer_id,killer_name,killer_ship) values (
            ".$killdata["killmail_id"].",
            \"".$_GET["killmail_hash"]."\",
            timestamp(\"".str_replace("Z","+00:00",$killdata["killmail_time"])."\"),
            ".$killdata["victim"]["character_id"].",
            \"".getCharacterName($killdata["victim"]["character_id"])."\",
            ".$killdata["victim"]["ship_type_id"].",
            0,
            1,
            ".$killdata["attackers"][0]["character_id"].",
            \"".getCharacterName($killdata["attackers"][0]["character_id"])."\",
            ".$killdata["attackers"][0]["ship_type_id"].")";
        
        $result=$dbcon->query($qr);

    }
    //쉽만 정상적으로 저장해야 함. (스트럭쳐들은 제외하고 더미 값을 저장해야 함.)
    else if(in_array($killdata["victim"]["ship_type_id"],$SHIP_EXCEPTION)){
        $qr="insert into Event_killmails (
            killmail_id,
            killmail_hash,
            killtime,
            victim_id,victim_name,victim_ship,
            value,killers_number,
            killer_id,killer_name,killer_ship) values (
            ".$killdata["killmail_id"].",
            \"".$_GET["killmail_hash"]."\",
            timestamp(\"".str_replace("Z","+00:00",$killdata["killmail_time"])."\"),
            ".$killdata["victim"]["character_id"].",
            \"".getCharacterName($killdata["victim"]["character_id"])."\",
            ".$killdata["victim"]["ship_type_id"].",
            0,
            1,
            ".$killdata["attackers"][0]["character_id"].",
            \"".getCharacterName($killdata["attackers"][0]["character_id"])."\",
            ".$killdata["attackers"][0]["ship_type_id"].")";
        
        $result=$dbcon->query($qr);
    }
    else{


        //터진 쉽의 밸류를 계산해야 함.
        $shipValue=0;
        //싣고 있는 아이템의 밸류 계산.
        for($i=0;$i<sizeof($killdata["victim"]["items"]);$i++){
            //flag가 5인 것은 카고에 들어있는 밸류이므로 제외함.
            if($killdata["victim"]["items"][$i]["flag"]!=5){

                $quantity=0;
                //원래 데이터에서 quantity_dropped 혹은 quantity_destroyed 중 하나만 표기되기 때문에 일원화 해 준다.
                if(array_key_exists("quantity_destroyed",$killdata["victim"]["items"][$i])){
                    $quantity=$killdata["victim"]["items"][$i]["quantity_destroyed"];
                }
                else if(array_key_exists("quantity_dropped",$killdata["victim"]["items"][$i])){
                    $quantity=$killdata["victim"]["items"][$i]["quantity_dropped"];
                }
                
                //마켓 DB 에서 가격불러오기
                $bqr="select price from Industry_Marketorders where typeid=".$killdata["victim"]["items"][$i]["item_type_id"]." and quantity>0 and is_buy_order=1 order by time desc, price desc limit 2";
                $sqr="select price from Industry_Marketorders where typeid=".$killdata["victim"]["items"][$i]["item_type_id"]." and quantity>0 and is_buy_order=0 order by time desc, price asc limit 2";
                $bresult=$dbcon->query($bqr);
                $sresult=$dbcon->query($sqr);
                if($bresult && $bresult->num_rows>0){
                    $price_array=$bresult->fetch_row();
                    $price["buy"]=floatval($price_array[0]);
                }
                else{
                    $price["buy"]=0.0;
                }
                if($sresult && $sresult->num_rows>0){
                    $price_array=$sresult->fetch_row();
                    $price["sell"]=floatval($price_array[0]);
                }
                else{
                    $price["sell"]=0.0;
                }
                $shipValue+=($price["buy"]+$price["sell"])*$quantity;
            }
        }

        //피팅 뿐만 아니라 쉽 값도 더해줘야 함.
        //마켓 DB 에서 가격불러오기
        $bqr="select price from Industry_Marketorders where typeid=".$killdata["victim"]["ship_type_id"]." and quantity>0 and is_buy_order=1 order by time desc, price desc limit 2";
        $sqr="select price from Industry_Marketorders where typeid=".$killdata["victim"]["ship_type_id"]." and quantity>0 and is_buy_order=0 order by time desc, price asc limit 2";
        $bresult=$dbcon->query($bqr);
        $sresult=$dbcon->query($sqr);
        if($bresult && $bresult->num_rows>0){
            $price_array=$bresult->fetch_row();
            $price["buy"]=floatval($price_array[0]);
        }
        else{
            $price["buy"]=0.0;
        }
        if($sresult && $sresult->num_rows>0){
            $price_array=$sresult->fetch_row();
            $price["sell"]=floatval($price_array[0]);
        }
        else{
            $price["sell"]=0.0;
        }
        $shipValue+=($price["buy"]+$price["sell"]);


        //셀바중 계산을 위해 반으로 나눔.
        $shipValue=ceil($shipValue/2);

        for($i=0;$i<sizeof($killdata["attackers"]) && !$error_occured;$i++){

            $qr="insert into Event_killmails (
                killmail_id,
                killmail_hash,
                killtime,
                victim_id,victim_name,victim_ship,
                value,killers_number,
                killer_id,killer_name,killer_ship) values (
                ".$killdata["killmail_id"].",
                \"".$_GET["killmail_hash"]."\",
                timestamp(\"".str_replace("Z","+00:00",$killdata["killmail_time"])."\"),
                ".$killdata["victim"]["character_id"].",
                \"".getCharacterName($killdata["victim"]["character_id"])."\",
                ".$killdata["victim"]["ship_type_id"].",
                ".$shipValue.",
                ".sizeof($killdata["attackers"]).",
                ".$killdata["attackers"][$i]["character_id"].",
                \"".getCharacterName($killdata["attackers"][$i]["character_id"])."\",
                ".$killdata["attackers"][$i]["ship_type_id"].")";
            
            $result=$dbcon->query($qr);

            if(!$result){
                $error_occured=true;
            }

        }
        if(!$error_occured){
            echo("{\"message\":\"정상적으로 등록되었습니다.\"}");
        }
        else{
            echo("{\"error\":\"DB_error_1\",\n\"message\":\"등록 중 에러 발생.DB_error_1.\"}");
        }
    }
}

?>