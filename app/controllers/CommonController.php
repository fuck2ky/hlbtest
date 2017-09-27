<?php
/**
 * 通用需求
 */
class CommonController extends ControllerBase{

    /**
     * 登录游戏服之前需要检测玩家是否存在,如果不存在则创建
     * url: common/checkPlayer
     * return: {"code":0,"data":{"checkPlayer":1},"basic":[]}
     * checkPlayer: 1 --账号已经存在; 0 ----新账号
     */
    public function checkPlayerAction(){
        debug('checkPlayerAction');
        //到这里，说明玩家是存在的
        $playerId = $this->getCurrentPlayerId();
        $PlayerMode     = new Player;
        $PlayerInfoMode = new PlayerInfo;
        $PlayerInfo     = $PlayerInfoMode->getByPlayerId($playerId);
        $player         = $PlayerMode->getByPlayerId($playerId);

        $postData       = getPost(); 

        //正常登录时需要更新信息
        if(!$this->userCodeLoginFlag) { 
            //更新到玩家的服务器访问区号记录表
            global $config;
            (new LoginPlayerLastServer)->saveLast($player['uuid'], $config->server_id);

            $updateData = [];

            if($this->judgePost($postData, $PlayerInfo, 'pay_channel')) {
                $updateData['pay_channel'] = $postData['pay_channel'];
            }
            if($this->judgePost($postData, $PlayerInfo, 'platform')) {
                $updateData['platform'] = $postData['platform'];
            }

            $updateData['login_hashcode'] = loginHashMethod($playerId);//每次生成一个标识码,用于多台设备同时登录时踢人
            $updateData['login_ip'] = (new Phalcon\Http\Request)->getClientAddress();//客户端ip   
            $PlayerInfoMode->alter($playerId, $updateData);

            echo $this->data->sendRaw(['checkPlayer'=>1, 'login_hashcode'=>$updateData['login_hashcode']]);
            exit;
        } 
        else {
            echo $this->data->sendRaw(['checkPlayer'=>1, 'login_hashcode'=>$PlayerInfo['login_hashcode']]);
            exit;
        }
    }


    /**
     * 前端同步服务器时间的请求
     * url: common/ntpdate
     * return: 1446795169 (时间戳)
     * return int ntp date
     */
    public function ntpdateAction(){
        $Z = intval(date('Z'));
        echo $this->data->send(['Time'=>time(), 'Time_Zone'=>$Z]);
        exit;
    }

    /**
    * 合并请求
    * common/combo
    * 比如: $postData['combo'] = [
    *           ['url'=>'King/getInfo', 'field'=>['a'=>['A'=>'AAA']]],
    *           ['url'=>'Lottery/checkPlayerLotteryInfo'],
    *           ['url'=>'data/index', 'field'=>['name'=>['Player', 'PlayerInfo']]]
    *       ];
    */
    public function comboAction(){
        $player = $this->currentPlayer;
        $uuid = $player['uuid'];
        $postData = getPost();
        //debug($postData, 1);
        $nodes = $postData['combo'];
        unset($postData['combo']);
        $data = comboRequest($nodes, $postData, $uuid);
        if(empty($data)) {
            echo $this->data->sendErr(1002);//网络不稳定
        } 
        else {
            echo encodeResponseData(json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        exit;
    }



    /**
     * 获取公告
     *
     * ```php
     * url: common/getAllNotice
     * return: [...]
     * ```
     */
    public function getAllNoticeAction(){
        $Notice = new Notice;
        $data = $Notice->getAll();
        echo $this->data->send($data);
        exit;
    }

    /**
     * short for checkPlayer validation
     */
    private function judgePost($postData, $pi, $field){
        return ( isset($postData[$field]) && $postData[$field]!= $pi[$field] );
    }




    /**
     * 合并消息
     *
     * ```
     *  common/viewAllWorldMsg
     *  common/viewAllGuildMsg
     *  data/index [ChatBlackList]
     * ```
     *  common/comboChat
     *  postData:{}
     *  return:{[World],[Guild],[ChatBlackList]}
     */
    public function comboChatAction(){
        $playerId = $this->getCurrentPlayerId();
        $player   = $this->getCurrentPlayer();
        $guildId  = $player['guild_id'];
        $campId   = $player['camp_id'];

        $ChatUtil = new ChatUtil;

        $cityBattleMsg = [];
        $campMsg       = [];

        $worldMsg = $ChatUtil->getAllWorldMsg();
        if(!$guildId) {
            $guildMsg = [];
            $guildCrossMsg = [];
        } else {
            $guildMsg = $ChatUtil->getAllGuildMsg($guildId);
            $guildCrossMsg = $ChatUtil->getAllGuildCrossMsg($guildId);
        }
        if($campId>0) {
            do {
                $roundId = (new CityBattleRound)->getCurrentRound();
                if(!$roundId) break;//round not exists
                $battleId = (new CityBattlePlayer)->getCurrentBattleId($playerId);
                if(!$battleId) break;//not join battle or not exists round
                $cityBattleMsg = $ChatUtil->getCityBattleMsg($roundId, $battleId, $campId);
            } while(false);
            $campMsg = $ChatUtil->getCampMsg($campId);
        }
        $data['World']         = $worldMsg;
        $data['Guild']         = $guildMsg;
        $data['GuildCross']    = $guildCrossMsg;
        $data['CityBattle']    = $cityBattleMsg;
        $data['Camp']          = $campMsg;
        $data['ChatBlackList'] = (new ChatBlackList)->getByPlayerId($playerId, true);
        echo $this->data->send($data);
        exit;
    }

    /**
     * 查看世界聊天信息
     *
     * ```php
     * common/viewAllWorldMsg
     * postData:{}
     * return:{}
     * ```
     */
    public function viewAllWorldMsgAction(){
        $playerId = $this->getCurrentPlayerId();
        $player = $this->getCurrentPlayer();
        $worldMsg = (new ChatUtil)->getAllWorldMsg();
        echo $this->data->send($worldMsg);
        exit;
    }
    /**
     * 查看联盟聊天信息
     *
     * ```php
     * common/viewAllGuildMsg
     * postData:{}
     * return:{}
     * ```
     */
    public function viewAllGuildMsgAction(){
        $playerId = $this->getCurrentPlayerId();
        $player = $this->getCurrentPlayer();
        $guildId = $player['guild_id'];
        if(!$guildId) {
            $errCode = 10305;//查看联盟聊天-玩家没有入盟
            goto sendErr;
        }
        $guildMsg = (new ChatUtil)->getAllGuildMsg($guildId);
        echo $this->data->send($guildMsg);
        exit;
        sendErr: {
            echo $this->data->sendErr($errCode);
            exit;
        }
    }
    /**
     * 查看阵营聊天信息
     *
     * ```php
     * common/viewAllCampMsg
     * postData:{}
     * return:{}
     * ```
     */
    public function viewAllCampMsgAction(){
        $player = $this->getCurrentPlayer();
        $campId = $player['camp_id'];
        if(!$campId) {
            $errCode = 10769;//查看阵营聊天-玩家没有阵营
            goto sendErr;
        }
        if($campId>0) {
            $campMsg = (new ChatUtil)->getCampMsg($campId);
        } else {
            $campMsg = [];
        }
        echo $this->data->send($campMsg);
        exit;
        sendErr: {
            echo $this->data->sendErr($errCode);
            exit;
        }
    }
    /**
     * 添加一个黑名单
     *
     * 使用方法如下
     * ```php
     * common/addChatBlack
     * postData:{"black_player_id":222}
     * returnData:{}
     * ```
     */
    public function addChatBlackAction(){
        $playerId      = $this->getCurrentPlayerId();
        $ChatBlackList = new ChatBlackList;
        $postData      = getPost();
        $blackPlayerId = $postData['black_player_id'];
        $flag          = $ChatBlackList->addNew($playerId, $blackPlayerId);
        if($flag) {
            $data['ChatBlackList'] = $ChatBlackList->getByPlayerId($playerId, true);
            echo $this->data->send($data);
            exit;
        } else {
            $errCode = 10306;//添加聊天黑名单:已经加过该玩家
            echo $this->data->sendErr($errCode);
            exit;
        }
    }
    /**
     * 删除一个黑名单
     *
     * 使用方法如下
     * ```php
     * common/removeChatBlack
     * postData: {"black_player_ids":[11,22]}
     * returnData:{}
     * ```
     */
    public function removeChatBlackAction(){
        $playerId       = $this->getCurrentPlayerId();
        $ChatBlackList  = new ChatBlackList;
        $postData       = getPost();
        $blackPlayerIds = $postData['black_player_ids'];
        $flag           = $ChatBlackList->removeBlack($playerId, $blackPlayerIds);
        if($flag) {
            $data['ChatBlackList'] = $ChatBlackList->getByPlayerId($playerId, true);
            echo $this->data->send($data);
            exit;
        } else {
            $errCode = 10307;//删除聊天黑名单:不存在该玩家
            echo $this->data->sendErr($errCode);
            exit;
        }
    }

}

