<?php
/**
 * 玩家每日在线时长统计表-model, 目前只在长连接中进行维护
 */
class PlayerOnline extends ModelBase{
    
    /**
     * 首次连接成功写入表
     * param  int $playerId 
     */
    
    public function setRecord($playerId){       
        $self    = new self;
        $self->player_id = $playerId;
        $self->date = date("Y-m-d");        
        $self->online = 0;      
        $self->save();
    }
    
    /**
     * 查询某日是否已经建立记录
     * param  int $playerId 
     *         string $date
     * return array record data
     */
    
    public function getRecord($playerId, $date){
        try {
            $res = self::findFirst(["player_id={$playerId} and date='{$date}'"]);
            return $res;
        } 
        catch(PDOException $e) {
            echo "################ PDOException:" . __METHOD__ . ":" . __LINE__,PHP_EOL;

            global $di, $config;
            $di['db']->connect($config->database->toArray());

            try {
                echo "+++++++++++++++++++++++重连中。。。。。。。\n";
                $res = self::findFirst(["player_id={$playerId} and date='{$date}'"]);
                echo "+++++++++++++++++++++++重连成功。。。。。。。\n";
                return $res;
            } catch(PDOException $e) {
                echo "--------------- PDOException:" . __METHOD__ . ":" . __LINE__,PHP_EOL;
                trace();
            }

            return null;
        }
    }
    
    /**
     * 更新时长
     * param  int $playerId 
     *         string $date
     * return true/false       
     */
    public function updateRecord($playerId, $date, $fields){
        $res = $this->updateAll($fields, ['player_id'=>$playerId,'date'=>getQuoteStr($date)]);
        return $res;
    }
}
