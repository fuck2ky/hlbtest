<?php
class Item extends ModelBase{
	//获取所有天赋
	public function dicGetAll(){
		$ret = $this->cache(CACHEDB_STATIC, __CLASS__, function() {
			$ret = $this->findList('id');
			foreach($ret as &$_r){
				$_r = $this->parseColumn($_r);
			}
			unset($_r);
			return $ret;
		});
		return $ret;
	}
	
	public function parseColumn($_r){
		$_r['drop'] = parseGroup($_r['drop'], false); //把drop解析成数组
		//$_r['use'] = parseArray($_r['use']);
		return $_r;
	}
	
  /**
   * 获取道具加速时间
   * 
   * param <type> $itemId 
   * param <type> $type 加速类型：1.建筑；2.造兵；3.医疗；4.研究;5.陷阱
   * 
   * return <type>
   */
	// public static function getAcceSecond($itemId, $type){
	// 	$Item = new Item;
	// 	$item = $Item->dicGetOne($itemId);
	// 	if(!$item) {
	// 		return false;
	// 	}
	// 	$iaid = $item['item_acceleration'];
	// 	$ItemAcceleration = new ItemAcceleration;
	// 	$ia = $ItemAcceleration->dicGetOne($iaid);
	// 	if(!$ia) {
	// 		return false;
	// 	}
	// 	if($ia['type'] && $ia['type'] != $type){
	// 		return false;
	// 	}
	// 	return $ia['item_num']*1;
	// }

  //武将碎片
	public static function getAllPieceIds(){
		$ids = Cache::dbByName(CACHEDB_PLAYER)->get(__CLASS__ . ':getAllPieceIds');
		if(!$ids){
			$data = self::find(['item_type=4'])->toArray();
			$ids = Set::extract('/id', $data);
			Cache::dbByName(CACHEDB_PLAYER)->set(__CLASS__ . ':getAllPieceIds', $ids);
		}
		return $ids;
	}

}