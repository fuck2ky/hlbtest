<?php
/**
 * 长连接处理消息
 */
class Message {
  public $data      = [];//需要处理的[Type, Data]
  public $returnMsg = [];//返回值，供processMsg返回

  //检查是否有效登录信息
  public function isValidLogin($fd, $loginData) {
    $isValid = false;

    if(isset($loginData['player_id']) && !empty($loginData['player_id'])) {
      $playerId = $loginData['player_id'];
      $hashCode = @$loginData['hash_code'];

      if(hashMethod($playerId)== $hashCode) {//验证通过
        $isValid = true; 

        // $ret = iquery("select id from player where id={$playerId}");
        // if($ret) {
        //   $isValid = true;
        //   log4server(fdPrefix($fd)."玩家数据=".arr2str($ret[0]));
        // }
        // else {
        //   log4server(fdPrefix($fd)."登录验证不通过-无玩家数据");
        // }
      }
      else {
        log4server(fdPrefix($fd)."登录验证不通过");
      }
    } 
    return $isValid;     
  }

  /**
   * 处理消息方法
   * param  swoole_server $serv
   * param  int        $fd
   * param  int        $from_id
   * param  array      $data
   * @return string
   */
  public function processMsg(swoole_server $serv, $fd, $from_id, $data) {
    $content = json_decode($data['content'], true);
    debug('content====');
    debug($content, true);

    //初始化B
    $this->data[$fd] = [];
    $this->data[$fd]['contentType'] = $content['Type'];
    $this->data[$fd]['contentData'] = $content['Data'];

    switch ($content['Type']) {
      // case 'world_chat'://广播：国家聊天
      //   $this->worldChatMsg($serv, $fd);
      //   break;

      // case 'battle_fight'://跨服联盟聊天 含语音
      //   $this->guildCrossChatMsg($serv, $fd);
      //   break;

      // case 'pay_callback'://充值成功
      //   $this->payCallbackMsg($serv, $fd);
      //   break;
      case 'all_conn_info'://获取所有连接fd信息(计算在线玩家数)
        $this->allConnInfoMsg($serv, $fd);
        break;
      default:
        break;
    }

    $result = '';
    if(isset($this->returnMsg[$fd])) {
      $result = $this->returnMsg[$fd];
      unset($this->returnMsg[$fd]);
    }
    if(isset($this->data[$fd])) {
      unset($this->data[$fd]);
    }
    return $result;
  }


  /**
   * 获取所有连接fd信息
   * param $serv
   * param $fd
   */
  public function allConnInfoMsg($serv, $fd) {
    $conn = [];
    foreach(ServSession::$table as $v) {
      $conn[] = $v;
    }
    $this->returnMsg[$fd] = json_encode($conn);
  }

  /**
   * param swoole_server $serv
   * param int $fd
   *
   *  世界聊天
   */
  // public function worldChatMsg(swoole_server $serv, $fd) {
  //   $contentData = $this->data[$fd]['contentData'];
  //   $allConn     = ServSession::getAllFd();
  //   $playerId    = $contentData['player_id'];
  //   $msg         = $contentData['content'];

  //   $pushData = [];
  //   if (isset($contentData['pushData'])) {
  //     $pushData = $contentData['pushData'];
  //   }

  //   $WorldChat = new ChatUtil;
  //   $returnContent = $WorldChat->saveWorldMsg($playerId, $msg, $pushData);
  //   log4server(fdPrefix($fd)."世界聊天信息=".arr2str($returnContent));
  //   if ($returnContent == -1 && empty($pushData)) {//禁言
  //     $arr = ['flag' => 'banned'];
  //     $this->returnMsg[$fd] = json_encode($arr);
  //   } 
  //   elseif ($returnContent == -2 && empty($pushData)) {//等级不足
  //     $arr = ['flag' => 'low_level', 'level' => $WorldChat->transData['level']];
  //     $this->returnMsg[$fd] = json_encode($arr);
  //   } 
  //   elseif ($returnContent) {
  //     $returnContent['type'] = $this->data[$fd]['contentType'];
  //     $packReturnContent = packData($returnContent);
  //     foreach ($allConn as $conn) {
  //       $serv->send($conn, $packReturnContent);
  //     }
  //   }
  // }

  /**
   *  联盟聊天
   */
  // public function guildCrossChatMsg(swoole_server $serv, $fd) {
  //   $contentData = $this->data[$fd]['contentData'];
  //   $playerId    = $contentData['player_id'];
  //   unset($contentData['player_id']);
  //   $paraData = $contentData;

  //   $player  = (new Player)->getByPlayerId($playerId);
  //   $guildId = $player['guild_id'];
  //   log4server(fdPrefix($fd)."guild_id={$guildId} 联盟战跨服聊天信息");

  //   if ($guildId) {
  //     $currentRoundId = (new CrossRound)->getCurrentRoundId();
  //     if(!$currentRoundId) {
  //       $arr = ['flag' => 'no_cross_battle'];
  //       $this->returnMsg[$fd] = json_encode($arr);
  //     }
  //     $allJoinedMember = (new PlayerGuild)->getCrossJoinedMember($guildId);
  //     $inFlag = false;//是否参加
  //     foreach($allJoinedMember as $member) {
  //       if($member['player_id']==$playerId) {
  //         $inFlag = true;
  //         break;
  //       }
  //     }

  //     if(!$inFlag) {
  //       $arr = ['flag' => 'not_join_cross_battle'];
  //       $this->returnMsg[$fd] = json_encode($arr);
  //       return;
  //     }

  //     $GuildChat = new ChatUtil;
  //     $returnContent = $GuildChat->saveGuildCrossMsg($playerId, $paraData);
  //     log4server(fdPrefix($fd)."联盟战跨服聊天信息=".arr2str($returnContent));
  //     if ($returnContent) {
  //       $returnContent['type'] = $this->data[$fd]['contentType'];
  //       $packReturnContent     = packData($returnContent);

  //       foreach ($allJoinedMember as $k => $v) {
  //         $pid = $v['player_id'];
  //         if(($pid!=$playerId || !isset($paraData['paraData']))) {
  //           $toFd = ServSession::getFdByPlayerId($pid);
  //           if ($toFd)
  //             $serv->send($toFd, $packReturnContent);
  //         }
  //       }
  //     }
  //   }
  // }

  /**
   *
   *  充值成功
   */
  // public function payCallbackMsg(swoole_server $serv, $fd) {
  //   $contentData = $this->data[$fd]['contentData'];
  //   $toFd = ServSession::getFdByPlayerId($contentData['playerId']);
  //   if(!$toFd)
  //       return;
  //   $msg = ['type'=>$this->data[$fd]['contentType'], 'goods_type'=>$contentData['goods_type'], 'pricing'=>$contentData['pricing']];
  //   $retData = packData($msg, StaticData::$msgIds['DataResponse']);
  //   $serv->send($toFd, $retData);
  // }
}

