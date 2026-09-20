<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: https://ugteci-aox.es');
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
function out($ok,$m,$c=200){http_response_code($c);echo json_encode(['ok'=>$ok,'message'=>$m,'mensaje'=>$m],JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')out(false,'Método no permitido.',405);
if(($_SERVER['HTTP_ORIGIN']??'')!==''&&($_SERVER['HTTP_ORIGIN']??'')!=='https://ugteci-aox.es')out(false,'Origen no permitido.',403);
$cp=dirname(__DIR__).'/privado/config-smtp.php'; if(!is_file($cp))out(false,'Error de configuración.',500); $cfg=require $cp;
$centro=trim($_POST['centro']??'');$dep=trim($_POST['departamento']??'');$msg=trim($_POST['mensaje']??'');
$qc=($_POST['quiereContacto']??'no')==='si'?'si':'no';$nom=$qc==='si'?trim($_POST['nombre']??''):'';$con=$qc==='si'?trim($_POST['contacto']??''):'';
if($centro===''||$msg==='')out(false,'Faltan campos obligatorios.',422);if($qc==='si'&&$con==='')out(false,'Indica cómo puede contactar UGT contigo.',422);
function rd($f){$r='';while(($l=fgets($f,515))!==false){$r.=$l;if(strlen($l)<4||$l[3]===' ')break;}return $r;}
function cd($r){return(int)substr($r,0,3);} function cmd($f,$s,$e){fwrite($f,$s."\r\n");return in_array(cd(rd($f)),(array)$e,true);}
$atts=[];$allowed=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];
$total=0;
if(isset($_FILES['archivos'])&&is_array($_FILES['archivos']['name'])){
 if(count($_FILES['archivos']['name'])>3)out(false,'Puedes adjuntar como máximo 3 archivos.',422);
 $fi=new finfo(FILEINFO_MIME_TYPE);
 foreach($_FILES['archivos']['name'] as $i=>$n){
  $er=$_FILES['archivos']['error'][$i];if($er===UPLOAD_ERR_NO_FILE)continue;if($er!==UPLOAD_ERR_OK)out(false,'No se pudo recibir uno de los archivos.',422);
  $sz=(int)$_FILES['archivos']['size'][$i];if($sz>5*1024*1024)out(false,'Cada archivo puede ocupar como máximo 5 MB.',422);$total+=$sz;if($total>10*1024*1024)out(false,'Los archivos no pueden superar 10 MB en total.',422);
  $tmp=$_FILES['archivos']['tmp_name'][$i];$mime=$fi->file($tmp);if(!isset($allowed[$mime]))out(false,'Uno de los archivos tiene un formato no permitido.',422);
  $base=preg_replace('/[^A-Za-z0-9._-]/','_',pathinfo(basename($n),PATHINFO_FILENAME));$data=file_get_contents($tmp);if($data===false)out(false,'No se pudo procesar un archivo.',422);
  $atts[]=['n'=>($base?:'archivo').'.'.$allowed[$mime],'m'=>$mime,'d'=>$data];
 }}
$em=$cfg['from_email'];$fp=@stream_socket_client('ssl://'.$cfg['host'].':'.(int)$cfg['port'],$eno,$estr,20,STREAM_CLIENT_CONNECT);if(!$fp)out(false,'No se ha podido enviar la información.',500);stream_set_timeout($fp,20);
$ok=cd(rd($fp))===220&&cmd($fp,'EHLO ugteci.es',250)&&cmd($fp,'AUTH LOGIN',334)&&cmd($fp,base64_encode($cfg['username']),334)&&cmd($fp,base64_encode($cfg['password']),235)&&cmd($fp,"MAIL FROM:<$em>",250)&&cmd($fp,"RCPT TO:<$em>",[250,251])&&cmd($fp,'DATA',354);
if(!$ok){fclose($fp);out(false,'No se ha podido enviar la información.',500);}
$b='=_UGT_'.bin2hex(random_bytes(12));$subject='=?UTF-8?B?'.base64_encode('Buzón confidencial UGT - '.$centro).'?=';$fn='=?UTF-8?B?'.base64_encode($cfg['from_name']).'?=';
$body="Centro: $centro\r\nDepartamento: ".($dep?:'No indicado')."\r\nSolicita contacto: ".($qc==='si'?'Sí':'No')."\r\n";
$body.=$qc==='si'?"Nombre: ".($nom?:'No indicado')."\r\nContacto: $con\r\n":"Datos de contacto: No facilitados\r\n";$body.="\r\nInformación recibida:\r\n$msg\r\n";
$mail="From: $fn <$em>\r\nTo: <$em>\r\nSubject: $subject\r\nDate: ".date(DATE_RFC2822)."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"\r\n\r\n--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$body\r\n";
foreach($atts as $a){$ne='=?UTF-8?B?'.base64_encode($a['n']).'?=';$mail.="--$b\r\nContent-Type: {$a['m']}; name=\"$ne\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$ne\"\r\n\r\n".chunk_split(base64_encode($a['d']),76,"\r\n")."\r\n";}
$mail.="--$b--\r\n";$mail=preg_replace('/^\./m','..',$mail);fwrite($fp,$mail."\r\n.\r\n");$r=rd($fp);fwrite($fp,"QUIT\r\n");fclose($fp);if(cd($r)!==250)out(false,'El servidor no ha aceptado el mensaje.',500);out(true,'Información enviada correctamente.');
?>