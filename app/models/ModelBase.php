<?php
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Resultset\Simple as Resultset;
use Phalcon\Db\Column as Column;

class ModelBase extends Model {
  public static $_tmpVar = [];

  public $blacklist    = null;//array('player_id', 'create_time', 'update_time', 'rowversion');
  public $condition    = '';
  public $desctype     = [];
  public static $_delaySocketSendFlag = false;//延迟发送标记  
  public static $_delaySocketSendData = [];//延迟发送的数据

  /**
   * 初始化
   */
  public function initialize(){
    //不验证not null，让mysql自己报错
    self::setup(['notNullValidations'=>false]);
  }

  /*
   * 自动获取/缓存静态数据
   * cacheName 缓存名
   * function 数据回调函数
   * timeout 缓存时长
   * db 缓存池名
   */
  public function cache($dbName=CACHEDB_STATIC, $key, $function, $timeout=null){
    $ret = Cache::dbByName($dbName)->hGetAll($key);
    if(!$ret){
      $ret = $function();
      if(is_array($ret)){
        Cache::dbByName($dbName)->hMset($key, $ret);
        if($timeout){
          Cache::dbByName($dbName)->setTimeout($key, $timeout);
        }
      }
    }
    return $ret;
  }

  
  /**
   * 字典表获取所有
   */
  public function dicGetAll(){
    $ret = $this->cache(CACHEDB_STATIC, get_class($this), function() {
      return $this->findList('id');
    });
    return $ret;
  }
  
  /**
   * 字典表获取指定主键
   * 
   * param <type> $id 
   * 
   * return <type>
   */
  public function dicGetOne($id) {
    $class = get_class($this);

    if(!isset(self::$_tmpVar[$class][$id])) {
      $d = Cache::dbByName(CACHEDB_STATIC)->hGet($class, $id);
      if($d){
        self::$_tmpVar[$class][$id] = $d;
      }
    }
    else{
      $d = self::$_tmpVar[$class][$id];
    }

    if(!$d){
      $this->dicGetAll();
      $d = Cache::dbByName(CACHEDB_STATIC)->hGet($class, $id);
    }
    return $d;
  }

  
  /**
   * 获取当前model的数据
   * 
   * param   int    $playerId 
   * param   bool   $forDataFlag: 给data包用，传回格式一定是find出来的格式，如果是findFirst，请在子类里覆盖实现
   * return  array  description
   */
  public function getByPlayerId($playerId, $forDataFlag=false){
    $modelClassName = get_class($this);
    $re = Cache::getPlayer($playerId, $modelClassName);
    if(!$re) {
      if($this->condition) {
        $re = self::find([$this->condition])->toArray();
        $this->condition = '';
      } 
      else {
        $re = self::find(["player_id={$playerId}"])->toArray();
      }
      $re = $this->adapter($re);
      Cache::setPlayer($playerId, $modelClassName, $re);
    }
    return filterFields($re, $forDataFlag, $this->blacklist);
  }
  

  /**
   * gtByPlayerId的适配器
   * 
   * case 1：时间 strtotime
   * case 2：numeric 2 int
   * 
   * 在其他地方覆写的getByPlayerId的方法，需要在内容存到redis前，包装下此方法
   * 
   * param  array $data 
   * param  bool  $findFirstFlag  如果是findFirst出来的数据，则设为true
   * param  array $callback        =
   *                         1 [['field'=>'111', 'fn'=>function(){}], ['field'=>'333', 'fn'=>function(){}]]
   *
   *                         2 如果是添加自定义字段就格式如下
   *                         $r = $this->adapter($r, false, [['split_flag'=>true,'field'=>'position', 'fn'=>function() use ($subDay){
   *                              return [['key'=>'sub_day', 'value'=>$subDay]];
   *                               }]]);
   *
   * return array $re
   */
  public function adapter($data, $findFirstFlag=false, Array $callback=[]){
    if($findFirstFlag) {
      $data = [$data];
    }

    $re = $this->getDescType();
    $re1 = $re2 = $re3 = [];
    foreach($re as $k=>$v) {
      $v = strtolower($v);
      if('timestamp'==$v) {
        $re1[] = $k;
      } 
      elseif(strpos($v, 'int')===0||strpos($v, 'bigint')===0||strpos($v, 'smallint')===0||strpos($v, 'tinyint')===0) {
        $re2[] = $k;
      } 
      elseif(strpos($v, 'float')===0){
        $re3[] = $k;
      }
    }

    foreach($data as &$d) {
      foreach($d as $k=>&$v) {
        if(in_array($k, $re1)) {//case 1: 将时间格式strtotime
          if('0000-00-00 00:00:00'==$v) {
            $v = 0;
          } 
          else {
            $v = strtotime($v);
          }
        }
        elseif(in_array($k, $re2)) {//case2 : 将int && bigint && ... 格式cast to int
          $v = intval($v);
        }
        elseif(in_array($k, $re3)) {//case3 : cast to float
          $v = floatval($v);
        }

        //当自定义处理函数时, 对符合条件的数据项, 执行自定义函数来格式化数据
        if(!empty($callback)) {
          foreach($callback as $vv) {
            if($k == $vv['field']) { 
              if(isset($vv['split_flag'])) {
                $re = $vv['fn']();
                foreach($re as $kv) {
                  $d[$kv['key']] = $kv['value'];
                }
              } 
              else {
                $v = $vv['fn']($v);
              }
            }
          }
        }
      }
    }

    unset($d, $v);
    if($findFirstFlag) {
      return $data[0];
    }
    return $data;
  }
  

  public function getDescType(){
    $resource = $this->getSource();
    $cacheName = 'DESCTYPE';
    $className = get_class($this);
    if(!isset($this->desctype[$resource])){
      $ret = Cache::dbByName(CACHEDB_STATIC)->hGet($cacheName, $resource);
      $this->desctype[$resource] = $ret;
    }
    else {
      $ret = $this->desctype[$resource];
    }

    if(!$ret){
      $re = $this->sqlGet('DESC `' . $resource . '`');
      $ret = Set::combine($re, '{n}.Field', '{n}.Type');
      Cache::dbByName(CACHEDB_STATIC)->hSet($cacheName, $resource, $ret);
      $this->desctype[$resource] = $ret;
    }
    return $ret;
  }

  //查找符合条件的项,并返回指定key的项,
  public function findList($key, $value=null, $conditions=array()){
    $ret = self::find($conditions)->toArray();
    if(!$ret) {
      return array();
    }
    $result = array();
    foreach($ret as $_r) {
      if($value){
        $result[$_r[$key]] = $_r[$value];
      }
      else{
        $result[$_r[$key]] = $_r;
      }
    }
    return $result;
  }
  
  //更新符合条件的数据
  //例如: saveAll(['show_time'=>$beginTime,'start_time'=>$beginTime,'end_time'=>$endTime], 'id='.$actConfigId))
  public function saveAll($data, $where=array()){
    $c = $this->getWriteConnection(); //Gets the connection used to write data to the model
    $tablename = $this->getSource(); //Returns table name mapped in the model

    if($c->updateAsDict($tablename, $data, $where)) {
      return $this->affectedRows();
    }
    else{
      return false;
    }
  }

    
  /**
   * 更新数据表
   *
   * 使用方法如下
   * ```php
   * $player->updateAll(['level'=>'level+1', 'uuid'=>"'pldream'"], ['server_id'=>3]);
   * $player->updateAll(['level'=>'level+1', 'uuid'=>"'pldream'"], ['server_id <'=>3]);
   * $player->updateAll(['level'=>'level+1', 'uuid'=>"'pldream'"], ['server_id'=>[3,4]]);
   * ```
   * 
   * param array $fields 更改的值
   * param array $conditions 条件
   * return int 返回mysql update影响的行数
   */
  public function updateAll(array $fields, array $conditions=[], $extra='') {
    $c = $this->getWriteConnection();
    $tableName = $this->getSource();
    $updateSql = '';
    foreach($fields as $k1=>$v1){
      $k1 = trim($k1);
      $v1 = str_replace('\\', '\\\\', trim($v1));//转义反斜杠
      $fieldArr[] = " `{$k1}` = " . $v1;
    }
    if(!$fieldArr) {
      return false;
    }
    $fieldStr = join(',', $fieldArr);

    $condArr = [];
    foreach($conditions as $k2=>$v2) {
      $k2 = trim($k2);
      $pos = strpos($k2, ' ');
      if($pos !== false) {//条件含>,<,>=,<=,like, not like 等
        $k2Arr[0] = substr($k2, 0, $pos);
        $k2Arr[1] = substr($k2, $pos+1, strlen($k2));
        $condArr[] = " `{$k2Arr[0]}` {$k2Arr[1]} {$v2}";
      } 
      else {
        if(is_array($v2)) {//value为array，用in条件
          $v2 = array_map("trim", $v2);
            $condArr[] = " `{$k2}` IN ('" .join("','", $v2) . "')";
        } 
        elseif($k2=='1'){//所有条件皆满足 e.g. ->updateAll(['exp'=>0], ['1'=>1]);
            $condArr[] = ' 1=1 ';
        } 
        else {//直接赋值
          $v2 = trim($v2);
          $condArr[] = " `{$k2}` = {$v2}";
        }
      }
    }

    $condStr = join(' AND ', $condArr);
    if(!$condStr) {
      $condStr = '1=1';
    }
    $updateSql = "UPDATE {$tableName} SET {$fieldStr} WHERE {$condStr} {$extra};";
    try {
      $ret = $c->execute($updateSql);
    } 
    catch (Exception $e) {
      debug('sqlerror:('.$updateSql.')');
      iquery('insert into player_common_log values (null, 0, "sqlerror", "'.iescape($updateSql).'", "'.date('Y-m-d H:i:s').'")', true);
      throw new Exception($e->getMessage());
    }

    return $this->affectedRows();
  }
  
  /*
   * 获取数据
   */
  public function sqlGet($sql){
    try {
      $c = $this->getReadConnection();
      $result = $c->query($sql);
    } 
    catch (Exception $e) {
      debug('sqlerror:('.$sql.')');
      iquery('insert into player_common_log values (null, 0, "sqlerror", "'.iescape($sql).'", "'.date('Y-m-d H:i:s').'")', true);
      throw new Exception($e->getMessage());
    }
    $result->setFetchMode(Phalcon\Db::FETCH_ASSOC);
    return $result->fetchAll();
  }
  
  /*
   * 更新数据
   */
  public function sqlExec($sql){
    $c = $this->getWriteConnection();
    try {
      $result = $c->query($sql);
    } 
    catch (Exception $e) {
      debug('sqlerror:('.$sql.')');
      iquery('insert into player_common_log values (null, 0, "sqlerror", "'.iescape($sql).'", "'.date('Y-m-d H:i:s').'")', true);
      throw new Exception($e->getMessage());
    }
    //$r = $this->di['db']->query('select ROW_COUNT()')->fetchAll();
    $r = $c->query('select ROW_COUNT()')->fetchAll();
    $r = $r[0]['ROW_COUNT()'] > 0 ? $r[0]['ROW_COUNT()'] : 0;
    return $r;
  }
  
  public function affectedRows(){
    $c = $this->getWriteConnection();
    return $c->affectedRows();
  }
  
  public function filterField($fields=array(), $reverse=false){
    $ar = $this->toArray();
    $ar = objFilter($ar, $fields, $reverse);
    return $ar;
  }
  
  public function clearDataCache($playerId=0, $basicFlag=true){
    if(!$playerId){
      $playerId = $this->player_id;
    }
    $class = get_class($this);
    Cache::delPlayer($playerId, $class);
    $this->getDI()->get('data')->datas[$playerId][] = $class;
    if($basicFlag) {  //如果为false则不会进basic
      $this->getDI()->get('data')->setBasic([$class]);
    }
  }

  /**
   * 多进程/多线程模式下建立重连机制
   * @inheritDoc
   */
  public static function find($parameters = null) {
    $re = null;
    try {
      $re = parent::find($parameters);
    } 
    catch(PDOException $e) {
      $modelName = get_class(new static);
      log4cli("Model:{$modelName}-find-+++++++++++++++++++++++重连中。。。");
      global $di, $config;
      $di['db']->connect($config->database->toArray());
      try{
        $re = parent::find($parameters);
        log4cli("Model:{$modelName}-find-+++++++++++++++++++++++重连成功！");
      } 
      catch(PDOException $e) {
        echo "Model:{$modelName}-find-重连失败--------------- PDOException:" . __METHOD__ . ":" . __LINE__,PHP_EOL;
        trace();
      }
    }
    return $re;
  }

  /**
   * 多进程/多线程模式下建立重连机制
   * @inheritDoc
   */
  public static function findFirst($parameters = null,  $autoCreate = null) {
    $re = null;
    try {
      $re = parent::findFirst($parameters);
    } 
    catch(PDOException $e) {
      $modelName = get_class(new static);
      log4cli("Model:{$modelName}-findFirst-+++++++++++++++++++++++重连中。。。");
      global $di, $config;
      $di['db']->connect($config->database->toArray());
      try{
        $re = parent::findFirst($parameters);
        log4cli("Model:{$modelName}-findFirst-+++++++++++++++++++++++重连成功！");
      } 
      catch(PDOException $e) {
        echo "Model:{$modelName}-findFirst-重连失败--------------- PDOException:" . __METHOD__ . ":" . __LINE__,PHP_EOL;
        trace();
      }
    }
    return $re;
  }
}
