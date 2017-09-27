<?php
/**
 * 会话类
 */
class ServSession {
    public static $table = null; //在 ServerTask分配的一段内存

    /**
     * for test convert $table to json string
     * @return string
     */
    public static function toJSON(){
        $arr = [];
        foreach(self::$table as $v) {
            $arr[] = $v;
        }
        return json_encode($arr);
    }

    public static function getFdByPlayerId($playerId){
        if(!self::$table) return 0;
        $conn = self::$table->get($playerId);
        if($conn) {
            return $conn['fd'];
        }
        return 0;
    }

    public static function getPlayerIdByFd($fd){
        if(!self::$table) return 0;
        foreach(self::$table as $v) {
            if($v['fd']==$fd) {
                return $v['player_id'];
            }
        }
        return 0;
    }

    /**
     * 设置玩家会话信息
     */
    public static function setFd($playerId, $fd){
        if(self::$table) {
            self::$table->set($playerId, ['fd' => $fd, 'player_id' => $playerId]);
        }
    }

    /**
     * 删除fdTable中的玩家会话信息
     * @param  int $fd 
     */
    public static function delLink($fd){
        $playerId = self::getPlayerIdByFd($fd);
        if($playerId>0) {
            self::$table->del($playerId);
        }
    }
}
