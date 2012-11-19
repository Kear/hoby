<?php
  /**
   * 处理业务关系及数据关系的模型
   *
   * 'Simple Makes Boom'
   * Created on 2012-11-7
   * @author: Kearney
   * @E-mail: kearneyjar@gmail.com
   *
   */

define('HAS_ONE',1);
define('BELONGS_TO',2);
define('HAS_MANY',3);
define('MANY_TO_MANY',4);

require_once('pdo.php');
class Leb_Model
{
    // 操作状态
    const MODEL_INSERT       =   1; //插入模型数据
    const MODEL_UPDATE       =   2; //更新模型数据
    const MODEL_BOTH         =   3; //包含上面两种方式
    const MUST_VALIDATE      =   1; //必须验证
    const EXISTS_VAILIDATE   =   0; //表单存在字段则验证
    const VALUE_VAILIDATE    =   2; //表单值不为空则验证


    static public $dbFieldtypeCheck = true; //是否启用字段验证
    static public $tokenOn = false;         //是否启用令牌
    static public $tokenName = 'auth_token';//令牌名

    static public $debug = _DEBUG_;

    // dao类型选择  // true为启用缓存,false为直接访问数据库,
    const DAO_TYPE_NONE = 0;  // 不做数据缓存
    const DAO_TYPE_BOTH = 1;  // memcache与mysql的data表都存
    const DAO_TYPE_MEMCACHE = 2;  // 只存储在memcache中
    const DAO_TYPE_MYSQL = 3;     // 只存储在mysql的data表中
    protected $_daoType = self::DAO_TYPE_BOTH;

    // 是否启用全局序列生成模式    true为启用，false为原有ID模式（兼容不需要全局ID的表）
    protected $_globalIdMode = false;

    //dao数据对象
    protected $_dao = null;

    //当前使用的扩展模型
    private $_extModel =  null;

    //当前数据库操作对象
    protected $_db = null;

    //主键名称
    protected $_pk  = '';

    //数据表前缀
    protected $_tablePrefix  =  '';

    //数据表后缀
    protected $_tableSuffix  =  '';

    //模型名称
    protected $_name = '';

    //数据库名称
    protected $_dbName  = '';

    //数据表名（不包含表前缀）
    protected $_tableName = '';

    //实际数据表名（包含表前缀）
    protected $_trueTableName ='';

    //最近错误信息
    protected $_error = '';

    //字段信息
    protected $_fields = array();

    //数据信息
    protected $_data   = array();

    protected $_options  = array();  //查询表达式参数
    protected $_validate = array();  // 自动验证定义
    protected $_auto     = array();  // 自动完成定义
    protected $_map      = array();  // 字段映射定义

    //是否自动检测数据表字段信息
    protected $_autoCheckFields = true;

    //数据库配置
    protected $_dbConfig        = array();

    //记录调用模型的模块名
    public static $_application = '';


    /**
     *
     * 架构函数
     * 取得DB类的实例对象 字段检查
     *
     * @param string $name     模型名称
     * @param array  $dbConfig 数据库配置
     * @example
     * 1、$dbConfig = array(
                        'host'     => '127.0.0.1',
                        'port'     => '3306',
                        'dbname'   => 'test',
                        'username' => 'root',
                        'password' => 'root',
                        'charset'  => 'utf8',
                        'persist'  => '0',
                    );
     * $model = new Leb_Model('user', $dbConfig);
     *
     * 2、$dbConfig = array(
                            'dbms' => 'mysqli',  //默认为pdomysql
                            'tablePrefix' => '',   //可选
                            'tableSuffix' => '',   //可选
                            'read' => array(
                                'host'     => '127.0.0.1',
                                'port'     => '3306',
                                'dbname'   => 'test',
                                'username' => 'root',
                                'password' => 'root',
                                'charset'  => 'utf8',
                                'persist'  => '0',
                            ),
                            'write' => array(
                                'host' => '127.0.0.1',
                                'port' => '3306',
                                'dbname' => 'test',
                                'username' => 'root',
                                'password' => 'root',
                                'charset' => 'utf8',
                                'persist' => '0',
                            ));
        $model = new Leb_Model('user', $dbConfig);
     *
     */
    public function __construct($name='',$dbConfig='')
    {
        // 模型初始化
        $this->_initialize();

        // 获取模型名称
        if(!empty($name)) {
            $this->_name = $name;
        }
        /*elseif(empty($this->_name)){
            $this->_name = $this->getModelName();
        }*/

        //使用默认配置
        if (empty($dbConfig)) {
            $dbConfig = require(_CONFIG_ . 'db.php');
        }

        // 数据库初始化操作
        // 获取数据库操作对象
        // 当前模型有独立的数据库连接信息
         $this->_dao = Leb_Dao_Abstract::getInstance($dbConfig);
        //如果使用index_mem_data 结构，则自动创建表结构,表大小默认为500W
        /** 暂时取消分表方案，如果分表，可以采用一索引对应一个数据表,或者采用表分区
        if($this->_daoType){
            $this->_dao->createHashTable($tablename="",$size="");
        }
         *
         */
        if (isset($dbConfig['debug'])) {
            self::$debug = $dbConfig['debug'];
            $this->_dao->debug = $dbConfig['debug'];
        }

        //表单令牌验证
        defined('_TOKEN_ON_') && self::$tokenOn = _TOKEN_ON_;
        defined('_TOKEN_NAME_') && self::$tokenName = _TOKEN_NAME_;

        isset($dbConfig['dbFieldtypeCheck']) && self::$dbFieldtypeCheck = $dbConfig['dbFieldtypeCheck'];

        // 设置表前缀
        $modelName = get_class($this);
        $refc = new ReflectionClass($modelName);
        $tpprop = $refc->getProperty('_tablePrefix');
        $decl = $tpprop->getDeclaringClass();
        // if ($decl->name == $modelName) { // linux only
        if ($tpprop->class == $modelName) { // linux && window
            // 这种情况以扩展model优先，不需要把config/db.php中的设置覆盖过来
        } else {
            $this->_tablePrefix = $this->_tablePrefix?$this->_tablePrefix:@$dbConfig['tablePrefix'];
            $this->_tableSuffix = $this->_tableSuffix?$this->_tableSuffix:@$dbConfig['tableSuffix'];
        }


        //初始化数据库名
        if ('' == $this->_dbName) {
            if (isset($dbConfig['write']['dbname'])) {
                $this->_dbName = $dbConfig['write']['dbname'];
            } else {
                isset($dbConfig['dbname']) &&  $this->_dbName = $dbConfig['dbname'];
            }
        }

        $this->_dbConfig = $dbConfig;
        unset($dbConfig);

        // 字段检测
        if(!empty($this->_name) && $this->_autoCheckFields) {
            $this->_checkTableInfo();
        }
    }

    /**
     * 自动检测数据表信息（接口保留兼容现有应用）
     * @access protected
     * @return voidf
     */
    protected function _checkTableInfo()
    {
        $this->_getTableInfo();
    }

    /**
     *
     * 获取字段信息并缓存（接口保留兼容现有应用）
     * @param  $updateFile  是否更新表结构缓存文件
     * @return void
     */
    public function flush($updateFile = true)
    {
        $this->_getTableInfo($updateFile);
    }

    /**
     * 获取字段信息并缓存
     */
    private function _getTableInfo($flush=false)
    {
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->_tableName);
        $this->_dao->setPk($this->_pk);
        $this->_dao->checkTableInfo($flush);
        $this->_fields = $this->_dao->getFieldInfo();
        $this->_pk = $this->_dao->getPk();
    }

    /**
     * 动态切换扩展模型
     *
     * @param string $type 模型类型名称
     * @param mixed $vars 要传入扩展模型的属性变量
     *
     * @return Model
     *
     */
    public function switchModel($type,$vars=array())
    {
        $class = ucwords(strtolower($type)).'Model';
        if(!class_exists($class))
            throw new Leb_Exception($class . ' Model 不存在');
        // 实例化扩展模型
        $this->_extModel   = new $class($this->_name);
        if(!empty($vars)) {
            // 传入当前模型的属性到扩展模型
            foreach ($vars as $var)
                $this->_extModel->setProperty($var,$this->$var);
        }
        return $this->_extModel;
    }

    /**
      * 加载Model
      * @param string $modelName
      * @param array  $dbconfig
      * @param string $application
      * @return Leb_Model
      */
     public function loadModel($modelName, $dbConfig = array(), $application='')
     {
         return Leb_Helper::loadModel($modelName, $dbConfig, $application);
     }

    /**
     * 设置数据对象的值
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     *
     */
    public function __set($name,$value)
    {
        // 设置数据对象属性
        $this->_data[$name]  =   $value;
    }

    /**
     * 获取数据对象的值
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->_data[$name])?$this->_data[$name]:null;
    }

    /**
     * 检测数据对象的值
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    /**
     * 销毁数据对象的值
     * @param string $name 名称
     * @return void
     *
     */
    public function __unset($name)
    {
        unset($this->_data[$name]);
    }

    /**
     * 设置表名
     * @param string $name 名称
     * @return Leb_Model
     */
    public function setTable($tableName)
    {
        $this->_trueTableName = '';
        $this->_name = $tableName;
        $this->_tableName = $tableName;
        $this->flush(false);
    }

    /**
     *
     * 利用__call方法实现一些特殊的Model方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     *
     */
    public function __call($method,$args)
    {
        if(in_array(strtolower($method),array('field','table','where',
                                              'order','limit','page',
                                              'having','group','lock',
                                              'distinct'),
                                        true)) {
            // 连贯操作的实现
            $this->_options[strtolower($method)] =   $args[0];
            return $this;
        }elseif(in_array(strtolower($method),array('count','sum','min',
                                                   'max','avg'),
                                             true)){
            // 统计查询的实现
            $field =  isset($args[0])?$args[0]:'*';
            return $this->getField(strtoupper($method).'('.$field.') AS tp_'.$method);
        }elseif(strtolower(substr($method,0,5)) == 'getby') {
            // 根据某个字段获取记录
            $field  = parse_name(substr($method,5));
            $options['where'] = $field.'=\''.$args[0].'\'';
            return $this->find($options);
        }else{
            throw new Leb_Exception(__CLASS__ . ':' . $method . '方法不存在');
            return;
        }
    }

    // 回调方法 初始化模型
    protected function _initialize() {}

    /**
     * 对保存到数据库的数据进行处理
     *
     * @param mixed $data 要操作的数据
     * @return boolean
     */
     protected function _facade($data)
     {
        // 检查非数据字段
        if(!empty($this->_fields)) {
            foreach ($data as $key=>$val){
                if(!in_array($key,$this->_fields,true)){
                    unset($data[$key]);
                }elseif(self::$dbFieldtypeCheck && is_scalar($val)) {
                    // 字段类型检查
                    $fieldType = strtolower($this->_fields['_type'][$key]);
                    if (false !== strpos($fieldType, 'bigint')) {
                        $max_int64 = "9223372036854775807";
                        $max_int32 = "2147483647";
                        // fix unsigned bigint field
                        if (intval($max_int64) == $max_int64 &&
                            (strlen($val) <= strlen($max_int64) && strcmp($val, $max_int64) <= 0)) {
                            $data[$key]   =  intval($val);
                        } else {
                            $data[$key]   =  $val;
                        }
                    } else if(false !== strpos($fieldType,'int')) {
                        $data[$key]   =  intval($val);
                    }elseif(false !== strpos($fieldType,'float')
                            || false !== strpos($fieldType,'double')){
                        $data[$key]   =  floatval($val);
                    }
                }
            }
        }
        $this->_before_write($data);
        return $data;
     }

    // 写入数据前的回调方法 包括新增和更新
    protected function _before_write(&$data) {}

    /**
     *
     * 新增数据
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return mixed
     */
    public function add($data='',$options=array())
    {
        if(empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if(!empty($this->_data)) {
                $data = $this->_data;
            }else{
                $this->_error = '添加记录类型必须为对象或数组';
                return false;
            }
        }

        // 对于使用全局序列的情况，自动生成
        if ($this->_globalIdMode && !isset($data[$this->_pk])) {
            $pkid = $this->makeSerialId($this->_virtShardId);
            $data[$this->_pk] = $pkid;
        }

        // 分析表达式
        $options =  $this->_parseOptions($options);
        //保存完整数据,为满足index_mem_data结构，提供完整数据
        //以后在抽象出abstract时候会做修改
        $this->_dao->setData($data);
        // 数据处理
        $data = $this->_facade($data);
        if(false === $this->_before_insert($data,$options)) {
            return false;
        }

        //回写模型信息到dao,为cache存储提供支持
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->getTableName());
        $this->_dao->setPk($this->_pk);
        // 写入数据到数据库
        $result = $this->_dao->daoInsert($data, $options, $this->_daoType);


        if(false !== $result ) {
            $insertId = $this->getLastInsID();

            if($insertId) {
                // 自增主键返回插入ID
                $data[$this->getPk()]  = $insertId;
                $this->_after_insert($data,$options);

                return $insertId;
            }
        }
        return $result;
    }

    // 插入数据前的回调方法
    protected function _before_insert(&$data,$options) {}

    // 插入成功后的回调方法
    protected function _after_insert($data,$options) {}

    /**
     * 通过Select方式添加记录
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $options 表达式
     * @return boolean
     *
     */
    public function selectAdd($fields='',$table='',$options=array())
    {
        // 分析表达式
        $options =  $this->_parseOptions($options);
        // 写入数据到数据库
        if(false === $result = $this->_dao->selectInsert($fields?$fields:$options['field'],
                                                        $table?$table:$this->getTableName(),
                                                        $options)){
            // 数据库插入操作失败
            $this->_error = 'SELECT方式插入记录失败';
            return false;
        }else {
            // 插入成功
            return $result;
        }
    }

    /**
     *
     * 保存数据
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return boolean
     */
    public function save($data='',$options=array())
    {
        if(empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if(!empty($this->_data)) {
                $data = $this->_data;
            }else{
                $this->_error = '保存的数据类型错误';
                return false;
            }
        }

        //保存完整数据,为满足index_mem_data结构，提供完整数据
        //以后在抽象出abstract时候会做修改
        $this->_dao->setData($data);
        // 数据处理
        $data = $this->_facade($data);

        // 分析表达式
        $options =  $this->_parseOptions($options);
        if(false === $this->_before_update($data,$options)) {
            return false;
        }

        if(!isset($options['where']) ) {
            $pks = $this->getPk(true);
            if(!$pks){
                $this->_error = '没有更新条件';
                return false;
            }

            $options['where'] = ' 1 ';
            foreach($pks as $pk => $v){
                if(isset($data[$pk])) {
                    $options['where'] .= " and `{$pk}`='{$data[$pk]}'";
                    $pkValue = $data[$pk];
                    unset($data[$pk]);
                }else{
                    // 如果没有任何更新条件则不执行
                    $this->_error = '没有更新条件';
                    return false;
                }
            }

            if(empty($data)){
                $this->_error = '无更新数据';
                return false;
            }
        }

        //回写模型信息到dao,为cache存储提供支持
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->getTableName());
        $this->_dao->setPk($this->_pk);

        $result = $this->_dao->daoUpdate($data,$options,  $this->_daoType);

        if(false !== $result) {
            if(isset($pkValue)) $data[$pk]   =  $pkValue;
            $this->_after_update($data,$options);
        }
        return $result;
    }

    // 更新数据前的回调方法
    protected function _before_update(&$data,$options) {}

    // 更新成功后的回调方法
    protected function _after_update($data,$options) {}

    /**
     *
     * 删除数据
     *
     * @param mixed $options 表达式
     * @return mixed
     *
     */
    public function delete($options=array())
    {
        $pks = $this->getPk(true);
        $pkc = count($pks);
        if(empty($options) && empty($this->_options)) {
            // 如果删除条件为空 则删除当前数据对象所对应的记录
            if(empty($this->_data) || empty($pks)){
                return false;
            }

            foreach($pks as $pk => $v)
            {
                if(!isset($this->_data[$pk]))
                {
                    return false;
                }
                else
                {
                    $pks[$pk] = $this->_data[$pk];
                }
            }

            if($pkc > 1){
                return $this->delete($pks);
            }else{
                return $this->delete($pks[$this->_pk]);
            }
        }

        if(1 == $pkc && (is_numeric($options) || is_string($options))) {
            // 根据主键删除记录
            $pk   =  $this->getPk();
            if(strpos($options,',')) {
                $where = "`{$pk}` IN (".$options.')';
            }else{
                $where = "`{$pk}`='{$options}'";
                $pkValue = $options;
            }
            $options = array();
            $options['where'] = $where;
        }elseif(is_array($options) && $pkc > 1){
            $where = " 1 ";
            foreach($options as $k => $v)
            {
                $where .= " and `{$k}`='{$v}'";
            }

            $options =  array();
            $options['where'] =  $where;
        }

        //防止整表数据误删除
        if(!isset($this->_options['where']) && !isset($options['where'])) {
            return false;
        }

        // 分析表达式
        $options =  $this->_parseOptions($options);

        //回写模型信息到dao,为cache存储提供支持
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->getTableName());
        $this->_dao->setPk($this->_pk);

        $result = $this->_dao->daoDelete($options,  $this->_daoType);
        if(false !== $result) {
            $data = array();
            if(isset($pkValue)) $data[$pk]   =  $pkValue;
            $this->_after_delete($data,$options);
        }
        // 返回删除记录个数
        return $result;
    }

    // 删除成功后的回调方法
    protected function _after_delete($data,$options) {}

    /**
     * 查询数据集
     *
     * @param array $options 表达式参数
     *
     * @return mixed
     */
    public function select($options=array())
    {
        if(is_string($options) || is_numeric($options)) {
            // 根据主键查询
            $where   =  $this->getPk().' IN ('.$options.')';
            $options =  array();
            $options['where'] =  $where;
        }
        // 分析表达式
        $options =  $this->_parseOptions($options);



        //回写模型信息到dao,为cache存储提供支持
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->getTableName());
        $this->_dao->setPk($this->_pk);

        //启用dao查询
        $resultSet = $this->_dao->daoSelect($options,  $this->_daoType);


        if(false === $resultSet) {
            return false;
        }
        if(empty($resultSet)) { // 查询结果为空
            return array();
        }
        $this->_after_select($resultSet,$options);
        return $resultSet;
    }

    // 查询成功后的回调方法
    protected function _after_select(&$resultSet,$options) {}

    public function findAll($options=array()) {
        return $this->select($options);
    }

    /**
     * 分析表达式
     *
     * @access private
     *
     * @param array $options 表达式参数
     *
     * @return array
     */
    private function _parseOptions($options)
    {
        if(is_array($options)) {
            $options =  array_merge($this->_options,$options);
        }
        // 查询过后清空sql表达式组装 避免影响下次查询
        $this->_options  =   array();
        if(!isset($options['table'])) {
            // 自动获取表名
            $options['table'] = $this->getTableName();
        }
        // 字段类型验证
        if(self::$dbFieldtypeCheck) {
            if(isset($options['where']) && is_array($options['where'])) {
                // 对数组查询条件进行字段类型检查
                foreach ($options['where'] as $key=>$val){
                    if(in_array($key,$this->_fields,true) && is_scalar($val)){
                        $fieldType = strtolower($this->_fields['_type'][$key]);
                        if (false !== strpos($fieldType, 'bigint')) {
                            $max_int64 = "9223372036854775807";
                            $max_int32 = "2147483647";
                            // fix unsigned bigint field
                            if (intval($max_int64) == $max_int64
                                && (strlen($val) <= strlen($max_int64) && strcmp($val, $max_int64) <= 0)) {
                                $options['where'][$key]   =  intval($val);
                            } else {
                                $options['where'][$key]   =  $val;
                            }
                        } else if(false !== strpos($fieldType,'int')) {
                            $options['where'][$key]   =  intval($val);
                        }elseif(false !== strpos($fieldType,'float')
                                || false !== strpos($fieldType,'double')){
                            $options['where'][$key]   =  floatval($val);
                        }
                    }
                }
            }
        }
        // 表达式过滤
        $this->_options_filter($options);
        return $options;
    }

    // 表达式过滤回调方法
    protected function _options_filter(&$options) {}

    /**
     *
     * 查询数据
     * @param mixed $options 表达式参数
     * @return mixed
     *
     */
    public function find($options=array())
    {
        if(is_numeric($options) || is_string($options)) {
            $where   = "`{$this->getPk()}`='{$options}'";
            $options = array();
            $options['where'] = $where;
        }

        // 总是查找一条记录
        $options['limit'] = 1;

        // 分析表达式
        $options =  $this->_parseOptions($options);

        //回写模型信息到dao,为cache存储提供支持
        $this->_dao->setDbName($this->_dbName);
        $this->_dao->setTableName($this->getTableName());
        $this->_dao->setPk($this->_pk);

        $resultSet = $this->_dao->daoSelect($options,  $this->_daoType);
        if(false === $resultSet) {
            return false;
        }
        if(empty($resultSet)) {// 查询结果为空
            return array();
        }
        $this->_data = $resultSet[0];
        $this->_after_find($this->_data,$options);
        return $this->_data;
     }

     // 查询成功的回调方法
     protected function _after_find(&$result,$options) {}

    /**
     *
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     *

     *
     * @param string|array $field  字段名
     * @param string|array $value  字段值
     * @param mixed $condition  条件
     *
     * @return boolean
     *
     */
    public function setField($field,$value,$condition='')
    {
        if(empty($condition) && isset($this->_options['where']))
            $condition = $this->_options['where'];
        $options['where'] = $condition;
        if(is_array($field)) {
            foreach ($field as $key=>$val)
                $data[$val] = $value[$key];
        }else{
            $data[$field] = $value;
        }
        return $this->save($data,$options);
    }

    /**
     *
     * 字段值增长
     *
     * @param string $field  字段名
     * @param mixed $condition  条件
     * @param integer $step  增长值
     *
     * @return boolean
     *
     */
    public function setInc($field,$condition='',$step=1)
    {
        return $this->setField($field,array('exp',$field.'+'.$step),$condition);
    }

    /**
     *
     * 字段值减少
     *
     * @param string $field  字段名
     * @param mixed $condition  条件
     * @param integer $step  减少值
     *
     * @return boolean
     *
     */
    public function setDec($field,$condition='',$step=1)
    {
        return $this->setField($field,array('exp',$field.'-'.$step),$condition);
    }

    /**
     *
     * 获取一条记录的某个字段值
     *
     * @param string $field  字段名
     * @param mixed $condition  查询条件
     * @param string $spea  字段数据间隔符号
     *
     * @return mixed
     *
     */
    public function getField($field,$condition='',$sepa=' ')
    {
        if(empty($condition) && isset($this->_options['where'])) {
            $condition = $this->_options['where'];
        }
        $options['where'] = $condition;
        $options['field'] = $field;
        $options =  $this->_parseOptions($options);
        if(strpos($field,',')) { // 多字段
            $resultSet = $this->_dao->select($options);
            if(!empty($resultSet)) {
                $field = explode(',',$field);
                $key = array_shift($field);
                $cols = array();
                foreach ($resultSet as $result){
                    $name = $result[$key];
                    $cols[$name] = '';
                    foreach ($field as $val) {
                        $cols[$name] .= $result[$val].$sepa;
                    }
                    $cols[$name] = substr($cols[$name],0,-strlen($sepa));
                }
                return $cols;
            }
        }else{   // 查找一条记录
            $options['limit'] = 1;
            $result = $this->_dao->select($options);
            if(!empty($result)) {
                return reset($result[0]);
            }
        }
        return false;
    }

    /**
     *
     * 创建数据对象 但不保存到数据库
     *
     * @param mixed $data 创建数据
     * @param string $type 状态
     *
     * @return mixed
     *
     */
     public function create($data='',$type='')
     {
        // 如果没有传值默认取POST数据
        if(empty($data)) {
            $data = $_POST;
        }elseif(is_object($data)){
            $data = get_object_vars($data);
        }elseif(!is_array($data)){
            $this->_error = '创建记录时，传入的数据类型必须是对象或数组';
            return false;
        }
        // 状态
        $type = $type?$type:(!empty($data[$this->getPk()])
                             ?self::MODEL_UPDATE:self::MODEL_INSERT);

        // 表单令牌验证
        if(self::$tokenOn && !$this->autoCheckToken($data)) {
            $this->_error = '表单自动验证令牌错误';
            return false;
        }
        // 数据自动验证
        if(!$this->autoValidation($data,$type)) return false;

        // 检查字段映射
        if(!empty($this->_map)) {
            foreach ($this->_map as $key=>$val){
                if(isset($data[$key])) {
                    $data[$val] =   $data[$key];
                    unset($data[$key]);
                }
            }
        }
        // 验证完成生成数据对象
        $vo = array();
        foreach ($this->_fields as $key=>$name){
            if(substr($key,0,1) == '_') continue;
            $val = isset($data[$name])?$data[$name]:null;
            //保证赋值有效
            if(!is_null($val)){
                $vo[$name] = ((_MAGIC_QUOTES_GPC_ && is_string($val)))
                             ?stripslashes($val):$val;
            }
        }
        // 创建完成对数据进行自动处理
        $this->autoOperation($vo,$type);
        // 赋值当前数据对象
        $this->_data =   $vo;
        // 返回创建的数据以供其他调用
        return $vo;
     }

    // 自动表单令牌验证
    public function autoCheckToken($data)
    {
        $name   = self::$tokenName;
        if(isset($_SESSION[$name])) {
            //当前需要令牌验证
            if(empty($data[$name]) || $_SESSION[$name] != $data[$name]) {
                //非法提交
                return false;
            }
            //验证完成销毁session
            unset($_SESSION[$name]);
        }
        return true;
    }

    /**
     *
     * 使用正则验证数据
     *
     * @param string $value  要验证的数据
     * @param string $rule 验证规则
     *
     * @return boolean
     *
     */
    public function regex($value,$rule)
    {
        $validate = array(
            'require'=> '/.+/',
            'email' => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url' => '/^http:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"\"])*$/',
            'currency' => '/^\d+(\.\d+)?$/',
            'number' => '/\d+$/',
            'zip' => '/^[1-9]\d{5}$/',
            'integer' => '/^[-\+]?\d+$/',
            'double' => '/^[-\+]?\d+(\.\d+)?$/',
            'english' => '/^[A-Za-z]+$/',
        );
        // 检查是否有内置的正则表达式
        if(isset($validate[strtolower($rule)]))
            $rule = $validate[strtolower($rule)];
        return preg_match($rule,$value)===1;
    }

    /**
     *
     * 自动表单处理
     *
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return mixed
     *
     */
    private function autoOperation(&$data,$type)
    {
        // 自动填充
        if(!empty($this->_auto)) {
            foreach ($this->_auto as $auto) {
                // 填充因子定义格式
                // array('field','填充内容','填充条件','附加规则',[额外参数])
                if(empty($auto[2])) $auto[2] = self::MODEL_INSERT; // 默认为新增的时候自动填充
                if( $type == $auto[2] || $auto[2] == self::MODEL_BOTH) {
                    switch($auto[3]) {
                        case 'function':    //  使用函数进行填充 字段的值作为参数
                        case 'callback': // 使用回调方法
                            $args = isset($auto[4])?$auto[4]:array();
                            if(isset($data[$auto[0]])) {
                                array_unshift($args,$data[$auto[0]]);
                            }
                            if('function'==$auto[3]) {
                                $data[$auto[0]]  = call_user_func_array($auto[1], $args);
                            }else{
                                $data[$auto[0]]  =  call_user_func_array(array(&$this,$auto[1]), $args);
                            }
                            break;
                        case 'field':    // 用其它字段的值进行填充
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'string':
                        default: // 默认作为字符串填充
                            $data[$auto[0]] = $auto[1];
                    }
                    if(false === $data[$auto[0]] )   unset($data[$auto[0]]);
                }
            }
        }
        return $data;
    }

    /**
     * 自动表单验证
     *
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return boolean
     *
     */
    private function autoValidation($data,$type)
    {
        // 属性验证
        if(!empty($this->_validate)) {
            // 如果设置了数据自动验证
            // 则进行数据验证
            // 重置验证错误信息
            foreach($this->_validate as $key=>$val) {
                // 验证因子定义格式
                // array(field,rule,message,condition,type,when,params)
                // 判断是否需要执行验证

                if(empty($val[5]) || $val[5]== self::MODEL_BOTH || $val[5]== $type ) {
                    /*if(0 == strpos($val[2],'{%') && strpos($val[2],'}')){
                        // 支持提示信息的多语言 使用 {%语言定义} 方式
                        $val[2]  =  substr($val[2],2,-1);
                    }*/

                    $val[3] = isset($val[3])?$val[3]:self::EXISTS_VAILIDATE;
                    $val[4] = isset($val[4])?$val[4]:'regex';

                    // 判断验证条件
                    switch($val[3]) {
                        case self::MUST_VALIDATE:   // 必须验证 不管表单是否有设置该字段
                            if(false === $this->_validationField($data,$val)){
                                $this->_error = $val[2];
                                return false;
                            }
                            break;
                        case self::VALUE_VAILIDATE:    // 值不为空的时候才验证
                            if(isset($data[$val[0]]) && '' != trim($data[$val[0]])){
                                if(false === $this->_validationField($data,$val)){
                                    $this->_error = $val[2];
                                    return false;
                                }
                            }
                            break;
                        default:    // 默认表单存在该字段就验证
                            if(isset($data[$val[0]])){
                                if(false === $this->_validationField($data,$val)){
                                    $this->_error    =   $val[2];
                                    return false;
                                }
                            }
                    }
                }
            }
        }
        return true;
    }

    /**
     *
     * 根据验证因子验证字段
     *
     * @param array $data 创建数据
     * @param string $val 验证规则
     * @return boolean
     *
     */
    private function _validationField($data,$val)
    {
        switch($val[4]) {
            case 'function':// 使用函数进行验证
            case 'callback':// 调用方法进行验证
                $args = isset($val[6])?$val[6]:array();
                array_unshift($args,$data[$val[0]]);
                if('function' == $val[4]) {
                    return call_user_func_array($val[1], $args);
                }else{
                    return call_user_func_array(array(&$this, $val[1]), $args);
                }
            case 'confirm': // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            case 'in': // 验证是否在某个数组范围之内
                return in_array($data[$val[0]] ,$val[1]);
            case 'equal': // 验证是否等于某个值
                return $data[$val[0]] == $val[1];
            case 'unique': // 验证某个值是否唯一
                if(is_string($val[0]) && strpos($val[0],',')) {
                    $val[0]  =  explode(',',$val[0]);
                }
                $map = array();
                if(is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field) {
                        $map[$field]   =  $data[$field];
                    }
                }else{
                    $map[$val[0]] = $data[$val[0]];
                }
                if($this->where($map)->find())
                    return false;
                break;
            case 'regex':
            default:    // 默认使用正则验证 可以使用验证类中定义的验证名称
                // 检查附加规则
                return $this->regex($data[$val[0]],$val[1]);
        }
        return true;
    }

    /**
     *
     * SQL查询
     *
     * @param mixed $sql  SQL指令
     * @param  string $type   返回数据类型，默认为带下标的二数组
     * @return mixed
     *
     */
    public function query($sql, $type = 'assoc')
    {
        if($this->_daoType){
            //回写模型信息到dao,为cache存储提供支持
            $this->_dao->setDbName($this->_dbName);
            $this->_dao->setTableName($this->getTableName());
            $this->_dao->setPk($this->_pk);
            $sql = strtolower($sql);
            $pattern = "/^\s*(.*?)\s+(count)*.*$/";
            $result = array();
            if(!empty($sql)) {
                if(strpos($sql,'__TABLE__')) {
                    $sql = str_replace('__TABLE__',$this->getTableName(),$sql);
                }
                if(preg_match($pattern, $sql,$option)){
                    if(isset($option[1]) && $option[1] == 'select' && isset($option[2]) && $option[2] == 'count'){
                        $result = $this->_dao->query($sql, $type);
                    }elseif(isset($option[1]) && $option[1] == 'select'){
                        $options = $this->separateSql($sql);
                        $options['table'] = $this->getTableName();
                        $result  = $this->_dao->daoSelect($options,  $this->_daoType);
                    }else{
                        $result = $this->_dao->query($sql, $type);
                    }
                } else {
                    $result = $this->_dao->query($sql, $type);
                }
                return $result;

            }else{
                return false;
            }
        }else{
            return $this->_dao->query($sql, $type);
        }
    }

    /**
     *
     * SQL查询
     *
     * 不对sql做任何修改，直接执行．使用时要非常注意．
     *
     * @param mixed $sql  SQL指令
     * @param  string $type   返回数据类型，默认为带下标的二数组
     * @return mixed
     *
     */
    public function rawQuery($sql, $type = 'assoc')
    {
        return $this->_dao->query($sql, $type);
    }

    /**
     *
     * 执行SQL语句
     *
     * @param string $sql  SQL指令
     *
     * @return false | integer
     *
     */
    public function execute($sql)
    {
        if($this->_daoType){
            //回写模型信息到dao,为cache存储提供支持
            $this->_dao->setDbName($this->_dbName);
            $this->_dao->setTableName($this->getTableName());
            $this->_dao->setPk($this->_pk);
            $sql = strtolower($sql);
            $pattern = "/^\s*(.*?)\s+.*$/";
            $result = "";
            if(!empty($sql)) {
                if(strpos($sql,'__TABLE__')){
                    $sql = str_replace('__TABLE__',$this->getTableName(),$sql);
                }
                if(preg_match($pattern, $sql,$option)){
                    switch($option[1]){
                        case 'insert':
                            $options = $this->separateSql($sql);
                            $options['table'] = $this->getTableName();
                            $data    = $this->getInsertData($sql);
                            $result  = $this->_dao->daoInsert($data,$options,  $this->_daoType);
                            break;
                        case 'update':
                            $options = $this->separateSql($sql);
                            $options['table'] = $this->getTableName();
                            $data    = $this->getUpdateData($sql);
                            $result  = $this->_dao->daoUpdate($data,$options,$this->_daoType);
                            break;
                        case 'delete':
                            $options = $this->separateSql($sql);
                            $options['table'] = $this->getTableName();
                            $result  = $this->_dao->daoDelete($options,$this->_daoType);
                            break;
                        default:
                            $result = $this->_dao->execute($sql);

                    }
                }
                return $result;

            }else {
                return false;
            }
        }else{
            if(!empty($sql)){
                return $this->_dao->execute($sql);
            }else{
                return false;
            }
        }

    }

    /**
     *
     * 得到当前的数据对象名称
     *
     * @return string
     *
     */
    public function getModelName()
    {
        if(empty($this->_name)) {
            $this->_name = get_class($this);
        }
        return $this->_name;
    }

    /**
     *
     * 得到完整的数据表名
     *
     * @return string
     *
     */
    public function getTableName()
    {
        if(empty($this->_trueTableName)) {
            $tableName  = !empty($this->_tablePrefix) ? $this->_tablePrefix : '';
            if(!empty($this->_tableName)) {
                $tableName .= $this->_tableName;
            }else{
                $tableName .= parse_name($this->_name);
            }
            $tableName .= !empty($this->_tableSuffix) ? $this->_tableSuffix : '';

            $this->_trueTableName = strtolower($tableName);

        }
        return $this->_trueTableName;
    }

    /**
     *
     * 启动事务
     *
     * @return void
     */
    public function startTrans()
    {
        $this->commit();
        $this->_dao->startTrans();
        return ;
    }

    /**
     *
     * 提交事务
     *
     * @return boolean
     *
     */
    public function commit()
    {
        return $this->_dao->commit();
    }

    /**
     *
     * 事务回滚
     *
     * @return boolean
     *
     */
    public function rollback()
    {
        return $this->_dao->rollback();
    }

    /**
     *
     * 返回模型的错误信息
     *
     * @return string
     *
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     *
     * 返回数据库的错误信息
     *
     * @return string
     *
     */
    public function getDbError()
    {
        return $this->_dao->getError();
    }

    /**
     *
     * 返回最后插入的ID
     *
     * @return string
     *
     */
    public function getLastInsID()
    {
        return $this->_dao->lastInsID;
    }

    /**
     *
     * 返回最后执行的sql语句
     *
     * @return string
     *
     */
    public function getLastSql()
    {
        return $this->_dao->getLastSql();
    }

    /**
     *
     * 获取主键名称
     *
     * @return string
     *
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
     *
     * 获取数据表字段信息
     *
     * @return array
     *
     */
    public function getDbFields()
    {
        return $this->_fields;
    }

    /**
     *
     * 设置数据对象值
     *
     * @param mixed $data 数据
     * @return Model
     *
     */
    public function data($data)
    {
        if(is_object($data)){
            $data = get_object_vars($data);
        }elseif(!is_array($data)){
            throw new Leb_Exception('数据对象错误');
        }
        $this->_data = $data;
        return $this;
    }

    /**
     *
     * 查询SQL组装 join
     *
     * @param mixed $join
     * @return Model
     *
     */
    public function join($join)
    {
        if(is_array($join))
            $this->_options['join'] =  $join;
        else
            $this->_options['join'][]  =   $join;
        return $this;
    }

    /**
     *
     * 设置模型的属性值
     *
     * @param string $name 名称
     * @param mixed $value 值
     * @return Model
     *
     */
    public function setProperty($name,$value)
    {
        if(property_exists($this,$name))
            $this->$name = $value;
        return $this;
    }

    /**
     * 修改一个字段
     *
     * @param string $table_name
     * @param string $field_name
     * @param string $new_name
     * @param string $type
     * @param string $length
     * @param string $default
     * @param string $allow_null
     * @param string $char_set
     * @return Boolean
     */
    public function alterTableField($table_name, $field_name, $new_name, $type, $length, $default, $allow_null, $char_set = 'utf8')
    {
        $sql = "ALTER TABLE `{$table_name}` ";
        $sql .= ($field_name != $new_name) ? " CHANGE `{$field_name}` `{$new_name}` " : "";
        $sql .= (!empty($type)) ? " {$teyp} " : "";//类型
        $sql .=  $length ? "({$length})" : "";//长度
        $sql .= " CHARACTER {$char_set} ";//编码
        $sql .= ($allow_null) ? " NULL " : " NOT NULL ";//是否允许为空
        $sql .= " DEFAULT '{$default}' ";
        //$sql .= !empty($default) ? " DEFAULT '{$default}' " : " DEFAULT '' ";//默认值
        $this->flush();
        return $this->execute($sql);
    }

    /**
     * 往表中增加一个字段
     *
     * @param string $table_name
     * @param string $field_name
     * @param string $type
     * @param string $length
     * @param string $allow_null
     * @param string $default
     * @return Boolean
     */
    public function addTableField($table_name, $field_name, $type, $length, $allow_null = ' NULL ', $default = '', $if_index = 0,$comment="")
    {
        //先判断字段是否已经存在，若存在，则返回true

        //添加字段
        $sql = "ALTER TABLE `{$table_name}` ADD `{$field_name}` ";
        $sql .= (!empty($type)) ? " {$type} " : "";//类型
        $sql .=  $length ? "({$length})" : "";//长度
        $sql .= ($allow_null) ? " NULL " : " NOT NULL ";//是否允许为空
        $sql .= ("" !== $default) ? " DEFAULT '{$default}' " : "";//默认值
        $sql .= ("" !== $comment) ? "COMMENT '{$comment}' "  : "";//字段说明
        $index_name = $field_name . time();
        $sql .= ($if_index > 0) ? " ,ADD INDEX ( `{$field_name}` ) " : "";//是否添加索引
        $this->flush();
        return $this->execute($sql);
    }

    /**
     * 往一个表中增加索引
     * @param <type> $table_name
     * @param <type> $index_name
     * @param <type> $type
     * @param <type> $field
     */
    public function addTableIndex($table_name,$index_name,$type,$field)
    {
        $sql = "ALTER TABLE `{$table_name}` ADD ".$type." $index_name($field) ";
        return $this->execute($sql);

    }

    /**
     * 判断一个表是否在在
     *
     * @param string $dbName
     * @param string $tableName
     */
    public function ifTableExist($dbName, $tableName)
    {
        $sql = "select `TABLE_NAME` from `INFORMATION_SCHEMA`.`TABLES` where `TABLE_SCHEMA`='{$dbName}' and `TABLE_NAME`='{$tableName}' ";
        $result = $this->query($sql);
        return count($result);
    }

    /**
     * 多语言转义函数
     * @param <type> $lan   要转义的字符串
     */
    public function language($lan, $app = '')
    {
        $return = language('model', $lan, $app ? $app : get_gvar('APPLICATION'));
        //language($module, $lan, $app, $controller, $action);
        return $return;
    }

    /**
     * 分解SQL
     * @param string $sql   原生SQL
     * @return array        分解后SQL数组
     */
    private function separateSql($sql)
    {
        $result = array('field' => '',
                        'where' => '',
                        'order' => '',
                        'limit' => '10',
                        'isForce' => true
                       );
        $sql = trim($sql);
        $endChar = substr($sql, strlen($sql) - 1);
        if ($endChar == ';') {
            $result['isEnd'] = false;
            $sql = substr($sql, 0, strlen($sql) - 1);
        }

        $result['field'] = $this->getFields($sql);
        $result['where'] = $this->getWhere($sql);
        //$group = $this->getGroup($sql);
        $result['order'] = $this->getOrder($sql);
        $result['limit'] = $this->getLimit($sql);

        return $result;
    }

    /**
     * 获取查询列
     * @param <type> $sql
     * @return <type>
     */
    private function getFields($sql)
    {
        $pattern = '/select\s+(.+)\s+from/Ui';
        preg_match($pattern, $sql, $match);
        return (isset ($match[1] )&& ($match[1] != '*'))? $match[1]: '';
    }

    /**
     * 获取where 条件
     * @param <type> $sql
     * @return <type>
     */
    private function getWhere($sql)
    {
        $pattern = '/where\s+(.+)((\s+(order|group|limit|;))|\s*$)/Ui';
        preg_match($pattern, $sql, $match);
        return isset ($match[1])? $match[1]: '';
    }

    /**
     *获取group条件
     * @param <type> $sql
     * @return <type>
     */
    private function getGroup($sql)
    {
        $pattern = '/group\s+by\s+(.+)((\s+(order|limit))|\s*$)/Ui';
        preg_match($pattern, $sql, $match);
        return isset ($match[1])? $match[1]: '';
    }

    /**
     *获取order条件
     * @param <type> $sql
     * @return <type>
     */
    private function getOrder($sql)
    {
        $pattern = '/order\s+by\s+(.+)((\s+(limit))|\s*$)/Ui';
        preg_match($pattern, $sql, $match);
        return isset ($match[1])? $match[1]: '';
    }

    /**
     *获取limit 条件
     * @param <type> $sql
     * @return <type>
     */
    private function getLimit($sql)
    {
        $pattern = '/limit\s+(.+)\s*$/Ui';
        preg_match($pattern, $sql, $match);
        return isset ($match[1])? $match[1]: '';
    }

    /**
     * 获取update更新的数据
     * @param <type> $sql
     * @return <type>
     */
    private function getUpdateData($sql)
    {
        $parttern = '/set(.+?)(where|$)/Ui';
        if(!preg_match($parttern,$sql,$match)){
            return false;
        }
        $dataStr= $match[1];
        $dataStrArr = explode(',',$dataStr);
        if(is_array($dataStrArr)){
            foreach($dataStrArr as $dsa){
                $dsa = explode('=',$dsa);
                $data[$dsa[0]]=$dsa[1];
            }
        }
        return isset($data)?$data:array();
    }

    /**
     *获取insert的数据
     * @param <type> $sql
     * @return <type>
     */
    private function getInsertData($sql)
    {
        $parttern = '/^\s*insert\s+into\s+\w+\(\s*(.+?)\s*\)\s+values\s*\(\s*(.+?)\s*\)\s*.*$/Ui';
        if(!preg_match($parttern,$sql,$match)){
            return false;
        }
        $fieldsStr = $match[1];
        $valuesStr = $match[2];
        $fieldsStrArr = explode(",",$fieldsStr);
        $valuesStrArr = explode(",",$valuesStr);
        for($i= 0;$i<count($fieldsStrArr);$i++){
            $fieldtmp = preg_replace("/\'|\"/", "", $fieldsStrArr[$i]);
            $valuetmp = preg_replace("/\'|\"/", "", $valuesStrArr[$i]);
            $data[$fieldtmp] = $valuetmp;
        }
        return isset($data)?$data:array();
    }

    /**
     *获取dao类型
     * @return bool 状态 true|false;
     */
    public function getDaoType()
    {
        return $this->_daoType;
    }

    /**
     *设置缓存状态
     * @param <type> $cacherable 缓存状态true|false;
     */
    public function setDaoType($daoType)
    {
        $this->_daoType = $daoType;
    }

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
}
