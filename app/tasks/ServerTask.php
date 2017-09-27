<?php
class ServerTask extends \Phalcon\CLI\Task{
  public $table = null; //内存块
  public $timerArr     = [];//定时器时间存储
  public $fdMems       = [];//拆分暂存包的信息
  public $startTimeArr = [];//在线时长
  private $serv;
  private $message;
  private $heartBeatInterval = 10; //心跳包 10 秒超时
  private $heartBeatOffset = 300; //当客户端申请暂停心跳检测时, 额外增加的超时5分钟
  private $startTime;

  
  /**
   * bootstrap
   */
  public function mainAction(){
    $this->init();
  }

  /**
   * 模拟请求
   */
  public function fakeAction(){
    $this->config->swoole->port = 9502;
    $this->init();
  }

  /**
   * 打印信息前缀
   * @return string
   */
  public static function prefix(){
    $now = date('Y-m-d H:i:s');
    return "[INFO][{$now}] ";
  }

  /**
   * 连接初始化操作
   */
  public function init() {
    //处理消息
    $this->message = new Message;
    $swoole = $this->config->swoole;
    //init server
    $this->serv = new swoole_server($swoole->host, $swoole->port);
    $this->serv->set($swoole->server_setting->toArray());

    $this->serv->on('Start', [$this, 'onStart']);
    $this->serv->on('Connect', [$this, 'onConnect']);
    $this->serv->on('Receive', [$this, 'onReceive']);
    $this->serv->on('Close', [$this, 'onClose']);
    $this->serv->on('Shutdown', [$this, 'onShutdown']);

    $this->serv->on('Task', [$this, 'onTask']);
    $this->serv->on('Finish', [$this, 'onFinish']);

    cli_set_process_title('php_swoole_server_task_'.$swoole->port);//set process name

    //开辟内存块存 fd 和 player_id 的映射关系
    $table = new swoole_table(65536);
    $table->column('fd', swoole_table::TYPE_INT, 4);
    $table->column('player_id', swoole_table::TYPE_INT, 4);
    $table->create();
    ServSession::$table = $table;

    $this->memCache = Cache::dbByName(CACHEDB_SWOOLE); //redis
    $this->serv->start();
  }

  /**
   * 服务器启动
   * @param  swoole_server $serv 
   */
  public function onStart($serv){        
    echo self::prefix()."onStart\n";
  }

  /**
   * 客户端连接过来调用的函数
   * @param  swoole_server $serv    
   * @param  int $fd      
   * @param  int $from_id 
   */
  public function onConnect($serv, $fd, $from_id) {
    log4server(fdPrefix($fd)." onConnect++++++++++++");
    $this->startTimeArr[$fd] = time();
  }

  /**
   * 从客户端接收数据包
   * @param  swoole_server $serv    
   * @param  int        $fd     
   * @param  int        $from_id 
   * @param  string        $data    
   */
  public function onReceive(swoole_server $serv, $fd, $from_id, $data) {
    $head = unpack('A4head/I1msgId/I1length', $data);
    /*log4server(fdPrefix($fd)."------------------>>>>>>>>包大小：".strlen($data));
    $_head = bin2hex($data);
    $_head = substr($_head, 0, 24);
    log4server(fdPrefix($fd)."------------------>>>>>>>>包头：".displayBinary(hex2bin($_head)));*/

    if (strlen($data) > 0 && isset($this->timerArr[$fd])) {
      $this->timerArr[$fd] = time();
    }

    //1.拼包
    $receiveSize = strlen($data);//本次收到长度
    $keyMemCache = 'mem_cache_fd_'.$fd;

    if(isset($head['head']) && $head['head'] == SWOOLE_MSG_HEAD) {
      $totalSize = $head['length'] + 12;  //应收长度
      if($totalSize > $receiveSize) {     //等下个包
        $lenInfo['total_size']   = $totalSize;
        $lenInfo['receive_size'] = $receiveSize;
        $this->fdMems[$fd]       = $lenInfo;

        $this->memCache->setex($keyMemCache, 1800, $data);
        return null;
      } 
      else { //收到整包时
        if(isset($this->fdMems[$fd])) {
          unset($this->fdMems[$fd]);
        }
        if($totalSize > 8192) {//大于等于8k时，swoole限制
          $this->memCache->setex($keyMemCache, 300, $data);
          $isDataInCache = true;
        }
      }
    } 
    else {//拼接
      $memData = $this->memCache->get($keyMemCache);
      $memData .= $data;
      $this->memCache->setex($keyMemCache, 3600, $memData);
      $this->fdMems[$fd]['receive_size'] += $receiveSize;
      if($this->fdMems[$fd]['receive_size'] < $this->fdMems[$fd]['total_size']) {
        return null;
      }
    }

    if (!empty( $this->fdMems[$fd])) {
      $data = $this->memCache->get($keyMemCache);
      $head = unpack('A4head/I1msgId/I1length', $data);
      $isDataInCache = true;
    }

    //2.检测包的合法性
    if (!isset($head['head'])||$head['head'] != SWOOLE_MSG_HEAD ||!isset($head['msgId']) || !in_array($head['msgId'], StaticData::$msgIds)) {
      echo "!非法连接!\n";
      if(isset($head['head'])) {
        echo "illegal head: ".$head['head'].PHP_EOL;
      }
      if(isset($head['msgId'])) {
        echo "illegal msgId: ".$head['msgId'].PHP_EOL;
      }
      $serv->send($fd, "!非法连接!\n");
      $serv->close($fd);
      return false;
    }

    //3.处理心跳检测 
    $this->checkHeartBeat($serv, $fd, $head);

    //4.启动一个异步线程来处理数据包 
    $param = ['fd'=>$fd, 'data'=>$data, 'mem_flag'=>0];
    if(isset($isDataInCache)) {
      $param['mem_flag'] = 1;
      $param['data'] = '';
    }
    $serv->task($param);//启动一个异步线程去处理
  }


  /**
   * Worker进程
   * @param  swoole_server $serv    
   * @param  int $task_id 
   * @param  int $from_id 
   * @param  array $param    
   * @return         
   */
  public function onTask(swoole_server $serv, $task_id, $from_id, $param) {
    $fd = $param['fd'];
    $msgIds = StaticData::$msgIds;
    if(!$serv->exist($fd)) {
      log4server(fdPrefix($fd).'连接已断');
      return;
    }

    if($param['mem_flag'] == 1) {
      $keyMemCache = 'mem_cache_fd_'.$fd;
      $tdata = Cache::dbByName(CACHEDB_SWOOLE)->get($keyMemCache);
    } 
    else {
      $tdata = $param['data'];
    }

    $tdata = unpackData($tdata);
    if($tdata['head'] != SWOOLE_MSG_HEAD) {
      return;
    } 
       
    if ($tdata['msgId'] != $msgIds['HeartBeatRequest']) {
      log4server(fdPrefix($fd)."传入数据 = [msgId]:" .color($tdata['msgId'],'purple',true).'-->[content]:'.arr2str(json_decode($tdata['content'], true)));
    }

    switch($tdata['msgId']) {

      case $msgIds['LoginRequest']://登陆
        $loginData = json_decode($tdata['content'], true);
        //检测登录信息有效性
        if (!$this->message->isValidLogin($fd, $loginData)) {
          $serv->close($fd);
          return;
        }
        $playerId = $loginData['player_id'];
        ServSession::setFd($playerId, $fd);   
        $this->doRecord($playerId);     //记录开始登陆时间入表 
        $msgId = $msgIds['LoginResponse'];      
        $data = packData('', $msgId);
        $serv->send($fd, $data);
        break;

      case $msgIds['HeartBeatRequest']://心跳
        $msgId   = $msgIds['HeartBeatResponse'];
        $data    = packData('', $msgId);
        $serv->send($fd, $data);
        break;

      case $msgIds['HeartBeatPauseRequest']://心跳暂停
        $msgId   = $msgIds['HeartBeatPauseResponse'];
        $data    = packData('', $msgId);
        $serv->send($fd, $data);
        break;

      case $msgIds['DataRequest']://消息
        $msgId   = $msgIds['DataResponse'];
        $content = $this->message->processMsg($serv, $fd, $from_id, $tdata);//处理数据包
        if(!empty($content)) {
          log4server(fdPrefix($fd) . "返回数据=" . arr2str($content));
        }
        $data    = packData($content, $msgId);
        $serv->send($fd, $data);
        break;

      case $msgIds['ChatSendRequest']://聊天
        $msgId   = $msgIds['ChatSendResponse'];
        $content = $this->message->processMsg($serv, $fd, $from_id, $tdata);//处理数据包
        $data    = packData($content, $msgId);
        $serv->send($fd, $data);
        break;

      case $msgIds['WebServerRequest']://web服务器发来的
        $msgId   = $msgIds['WebServerResponse'];
        $data    = packData('', $msgId);
        $serv->send($fd, $data);//返回验证信息，让web客户端关闭连接
        //处理逻辑
        $this->message->processMsg($serv, $fd, $from_id, $tdata);//处理数据包
        break;

      default: 
        $msgId = 0;
        $data  = packData('', $msgId);
        $serv->send($fd, $data);
    }//switch end

  }

  /**
   * 接收到Task任务的处理结果$data
   * @param  swoole_server $serv    
   * @param  int $task_id 
   * @param  array $data    
   */
  public function onFinish($serv,$task_id, $data) {
      // echo self::prefix()."Task {$task_id} finish\n";
      // echo "Result: {$data}\n";
  }

  /**
   * 客户端连接关闭
   * @param  swoole_server $serv    
   * @param  int $fd      
   * @param  int $from_id 
   */
  public function onClose($serv, $fd, $from_id) {
    if(isset($this->startTimeArr[$fd])) {
      if (isset($this->timerArr[$fd])) {
        unset($this->timerArr[$fd]);
        //记录本次登陆总时长
        $totalTime = time() - $this->startTimeArr[$fd];
        if($totalTime > 0) {
          $this->closeRecord($fd, $totalTime);
        }
      }
      unset($this->startTimeArr[$fd]);
    }
    ServSession::delLink($fd);
    if(isset($this->fdMems[$fd])) {
      unset($this->fdMems[$fd]);
    }
    log4server(fdPrefix($fd)."+++++++++++ socket close +++++++++++");
  }

  /**
   * server关闭 kill -15 swoole主线程 # ps -ejHF|grep php
   * @param  swoole_server $serv 
   */
  public function onShutdown($serv) {
    global $di;
    $di['db']->close();
    Cache::dbByName(CACHEDB_SWOOLE)->flushDB();
  }

  //开启心跳检测 (定时器在主线程中开启)
  private function checkHeartBeat($serv, $fd, $head) {

    //登录时延时5秒启动心跳包检测
    if (isset($head['msgId']) && $head['msgId'] == StaticData::$msgIds['LoginRequest']) {
      $this->memCache      = Cache::dbByName(CACHEDB_SWOOLE);   
      $serv->after(5000, function() use ($serv, $fd){
            if(!$serv->exist($fd)) return ;

            $this->timerArr[$fd] = time();
            log4server(fdPrefix($fd)."启动定时器检测心跳");

            //循环定时器 5 秒间隔
            $serv->tick(5000, function($id) use ($serv, $fd) {
                if(!$serv->exist($fd) || !isset($this->timerArr[$fd])) {
                  $serv->clearTimer($id);
                  return;
                }

                $elapsedTime = time() - $this->timerArr[$fd];
                if ($elapsedTime > $this->heartBeatInterval) {
                  log4server(fdPrefix($fd)."[异常] 超时{$this->heartBeatInterval}s, 关闭连接 ！！！！！！");
                  $serv->clearTimer($id);
                  $serv->close($fd);
                  return;
                }
            });
        });
    } 
    elseif (isset($head['msgId']) && $head['msgId'] == StaticData::$msgIds['HeartBeatRequest']) {
      if(!$serv->exist($fd)) return;
      $this->timerArr[$fd] = time();
    }
    elseif (isset($head['msgId']) && $head['msgId'] == StaticData::$msgIds['HeartBeatPauseRequest']) {
      if(!$serv->exist($fd)) return;
      $this->timerArr[$fd] += $this->heartBeatOffset;
    }    
  } 

  /**
   * 记录连接时间
   */
  public function doRecord($playerId) {
    $date = date("Y-m-d");
    $objOnline = new PlayerOnline();
    $res = $objOnline->getRecord($playerId, $date);
    if(!$res){
      $objOnline->setRecord($playerId);
    }
  }

  /**
   * 断开连接更新时间
   */
  public function closeRecord($fd, $during) {

    $playerId = ServSession::getPlayerIdByFd($fd);
    if(!$playerId){
      return;
    }   
    $date = date("Y-m-d");
    $objOnline = new PlayerOnline();        
    $res = $objOnline->getRecord($playerId, $date);
    if(!$res){
      $objOnline->setRecord($playerId);
      //非当日close
      if($during > 86400){
        $fields = array();
        $fields['online'] = "online+{$during}";
        $objOnline->updateRecord($playerId, $date, $fields);
      }
      else {
        //只是隔日需要细分
        //昨天上线时间
        $duringYesterday = strtotime($date) - $this->startTime;
        $yesterDay = date("Y-m-d", (time()-86400));
        $fieldsYesterday = array();
        $fieldsYesterday['online'] = "online+{$duringYesterday}";
        $objOnline->updateRecord($playerId, $yesterDay, $fieldsYesterday);
        //今天上线时间
        $duringToday = time()- strtotime($date);
        $fieldsToday = array();
        $fieldsToday['online'] = "online+{$duringToday}";
        $objOnline->updateRecord($playerId, $date, $fieldsToday);        
      }
    }
    else{
      //当日close
      $fields = array();
      $fields['online'] = "online+{$during}";
      $objOnline->updateRecord($playerId, $date, $fields);
    }
  }

}

