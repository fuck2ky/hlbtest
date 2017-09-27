<?php
/**
 * 登录服数据库中的服务器列表
 */
class LoginServerList extends ModelBase{
    public function initialize(){
        $this->setConnectionService('db_login_server');
    }

    /**
     * 字典表获取所有
     */
    public function dicGetAll(){
        $ret = Cache::dbByName(CACHEDB_STATIC)->hGetAll(__CLASS__);
        if(!$ret){
            $ret = $this->adapter($this->findList('id'));
            Cache::dbByName(CACHEDB_STATIC)->hMset(__CLASS__, $ret);
        }

        $ret = Set::sort($ret, '{n}.id', 'asc');
        return $ret;
    }

    /**
     * 根据serverId返回game_server_ip
     * @param $serverId
     *
     * @return string
     */
    public function getGameServerIpByServerId($serverId){
        $allLoginServerList = $this->dicGetAll();
        $gameServerIp = '';
        foreach($allLoginServerList as $v) {
            if($v['id']==$serverId) {
                $gameServerIp = trim($v['game_server_ip']);
                if(empty($gameServerIp)) {//ip不存在的情况下用 host，确保不出错
                    $gameServerIp = $v['game_server_host'];
                }
                break;
            }
        }
        return $gameServerIp;
    }

    /**
     * 根据serverId返回gameServerHost
     * @param $serverId
     *
     * @return string
     */
    public function getGameServerHostByServerId($serverId){
        $allLoginServerList = $this->dicGetAll();
        $gameServerHost = '';
        foreach($allLoginServerList as $v) {
            if($v['id']==$serverId) {
                $gameServerHost = $v['gameServerHost'];
                break;
            }
        }
        return $gameServerHost;
    }

    /**
     * @param $id
     * @param $field
     * @param $value
     *
     * @return bool
     *
     * 更改字段
     */
    public function alterServerList($id, $field, $value){
        $re = self::findFirst($id);
        if($re) {
            $re->{$field} = $value;
            $re->save();
            Cache::dbByName(CACHEDB_STATIC)->del(__CLASS__);
            return true;
        }
        return false;
    }

    /**
     * @param $id
     *
     * @return bool
     *
     * 更改维护状态
     */
    public function alterDefaultEnter($id){
        $re = self::findFirst($id);
        if($re) {
            $this->updateAll(['default_enter'=>0], [1=>1]);
            $re->default_enter = 1;
            $re->save();
            Cache::dbByName(CACHEDB_STATIC)->del(__CLASS__);
            return true;
        }
        return false;
    }

    /**
     * 更改所有服状态
     * @param $status
     */
    public function alterAllStatus($status) {
        $this->updateAll(['status'=>$status], [1=>1]);
        Cache::dbByName(CACHEDB_STATIC)->del(__CLASS__);
    }
    
}