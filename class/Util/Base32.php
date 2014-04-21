<?php
/**
 * 32位 编码解码规则
 *
 * example:
 * $num = Base32::encode('12345654321');
 * var_dump($num);
 * // outputs: "EIXRI4K"
 *
 * $num = Base32::decode('EIXRI4K');
 * var_dump($num);
 * // outputs: "12345654321"
 *
 * @author qiuf
 */

class Util_Base32
{
	const ALPHABET = "3456789ABCDEFGHIJKLMNPQRSTUVWXYZ";

	public static function encode($int) {
		$base32_string = "";
		$base = strlen(self::ALPHABET);
		while($int >= $base) {
			$div = (int)floor($int / $base);
			$mod = ($int - ($base * $div));
			$base32_string = substr(self::ALPHABET, $mod, 1) . $base32_string;
			$int = $div;
		}

		if($int) $base32_string = substr(self::ALPHABET, $int, 1) . $base32_string;
		return $base32_string;
	}

	public static function decode($base32) {
		$int_val = 0;
		for($i=strlen($base32)-1,$j=1,$base=strlen(self::ALPHABET);$i>=0;$i--,$j*=$base) {
			$int_val += $j * strpos(self::ALPHABET, $base32{$i});
		}
		return $int_val;
	}
}
