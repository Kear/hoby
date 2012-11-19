<?php
/*
 * 'Simple Makes Boom'
 * Created on 2012-11-15
 * @author: Kearney
 * @E-mail: kearneyjar@gmail.com
 *
 */


class Db
{

    //数据
    protected $_data = array();

    // 暂时关闭分表相关定义
    //每个数据表的大小
    //private $_dataTableSize = "5000000";

    //数据表名
    private $_dataTableName = 'data';

    //数据表键
    private $_dataTableKey = 'key';

    //数据表值
    private $_dataTableValue = 'value';

    //查询缓存的开关
    private $_memType = _DEBUG_;
    const DAO_TYPE_NONE = 0;  // 不做数据缓存
    const DAO_TYPE_BOTH = 1;  // memcache与mysql的data表都存
    const DAO_TYPE_MEMCACHE = 2;  // 只存储在memcache中
    const DAO_TYPE_MYSQL = 3;     // 只存储在mysql的data表中

    //数据库名
    protected $_dbName = '';

    //索引表名
    protected $_tableName = '';

    //主键
    protected $_pk = 'id';

    //字段信息
    private $_fields = array();

    //缓存对象
    protected $_cacher = null;

    //缓存key前缀
    protected $_cacherKeyPreifx = '';

    // 缓存原始key附加存储的键值
    protected $_plainCacherKey = 'plain_cacher_key';

    //缓存开关
    private  $_cacherable = true;

    // 是否是全局ID模式
    protected $_globalIdMode = false;

    const PARAM_PREFIX=':ycp';

    // 数据库类型
    protected $dbType      = null;

    // 是否自动释放查询结果
    protected $autoFree    = false;

    // 是否显示调试信息 如果启用会在日志文件记录sql语句
    public $debug          = _DEBUG_;

    // 是否使用永久连接
    protected $pconnect    = true;

    // 当前SQL指令
    protected $queryStr    = '';
    protected $daoQueryList = array(); // array('index'=>'', 'data'=>'');

    // 最后插入ID
    public $lastInsID   = null;

    // 返回或者影响记录数
    protected $numRows     = 0;

    // 返回字段数
    protected $numCols     = 0;

    // 事务指令数
    protected $transTimes  = 0;

    // 错误信息
    protected $error       = '';

    // 数据库连接ID 支持多个连接
    protected $linkID      = array();

    // 当前连接ID
    protected $_linkID     = null;

    // 当前查询ID
    protected $queryID     = null;

    // 是否已经连接数据库
    protected $connected   = false;

    // 数据库连接参数配置
    protected $config      = '';

    // SQL 执行时间记录
    protected $beginTime;

    // 数据库表达式
    protected $comparison  = array('eq'=>'=','neq'=>'!=','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE');

    // 查询表达式
    protected $selectSql   = 'SELECT%DISTINCT% %FIELDS% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT%';

    static private $instance = array();

    /**
     *
     * 架构函数
     *
     * @access public
     * @param array $config 数据库配置数组
     *
     */
    function __construct($config='')
    {
    }

    /**
     *
     * 取得数据库类实例
     *
     * @static
     * @access public
     * @return mixed 返回数据库驱动类
     *
     */
    public static function getInstance()
    {
        $args = func_get_args();
        $hash = md5(serialize($args));

        if (!isset(self::$instance[$hash])) {
            $o= new self();
            $instance[$hash] = call_user_func_array(array($o, 'factory'), $args);
        }
        return $instance[$hash];
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     *
     * 加载数据库 支持配置文件或者 DSN
     *
     * @access public
     * @param mixed $db_config 数据库配置信息
     * @return string
     * @throws Leb_Exception
     *
     */
    public function factory($db_config='')
    {
        // 读取数据库配置
        $db_config = $this->parseConfig($db_config);

        if(empty($db_config['dbms'])){
            //throw new Leb_Exception('找不到配置文件');
            //默认使用PDO
            $db_config['dbms'] = 'pdomysql';
        }

        // 数据库类型
        // 获取当前的数据库类型
        $this->dbType = strtolower($db_config['dbms']);
        if (substr($this->dbType,0,3) == 'pdo') {
            $this->dbType = substr($this->dbType,3);
            $db = 'pdo';
        } else {
            $db = $this->dbType;
        }

        // 读取系统数据库驱动目录
        $dbDriverPath = dirname(__FILE__).'/';
        require_once($dbDriverPath . $db . '.php');
        $dbClass = 'Leb_Dao_' . $db;

        // 检查驱动类
        if(class_exists($dbClass)) {
            $db = new $dbClass($db_config);
            $db->dbType = strtoupper($this->dbType);
        } else {
            // 类没有定义
            throw new Leb_Exception('数据库驱动不存在: ' . $db_config['dbms']);
        }

        if($this->_cacherable){
            if(is_null($this->_cacher)){
                $db->_cacher = Leb_Dao_Memcache::getInstance();
            }
       }
        return $db;
    }

    /**
     *
     * 根据DSN获取数据库类型 返回大写
     *
     * @access protected
     * @param string $dsn  dsn字符串
     * @return string
     *
     */
    protected function _getDsnType($dsn)
    {
        $match  =  explode(':',$dsn);
        $dbType = strtoupper(trim($match[0]));
        return $dbType;
    }

    /**
     *
     * 分析数据库配置信息，支持数组和DSN
     *
     * @access private
     * @param mixed $db_config 数据库配置信息
     * @return string
     *
     */
    private function parseConfig($db_config='')
    {
        if ( !empty($db_config) && is_string($db_config)) {
            // 如果DSN字符串则进行解析
            $db_config = $this->parseDSN($db_config);
        }
        return $db_config;
    }

    /**
     *
     * 增加数据库连接(相同类型的)
     *
     * @access protected
     * @param mixed $config 数据库连接信息
     * @param mixed $linkNum  创建的连接序号
     *
     * @return void
     *
     */
    public function addConnect($config,$linkNum=null)
    {
        $db_config  =   $this->parseConfig($config);
        if(empty($linkNum))
            $linkNum     =   count($this->linkID);
        if(isset($this->linkID[$linkNum]))
            // 已经存在连接
            return false;
        // 创建新的数据库连接
        return $this->connect($db_config,$linkNum);
    }

    /**
     *
     * 切换数据库连接
     *
     * @access protected
     * @param integer $linkNum  创建的连接序号
     * @return void
     *
     */
    public function switchConnect($linkNum)
    {
        if(isset($this->linkID[$linkNum])) {
            // 存在指定的数据库连接序号
            $this->_linkID = $this->linkID[$linkNum];
            return true;
        }else{
            return false;
        }
    }

    /**
     *
     * 初始化数据库连接
     *
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     *
     */
    protected function initConnect($master=true)
    {
        $_config = array();
        if(empty($_config)) {
            // 缓存分布式数据库配置解析
            $_config = array();
            //如果没有设置读或写
            if (!isset($this->config['write'])
                    || !isset($this->config['read'])){
                $_config['write'] = $_config['read'] = $this->config;
            } else {
                $_config['write'] = $this->config['write'];
                $_config['read'] = $this->config['read'];
            }
        }

        // 主从式采用读写分离
        if($master) {
            // 默认主服务器是连接第一个数据库配置
            $t = 'write';
        } else {
            // 读操作连接从服务器
            $t = 'read';
        }

        if (isset($_config[$t]['persist'])) {
            $this->pconnect = $_config[$t]['persist'] ? true : false;
        }

        $db_config = array(
            'username'  => $_config[$t]['username'],
            'password'  => $_config[$t]['password'],
            'host'      => $_config[$t]['host'],
            'port'      => $_config[$t]['port'],
            'dbname'    => $_config[$t]['dbname'],
            'charset'   => $_config[$t]['charset'],
            'dsn'       => @$_config[$t]['dsn'],
            'params'    => @$_config[$t]['params']
        );

        $this->_linkID = $this->connect($db_config,$t);
    }

    /**
     *
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName
     *
     * @static
     * @access public
     * @param string $dsnStr
     * @return array
     *
     */
    public function parseDSN($dsnStr)
    {
        if( empty($dsnStr) ){return false;}
        $info = parse_url($dsnStr);
        if($info['scheme']){
            $dsn = array(
            'dbms'        => $info['scheme'],
            'username'  => isset($info['user']) ? $info['user'] : '',
            'password'   => isset($info['pass']) ? $info['pass'] : '',
            'host'  => isset($info['host']) ? $info['host'] : '',
            'port'    => isset($info['port']) ? $info['port'] : '',
            'dbname'   => isset($info['path']) ? substr($info['path'],1) : ''
            );
        }else {
            preg_match('/^(.*?)\:\/\/(.*?)\:(.*?)\@(.*?)\:([0-9]{1, 6})\/(.*?)$/',trim($dsnStr),$matches);
            $dsn = array (
            'dbms'        => $matches[1],
            'username'  => $matches[2],
            'password'   => $matches[3],
            'host'  => $matches[4],
            'port'    => $matches[5],
            'dbname'   => $matches[6]
            );
        }
        return $dsn;
     }

    /**
     *
     * 数据库调试 记录当前SQL
     * @access protected
     *
     */
    protected function debug()
    {
        // 记录操作结束时间
        if ( $this->debug ) {
            $runtime = number_format(microtime(TRUE) - $this->beginTime, 6);
            Leb_Debuger::debug(" RunTime:".$runtime."s SQL = ".$this->queryStr);
            if ($error = $this->error()) {
                throw new Leb_Exception($error);
            }
        }
    }

    /**
     *
     * 设置锁机制
     *
     * @access protected
     * @return string
     *
     */
    protected function parseLock($lock=false)
    {
        if(!$lock) return '';
        if('ORACLE' == $this->dbType) {
            return ' FOR UPDATE NOWAIT ';
        }
        return ' FOR UPDATE ';
    }

    /**
     *
     * set分析
     *
     * @access protected
     * @param array $data
     * @return string
     *
     */
    protected function parseSet($data)
    {
        $set = array();
        foreach ($data as $key=>$val){
            $value = $this->parseValue($val);
            if(is_scalar($value)) {// 过滤非标量数据
                $set[] = $this->addSpecialChar($key) . '=' . $value;
            }
        }
        return ' SET '.implode(',',$set);
    }

    /**
     *
     * value分析
     *
     * @access protected
     * @param mixed $value
     * @return string
     *
     */
    protected function parseValue(&$value)
    {
        if(is_string($value)) {
            $value = '\''.$this->escape_string($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value = $this->escape_string($value[1]);
        }elseif(is_null($value)){
            $value   =  'null';
        }
        return $value;
    }

    /**
     *
     * field分析
     *
     * @access protected
     * @param mixed $fields
     * @return string
     *
     */
    protected function parseField($fields)
    {
        if(is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array   =  array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key))
                    $array[] = $this->addSpecialChar($key).' AS '.$this->addSpecialChar($field);
                else
                    $array[] = $this->addSpecialChar($field);
            }
            $fieldsStr = implode(',', $array);
        }elseif(is_string($fields) && !empty($fields)) {
            $fieldsStr = $this->addSpecialChar($fields);
        }else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }

    /**
     *
     * table分析
     *
     * @access protected
     * @param mixed $table
     * @return string
     *
     */
    protected function parseTable($tables)
    {
        if(is_string($tables))
            $tables  =  explode(',',$tables);
        $array   =  array();
        foreach ($tables as $key=>$table){
            if(is_numeric($key)) {
                $array[] =  $this->addSpecialChar($table);
            }else{
                $array[] =  $this->addSpecialChar($key).' '.$this->addSpecialChar($table);
            }
        }
        return implode(',',$array);
    }

    /**
     *
     * where分析
     *
     * @access protected
     * @param mixed $where
     * @return string
     *
     */
    protected function parseWhere($where) {
        $whereStr = '';
        if(is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        }else{ // 使用数组条件表达式
            if(array_key_exists('_logic',$where)) {
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' '.strtoupper($where['_logic']).' ';
                unset($where['_logic']);
            }else{
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach ($where as $key=>$val){
                $whereStr .= "( ";
                if(0 === strpos($key,'_')) {
                    // 解析特殊条件表达式
                    $whereStr   .= $this->parseLebWhere($key,$val);
                }else{
                    $key = $this->addSpecialChar($key);
                    if(is_array($val)) {
                        if(is_string($val[0])) {
                            if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT|NOTLIKE|LIKE)$/i',$val[0])) { // 比较运算
                                $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                            }elseif('exp'==strtolower($val[0])){ // 使用表达式
                                $whereStr .= ' ('.$key.' '.$val[1].') ';
                            }elseif(preg_match('/IN/i',$val[0])){ // IN 运算
                                if(is_array($val[1])) {
                                    array_walk($val[1], array($this, 'parseValue'));
                                    $zone = implode(',',$val[1]);
                                }else{
                                    $zone = $val[1];
                                }
                                $whereStr .= $key.' '.strtoupper($val[0]).' ('.$zone.')';
                            }elseif(preg_match('/BETWEEN/i',$val[0])){ // BETWEEN运算
                                $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                                $whereStr .= ' ('.$key.' '.strtoupper($val[0]).' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]).' )';
                            }else{
                                throw new Leb_Exception('SQL BETWEEN 语法错误'.':'.$val[0]);
                            }
                        }else {

                            $count = count($val);
                            if(in_array(strtoupper(trim($val[$count-1])),array('AND','OR','XOR'))) {
                                $rule = strtoupper(trim($val[$count-1]));
                                $count = $count -1;
                            }else{
                                $rule = 'AND';
                            }
                            for($i=0;$i<$count;$i++) {
                                $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                                if('exp'==strtolower($val[$i][0])) {
                                    $whereStr .= '('.$key.' '.$data.') '.$rule.' ';
                                }else{
                                    $op = is_array($val[$i])?$this->comparison[strtolower($val[$i][0])]:'=';
                                    $whereStr .= '('.$key.' '.$op.' '.$this->parseValue($data).') '.$rule.' ';
                                }
                            }
                            $whereStr = substr($whereStr,0,-4);
                        }
                    }else {
                           $whereStr .= $key . " = " . $this->parseValue($val);
                    }
                }
                $whereStr .= ' )'.$operate;
            }
            $whereStr = substr($whereStr,0,-strlen($operate));
        }
        return empty($whereStr)?'':' WHERE '.$whereStr;
    }

    /**
     *
     * 特殊条件分析
     *
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return string
     *
     */
    protected function parseLebWhere($key,$val)
    {
        $whereStr   = '';
        if(strpos($key ,'_complex') === 0){
            $key = '_complex';
        }
        switch($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr   = substr($this->parseWhere($val),6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val,$where);
                if(array_key_exists('_logic',$where)) {
                    $op = ' ' . strtoupper($where['_logic']) . ' ';
                    unset($where['_logic']);
                } else {
                    $op = ' AND ';
                }
                $array = array();
                foreach ($where as $field=>$data) {
                    $array[] = $this->addSpecialChar($field).' = '.$this->parseValue($data);
                }
                $whereStr = implode($op,$array);
                break;
        }
        return $whereStr;
    }

    /**
     *
     * limit分析
     *
     * @access protected
     * @param mixed $lmit
     * @return string
     *
     */
    protected function parseLimit($limit)
    {
        return !empty($limit)?   ' LIMIT '.$limit.' ':'';
    }


    /**
     * mssql limit 分析
     * @param string $sql
     * @param string $limit
     * @return string
     */
    protected function mssqlLimit($sql, $limit)
    {
        return $sql;
    }
    /**
     *
     * join分析
     *
     * @access protected
     * @param mixed $join
     * @return string
     *
     */
    protected function parseJoin($join)
    {
        $joinStr = '';
        if(!empty($join)) {
            if(is_array($join)) {
                foreach ($join as $key=>$_join){
                    if(false !== stripos($_join,'JOIN'))
                        $joinStr .= ' '.$_join;
                    else
                        $joinStr .= ' LEFT JOIN ' .$_join;
                }
            }else{
                $joinStr .= ' LEFT JOIN ' .$join;
            }
        }
        return $joinStr;
    }

    /**
     *
     * order分析
     *
     * @access protected
     * @param mixed $order
     * @return string
     *
     */
    protected function parseOrder($order)
    {
        if(is_array($order)) {
            $array   =  array();
            foreach ($order as $key=>$val){
                if(is_numeric($key)) {
                    $array[] = $this->addSpecialChar($val);
                }else{
                    $array[] = $this->addSpecialChar($key).' '.$val;
                }
            }
            $order = implode(',',$array);
        }
        return !empty($order)? ' ORDER BY '.$order:'';
    }

    /**
     *
     * group分析
     *
     * @access protected
     * @param mixed $group
     * @return string
     *
     */
    protected function parseGroup($group)
    {
        return !empty($group)? ' GROUP BY '.$group:'';
    }

    /**
     *
     * having分析
     *
     * @access protected
     * @param string $having
     * @return string
     *
     */
    protected function parseHaving($having)
    {
        return  !empty($having)? ' HAVING ' . $having:'';
    }

    /**
     *
     * distinct分析
     *
     * @access protected
     * @param mixed $distinct
     * @return string
     *
     */
    protected function parseDistinct($distinct)
    {
        return !empty($distinct)? ' DISTINCT ' :'';
    }

    /**
     *
     * 插入记录
     *
     * @access public
     *
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @return false | integer
     *
     */
    public function insert($data, $options=array(), $param=array())
    {
        if(is_array($data)){
            foreach ($data as $key=>$val){
                $bind_key = self::PARAM_PREFIX.$key;
                $fields[] = "`{$key}`={$bind_key}";
                $param[$bind_key] = $val;
            }
            $data = implode(',', $fields);
        }

        if(!$data || !is_string($data)){
            throw new Leb_Exception($this->error());
        }

        $sql = 'INSERT INTO '.$this->parseTable($options['table']).' SET '.$data;
        if ($options['table'] == $this->_dataTableName) {
            $this->daoQueryList['data'] = $sql;
        } else {
            $this->daoQueryList = array();
            $this->daoQueryList['index'] = $sql;
        }
        return $this->execute($sql, $param);
    }

    /**
     * dao插入
     * @param <type> $data
     * @param <type> $options
     * @return <type>
     */
    public function daoInsert($data, $options = array(), $daoType = self::DAO_TYPE_BOTH)
    {
        $index = $fast = false;
        do
        {
            //插入索引表
            $index = $this->insert($data, $options);

            $indexId = $this->lastInsID;
            if ($this->getGlobalIdMode() && isset($data[$this->getPk()])) {
                $indexId = $this->lastInsID = $data[$this->getPk()];
            }

            if(!$daoType || false === $index || false === $indexId) {
                return $index;
            }

            $autoinc_field = $pk = $this->getPk();
            $tblInfo = $this->_fields;
            if(empty($tblInfo))
            {
                throw new Leb_Exception('无表结构信息: '.$this->_tableName);
            }

            if(!empty($tblInfo['_autoinc'])){
                $autoinc_field = $tblInfo['_autoinc'];
            }

            $tblInfo = $tblInfo['_default'];
            //插入完整数据到快表
            $data = $this->_data;
            if($autoinc_field == $this->getPk()){
                $data[$autoinc_field] = $indexId;
            }

            $tblInfo[$autoinc_field] = $indexId;
            $key = $this->getCacheKey($data[$pk]);
            $hkey= $this->getGlobalIdMode() ? $key : md5($key);

            if(isset($data[$pk]) && $autoinc_field == $pk) {
                unset($data[$pk]);
            }else{
                $data[$autoinc_field] = $indexId;
            }

            $data[$this->_plainCacherKey] = $key;
            $mem = array_merge($tblInfo, $data);
            $mem = $this->processData($mem);

            if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MYSQL) {
                $opt['table'] = $this->_dataTableName;
                $fast = $this->insert(array($this->_dataTableKey=>$hkey, $this->_dataTableValue=>$mem), $opt);
                if(false === $fast){
                    break;
                }
            }

            //恢复lastInsID
            $this->lastInsID = $indexId;
            if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MEMCACHE) {
                //更新缓存
                $this->_cacher->set($hkey, $mem, array('flag' => false, 'expire' => 0));
            }
        }while(false);

        //检查是否一致否则回滚
        if(false === $fast && ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MYSQL)){
            $opt['where'] = "`{$this->getPk()}`='{$this->lastInsID}'";
            $opt['table'] = $this->_tableName;
            $this->delete($opt);
            return false;
        }else{
            return $index;
        }
    }

    /**
     *
     * 通过Select方式插入记录
     *
     * @access public
     *
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $option  查询数据参数
     *
     * @return false | integer
     *
     */
    public function selectInsert($fields,$table,$options=array())
    {
        if(is_string($fields)) {
            $fields = explode(',',$fields);
        }

        array_walk($fields, array($this, 'addSpecialChar'));
        $sql = 'INSERT INTO '.$this->parseTable($table).' ('.implode(',', $fields).') ';

        $selectSql = str_replace(
            array('%TABLE%','%DISTINCT%','%FIELDS%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%'),
            array(
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
                $this->parseField(isset($options['field'])?$options['field']:'*'),
                $this->parseJoin(isset($options['join'])?$options['join']:''),
                $this->parseWhere(isset($options['where'])?$options['where']:''),
                $this->parseGroup(isset($options['group'])?$options['group']:''),
                $this->parseHaving(isset($options['having'])?$options['having']:''),
                $this->parseOrder(isset($options['order'])?$options['order']:''),
                $this->parseLimit(isset($options['limit'])?$options['limit']:'')
            ), $this->selectSql);

        $selectSql = $this->mssqlLimit($selectSql, isset($options['limit'])?$options['limit']:'');

        $sql .= $selectSql;
        $sql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);
        return $this->execute($sql);
    }

    /**
     *
     * 更新记录
     *
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     *
     */
    public function update($data,$options=array(), $param=array())
    {
        $fields = null;
        if($data && is_array($data))
        {
            foreach($data as $k => $v)
            {
                $key = self::PARAM_PREFIX.$k;
                if(is_array($v) && isset($v[0]) && $v[0] == 'exp')
                {
                    if(strstr($v[1],"+")){
                        unset($param[$k]);
                        $exparr = explode("+",$v[1]);
                        $fields[] = "`{$k}`=`{$exparr[0]}`+{$exparr[1]}";
                    }elseif(strstr($v[1],"-")){
                        unset($param[$k]);
                        $exparr = explode("-",$v[1]);
                        $fields[] = "`{$k}`=`{$exparr[0]}`+{$exparr[1]}";
                    }
                }
                else
                {
                    $fields[] = "`{$k}`={$key}";
                    $param[$key] = $v;
                }
            }
            $data = implode(',', $fields);
        }

        if(!$data || !is_string($data))
        {
            throw new Leb_Exception($this->error());
        }

        $sql = 'UPDATE '.$this->parseTable($options['table']). ' SET '
            .$data
            .$this->parseWhere(isset($options['where'])?$options['where']:'')
            .$this->parseOrder(isset($options['order'])?$options['order']:'')
            .$this->parseLimit(isset($options['limit'])?$options['limit']:'')
            .$this->parseLock(isset($options['lock'])?$options['lock']:false);

        if ($options['table'] == $this->_dataTableName) {
            $this->daoQueryList['data'] = $sql;
        } else {
            $this->daoQueryList = array();
            $this->daoQueryList['index'] = $sql;
        }

        return $this->execute($sql, $param);
    }

    /**
     * dao更新数据
     * @param <type> $data
     * @param <type> $options
     * @return <type>
     */
    public function daoUpdate($data, $options = array(),$daoType = self::DAO_TYPE_BOTH)
    {
        //结果集
        $resultSet = array();
        //是否存在缓存
        if ($this->_cacher && $daoType) {
            //取表默认信息
            $tableInfo = $this->_fields;
            if(empty($tableInfo))
            {
               throw new Leb_Exception('无表结构信息: '.$this->_tableName);
            }

            $tableInfo = $tableInfo['_default'];
            //索引表中获取更新的记录ID
            $indexIds = $this->selectIndexPk($options);
            if (false === $indexIds) { //查询结果为空
                return false;
            }

             //1,批量更新索引表
            //如果更新内容包含主键，则去掉主键
            if(isset($data[$this->getPk()])){
                unset($data[$this->getPk()]);
            }
            if (empty($data) && !empty($this->_data)) {
                // fix setInc/setDec/setField etc, one memcache field operation
                // should not run SQL update, but run memcache update
            } else {
                $indexResult = $this->update($data, $options);
                if (false === $indexResult) {
                    return false;
                }
            }

            //2,批量更新缓存
            foreach ($indexIds as $iid) {
                //重新赋予全数据
                $data = $this->_data;
                 //初始化缓存中的值
                $tableInfo[$this->getPk()] = $iid;
                /**
                 * 缓存key
                 * 结构为 数据库名_表名_主键
                 */
                $key = $this->getCacheKey($iid);
                $hkey= $this->getGlobalIdMode() ? $key : md5($key);
                $data[$this->_plainCacherKey] = $key;

                //计算是哪个数据表
                /**暂时关闭分表相关定义
                $hashId = $this->hashTable($iid, $this->_dataTableSize);
                $hashTable = $this->_dataTableName."_".$hashId;
                */

                if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MEMCACHE) {
                    //获取缓存中的值
                    $memResult = $this->_cacher->get($hkey);
                    //缓存中存储为json 序列化格式
                    $memResult = $this->processData($memResult,false);
                    //如果缓存中有,则替换
                    if ($memResult) {
                        //如果定义列
                        foreach ($data as $dkey => $dvalue) {
                            //判断是否使用setinc或者setdec进行字段增减
                            if(isset($dvalue[0]) && $dvalue[0] == 'exp'){
                                if(strstr($dvalue[1],"+")){
                                    $exparr = explode("+",$dvalue[1]);
                                    $dvalue = isset($memResult[$dkey]) ? ($memResult[$dkey] + $exparr[1]) : $exparr[1];
                                }elseif(strstr($dvalue[1],"-")){
                                    $exparr = explode("-",$dvalue[1]);
                                    $dvalue = isset($memResult[$dkey]) ? ($memResult[$dkey] - $exparr[1]) : -$exparr[1];
                                }
                            }
                            $memResult[$dkey] = $dvalue;

                        }

                        $memResult = $this->processData($memResult);
                        $this->_cacher->set($hkey, $memResult, array('flag' => false, 'expire' => 0));

                    }
                }

                if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MYSQL) {
                    //3,批量更新全表，此步骤暂时为模拟使用，以后会作为异步操作?????????
                    $dataOptions['where'] = array($this->_dataTableKey => $hkey);
                    $dataOptions['table'] = $this->_dataTableName;
                    $dbResult = $this->select($dataOptions);

                    if(isset($dbResult[0][$this->_dataTableValue])&&!empty($dbResult[0][$this->_dataTableValue])){
                        $dbResult = $this->processData($dbResult[0][$this->_dataTableValue],false);
                    }

                    if($dbResult){
                        foreach($data as $dkey => $dvalue){
                            //判断是否使用setinc或者setdec进行字段增减
                            if(isset($dvalue[0]) && $dvalue[0] == 'exp'){
                                if(strstr($dvalue[1],"+")){
                                    $exparr = explode("+",$dvalue[1]);
                                    $dvalue = $dbResult[$dkey] + $exparr[1];

                                }elseif(strstr($dvalue[1],"-")){
                                    $exparr = explode("-",$dvalue[1]);
                                    $dvalue = $dbResult[$dkey] - $exparr[1];
                                }
                            }
                            $dbResult[$dkey] = $dvalue;
                        }


                        $dbResult = $this->processData($dbResult);
                        $dataResult[$this->_dataTableValue] = $dbResult;
                        $result = $this->update($dataResult, $dataOptions);
                        unset($dataResult);
                        if (false === $result) {
                            return false;
                        }
                    }else{
                        //如果索引表有数据，data表没数据，则新增数据至data表
                        //如果memcache有值，则用memcache的值,如果memcache没有，则用表结构默认值
                        if(!empty($memResult)){
                            $memResult = $this->processData($memResult,false);
                            $dbResult  = array_merge($memResult,$data);
                        }else{
                            $dbResult = array_merge($tableInfo,$data);
                        }

                        $dbResult = $this->processData($dbResult);
                        unset($dataOptions);
                        $dataOptions['table'] = $this->_dataTableName;
                        $data = array($this->_dataTableKey=>$hkey,  $this->_dataTableValue=>$dbResult);
                        $result = $this->insert($data, $dataOptions);
                        if(false === $result){
                            return false;
                        }
                    }
                }
            }

        } else {
            $result = $this->update($data, $options);
            if (false === $result) {
                return false;
            }
        }
        $resultSet = $result;
        return $resultSet;
    }

    /**
     *
     * 删除记录
     *
     * @access public
     * @param array $options 表达式
     * @return false | integer
     *
     */
    public function delete($options=array())
    {
        $sql = 'DELETE FROM '
            .$this->parseTable($options['table'])
            .$this->parseWhere(isset($options['where'])?$options['where']:'')
            .$this->parseOrder(isset($options['order'])?$options['order']:'')
            .$this->parseLimit(isset($options['limit'])?$options['limit']:'')
            .$this->parseLock(isset($options['lock'])?$options['lock']:false);

        if ($options['table'] == $this->_dataTableName) {
            $this->daoQueryList['data'] = $sql;
        } else {
            $this->daoQueryList = array();
            $this->daoQueryList['index'] = $sql;
        }

        return $this->execute($sql);
    }

    /**
     * dao删除数据
     * @param <type> $data
     * @param <type> $options
     * @return <type>
     */
    public function daoDelete($options = array(), $daoType = self::DAO_TYPE_BOTH)
    {
        $count = 0;
        do
        {
            if(!$this->_cacher || !$daoType) {
                break;
            }

            $indexIds = $this->selectIndexPk($options);
            if(false === $indexIds) {
                break;
            }

            foreach($indexIds as $iid) {
                $key = $this->getCacheKey($iid);
                $hkey= $this->getGlobalIdMode() ? $key : md5($key);

                //清理物理记录
                $c = $this->delete($options);
                if(false === $c){
                    continue;
                }else{
                    $count += $c;
                }

                if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MEMCACHE) {
                    //清理缓存
                    $this->_cacher->del($hkey);
                }

                if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MYSQL) {
                    //清理快表
                    $opt['where'] = array($this->_dataTableKey=>$hkey);
                    $opt['table'] = $this->_dataTableName;
                    $this->delete($opt);
                }
            }

            return !$count ? false : $count;
        }while(false);

        $rv = $this->delete($options);
        return $rv;
    }

    /**
     *
     * 查找记录
     *
     * @access public
     * @param array $options 表达式
     * @return array
     *
     */
    public function select($options=array())
    {
        if(isset($options['page'])) {
            // 根据页数计算limit
            @list($page,$listRows) = explode(',',$options['page']);
            $listRows = $listRows?$listRows:((isset($options['limit']) && is_numeric($options['limit']))?$options['limit']:20);
            $offset  =  $listRows*((int)$page-1);
            $options['limit'] = trim($offset.','.$listRows, " ,\t");
        }
        $sql = str_replace(
            array('%TABLE%','%DISTINCT%','%FIELDS%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%'),
            array(
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
                $this->parseField(isset($options['field'])?$options['field']:'*'),
                $this->parseJoin(isset($options['join'])?$options['join']:''),
                $this->parseWhere(isset($options['where'])?$options['where']:''),
                $this->parseGroup(isset($options['group'])?$options['group']:''),
                $this->parseHaving(isset($options['having'])?$options['having']:''),
                $this->parseOrder(isset($options['order'])?$options['order']:''),
                $this->parseLimit(isset($options['limit'])?$options['limit']:'')
            ),$this->selectSql);
        $sql = $this->mssqlLimit($sql, isset($options['limit'])?$options['limit']:'');
        $sql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);

        if ($options['table'] == $this->_dataTableName) {
            $this->daoQueryList['data'] = $sql;
        } else {
            $this->daoQueryList = array();
            $this->daoQueryList['index'] = $sql;
        }

        return $this->query($sql);
    }

    /**
     * 返回逻辑字段key
     * @param mix val
     * @param mix field
     * @return string
     * 结构为 数据库名_表名_主键
     */
    public function getCacheKey($val, $field=null)
    {
        if ($this->getGlobalIdMode()) {
            return $val;
        }
        $tbl = $this->stringReplece($this->_tableName);
        $key = is_array($field) ? implode('_', $field) : $field;
        $plain_key = $field ? $this->_dbName.'_'.$tbl.'_'.$key.'_'.$val : $this->_dbName.'_'.$tbl.'_'.$val;

        return $plain_key;
    }

    /**
     * dao查询处理
     * @param <type> $options
     * @return <type>
     */
    public function daoSelect($options = array(), $daoType = self::DAO_TYPE_BOTH)
    {
        $Sets = array();
        do
        {
            if(!$this->_cacher || !$daoType) {
                break;
            }

            $indexIds = $this->selectIndexPk($options);
            if(false === $indexIds) {
                break;
            }
            $dbSet = array();

            //get from memcache
            if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MEMCACHE) {
                $count = count($indexIds);
                $index_arr = array();
                for($i=0; $i < $count; ) {
                    $keys = array_slice($indexIds, $i, 30, true);//机器性能好可调大
                    $i += count($keys);
                    $hkeys = array();
                    foreach($keys as $k => $v) {
                        $rkey = $this->getCacheKey($v);
                        $hkey = $this->getGlobalIdMode() ? $rkey : md5($rkey);
                        $hkeys[] = $index_arr[$hkey] = $hkey;
                    }
                    $tmp = $this->_cacher->get($hkeys);
                    if(empty($tmp)) {
                        continue;
                    }

                    foreach($tmp as $k => $v) {
                        if($data = $this->processData($v, false)) {
                            $dbSet[$k] = $data;
                        }
                    }
                }


                $diff = array_diff_key($index_arr, $dbSet);
                if(empty($diff)) {
                    $Sets = array_values($dbSet);
                    break;
                }
            }

            //get from db
            if ($daoType == self::DAO_TYPE_BOTH || $daoType == self::DAO_TYPE_MYSQL) {
                $count = count($diff);
                $diff = array_flip($diff);
                $opt['table'] = $this->_dataTableName;

                for($i=0; $i < $count; )
                    {
                        $keys = array_slice($diff, $i, 100);
                        $i += count($keys);
                        $opt['where'] = "`{$this->_dataTableKey}` IN ('".implode("','", $keys)."')";
                        $tmp = $this->select($opt);
                        if(empty($tmp))
                            continue;
                        foreach($tmp as $k => $v)
                            {
                                if($data = $this->processData($v[$this->_dataTableValue], false))
                                    $dbSet[$v[$this->_dataTableKey]] = $data;
                            }
                    }

                foreach($indexIds as $v) {
                    $k = $this->getGlobalIdMode() ? $this->getCacheKey($v) : md5($this->getCacheKey($v));
                    if(isset($dbSet[$k]))
                        $Sets[] = $dbSet[$k];
                }
            }
        }while(false);

        if(empty($Sets) && (!$this->_cacher || !$daoType)){
            $Sets = $this->select($options);
        }

        return $Sets;
    }

    /**
     *
     * 字段和表名添加`
     * 保证指令中使用关键字不出错 针对mysql
     *
     * @access protected
     *
     * @param mixed $value
     *
     * @return mixed
     *
     */
    protected function addSpecialChar(&$value)
    {
        if(0 === strpos($this->dbType,'MYSQL')){
            $value   =  trim($value);
            if( false !== strpos($value,' ') || false !== strpos($value,',') || false !== strpos($value,'*') ||  false !== strpos($value,'(') || false !== strpos($value,'.') || false !== strpos($value,'`')) {
                //如果包含* 或者 使用了sql方法 则不作处理
            }else{
                $value = '`'.$value.'`';
            }
        }
        return $value;
    }

    /**
     *
     * 查询次数更新或者查询
     *
     * @access public
     * @param mixed $times
     * @return void
     *
     */
    public function Q($times='')
    {
        static $_times = 0;
        if(empty($times)) {
            return $_times;
        }else{
            $_times++;
            // 记录开始执行时间
            $this->beginTime = microtime(TRUE);
        }
    }

    /**
     *
     * 写入次数更新或者查询
     *
     * @access public
     * @param mixed $times
     * @return void
     *
     */
    public function W($times='')
    {
        static $_times = 0;
        if(empty($times)) {
            return $_times;
        }else{
            $_times++;
            // 记录开始执行时间
            $this->beginTime = microtime(TRUE);
        }
    }

    /**
     *
     * 获取最近一次查询的sql语句
     *
     * @access public
     * @return string
     *
     */
    public function getLastSql()
    {
        if (empty($this->daoQueryList))
            return $this->queryStr;
        else
            return implode('; ', array_values($this->daoQueryList));
    }

    /**
     *
     * 获取最近的错误信息
     *
     * @access public
     * @return string
     *
     */
    public function getError()
    {
        return $this->error();
    }

    /**
     * 获取缓存对象
     * @return <type>
     */
    public function getCacher()
    {
        if(!is_object($this->_cacher)){
            $this->_cacher = Leb_Dao_Memcache::getInstance();
        }
        return $this->_cacher;
    }

    /**
     *获取缓存key前缀
     * @return <type>
     */
    public  function getCacherKeyPrefix()
    {
        return $this->_cacherKeyPreifx;
    }

    /**
     *设置缓存key前缀
     * @param <type> $cacherkeyprefix
     */
    public function setCacherKeyPrefix($cacherkeyprefix)
    {
        $this->_cacherKeyPreifx = $cacherkeyprefix;
    }

    /**
     * 获取缓存开关
     * @return <type>
     */
    public function getCacherable()
    {
        return $this->_cacherable;
    }

    /**
     *设置缓存开关
     * @param <type> $cacherable
     */
    public function setCacherable($cacherable)
    {
        $this->_cacherable = $cacherable;
    }

    /**
     *设置数据库名称
     * @param <type> $dbname
     */
    public function setDbName($dbname)
    {
        $this->_dbName = $dbname;
    }

    /**
     *获取数据库名称
     * @return <type>
     */
    public function getDbName()
    {
        return $this->_dbName;
    }

    /**
     * 设置主键
     * @param <type> $pk
     */
    public function setPk($pk)
    {
        $this->_pk = $pk;
    }

    /**
     *获取主键
     * @return <type>
     */
    public function getPk($isArray=false)
    {
        $pk = isset($this->_fields['_pk'])?$this->_fields['_pk']:$this->_pk;
        if($isArray)
        {
            $pks = explode(',', $pk);
            $pks = array_flip($pks);
            return $pks;
        }
        else
        {
            return $pk;
        }
    }

    /**
     *设置表名
     * @param <type> $tablename
     */
    public function setTableName($tablename)
    {
        $this->_tableName = $tablename;

    }

    /**
     *获取表名
     * @return <type>
     */
    public function getTableName()
    {
        return $this->_tableName;
    }

    public function getFieldInfo()
    {
        return $this->_fields;
    }

    public function setGlobalIdMode($mode = true)
    {
        $this->_globalIdMode = $mode;
    }

    public function getGlobalIdMode()
    {
        return $this->_globalIdMode;
    }

    /**
     * 只获取主键结果集
     * 以后会更换为索引表
     * @param <type> $optoins
     */
    protected function selectIndexPk($options = array())
    {
        $result = array();
        //根据条件查询索引表，只返回主键
        $options['field'] = $this->getPk();
        $resultSet = $this->select($options);
        if (false === $resultSet) {
            return false;
        }

        if(is_array($resultSet)) {
            foreach($resultSet as $rskey => $rsvalue) {
                $result[$rskey] = $rsvalue[$this->_pk];
            }
        }
        return $result;
    }

    public function getTableKey()
    {
        return $this->_dbName.'_'.$this->_tableName;
    }

   /**
    * 获取完整数据对象
    * @return <type>
    */
   public  function getData()
   {
       return $this->_data;
   }

   /**
    * 设置完整数据对象
    * @param <type> $data
    */
   public function setData($data)
   {
       $this->_data = $data;
   }

   /**
    * 过滤掉字符串中的指定字符
    * @param <type> $str
    * @return <type>
    */
   private function stringReplece($str,$pattren="")
   {
       $result = "";
       if(!$pattren){
            $pattren = "/_\d*$/";
       }
       $result = preg_replace($pattren, "", $str);
       return $result;
   }

   /**
    * hash 分表
    * $id  分表字段
    * $deep 分表 深度 2|4|8
    */
   /** 暂时关闭分表相关定义
   private function hashTable($id="",$size=""){
       if(empty($id)){
           $id = $size;
       }
       $hash = ceil($id/$size);
       if($hash <1){
           $hash = 1;
       }
       return $hash;
    }
   public  function createHashTable($tablename = "",$size="")
    {
       $result = "";
       if(empty($size)){
           $size = $this->_dataTableSize;
       }
       if(empty($tablename)){
           $tablename = $this->_dataTableName;
       }

           $hash = $this->hashTable($id="",$size);
           $table = $tablename."_".$hash;
           $tablearr = $this->getTables($this->_dbName);
           if(!in_array($table,$tablearr))
           {
                $result = $this->createTable($table);
           }
       return $result;

    }
    *
    */

    /**
     *数组转换为utf-8字符集
     * @param <type> $data
     */
    private function array2utf8($data=array())
   {
        if(is_array($data)){
            foreach($data as $key => $value){
                $data[$key] = $this->array2utf8($value);
            }
        }else{
            $charset = mb_detect_encoding($data,array('UTF-8','GBK','GB2312'));
            $charset = strtolower($charset);
            if('cp936' == $charset){
                $charset='GBK';
            }
            if("utf-8" != $charset){
                $data = iconv($charset,"UTF-8//IGNORE",$data);
            }
        }

        return $data;
    }
    /**
     * 数据处理
     * @param <type> $data
     * @param <type> $encode true为encode，flase为decode
     * @return <type> array
     */
    private function processData($data, $encode = true)
    {
        $rdata = false;
        if ($encode) {
            $data = $this->array2utf8($data);
            // $rdata = serialize($data);
            $rdata = leb_json_encode($data);
        } else {
            // $rdata = unserialize($data);
            $rdata = leb_json_decode($data);
            if ($rdata === false || $rdata == NULL) {
                // echo "decode error:" . leb_json_last_strerror() . "\n";
                // print_r($data);
                // var_dump($rdata);
            }
        }
        return $rdata;
    }

    /**
     * 测试连接是否可用
     * @param <type> $linkID
     * @param <type> $dbType
     * @return <type>
     */
    public function myPing($linkID,$dbType='mysql')
    {
        $flag = true;
        switch($dbType){
            case 'pdo':
                $status = $linkID->getAttribute(PDO::ATTR_SERVER_INFO);
                if($status == 'MySQL server has gone away')
                {
                    $flag = false;
                }
                break;
            case 'mysql':
                $flag =  mysql_ping($linkID);
                break;
            default:

        }
        return $flag;
    }

    /**
     * 自动检测数据表信息
     * @access protected
     * @return voidf
     */
    public function checkTableInfo($flush=false)
    {
        $fields = $cacher= $pks = null;
        do
        {
            if($this->debug || $flush) {
                break;
            }

            $cacher = $this->getCacher();
            if(!$cacher) {
                break;
            }

            $tmp = $cacher->get($this->getTableKey());
            if(empty($tmp)) {
                break;
            }

            $fields = json_decode($tmp,true);
            if(!$fields || !isset($fields['_default'])
                || !isset($fields['_pks']) || !count($fields['_default'])){
                $fields = null;
                break;
            }

            $this->_pk = trim($this->_pk, " \t,");
            if(!$this->_pk){
                $this->_pk = trim(implode(',', $fields['_pks']), " ,\t");
            }

            //过滤无效逻辑主键
            $pks = explode(',', $this->_pk);
            foreach($pks as $k => $v)
            {
                if(!array_key_exists($v, $fields['_default'])) {
                    unset($pks[$v]);
                }
            }
        }while(false);

        if($fields && $cacher && !$this->debug && !$flush){
            $this->_fields = $fields;
            if($pks){
                $this->_pk = trim(implode(',', $pks), " ,\t");
            }else{
                $this->_pk = '';
            }
            $this->_fields['_pk'] = $this->_pk;
        }else{
            $this->flush();
        }
    }

    /**
     * 从数据库获取字段信息并缓存
     * @return void
     */
    public function flush($flush=true)
    {
        $tbl = $this->getTableName();
        $fields = $this->getFields($tbl);
        if(!$fields){
            throw new Leb_Exception('表结构信息: '.$tbl);
            return;
        }

        $this->_fields = array_keys($fields);
        $this->_fields['_autoinc'] = false;
        $this->_pk = trim($this->_pk, " \t,");
        $upks = array();
        if($this->_pk)
        {
            $upks = explode(',', $this->_pk);
        }
        $this->_fields['_pks'] = array();
        $type = $default = null;
        //验证用户设置主键字段
        foreach($fields as $key=>$val)
        {
            // 记录字段类型
            $type[$key] = $val['type'];
            $default[$key] = $val['default'];

            if(!$val['primary']) {
                continue;
            }

            //物理主键
            $this->_fields['_pks'][] = $key;
            if($val['autoinc']) {
                $this->_fields['_autoinc'] = $key;
            }
        }

        //过滤无效逻辑字段
        foreach($upks as $k => $v)
        {
            if(!array_key_exists($v, $fields)) {
                unset($upks[$k]);
            }
        }

        //逻辑主键
        if(!$upks)
        {
            $upks = $this->_fields['_pks'];
        }
        $this->_pk = implode(',', $upks);
        $this->_fields['_pk'] = $this->_pk;

        // 记录字段类型信息
        $this->_fields['_type'] = $type;
        $this->_fields['_default'] = $default;

        //永久缓存数据表信息
        $cacher = $this->getCacher();
        if(!$cacher || !empty($this->_fields))
        {
            return;
        }

        if(!$this->debug || $flush) {
           $data = $this->array2utf8($this->_fields);
           $option = array('flag'=>false, 'expire'=>0);
           $data = json_encode($data);
           $cacher->set($this->getTableKey(), $data, $option);
        }
    }
}//类定义结束
