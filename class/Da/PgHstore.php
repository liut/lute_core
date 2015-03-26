<?php

/**
 * php_array to pg_hstore
 * pg_hstore to php_array
 *
 * Da_PgHstore
 *
 * @package core
 * @author sundb
 **/
class Da_PgHstore
{
    private $_db_key;
    /**
     * @param $db_key string
     * @return self
     */
    public static function farm($db_key)
    {
        static $instances = [];
        if (!isset($instances[$db_key])) {
                $instances[$db_key] = new static($db_key);
        }
        return $instances[$db_key];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    private function __construct($db_key)
    {
            $this->_db_key = $db_key;
    }
    /***********************************\
    *                                   *
    *     HSTORE: PHP => POSTGRESQL     *
    *                                   *
    \***********************************/
    public function hstoreFromPhp($php_array, $hstore_array = False)
    {
        if($hstore_array) {
            // Converts a PHP array of Associative Arrays to a PostgreSQL
            // Hstore Array. PostgreSQL Data Type: "hstore[]"
            $pg_hstore = array();
            foreach($php_array as $php_hstore) {
                $pg_hstore[] = $this->_hstoreFromPhpHelper($php_hstore);
            }

            // Convert the PHP Array of Hstore Strings to a single
            // PostgreSQL Hstore Array.
            $pg_hstore = $this->arrayFromPhp($pg_hstore);
        } else {
            // Converts a single one-dimensional PHP Associative Array
            // to a PostgreSQL Hstore. PostgreSQL Data Type: "hstore"
            $pg_hstore = $this->_hstoreFromPhpHelper($php_array);
        }
        return $pg_hstore;
    }

    private function _hstoreFromPhpHelper(array $php_hstore)
    {
        $pg_hstore = array();

        foreach ($php_hstore as $key => $val) {
            $search = array('\\', "'", '"');
            $replace = array('\\\\', "''", '\"');

            $key = str_replace($search, $replace, $key);
            $val = $val === NULL
                    ? 'NULL'
                    : '"' . str_replace($search, $replace, $val) . '"';

            $pg_hstore[] = sprintf('"%s"=>%s', $key, $val);
        }

        return sprintf("%s", implode(',', $pg_hstore));
    }

    /***********************************\
    *                                   *
    *     HSTORE: POSTGRESQL => PHP     *
    *                                   *
    \***********************************/
    public function hstoreToPhp($string)
    {
        // If first and last characters are "{" and "}", then we know we're
        // working with an array of Hstores, rather than a single Hstore.
        if(substr($string, 0, 1) == '{' && substr($string, -1, 1) == '}') {
            $array = $this->arrayToPhp($string, 'hstore');
            $hstore_array = array();
            foreach($array as $hstore_string) {
                    $hstore_array[] = $this->_hstoreToPhpHelper($hstore_string);
            }
        } else {
            $hstore_array = $this->_hstoreToPhpHelper($string);
        }
        return $hstore_array;
    }

    private function _hstoreToPhpHelper($string)
    {
        if(!$string || !preg_match_all('/"(.+)(?<!\\\)"=>(NULL|""|".+(?<!\\\)\s*"),?/U', $string, $match, PREG_SET_ORDER)) {
            return array();
        }
        $array = array();

        foreach ($match as $set) {
            list(, $k, $v) = $set;
            $v = $v === 'NULL'
                    ? NULL
                    : substr($v, 1, -1);

            $search = array('\"', '\\\\');
            $replace = array('"', '\\');

            $k = str_replace($search, $replace, $k);
            if ($v !== NULL)
            $v = str_replace($search, $replace, $v);

            $array[$k] = $v;
        }
        return $array;
    }

    /**********************************\
    *                                  *
    *     ARRAY: POSTGRESQL => PHP     *
    *                                  *
    \**********************************/
    public function arrayToPhp($string, $pg_data_type)
    {
        if(substr($pg_data_type, -2) != '[]') {
            // PostgreSQL arrays are signified by
            $pg_data_type .= '[]';
        }
        $dbh = Da_Wrapper::dbo($this->_db_key);
        return $dbh->getFlat("SELECT UNNEST(" . $dbh->quote($string) . "::" . $pg_data_type . ") AS value");
        // $array_values = array();

        // $pos = 0;
        // foreach ($grab_array_values as $array_value) {
        //     if ($array_value === NULL) {
        //         $array_values[] = NULL;
        //         continue;
        //     }
        //     $array_values[] = $array_value['value'];
        // }

        // return $array_values;
    }

    /**********************************\
    *                                  *
    *     ARRAY: PHP => POSTGRESQL     *
    *                                  *
    \**********************************/
    public function arrayFromPhp($array)
    {
        $return = '';
        foreach($array as $array_value) {
            if($return) {
                $return .= ',';
            }
            $array_value = str_replace("\\", "\\\\", $array_value);
            $array_value = str_replace("\"", "\\\"", $array_value);
            $return .= '"' . $array_value . '"';
        }
        return '{' . $return . '}';
    }
}
