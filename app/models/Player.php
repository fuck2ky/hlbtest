<?php
/**
 * 玩家表-model
 */
class Player extends ModelBase{
    public static $basicInfo = ['nick','level'];//远程调用玩家基础信息
    public $blacklist = array('uuid');

    /**
     * 获取玩家最后在线时间
     * param  int $playerId 
     * return int
     */
    public static function getPlayerOnlineInfo($playerId) {
        $re = Cache::dbByName(CACHEDB_SWOOLE)->hGet(REDIS_KEY_ONLINE, $playerId);
        if($re) {
            return $re;
        }
        return 0;
    }

    /**
     * 通过 uuid 获取玩家信息 
     * param  string $uuid uuid from frontend
     * return array       data of player
     */
    public function getPlayerByUUID($uuid){//注册后，换手机登录，此时要修改这个cache
        $key = "uuid:{$uuid}";
        $cache = Cache::dbByName(CACHEDB_PLAYER);
        $playerId = $cache->get($key);
        $player = null;

        if($playerId) {
            $player = $this->getByPlayerId($playerId);
        } 
        else {
            $re = self::findFirst(["uuid='{$uuid}'"]);
            if($re){
                $playerId = $re->id;
                $cache->set($key, $playerId);
                $cache->setTimeout($key, 3600);//过期  60*60  #1 hour
                $player = $this->getByPlayerId($playerId);
            }
        }
        return $player;
    }
    
    /**
     * 通过id获取玩家信息
     *     
     * param  int  $id
     * param  boolean $forDataFlag is or not for dataController
     * return array formated data
     */
    public function getByPlayerId($id, $forDataFlag=false) {
        if(!$id){
            trace();
            exit("\n[ERROR]!!!NOT EXISTS Player. id=!!-> {$id} <-!! .[输入了不存在的玩家id]\n");
        }
        $player = Cache::getPlayer($id, __CLASS__);
        if(!$player) {
            try {
                $player = self::findFirst($id);
            } 
            catch(PDOException $e) {
                $player = (new self)->findFirst($id);
            }

            if($player) {
                $player = $player->toArray();
                $player = $this->afterFindPlayer($player);
                Cache::setPlayer($id, __CLASS__, $player);
            } 
            else {
                trace();
                exit("\n[ERROR]!!!NOT EXISTS Player. id=!!-> {$id} <-!! .[输入了不存在的玩家id]\n");
            }
        }

        $player['last_online_time'] = self::getPlayerOnlineInfo($id);
        if(!$player['last_online_time']) {
            $player['last_online_time'] = $player['login_time'];
        }

        if($forDataFlag) {
            return filterFields([$player], $forDataFlag, $this->blacklist)[0];
        } 
        else {
            return $player;
        }
    }

    public function getByPlayerNick($name, $forDataFlag=false){
        $player = self::findFirst(['nick="'.$name.'"']);
        if(!$player)
            return false;
        return $this->getByPlayerId($player->id, $forDataFlag);
    }

    public function afterFindPlayer($player){
        $player = $this->adapter($player, true);

        //新手保护修正
        // if($player['fresh_avoid_battle_time'] !== false && $player['fresh_avoid_battle_time'] > $player['avoid_battle_time']){
        //     $player['avoid_battle_time'] = $player['fresh_avoid_battle_time'];
        // }
        return $player;
    }
    

    /**
     * 生成玩家后的初始化操作，建筑，兵等
     * param  int $playerId 
     */
    public function initAfterNewPlayer($playerId){
        //默认道具
        // $PlayerItem = new PlayerItem;
        // $str = $Starting->getValueByKey("default_item");
        // $itemList = sanguoDecodeStr($str);
        // foreach ($itemList as $key => $value) {
        //     $PlayerItem->add($playerId, $key, $value);
        // }

        //新手保护
        // $buffTime = $Starting->getValueByKey("avoid_battle_default_time");
        // $this->setFreshAvoidBattleTime($playerId, $buffTime);
        // (new PlayerTarget)->getByPlayerId($playerId);//初始化新手目标

        //创建酒馆
        // $PlayerPub = new PlayerPub;
        // $PlayerPub->add($playerId);
        //clear cache here
        $this->clearDataCache($playerId);
    }

    /**
     * 检查是否存在相同nick
     * param  string $nick 昵称
     * return bool 
     */
    public function checkNickExists($nick){
        $re = self::findFirst(["nick=:nick:", 'bind'=>['nick'=>$nick]]);
        if($re) {
            return true;
        }
        return false;
    }

    /**
     * 根据nick搜索玩家
     * param  string $nick 昵称
     * return array       
     */
    public function searchByNick($nick, $searchData=[]){
        $nick       = addslashes($nick);
        $q          = $this->query();
        $fromPage   = $searchData['from_page'];
        $numPerPage = $searchData['num_per_page'];
        $re         = $q->where("nick like :nick: and uuid not like :uuid:")->bind(['nick'=>"%{$nick}%", 'uuid'=>"%{Robot}%"])->columns(['id', 'nick', 'level'])->limit($numPerPage, $fromPage*$numPerPage)->execute();
        $r          = $this->adapter($re->toArray());
        if($r) {
            return $r;
        } else {
            return [];
        }
    }

    /**
     * 生成唯一标识码
     * return string 
     */
    public function getRandomString() {
        while(true){
            $s          = "";
            $characters = "23456789ABCDEFGHJKMNPQRSTUVWXYZ";
            for($i=0; $i<6; $i++) {
                $s .= $characters[mt_rand(0, strlen($characters)-1)];
            }
            $player = self::findFirst("user_code='{$s}'");
            if(!$player) break;
        }
        
        return $s;
    }

    /**
     * 生成新玩家
     * 
     *$postData: ['uuid'=>'uuid', 'avatar_id'=>1, 'nick'=>'nick']
     * ```
     * param  array $postData post data for create a player
     */
    public function newPlayer($postData){
        //随机生成一个玩家名
        while(true) {
            $nick = 'sg'.uniqid();
            if(!$this->checkNickExists($nick)) {
                break;
            }
        }

        global $config;
        $self                    = new self;
        $self->user_code         = $this->getRandomString();
        $self->uuid              = $postData['uuid'];
        $self->nick              = $nick;
        // $self->server_id         = $config->server_id;
        // $self->avatar_id         = 1;//$postData['avatar_id']; 这里写死
        // $starting                = (new Starting)->dicGetAll();//获取玩家初始化配置数据
        // $self->level             = $starting['player_level'];
        // $self->gold              = $starting['gold_starting'];
        // $self->food              = $starting['food_starting'];
        // $self->wood              = $starting['wood_starting'];
        // $self->stone             = $starting['stone_starting'];
        // $self->iron              = $starting['iron_starting'];
        // $self->silver            = $starting['silver_starting'];
        // $self->food_out          = $starting['food_out'];
        // $self->gem_gift          = $starting['gem_gift'];
        // $self->power             = $starting['power'];
        $self->login_time        = $self->create_time = date('Y-m-d H:i:s');
        
        $self->save();
        
        $playerId                = $self->id;
        $ret                     = self::findFirst($playerId)->toArray();

        (new PlayerInfo)->newPlayerInfo($playerId, $postData);//创建玩家静态表
        $this->initAfterNewPlayer($playerId);

        return $ret;
    }

    /**
     * 更改player表的值
     * param  int $playerId 
     * param  array  $fields  
     */
    public function alter($playerId, array $fields){
        $ret = $this->updateAll($fields, ['id'=>$playerId]);
        $this->clearDataCache($playerId);
        return $ret;
    }



    /**
     * 取模糊数字
     * param  int $number e.g. 12345, 1234
     * return int   10000, 1000
     */
    private function getFuzzyNumber($number){    
        $numeric = strval($number);
        if(strlen($numeric)==1) return 10;
        return intval($numeric[0].str_repeat('0', strlen($numeric)-1));
    }

    /**
     * 获取军团的模糊数字
     * param  int $number 
     * return int         
     */
    private function getFuzzyArmyNumber($number){
        if(in_array($number, range(0,3))) return '1~3';
        if(in_array($number, range(4,7))) return '4~7';
        if(in_array($number, range(8,11))) return '8~11';
        if(in_array($number, range(12, 16))) return '12~16';
        if(in_array($number, range(17, 25))) return '17~25';
    }

    /**
     * 删除玩家 
     * 
     * param  int $playerId 
     */
    public function deletePlayer($id){
        $re = self::findFirst($id);
        $re->delete();
        $this->clearDataCache($id);//清缓存
        Cache::dbByName(CACHEDB_PLAYER)->delete("uuid:".$re->uuid);
        return $re->toArray();
    }


    /**
     * 元宝变更
     * 
     * param <type> $playerId 
     * param <type> $point 增加/扣除元宝
     * param <type> $giftFlag  true：gift；false：rmb
     * param <type> $recordVars  元宝日志
     * 
     * return bool
     */
    public function updateGem($playerId, $point, $giftFlag = true, $recordVars = array(), $dropId=0){
        if($point < 0) {
            $_point = abs($point);
            if(false === $giftFlag){
                $sql = 'UPDATE player SET
                `gem_rmb` = `gem_rmb` - @sub_gem_rmb := IF(`gem_rmb` >= '.$_point.', '.$_point.', `gem_rmb`),
                `gem_gift` = `gem_gift` - (@sub_gem_gift := '.$_point.' - @sub_gem_rmb)
                WHERE `id` = '.$playerId;       
            }
            else {
                $sql = 'UPDATE player SET
                `gem_gift` = `gem_gift` - @sub_gem_gift := IF(`gem_gift` >= '.$_point.', '.$_point.', `gem_gift`),
                `gem_rmb` = `gem_rmb` - ('.$_point.' - @sub_gem_gift)
                WHERE `id` = '.$playerId;
            }
            $sql .= ' AND `gem_rmb`+`gem_gift` >='.$_point;
        }
        else {
            $sql = 'UPDATE player SET '.
            ($giftFlag ? '`gem_gift` = `gem_gift` + '.$point : '`gem_rmb` = `gem_rmb` + '.$point).'
            WHERE `id` = '.$playerId;
        }
        debug('sql====: '. $sql);
        if(!$this->sqlExec($sql)){
            return false;
        }

        //增加/消费记录
        if($point < 0 && false !== $recordVars){
            $d = $this->sqlGet('select @sub_gem_gift');
            $subGiftPoint = $d[0]['@sub_gem_gift']*1;
            $subRmbPoint = $_point - $subGiftPoint*1;
            if(is_array($recordVars)){
                $cost = @$recordVars['cost']*1;
                $memo = @$recordVars['memo'].'';
            }
            else{
                $cost = 0;
                $memo = @$recordVars;
            }
            (new PlayerConsumeLog)->add($playerId, $subRmbPoint, $subGiftPoint, $cost, $memo);
        }
        elseif($point > 0 && false !== $recordVars){
            $addRmbPoint = 0;
            $addGiftPoint = 0;
            if($giftFlag){
                $addGiftPoint = $point;
            }
            else{
                $addRmbPoint = $point;
            }
            (new PlayerGemLog)->add($playerId, $addRmbPoint, $addGiftPoint, $recordVars, $dropId);
        }
        
        $this->clearDataCache($playerId);//清缓存
        return true;
    } 

    /**
     * 资源变更
     * param  int $playerId 
     * param  array  $resource ['gold'=>9, 'food'=>-10, 'wood'=>11, 'stone'=>12, 'iron'=>13]
     */
    public function updateResource($playerId, array $resource){
        if(empty($resource)) return false;

        $condition = ['id'=>$playerId];
        foreach($resource as $k=>&$v) {
            if($v==0)  {
                unset($resource[$k]);
                continue;
            }
            elseif($v>0) {
                $v = $k . '+' . abs($v);
            } 
            else {
                $condition[$k.' >='] = abs($v);
                $v = $k . '-' . abs($v);
                $v = "IF({$v}<0,0,{$v})";
            }
        }
        unset($v);
        
        $this->affectedRows = $this->updateAll($resource, $condition);
        if($this->affectedRows>0) {
            $this->clearDataCache($playerId);//清缓存
            return true;
        }
        return false;
    } 
}
