<?php
/**
 * 玩家登录过的服务器列表,用户游戏内玩家切换到其他区
 * 该表位于登录数据库, 确保记录所有游戏服里的玩家
 */
class LoginPlayerLastServer extends ModelBase{

    public function initialize(){
        $this->setConnectionService('db_login_server');
    }

    /**
     * 保存最后一次访问服务器id
     */
    public function saveLast($uuid, $serverId){
        $re        = self::findFirst(["uuid='{$uuid}'"]);
        $className = get_class($this);
        if(!$re) {
            $self                 = new self;
            $self->uuid           = $uuid;
            $self->server_id      = $serverId;
            $self->login_time     = date('Y-m-d H:i:s');
            if($self->save()) {
                $key = $className.'--'.$uuid;
                Cache::dbByName(CACHEDB_PLAYER)->del($key);
            }
        } 
        else {
            if($re->server_id != $serverId) {
                $re->server_id = $serverId;
                $re->login_time = date('Y-m-d H:i:s');
                if($re->save()) {
                    $key = $className.'--'.$uuid;
                    Cache::dbByName(CACHEDB_PLAYER)->del($key);
                }
            }
        }
    }

    /**
     * 获取所有公告
     * @return  array
     */
    public function getByUuid($uuid){
        $className = get_class($this);
        $cache     = Cache::dbByName(CACHEDB_PLAYER);
        $key       = $className . '--' . $uuid;
        $re        = $cache->get($key);
        if(!$re) {
            $re = self::findFirst(["uuid='{$uuid}'"]);
            if($re) {
                $re = $this->adapter($re->toArray(), true);
                $cache->set($key, $re);
            }
        }
        if(!$re) 
            $re = [];

        return $re;
    }
}
