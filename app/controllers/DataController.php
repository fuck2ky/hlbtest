<?php

class DataController extends ControllerBase {
  public function initialize() {
    parent::initialize();
    $this->view->disable();
  }

  public function indexAction() {
    $d = getPost();
    $playerId = $this->getCurrentPlayerId();
    $ret = $this->get($playerId, @$d['name']);
    echo $this->data->send($ret);
  }

  /**
   * 获取数据包
   * return void
   */
  public static function get($playerId, $name=array()) {
    global $di, $config;
    $data = $di->get('data');
    if(!$data->playerId) {
      $di->get('data')->setPlayerId($playerId);
    }

    $ret = array();
    if(is_array($name)) {
      foreach($name as $_name){ 
        if(class_exists($_name)){
          $_oname = new $_name; 

          if(method_exists(__CLASS__,'instead'.$_name)){
            $_ret = self::{'instead'.$_name}($playerId);
          }
          else {
            // if(substr($_name, 0, 5) == 'Cross'){
            //   $player = (new self)->getCurrentPlayer();
            //   $guildId = CrossPlayer::joinGuildId($config->server_id, $player['guild_id']);
            //   $battleId = (new CrossBattle)->getBattleIdByGuildId($guildId);
            //   if(!$battleId){
            //     $battleId = (new CrossBattle)->getLastBattleIdByGuildId($guildId);
            //   }
            //   $_oname->battleId = $battleId*1;
            // } 
            $_ret = $_oname->getByPlayerId($playerId, true);
          }

          //如果需要,对数据进行处理
          if($_ret && method_exists(__CLASS__,'deal'.$_name)){
            $ret[$_name] = self::{'deal'.$_name}($_ret, $playerId);
          }
          else {
            $ret[$_name] = $_ret;
          }
        }
      }
    }
    return $ret;
  } 


  // public static function insteadPlayerBuff($playerId){
  //   $PlayerController = new PlayerController;
  //   return $PlayerController->getBuff($playerId);
  // }

  // public static function dealPlayerArmy($data, $playerId){
  //   $ret = array();
  //   $PlayerArmyUnit = new PlayerArmyUnit;
  //   foreach($data as $_k => $_data){
  //     $_data['weight'] = $PlayerArmyUnit->calculateWeight($playerId, $_data['id']);
  //     $ret[$_data['id']] = $_data;
  //   }
  //   return $ret;
  // }
  
}

