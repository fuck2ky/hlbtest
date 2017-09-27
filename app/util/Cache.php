<?php

//操作 redis 缓存 
class Cache{

  static $redis = null;

  static $tmpSwitch = true; //将 redis 数据缓存一部分到数组
  static $tmpPlayerData = []; //缓存一部分玩家数据

  public static function dbById($index) {

    if (is_null(self::$redis)) {
      $redis = getNewRedisConnect();
    }

    try{
      $redis->select($index);

    } catch (Exception $e) {
      try{
        $redis->close();
        $redis = getNewRedisConnect();
        $redis->select($index);
      } 
      catch (Exception $e) {
      }
    }

    return $redis;
  }

  /**
   * 指定缓存(数据库),redis 默认支持索引index = 0~15 共16个数据库
   * index 在 config.php 中 redis信息里设置
   */
  public static function dbByName($poolName = CACHEDB_PLAYER){

    $index = self::dbName2Id($poolName);

    return self::dbById($index);
  }

  //缓存对应的索引值 0~15
  public static function dbName2Id($poolName){
    global $config;
    $c = $config->redis->toArray();
    if(!isset($c['index'][$poolName])){
      $poolName = CACHEDB_PLAYER;
    }
    return $c['index'][$poolName];
  }

  
  /**
   * 锁定
   * 
   * @param <type> $key 
   * @param <type> $timeout 
   * @param <type> $loopSec
   *
   * @return <type>
   */
  public static function lock($key, $poolName=CACHEDB_PLAYER, $timeout=10, $loopSec=60){
    $key = self::lockkey($key);
    $time = time();

    while(!self::dbByName($poolName)->setnx($key, 1)){
      usleep(1000);
      if(time() - $time >= $loopSec){
        exit;
      }
    }

    if(false !== $timeout) {
      self::dbByName($poolName)->setTimeout($key, $timeout);
    }
  }
  
  /**
   * 解锁
   * 
   * @param <type> $key 
   * 
   * @return <type>
   */
  public static function unlock($key, $poolName=CACHEDB_PLAYER){
    $key = self::lockkey($key);
    self::dbByName($poolName)->delete($key);
  }
  
  /**
   * 锁名
   * 
   * @param <type> $key 
   * 
   * @return <type>
   */
  public static function lockkey($key){
    return 'LOCK:'.$key;
  }
  
  /**
   * 设置玩家数据包
   * 
   * @param <type> $playerId 
   * @param <type> $key 
   * @param <type> $value 
   * @param <type> $pool
   *
   * @return <type>
   */
  public static function setPlayer($playerId, $key, $value, $pool=CACHEDB_PLAYER){
    $dataKey = self::getPlayerKey($playerId);
    self::dbByName($pool)->hSet($dataKey, $key, $value);

    if(self::$tmpSwitch){
      @self::$tmpPlayerData[$playerId][$key] = $value;
      if(count(self::$tmpPlayerData) > 50) //如果超出则截取当前附近100条数据
        self::$tmpPlayerData = array_slice(self::$tmpPlayerData, -50, 50, true); 
    }
  }
  
  /**
   * 获取玩家数据包
   * 
   * @param <type> $playerId 
   * @param <type> $key 
   * @param <type> $pool
   *
   * @return <type>
   */
  public static function getPlayer($playerId, $key, $pool=CACHEDB_PLAYER){
    $ret = @self::$tmpPlayerData[$playerId][$key];
    if(!$ret){
      $dataKey = self::getPlayerKey($playerId);
      $ret = self::dbByName($pool)->hGet($dataKey, $key);
      if(self::$tmpSwitch){
        @self::$tmpPlayerData[$playerId][$key] = $ret;
        if(count(self::$tmpPlayerData) > 50)
          self::$tmpPlayerData = array_slice(self::$tmpPlayerData, -50, 50, true);
      }
    }
    return $ret;
  }
  
  /**
   * 删除玩家数据包
   * 
   * @param <type> $playerId 
   * @param <type> $key 
   * @param <type> $pool
   *
   * @return <type>
   */
  public static function delPlayer($playerId, $key, $pool=CACHEDB_PLAYER){
    $dataKey = self::getPlayerKey($playerId);
    self::dbByName($pool)->hDel($dataKey, $key);
    unset(self::$tmpPlayerData[$playerId][$key]);
  }
  
  /**
   * 删除玩家所有数据包
   * 
   * @param <type> $playerId 
   * 
   * @return <type>
   */
  public static function delPlayerAll($playerId){
    global $config;

    $dataKey = self::getPlayerKey($playerId);

    $c = $config->redis->toArray();
    foreach ($c['index'] as $idx) {
      self::dbById($idx)->del($dataKey);
    }
    unset(self::$tmpPlayerData[$playerId]);
  }
  
  /**
   * 获取玩家数据包key
   * 
   * @param <type> $playerId 
   * 
   * @return <type>
   */
  public static function getPlayerKey($playerId){
    return 'data_I'.$playerId;
  }


  /**
  * 清cache
  * @param  boolean $serverFlag 是否清swoole服务相关cache
  */
  public static function clearAllCache($serverFlag=false){
    global $config;
    $c = $config->redis->toArray();
    foreach ($c['index'] as $idx) {
      self::dbById($idx)->flushDB();
    }

    self::$tmpPlayerData = [];
  }
  
  public static function clearPlayerCache(){
    self::dbByName(CACHEDB_PLAYER)->flushDB();
    self::$tmpPlayerData = [];
  }
  
  public static function close(){
    try{
      if (isset(self::$redis)) {
        self::$redis->close();
      }
    } 
    catch (Exception $e) {
    }
  }

}
