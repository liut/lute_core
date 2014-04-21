<?PHP



define('_CLI_DEBUG', TRUE );

include_once __DIR__ . '/init.php';

$val = Validation::farm();

// 为不同字段设置不同的验证规则

$val->add('username', '用户名', 'required|trim|valid_string[alpha,lowercase,numeric]');
$val->add('email', 'Email', 'required|trim|valid_email');
$val->add('age', 'Age', 'valid_string[numeric]');
$val->add('username', '你的名称', 'required');

// 规则可以是个数组
$val->add('username', 'Username', array('required', 'trim', 'valid_string[alpha,lowercase,numeric]', 'min_length[4]'));

// 设置错误提示信息
$val->setMessage('required', '需要 :label ');
$val->setMessage('valid_email', ':label 格式不正确');
$val->setMessage('valid_string', '“:value” 不是有效的 :label');
$val->setMessage('min_length', ':label 太短');

$input = [
	'username' => 'abc',
	'email' => 'bad or invalid email address'
];

// 运行验证过程
// $input 可以是 $_POST 或 Request 实例
if ($val->run($input))
{
	// 验证成功（即全部通过）
	// 取回所有验证通过的字段集合
	$vars = $val->validated();
	var_dump($vars);
	// 或者取其中一个字段
	$var = $val->validated('username');
}
else
{
	// 验证失败
	// 取所有的错误对象集合
	$errors = $val->error();
	foreach($errors as $e)
	{
		//echo $e->getMessage().PHP_EOL;
		echo $e . PHP_EOL;
		//echo e->message(FALSE, '<li>', '</li>');
	}
}


echo 'Lang::current ', print_r(Lang::current(), TRUE), PHP_EOL;
echo 'Lang::loaded ', print_r(Lang::loaded(), TRUE), PHP_EOL;
echo 'Lang::lines ', print_r(Lang::lines(), TRUE), PHP_EOL;



