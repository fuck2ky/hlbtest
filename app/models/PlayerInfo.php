<?php
//玩家详细信息
class PlayerInfo extends ModelBase{
    /**
     * 暂定的冗余字段
     *  level_animation
     *  email
     * @var array
     */
    public $blacklist = ['update_time', 'login_hashcode', 'platform', 'pay_channel'];


    /**
     * 新建一条玩家静态数据表，存非频繁改动的字段
     * param  int $playerId player id 
     */
    public function newPlayerInfo($playerId, $data){
        $self                   = new self;
        $self->player_id        = $playerId;
        $self->login_hashcode   = loginHashMethod($playerId);
        $self->create_time      = $this->update_time = date('Y-m-d H:i:s', time());
        if (isset($data['login_channel'])) {
            $self->login_channel = $data['login_channel'];
        }

        if (isset($data['pay_channel'])) {
            $self->pay_channel = $data['pay_channel'];
        }

        if (isset($data['platform'])) {
            $self->platform = $data['platform'];
        }
        
        $self->save();

        return $self->id; //返回表对应的id
    }

    /**
     * 通过id获取玩家信息
     *
     * return $player array
     */
    public function getByPlayerId($playerId, $forDataFlag=false){
        $r = Cache::getPlayer($playerId, __CLASS__);
        if(!$r) {
            $re = self::findFirst(["player_id=:playerId:", 'bind'=>['playerId'=>$playerId]]);
            if($re) {
                $re = $re->toArray();
                $r = $this->adapter($re, true);
                Cache::setPlayer($playerId, __CLASS__, $r);
            } 
            else {
                return [];
            }
        }

        if($forDataFlag) {
            return filterFields([$r], $forDataFlag, $this->blacklist)[0];
        } 
        else {
            return $r;
        }
    }

    /**
     * 更改player_info表的值
     * param  int $playerId 
     * param  array  $fields  
     */
    public function alter($playerId, array $fields){
        $re = self::findFirst("player_id=$playerId");
        if(!$re) return null;

        if(!array_key_exists('update_time', $fields)){
            $fields['update_time'] = date('Y-m-d H:i:s');
        }
        $re->save($fields);
        $this->clearDataCache($playerId);
		return true;
    }
	
    /**
     * 是否在禁言期间,如果是,则返回禁言截止日期
     * param  int $playerId 
     * return int           
     */
    public function getBanMsgTime($playerId){
        $info = $this->getByPlayerId($playerId);
        $banMsgTime = $info['ban_msg_time'];
        if($banMsgTime && $banMsgTime >= time()) {//禁言ing
            return $banMsgTime;
        }
        return false;
    }
    /**
     * 是否在封号期间,如果是,则返回封号截止日期
     * param  int $playerId 
     * return int           
     */
    public function getBanTime($playerId){
        $info = $this->getByPlayerId($playerId);
        $banTime = $info['ban_time'];
        if($banTime && $banTime >= time()) {//禁言ing
            return $banTime;
        }
        return false;
    }

    /**
     * 是否拥有月卡
     * param  int $playerId 
     * return bool           
     */
    // public function haveMonthCard($playerId){
    //     $re = $this->getByPlayerId($playerId);
    //     $monthCardDeadline = $re['month_card_deadline'];
    //     return ($monthCardDeadline>time()) ? true : false;
    // }

    /**
     * 获取月卡奖励
     * param  int $playerId 
     * return int           
     */
    // public function getMonthCardAward($playerId){
    //     if($this->haveMonthCard($playerId)) {
    //         $re = $this->getByPlayerId($playerId);
    //         $monthCardDate = $re['month_card_date'];
    //         if(date('Y-m-d 00:00:00', $monthCardDate)==date("Y-m-d 00:00:00")) {//当天已经领过
    //             return -2;
    //         } else {//开始领取
    //             $awardNum = (new Starting)->dicGetOne('month_card_daily_reward');
    //             (new Player)->updateGem($playerId, $awardNum, true, '月卡奖励');
    //             $this->alter($playerId, ['month_card_date'=>date('Y-m-d H:i:s')]);
    //         }
    //     } else {
    //         return -1;//没有月卡
    //     }
    //     return 1;//成功领取
    // }
}
