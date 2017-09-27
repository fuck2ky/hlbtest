<?php
//道具
class PlayerItem extends ModelBase{
	public $blacklist = array('player_id', 'create_time', 'update_time', 'rowversion');
	public function beforeSave(){
		$this->update_time = date('Y-m-d H:i:s');
		$this->rowversion = uniqid();
	}
	
	public function afterSave(){
		$this->clearDataCache();
	}
	
  /**
   * 新增道具
   * 
   * param <type> $playerId 
   * param <type> $itemId 
   * 
   * return <type>
   */
	public function add($playerId, $itemId, $num=1){
		$isNew = 0;
		// if($itemId >= 40000 && $itemId <= 50000){
		// 	$isNew = 0;
		// }else{
		// 	$isNew = 1;
		// }
		$o = new self;
		if(!$o->find(array('player_id='.$playerId. ' and item_id='.$itemId))->toArray()){
			$ret = $o->create(array(
				'player_id' => $playerId,
				'item_id' => $itemId,
				'num' => $num,
				'is_new' => $isNew,
				'create_time' => date('Y-m-d H:i:s'),
				//'rowversion' => '',
			));
			if(!$ret)
				return false;
		}
		else{
			$now = date('Y-m-d H:i:s');
			$ret = $o->updateAll(array(
				'num' => 'num+'.$num,
				'is_new' => $isNew,
				'update_time'=>"'".$now."'",
				'rowversion'=>"'".uniqid()."'"
			), array("player_id"=>$playerId, "item_id"=>"'".$itemId."'"));
		}

		$o->clearDataCache($playerId);
		socketSend(['Type'=>'item', 'Data'=>['playerId'=>[$playerId]]]);
		return $o->affectedRows();
	}
		
  /**
   * 丢弃道具
   * 
   * param <type> $playerId 
   * param <type> $itemId 
   * 
   * return <type>
   */
	public function drop($playerId, $itemId, $num=1){
		$o = $this->findFirst(array('player_id='.$playerId. ' and item_id='.$itemId.' and num>='.$num));
		if(!$o){
			return false;
		}
		else{
			$data = $o->toArray();
			if($data['num'] == $num){
				$o->delete();
				if(!$o->affectedRows()){
					return false;
				}
			}
			else{
				$now = date('Y-m-d H:i:s');
				$ret = $this->updateAll(array(
					'item_id'=>$itemId,
					'num' => 'num-'.$num,
					'update_time'=>"'".$now."'",
					'rowversion'=>"'".uniqid()."'"
				), array("player_id"=>$playerId, "item_id"=>"'".$itemId."'", "num >="=>$num));

				if(!$ret){
					return false;
				}
			}

			(new PlayerCommonLog)->add($playerId, ['type'=>'使用道具', 'item_id'=>$itemId, 'num'=>$num, 'name'=>(new Item)->dicGetOne($itemId)['desc1']]);
		}
		$this->clearDataCache($playerId);
		return true;
	}
	
	public function hasItemCount($playerId, $itemId){
		$data = $this->getByPlayerId($playerId);
		foreach($data as $_data){
			if($_data['item_id'] == $itemId){
				return $_data['num'];
			}
		}
		return 0;
	}
	
	public function setNew($playerId, $isNew){
		$now = date('Y-m-d H:i:s');
		$ret = $this->updateAll(array(
			'is_new' => $isNew,
			'update_time'=>"'".$now."'",
			'rowversion'=>"'".uniqid()."'"
		), array("player_id"=>$playerId));

		$this->clearDataCache($playerId);
		return true;
	}
}