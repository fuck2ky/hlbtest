<?php
/**
 * 走马灯
 *
 */
class RoundMessage extends ModelBase{
    /**
     * param int $playerId
     * param array $data
     *
     * return null
     *
     * ```php
     * type:
     *  0: 系统消息
     *  1: 战斗相关
     *
     * 添加走马灯数据, RoundMessageTask会起一定时器15秒getRoundMessage()轮询推送.(有几种情况同时推送到聊天界面里)
     * 
     * $data['type'] = 1;
     * $data['...'] = ...;
     * $RoundMessage = new RoundMessage;
     * $RoundMessage->addNew($playerId, $data);
     * ```
     */

    // 举例：
    //     $rmdata['item_id']     = $dropItemId;
    //     $rmdata['player_nick'] = $player['nick'];
    //     $RoundMessage->addNew($playerId, ['type'=>8, 'data'=>$rmdata]);//走马灯公告
    public function addNew($playerId, array $data){
        $self              = new self;
        
        $self->player_id   = $playerId;
        if($data['type']!=0) {
            $Player            = new Player;
            $player            = $Player->getByPlayerId($playerId);
            $self->player_nick = $player['nick'];
        }

        foreach($data as $k=>$v) {
            if($k=='data') {
                $self->$k = json_encode($v, JSON_UNESCAPED_UNICODE);
            } 
            else {
                $self->$k = $v;
            }
        }
        $self->create_time = date('Y-m-d H:i:s');
        $self->save();


        //如下几种情况同时推送到聊天界面里
        // $pushData = ['type'=>0];
        // switch($self->type){
        //     case 2://招募武将
        //         $generals = (new General)->getAllByOriginId();
        //         $general  = $generals[$self->general_id];
        //         if($general['general_quality']>4) {
        //             $pushData['type']       = 2;
        //             $pushData['general_id'] = intval($self->general_id);
        //         }
        //         break;
        //     case 4://武器进阶
        //         $pushData['type']         = 3;
        //         $pushData['equipment_id'] = intval($self->equipment_id);
        //         break;
        // }
        // if($pushData['type'] != 0) {
        //     $data = ['Type'=>'world_chat', 'Data'=>['player_id'=>$playerId, 'content'=>'', 'pushData'=>$pushData]];
        //     socketSend($data);
        // }
    }

    /**
     * 获取消息
     * return array
     */
    public function getRoundMessage(){
        $alistNum = 100;
        $blistNum = 10;

        //先读取GM消息
        $re = self::find(['type=0', 'order'=>'create_time asc', 'limit'=>$alistNum])->toArray();
        if(!$re) {
            $redis = Cache::dbByName(CACHEDB_PLAYER);
            $key = 'roundMessage_Current_Type';
            
            Data:
            $currentType = $redis->get($key);
            $atypeFlag = false;
            if(!$currentType || $currentType=='B') {
                $redis->set($key, 'A');
                $re = self::find(['type=1', 'order'=>'create_time desc', 'limit'=>$alistNum])->toArray();
                if(count($re)==1) {
                    $doNotUpdateFlag = true;
                }
                $atypeFlag = true;
            } 
            else {
                $re = self::find(['type<>1 and type<>0', 'order'=>'create_time desc', 'limit'=>$blistNum])->toArray();
                $redis->set($key, 'B');
                if(empty($re)) {
                    goto Data;
                }
            }
        }

        $re = array_reverse($re);
        //消息处理
        foreach($re as $k=>$v) {
            $v['data'] = json_decode($v['data'], true);//解开data的json格式
            if($v['status']==0) {
                continue;
            } 
            else {
                if(isset($doNotUpdateFlag)) {//最后一条A记录
                    return $this->adapter($v, true);
                }
                $createTime = $v['create_time'];
                
                if(!isset($atypeFlag)) {//删无效公告
                    self::find("create_time<='{$createTime}' and type=0")->delete();
                } 
                else {
                    if($atypeFlag) {//删无效A
                        self::find("create_time<='{$createTime}' and type=1")->delete();
                    } 
                    else {//删无效B
                        self::find("create_time<='{$createTime}' and type<>0 and type<>1")->delete();
                    }
                }
                return $this->adapter($v, true);
            }
        }
    }
}
