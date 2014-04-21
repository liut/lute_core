<?PHP

function error($msg) {
	die($msg);
}

/**
 * Hashes a password and returns the hash based on the specified enc_type.
 *
 * @param string The password to hash in clear text.
 * @param string Standard LDAP encryption type which must be one of
 *        crypt, ext_des, md5crypt, blowfish, md5, sha, smd5, ssha, or clear.
 * @return string The hashed password.
 */
function password_hash($password_clear,$enc_type) {

    $enc_type = strtolower($enc_type);

    switch($enc_type) {
        case 'blowfish':
            if (! defined('CRYPT_BLOWFISH') || CRYPT_BLOWFISH == 0)
                error(_('Your system crypt library does not support blowfish encryption.'),'error','index.php');

            # Hardcoded to second blowfish version and set number of rounds
            $new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$2a$12$'.random_salt(13)));

            break;

        case 'crypt':
            if ($_SESSION[APPCONFIG]->getValue('password', 'no_random_crypt_salt'))
                $new_value = sprintf('{CRYPT}%s',crypt($password_clear,substr($password_clear,0,2)));
            else
                $new_value = sprintf('{CRYPT}%s',crypt($password_clear,random_salt(2)));

            break;

        case 'ext_des':
            # Extended des crypt. see OpenBSD crypt man page.
            if (! defined('CRYPT_EXT_DES') || CRYPT_EXT_DES == 0)
                error(_('Your system crypt library does not support extended DES encryption.'),'error','index.php');

            $new_value = sprintf('{CRYPT}%s',crypt($password_clear,'_'.random_salt(8)));

            break;

        case 'k5key':
            $new_value = sprintf('{K5KEY}%s',$password_clear);

            system_message(array(
                'title'=>_('Unable to Encrypt Password'),
                'body'=>'phpLDAPadmin cannot encrypt K5KEY passwords',
                'type'=>'warn'));

            break;

        case 'md5':
            $new_value = sprintf('{MD5}%s',base64_encode(pack('H*',md5($password_clear))));
            break;

        case 'md5crypt':
            if (! defined('CRYPT_MD5') || CRYPT_MD5 == 0)
                error(_('Your system crypt library does not support md5crypt encryption.'),'error','index.php');

            $new_value = sprintf('{CRYPT}%s',crypt($password_clear,'$1$'.random_salt(9)));

            break;

        case 'sha':
            # Use php 4.3.0+ sha1 function, if it is available.
            if (function_exists('sha1'))
                $new_value = sprintf('{SHA}%s',base64_encode(pack('H*',sha1($password_clear))));
            elseif (function_exists('mhash'))
                $new_value = sprintf('{SHA}%s',base64_encode(mhash(MHASH_SHA1,$password_clear)));
            else
                error(_('Your PHP install does not have the mhash() function. Cannot do SHA hashes.'),'error','index.php');

            break;

        case 'ssha':
            if (function_exists('mhash') && function_exists('mhash_keygen_s2k')) {
                mt_srand((double)microtime()*1000000);
                $salt = mhash_keygen_s2k(MHASH_SHA1,$password_clear,substr(pack('h*',md5(mt_rand())),0,8),4);
                $new_value = sprintf('{SSHA}%s',base64_encode(mhash(MHASH_SHA1,$password_clear.$salt).$salt));

            } else {
                error(_('Your PHP install does not have the mhash() or mhash_keygen_s2k() function. Cannot do S2K hashes.'),'error','index.php');
            }

            break;

        case 'smd5':
            if (function_exists('mhash') && function_exists('mhash_keygen_s2k')) {
                mt_srand((double)microtime()*1000000);
                $salt = mhash_keygen_s2k(MHASH_MD5,$password_clear,substr(pack('h*',md5(mt_rand())),0,8),4);
                $new_value = sprintf('{SMD5}%s',base64_encode(mhash(MHASH_MD5,$password_clear.$salt).$salt));

            } else {
                error(_('Your PHP install does not have the mhash() or mhash_keygen_s2k() function. Cannot do S2K hashes.'),'error','index.php');
            }

            break;

        case 'clear':
        default:
            $new_value = $password_clear;
    }

    return $new_value;
}


