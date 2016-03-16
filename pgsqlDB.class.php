<?php
/**
PGSQL database connection wrapper

EXAMPLE:
 ** init **
$_DB = array(
    'default' => array(
        'host' => '127.0.0.1',
        'port' => '5432',
        'user' => 'u',
        'password' => '1',
        'codepage' => 'UTF8',
        'database' => 'b',
        'link' => null
    )
);

$DB = new DB($_DB['default']);
$DB -> connect();

 ** get single row result **
 $DB -> item('SELECT * FROM my_table');

 ** get all result **
 $DB -> table('SELECT * FROM my_table');
*/
class PgsqlDB{
    var $db_conn;
    var $user;
    var $db_name;
    var $host;
    var $pass;
    var $port;
    var $codepage = 'UTF8';
    var $last_query;
    var $log;
    function __construct(&$DB){
        $this -> host = $DB['host'];
        $this -> user = $DB['user'];
        $this -> password = $DB['password'];
        $this -> port = $DB['port'];
        $this -> db_name = $DB['database'];
        $this -> codepage = $DB['codepage'];
        // create log file instance if possible
        if(class_exists('logWriter')){
            global $_SYSTEM_CONF;
            $log_name = isset($_SYSTEM_CONF['sql_log_name'])?$_SYSTEM_CONF['sql_log_name']:'sql'.date('YmdH').'.log';
            $log_dir = isset($_SYSTEM_CONF['log_dir'])?$_SYSTEM_CONF['log_dir']:'logs/';
            $this -> log = new logWriter($log_name, $log_dir);
        }
    }
    function connect(){
        $connection_str = 'host='.$this -> host.' port='.$this -> port.' dbname='.$this -> db_name.' user='.$this -> user.' password='.$this -> password;
        $conn = pg_connect($connection_str);
        if($conn === false){
            throw new Exception('connection error');
        }
        $this -> db_conn = $conn;
        $this -> query('SET NAMES \''.$this -> codepage.'\'');
    }
    function close(){
        pg_close($this -> db_conn);
    }
    function query($str){
        $result = $this -> last_query = pg_query($this -> db_conn, $str);
        if($result === false){
            //pg_last_error($this -> db_conn)
            if($this -> log !== null){
                $this -> log -> put(array('query' => $str, 'error' => pg_last_error($this -> db_conn)));
            }
            throw new Exception('Database error');
        }
        return $this -> last_query;
    }
    function fetch_array($query_id){
        return pg_fetch_assoc($query_id);
    }
    function item($str, $key = null){
        $qid = $this -> query($str);
        $result = $this -> fetch_array($qid);
        if($key !== null){
            $result = $result[$key];
        }
        return $result;
    }
    function table($str, $key_index_name = null, $value_index_name = null){
        $qid = $this -> query($str);
        $i = 0;
        $result = array();
        while($t = $this -> fetch_array($qid)){
            $key = $key_index_name === null?$i:$t[$key_index_name];
            $value = $value_index_name === null?$t:$t[$value_index_name];
            $result[$key] = $value;
            $i++;
        }
        return $result;
    }
    function tree($s){
        $result = array();
        $qid = $this -> query($s);
        while($t = $this -> fetch_array($qid)){
            $link_key = (isset($t['root']) && $t['root'] == 1)?0:$t['id'];
            if(!isset($result[$link_key])){
                $result[$link_key] = $t;
            }else{
                foreach($t as $key => $value){
                    $result[$link_key][$key] = $value;
                }
            }
            if(isset($t['root']) && $t['root'] == 1){
                if(!isset($result[$t['id']])){
                    $result[$t['id']] = $t;
                }else{
                    foreach($t as $key => $value){
                        $result[$t['id']][$key] = $value;
                    }
                }
            }
            if($t['parent_id'] === null){
                continue;
            }
            if(isset($t['root']) && $t['root'] == 1){
                continue;
            }
            if(!isset($result[$t['parent_id']])){
                $result[$t['parent_id']] = array('childs' => array($t['id'] => &$result[$t['id']]));
            }else{
                $result[$t['parent_id']]['childs'][$t['id']] = &$result[$t['id']];
            }
        }
        return $result;
    }
    function escape($str){
        return pg_escape_string($this -> db_conn, $str);
    }
    function affected_rows($qid = null){
        return pg_affected_rows($qid === null?$this -> last_query:$qid);
    }
}
?>
