<?PHP



// define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

$hstore_string = <<< EOT
"bloger_head"=>"eh/tr/0ejj8hrnhg0ff57edszpq.jpg", "bloger_link"=>"http://azlina-lin.blogspot.jp", "bloger_name"=>"Azlina Lin", "bloger_text"=>"I'm Noor Azlina Abdul Samad K....that's a mouthful! hehe! You can call me Lin. :) I am an Art and Design graduate from UiTM, Shah Alam, majored in Ceramic Design."
EOT;


$pg_obj = Util_PgHstore::farm('bc.postgres');
$arr = $pg_obj->hstoreToPhp($hstore_string);

print_r($arr);


