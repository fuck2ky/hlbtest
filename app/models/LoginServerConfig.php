<?php
/*
 * 配置
 *
 */
class LoginServerConfig extends ModelBase{

    public function initialize(){
        $this->setConnectionService('db_login_server');
    }

	public function getValueByKey($key){
        $className = get_class($this);
        $cache = Cache::dbByName(CACHEDB_STATIC);
        $ret = $cache->get($className);
        if (!$ret) {
            $ret = $this->findList('key', 'value');
            $cache->set($className, $ret);
        }

		if(isset($ret[$key]))
			return $ret[$key];
		return null;
	}

    /**
     * @param $key
     * @param $value
     *
     * 保存key value对
     */
	public function saveData($key, $value){
        $className = get_class($this);
        $re        = self::findFirst(["key=:key:", 'bind'=>['key'=>$key]]);
        if($re) {
            $re->key   = $key;
            $re->value = $value;
            $re->save();
        } 
        else { //如果表中找不到则新创建一条记录
            $self        = new self;
            $self->key   = $key;
            $self->value = $value;
            $self->save();
        }
        
        //清缓存
        Cache::dbByName(CACHEDB_STATIC)->del($className);
    }
}