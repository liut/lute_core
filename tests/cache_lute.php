<?PHP


$id = '1mh_tattoo-o-ring-200pcs-lot';

$pattern = '#^([0-9a-z])([0-9a-z])([0-9a-z]+)(_[a-z0-9-]+|)#'; // 

$ret = preg_match($pattern, $id, $matches);

var_dump($ret, $matches);
