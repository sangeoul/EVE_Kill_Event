<?php


include $_SERVER['DOCUMENT_ROOT']."/PublicESI/phplib.php";

dbset();

session_start();

logincheck();

$INTERCEPTOR=array(11178,11198,11202,11186);
$INTERDICTOR=array(22456,22460,22464,22452);

$loginurl="https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=https://".$serveraddr."/CorpESI/Event/getesi.php&scope=".$ESI_scope["KillEvent"]."&client_id=".$client_id["KillEvent"];

if(!isset($_GET["character_id"])) {
    $_GET["character_id"]=$_SESSION['PublicESI_userid'];

}

echo(getCharacterName($_GET["character_id"])."\n<br>\n<br>");

//현재 페이지에서 불러오는 character 가 유저의 소유인지 확인한다.
$qr="select * from PublicESI_keys where userid=".$_SESSION['PublicESI_userid']." and characterid=".$_GET["character_id"]." and service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

echo("<table><tr>\n
<th>Ship</th>\n
<th>Point</th>\n
<th></th>\n
</tr>
");
//소유가 확인되면 정보를 출력함.
if($result->num_rows>0){
    $qr="select victim_id,victim_ship,killmail_id,value,killtime,killer_id,killer_ship from (
        (select  victim_id,victim_ship,killmail_id,value,killtime,killer_id,killer_ship from Event_killmails where killer_id=".$_GET["character_id"]." and value>0 ) 
        union
        (select  distinct victim_id,victim_ship,killmail_id,value,killtime,0 as killer_id,0 as killer_ship from Event_killmails where value>0 and victim_id=".$_GET["character_id"].") 
        ) A
        order by killtime desc;";
    $result=$dbcon->query($qr);

    for($i=0;$i<$result->num_rows;$i++){
        
        $data=$result->fetch_array();
        // 킬 기록을 표시
        if($data["killer_id"]==$_GET["character_id"]){
            echo("<tr>\n
            <td>".getItemName($data["victim_ship"])."</td>\n
            <td><font color=#028A0F >+".number_format($data["value"]/$data["killers_number"])."</td>\n
            <td><a href=\"https://zkillboard.com/kill/".$data["killmail_id"]."/\" target=_blank><img src=\"./zkillboard.png\"></a></td>\n
            ");
        }

        //로스 기록을 표시
        else if($data["victim_id"]==$_GET["character_id"]){

            //인터셉터
            if(in_array($data["victim_ship"],$INTERCEPTOR)){
                echo("<tr>\n
                <td>".getItemName($data["victim_ship"])."</td>\n
                <td><font color=#FF0000 >-".number_format($data["value"]/2)."</td>\n
                <td><a href=\"https://zkillboard.com/kill/".$data["killmail_id"]."/\" target=_blank><img src=\"./zkillboard.png\"></a></td>\n
                ");
            }
            //인터딕터
            else if(in_array($data["victim_ship"],$INTERDICTOR)){
                echo("<tr>\n
                <td>".getItemName($data["victim_ship"])."</td>\n
                <td><font color=#FF0000 >-".number_format($data["value"]/3)."</td>\n
                <td><a href=\"https://zkillboard.com/kill/".$data["killmail_id"]."/\" target=_blank><img src=\"./zkillboard.png\"></a></td>\n
                ");
            }
            else{
                echo("<tr>\n
                <td>".getItemName($data["victim_ship"])."</td>\n
                <td><font color=#FF0000 >-".number_format($data["value"])."</td>\n
                <td><a href=\"https://zkillboard.com/kill/".$data["killmail_id"]."/\" target=_blank><img src=\"./zkillboard.png\"></a></td>\n
                ");               
            }

        }


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
td{
    border:1px solid black;
    border-collapse: collapse;
}
table{
    border-collapse: collapse;
}

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

