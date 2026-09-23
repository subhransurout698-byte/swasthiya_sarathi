<?php
function triage_analyse($symptoms){
$s=strtolower($symptoms);$high=['difficulty breathing','breathing difficulty','unconscious','severe bleeding','chest pain','seizure','fainted'];
$medium=['high fever','persistent vomiting','severe pain','dizziness','dehydration'];$flags=[];$priority='LOW';
foreach($high as $x)if(strpos($s,$x)!==false){$flags[]='Urgency signal: '.$x;$priority='HIGH';}
if($priority==='LOW')foreach($medium as $x)if(strpos($s,$x)!==false){$flags[]='Needs timely review: '.$x;$priority='MEDIUM';}
$q=[];if(!preg_match('/\b(day|days|hour|hours|week|weeks)\b/',$s))$q[]='When did the symptoms start?';
if(strpos($s,'fever')!==false)$q[]='Has the temperature been measured?';
if(strpos($s,'pain')!==false)$q[]='Where is the pain located and how severe is it?';
if(!$q)$q[]='Please confirm whether symptoms are improving, worsening, or unchanged.';
return ['priority'=>$priority,'flags'=>$flags,'questions'=>$q,'summary'=>'Reported symptoms: '.trim($symptoms).'. Suggested review priority: '.$priority.'.'];
}
function jlist($v){return json_encode(array_values($v),JSON_UNESCAPED_UNICODE);}
?>