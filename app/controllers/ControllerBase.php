<?php

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{

  public $controllerName = ''; //当前请求的controller

  public $actionName = ''; //当前请求的action

  public $postData = null;

  public $lockKey  = 'doBeforeAction_playerId=';

  public $currentPlayer = null;

  public $currentPlayerId = 0;

  public $userCodeLoginFlag = false; //user code登录模式(不踢人)

  public $ipLimitSwitch = true; //是否限IP访问

  /**
   * 控制器初始化. 自动优先执行
   */
  public function initialize() {
    $this->auth(); 
  } 

  /**
   * 客户端授权认证
   */
  public function auth(){

    $this->controllerName = strtolower($this->dispatcher->getControllerName());
    $this->actionName = strtolower($this->dispatcher->getActionName());

    // if(isset($_REQUEST['adminQA']) && $_REQUEST['adminQA'] == 1) {
    //   StaticData::$adminQAFlag = true;
    //   unset($_REQUEST['adminQA']);
    // }
    $isPost = $this->request->isPost() || StaticData::$adminQAFlag;
    
    if($isPost) {
      $postData = $this->postData = getPost();
      $uuid = $postData['uuid'];
      $hashCode = $postData['hashCode'];
      $inner = isset($postData['inner'])&&$postData['inner'] == 1;//内部访问用于客户端批量访问时,服务器端内部组织数据用

      if($inner || validateUUID($uuid, $hashCode)) {//验证uuid    
        //检查时间戳,防止恶意重发
        if($this->controllerName != 'common' && $this->actionName != 'ntpdate'
           && isset($postData['timeCollated'])
           && $postData['timeCollated']==1
           && isset($postData['timestamp'])) {
            $this->checkTimetamp($postData['timestamp']);
        }

        //判断是否存在玩家
        $PlayerMode = new Player; 
        $player = $PlayerMode->getPlayerByUUID($uuid);
        
        if(!$player) {//如果用户不存在
          if($this->controllerName == 'common' && $this->actionName == 'checkplayer') { //新创建玩家
            $player = $PlayerMode->newPlayer($postData);
            if(!$player) {
              $errorEvent = $this->getDI()->get('errorEvent');
              echo $this->data->sendErr($errorEvent['CreatePlayerFail']);
              exit;
            }
            $playerInfo = (new PlayerInfo)->getByPlayerId($player['id']);
            echo $this->data->sendRaw(['checkPlayer'=>0,'login_hashcode'=>$playerInfo['login_hashcode']]);
            exit;
          }
          else {
            exit("\n[ERROR] illegal url to new player\n");
          }
        }

        $this->currentPlayer   = $player;
        $this->currentPlayerId = $player['id'];
        $this->data->setPlayerId($player['id']);
        $this->doBeforeActionEachRequest();
      }
      else {
        exit("\n[ERROR]illegal login\n");
      }
    } 
    else {
      exit("\n[ERROR]not a post request\n");
    }
  } 

  /**
   * 每次请求到对应action前的操作
   */
  public function doBeforeActionEachRequest(){
    $player         = $this->currentPlayer;
    $playerId       = $this->currentPlayerId;
    $controllerName = $this->controllerName;
    $actionName     = $this->actionName;
    $postData       = $this->postData;

    $PlayerMode     = new Player;
    $PlayerInfoMode = new PlayerInfo;

    $lockKey  = $this->lockKey.$playerId;
    Cache::lock($lockKey);//锁定

    //a 检测login time, 更新登录日期
    if(date('Y-m-d', $player['login_time']) != date('Y-m-d')) {
      $PlayerMode->alter($playerId, ['login_time'=>getQuoteTime()]);
    } 

    //b 检测服务器端版本号,用于提示客户端版本更新
    if(isset($postData['game_version'])) {
      $currentGameVersion = (new LoginServerConfig)->getValueByKey('game_version'); 
      if($postData['game_version'] != $currentGameVersion) {
        Cache::unlock($lockKey);
        $errorEvent = $this->getDI()->get('errorEvent');
        echo $this->data->sendErr($errorEvent['VersionError']);
        exit;
      }
    } 

    //c 登陆时检测是否在其他设备登录
    // (因为每次checkplayer时都会生成一个新的login_hashcode,并更新到数据库,当使用旧的hashcode访问时会被提示下线)
    if(isset($postData['login_hashcode']) && !($controllerName=='common'&& $actionName=='checkplayer')) {
      $curInfo = $PlayerInfoMode->getByPlayerId($player['id']);
      if($postData['login_hashcode'] != $curInfo['login_hashcode']) {
        Cache::unlock($lockKey);
        $errorEvent = $this->getDI()->get('errorEvent');
        echo $this->data->sendErr($errorEvent['ForceOffline']); //'该帐号在其他设备上登录'
        exit;
      } 

      if($PlayerInfoMode->getBanTime($player['id'])) {
        Cache::unlock($lockKey);
        $errorEvent = $this->getDI()->get('errorEvent');
        echo $this->data->sendErr($errorEvent['CancelAccount']); //'该帐号已被封号'
        exit;
      }
    }

    // //d 新手引导更改步骤数
    // if(isset($postData['steps']) && isset($postData['steps']['step']) && is_numeric($postData['steps']['step'])) {//更改步骤数
    //     $PlayerMode->alter($playerId, ['step'=>$postData['steps']['step']]);
    // }
    // //e 新手引导 数据集合
    // if(isset($postData['steps']) && isset($postData['steps']['step_set']) && is_numeric($postData['steps']['step_set'])) {//更改步骤数
    //     $stepSet   = $player['step_set'];
    //     $stepSet[] = $postData['steps']['step_set'];
    //     $stepSet   = array_unique($stepSet);
    //     $PlayerMode->alter($playerId, ['step_set'=>q(json_encode($stepSet))]);
    // }


    if ($this->ipLimit()) {//限制ip
      Cache::unlock($lockKey);
      $errorEvent = $this->getDI()->get('errorEvent');
      echo $this->data->sendErr($errorEvent['UnderMaintenance']); //维护期间-白名单之外的ip
      exit;
    }

    // $this->saveAccessLog();
    
    Cache::unlock($lockKey);//锁定
  }

  /**
   * 获取当前玩家id 
   */
  public function getCurrentPlayerId(){
    if(!$this->currentPlayerId){
      $this->auth();
    }
    return $this->currentPlayerId;
  }

  //检查时间戳,防止恶意重发数据(重放攻击)
  public function checkTimetamp($timestamp){
    $subTime = time()-$timestamp;
    if($subTime > 8) {//时间在8秒之外为无用请求
      $errorEvent = $this->getDI()->get('errorEvent');
      echo $this->data->sendErr($errorEvent['InvalidTimestamp']);
      exit;
    }
  }

  //限ip
  public function ipLimit(){
    global $config;
    $serverId   = $config->server_id;
    $serverList = (new LoginServerList)->dicGetAll();
    $serverList = Set::combine($serverList, '{n}.id', '{n}');
    $serverList = $serverList[$serverId];

    if(in_array($serverList['status'], [1,2]) && $this->ipLimitSwitch) {
      $clientIp = $_SERVER['REMOTE_ADDR'];
      $LoginServerConfig = new LoginServerConfig;
      //包含多个批量ip白名单
      $ipLimitConfig = $LoginServerConfig->getValueByKey('ip_limit_config_global');
      if($ipLimitConfig) {
        $ipLimit  = json_decode($ipLimitConfig, true);
        $limitIps = $ipLimit['ips'];
    
        foreach($limitIps as $v) {
          $v = trim($v);
          if(strpos($v, '.*') !== false) {//ip段
            $ipSegment = substr($v, 0, strlen($v)-strlen('.*'));
            if(strpos($clientIp, $ipSegment)=== 0) {
              return false; //属于白名单中,可继续访问
            }
          }
          if($v==$clientIp) {//直接ip命中
            return false; //属于白名单中,可继续访问
          }
        }
      }

      //单个批量ip白名单
      $ipLimitConfig2 = $LoginServerConfig->getValueByKey('ip_limit_config_single');
      if($ipLimitConfig2) {
        $ipLimit2 = json_decode($ipLimitConfig2, true);
        $ipLimit2 = Set::combine($ipLimit2, '{n}.serverId', '{n}.ips');
        if(isset($ipLimit2[$serverId])) {
          foreach($ipLimit2[$serverId] as $v) {
            $v = trim($v);
            if(strpos($v, '.*')!==false) {//ip段
              $ipSegment2 = substr($v, 0, strlen($v)-strlen('.*'));
              if(strpos($clientIp, $ipSegment2)===0) {
                return false; //属于白名单中,可继续访问
              }
            }
            if($v == $clientIp) {//直接ip命中
              return false; //属于白名单中,可继续访问
            }
          }
        }
      }

      return true;
    }

    return false;
  }

  // public function saveAccessLog(){
  //   if(ACCESS_LOG_FLAG) {
  //     $playerId = $this->currentPlayer['id'];
  //     (new PlayerCommonLog)->add($playerId, ['type' => 'accesslog', 'url' => StaticData::$_url, 'postData' => StaticData::$_postData]);
  //   }
  // }

  public function getControllerName(){
    return $this->getDI()['dispatcher']->getControllerName();
  }
  
  public function getActionName(){
    return $this->getDI()['dispatcher']->getActionName();
  }
  
  public function getParams(){
    return $this->getDI()['dispatcher']->getParams();
  }

  public function afterCommit(){
    foreach($this->di['data']->datas as $_playerId => $_d){
      $_d = array_unique($_d);
      foreach($_d as $__d){
        Cache::delPlayer($_playerId, $__d);
      }
    }
  }

}
