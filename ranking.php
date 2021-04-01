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

//캐릭터 리스트를 모두 불러옴.

$qr="select * from PublicESI_keys where service_type=\"KillEvent\" and active>=1";
$result=$dbcon->query($qr);

for($i=0;$i<$result->num_rows;$i++){
    $data=$result->fetch_array();
    $characters[$i]["id"]=$data["characterid"];
    $characters[$i]["name"]=$data["charactername"];
    $characters[$i]["corp"]=$data["corpid"];
}

//캐릭터별 점수를 계산함.

for($i=0;$i<sizeof($characters);$i++){

    //킬포인트/로스포인트 정리
    $qr="select sum(value/killers_number) from Event_killmails where killer_id=".$characters[$i]["id"];
    $result=$dbcon->query($qr);
    $killpoint=$result->fetch_row();
    $killpoint=ceil($killpoint[0]);

    $qr="select sum(value/killers_number) from Event_killmails where victim_id=".$characters[$i]["id"]." and (victim_ship!=11176 and victim_ship!=11198 and victim_ship!=11202 and victim_ship!=11186)";
    $result=$dbcon->query($qr);
    $losspoint=$result->fetch_row();
    $losspoint=ceil($losspoint[0]);


    //인터셉터(1/2)
    $qr="select sum(value/(2*killers_number)) from Event_killmails where victim_id=".$characters[$i]["id"]." and (victim_ship=11176 or victim_ship=11198 or victim_ship=11202 or victim_ship=11186)";
    $result=$dbcon->query($qr);
    $losspoint_cept=$result->fetch_row();
    $losspoint+=ceil($losspoint_cept[0]);
    
    //인터딕터(1/4)
    $qr="select sum(value/(3*killers_number)) from Event_killmails where victim_id=".$_GET["character_id"]." and (victim_ship=22456 or victim_ship=22460 or victim_ship=22464 or victim_ship=22452)";
    $result=$dbcon->query($qr);
    $losspoint_dict=$result->fetch_row();
    $losspoint+=ceil($losspoint_dict[0]);   

    $characters[$i]["killpoint"]=$killpoint;
    $characters[$i]["losspoint"]=$losspoint;

}
echo("<script>\n");
echo("var characters=new Array();\n");
for($i=0;$i<sizeof($characters);$i++){
    echo("characters[".$i."]={\n
        id:".$characters[$i]["id"].",\n
        name:\"".$characters[$i]["name"]."\",\n
        corp:".$characters[$i]["corp"].",\n
        killpoint:".$characters[$i]["killpoint"].",\n
        losspoint:".$characters[$i]["losspoint"]."\n
    };\n");
}
echo("</script>\n")
?>
T.R.C
<div id=trcboard>
</div>
<hr>
T.R.U
<div id=truboard>
</div>
<script src="https://lindows.kr/jslib.js"></script>
<script>
//정렬
for(var i=0;i<characters.length-1;i++){
    for(var j=i+1;j<characters.length;j++){
        if(characters[i].killpoint-characters[i].losspoint < characters[j].killpoint-characters[j].losspoint){
            let tempstruct=characters[j];
            characters[j]=characters[i];
            characters[i]=tempstruct;
        }
    }
}

var trci=1;
var trui=1;
for(var i=0;i<characters.length;i++){

                        //DHGU                          BBC
if(characters[i].corp==98616206 || characters[i].corp==98632907){
    document.getElementById("trcboard").innerHTML+=(trci)+". "+characters[i].name+"\n<br>\n"+number_format(characters[i].killpoint-characters[i].losspoint)+"\n<br><br>\n";
    trci++;
}
//                            NR.AS
else if(characters[i].corp==98603624){
    document.getElementById("truboard").innerHTML+=(trui)+". "+characters[i].name+"\n<br>\n"+number_format(characters[i].killpoint-characters[i].losspoint)+"\n<br><br>\n";
    trui++;

}

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

