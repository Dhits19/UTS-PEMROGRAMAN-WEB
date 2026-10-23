<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
$dir=__DIR__.'/data'; if(!is_dir($dir)) mkdir($dir,0750,true);
$uf=$dir.'/users.json'; $bf=$dir.'/bookings.json';
foreach([$uf,$bf] as $f) if(!file_exists($f)) file_put_contents($f,'[]',LOCK_EX);
function rd($f){$v=json_decode(@file_get_contents($f)?:'[]',true);return is_array($v)?$v:[];}
function wr($f,$v){return file_put_contents($f,json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)!==false;}
function out($v,$s=200){http_response_code($s);echo json_encode($v,JSON_UNESCAPED_UNICODE);exit;}
$x=json_decode(file_get_contents('php://input')?:'{}',true);if(!is_array($x))$x=[];
$a=$_GET['action']??'';$users=rd($uf);$bookings=rd($bf);
switch($a){
case 'register':
$n=trim((string)($x['name']??''));$e=strtolower(trim((string)($x['email']??'')));$p=(string)($x['password']??'');
if($n===''||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($p)<4)out(['ok'=>false,'message'=>'Nama, email valid, dan password minimal 4 karakter wajib diisi.'],422);
foreach($users as $u)if($u['email']===$e)out(['ok'=>false,'message'=>'Email sudah terdaftar. Silakan login.'],409);
$u=['id'=>bin2hex(random_bytes(8)),'name'=>$n,'email'=>$e,'password'=>password_hash($p,PASSWORD_DEFAULT)];$users[]=$u;
if(!wr($uf,$users))out(['ok'=>false,'message'=>'Gagal menyimpan akun. Pastikan folder data dapat ditulis.'],500);
session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'name'=>$n,'email'=>$e];out(['ok'=>true]);
case 'login':
$e=strtolower(trim((string)($x['email']??'')));$p=(string)($x['password']??'');
foreach($users as $u)if($u['email']===$e&&password_verify($p,$u['password'])){session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email']];out(['ok'=>true]);}
out(['ok'=>false,'message'=>'Email atau password salah, atau akun belum terdaftar.'],401);
case 'me':if(empty($_SESSION['user']))out(['ok'=>false,'message'=>'Belum login.'],401);out(['ok'=>true,'user'=>$_SESSION['user']]);
case 'logout':$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();out(['ok'=>true]);
case 'bookings':
if(empty($_SESSION['user']))out(['ok'=>false,'message'=>'Silakan login terlebih dahulu.'],401);
$m=array_values(array_filter($bookings,fn($b)=>$b['user_id']===$_SESSION['user']['id']));usort($m,fn($a,$b)=>$b['id']<=>$a['id']);out(['ok'=>true,'bookings'=>$m]);
case 'create_booking':
if(empty($_SESSION['user']))out(['ok'=>false,'message'=>'Silakan login terlebih dahulu.'],401);
$c=(string)($x['console']??'');$d=(string)($x['date']??'');$t=(string)($x['time']??'');$du=(int)($x['duration']??0);$dt=DateTime::createFromFormat('!Y-m-d',$d);
if(!in_array($c,['PS4','PS5'],true)||!$dt||$dt->format('Y-m-d')!==$d||$d<date('Y-m-d')||!preg_match('/^(10|11|12|13|14|15|16|17|18|19|20|21):00$/',$t)||$du<1||$du>4)out(['ok'=>false,'message'=>'Data booking tidak valid.'],422);
$s=(int)substr($t,0,2);if($s+$du>22)out(['ok'=>false,'message'=>'Jam selesai tidak boleh melewati pukul 22:00.'],422);
foreach($bookings as $b){if($b['console']!==$c||$b['date']!==$d)continue;$bs=(int)substr($b['time'],0,2);$be=$bs+(int)$b['duration'];if($s<$be&&$s+$du>$bs)out(['ok'=>false,'message'=>'Jadwal konsol bentrok dengan booking lain. Pilih jam lain.'],409);}
$b=['id'=>time().random_int(100,999),'user_id'=>$_SESSION['user']['id'],'console'=>$c,'date'=>$d,'time'=>$t,'duration'=>$du];$bookings[]=$b;if(!wr($bf,$bookings))out(['ok'=>false,'message'=>'Gagal menyimpan booking. Periksa izin folder data.'],500);out(['ok'=>true]);
case 'delete_booking':
if(empty($_SESSION['user']))out(['ok'=>false,'message'=>'Silakan login terlebih dahulu.'],401);$id=(int)($x['id']??0);$new=[];$found=false;
foreach($bookings as $b){if((int)$b['id']===$id&&$b['user_id']===$_SESSION['user']['id']){$found=true;continue;}$new[]=$b;}
if(!$found)out(['ok'=>false,'message'=>'Booking tidak ditemukan.'],404);if(!wr($bf,$new))out(['ok'=>false,'message'=>'Gagal menghapus booking.'],500);out(['ok'=>true]);
default:out(['ok'=>false,'message'=>'Aksi tidak dikenal.'],404);
}