<?PHP

define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

$_SERVER['HTTP_CLIENT_IP'] = "192.168.14.67, 127.0.0.1";
$_SERVER['HTTP_USER_AGENT'] = "catalufa/1.1 CFNetwork/609.1.4 Darwin/13.0.0";

$req = Request::current();

$ip = $req->CLIENT_IP;

var_dump($ip);

var_dump($req->isApp(), $req->isApp('catalufa'), $req->isApp('aaa'));

