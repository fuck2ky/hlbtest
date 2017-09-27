<?php
class Drop extends ModelBase{
	public $except = array();
	public $resource = [
		['id'=>10100, 'name'=>'黄金', 'consume'=>true],
		['id'=>10200, 'name'=>'粮食', 'consume'=>true],
	];

	public function dicGetAll(){
		$ret = $this->cache(CACHEDB_STATIC, __CLASS__, function() {
			$ret = $this->findList('id');
			foreach($ret as &$_r){
				$_r['drop_data'] = explode(';', $_r['drop_data']);
				foreach($_r['drop_data'] as &$__r){
					$__r = explode(',', $__r);
				}
				unset($__r);
			}
			unset($_r);
			return $ret;
		});
		return $ret;
	}
	
	//概率掉落
	public function rand($playerId, $dropIds, &$dropType=0){
		$dropType = 2;

		//获得主公等级
		$Player = new Player;
		$player = $Player->getByPlayerId($playerId);
		if(!$player) {
			return false;
		}
		
		//循环
		$dropCfg = [];
		foreach($dropIds as $_id){
			$drop = $this->dicGetOne($_id);
			if(!$drop){
				continue;
			}
			$dropCfg[] = $drop;
		}

		if(!@$dropCfg){
			return [];
		}

		$ret = array();
		foreach($dropCfg as $_drop){
			//计算掉落概率1
			if(lcg_value1() > $_drop['rate'] / 10000){ //如果概率范围内掉落只往下走
				continue;
			} 
			//计算掉落概率2
			$keys = array();
			if($_drop['drop_type'] == 1 || $_drop['drop_type'] == 4){
				$rates = array();
				if(!$_drop['drop_data']) {
					continue;//防止粗心的策划没配数据！fuck
				}
				foreach($_drop['drop_data'] as $_k => $_d){
					//设置概率
					$rates[$_k] = $_d[3];//如果报错,可能drop多了一个分号在结尾
				}

				if(!$rates) {
					continue;
				}

				$i = 0;
				while($i < $_drop['drop_count']*1){
					if(!$rates) {
						return false;
					}
					$_key = random($rates);
					@$keys[$_key]++;
					// if($_drop['drop_data'][$_key][0] == 2 && Item::isGodFragment($_drop['drop_data'][$_key][1])){
					// 	unset($rates[$_key]);
					// 	$myGodGeneralItemIds[] = $_drop['drop_data'][$_key][1]*1;
					// }
					$i++;
				}
			}
			else{
				$keys = array_combine(array_keys($_drop['drop_data']), array_fill(0, count($_drop['drop_data']), 1));
			}
			foreach($keys as $_k => $_i){
				$_tmp = $_drop['drop_data'][$_k];
				$_tmp[2] *= $_i;
				$ret[] = $_tmp;
			}
			
		}
		if(!$ret)
			return [];
		return $ret;
	}
	
  /**
   * 获得掉落的道具
   * 
   * param <type> $playerId 
   * param <array|int> dropId数组 
 	 * param <int> num 数量
   * param <type> 说明
   * 
   * return <type>
   */
	public function gain($playerId, $dropIds, $num=1, $memo='', $extra=[]){
		if(!is_array($dropIds)) {
			$dropIds = array($dropIds);
		}

		$i = 0;
		$dropData = array();
		while($i < $num){
			$_dropData = $this->rand($playerId, $dropIds, $dropType);
			if(!$_dropData) {
				return false;
			}

			if(in_array($dropType, [2, 3])){ //整组掉落
				$dropData = $_dropData;
				foreach($dropData as &$_d){
					$_d[2] *= $num;
				}
				unset($_d);
				break;
			}
			else{
				$dropData = array_merge($dropData, $_dropData);
			}
			$i++;
		}

		//整理道具，增加发送速度
		$gainItems = array();
		foreach($dropData as $_dropData){
			list($_type, $_itemId, $_num, $_rate) = $_dropData;
			@$gainItems[$_type][$_itemId] += $_num;
			
			// //白银特殊处理
			// if($_type == 1 && $_itemId == 10600){
			// 	$extra['silverSplit'] = $num;
			// }
		}
		if(!$memo){
			$memo = 'fromDrop:['.join(',', $dropIds).']|num:'.$num;
			$extra['dropId'] = @$dropIds[0];
		}
		return $this->_gain($playerId, $gainItems, $memo, $extra);
	}
	

	public function _gain($playerId, $gainItems, $memo='', $extra=[]){
		//获取
		$Player = new Player;
		$PlayerItem = new PlayerItem;
		// $PlayerEquipment = new PlayerEquipment;
		// $PlayerEquipMaster = new PlayerEquipMaster;
		$PlayerCommonLog = new PlayerCommonLog;
		$Item = new Item;
		$Equipment =  new Equipment();
		$dropItems = array();

		foreach($gainItems as $_type => $_gainItems){
			foreach($_gainItems as $_itemId => $_num){

				switch($_type){
					case 1://资源：粮草、黄金
						$resource = array();
						$gem = 0;
						if($_itemId == '10200'){//粮食
							$resource['food'] = $_num;
						}
						elseif($_itemId == '10100'){//黄金
							$resource['gold'] = $_num;
						}
						if($resource){
							if(!$Player->updateResource($playerId, $resource)){
								return false;
							} 
						}
						if($gem){
							if(!$Player->updateGem($playerId, $gem, true, $memo, @$extra['dropId'])){
								return false;
							}
						}
					break;

					case 2://道具
						if(!$PlayerItem->add($playerId, $_itemId, $_num)){
							return false;
						}
						$_item = $Item->dicGetOne($_itemId);
						$PlayerCommonLog->add($playerId, ['type'=>$memo.'['.$_item['desc1'].']', 'memo'=>['num'=>$_num]]);
					break;

					case 4://装备
						if(!$PlayerEquipment->add($playerId, $_itemId, $_num)){
							return false;
						}
						$_equip = $Equipment->dicGetOne($_itemId);
						$PlayerCommonLog->add($playerId, ['type'=>$memo.'['.$_equip['desc1'].']', 'memo'=>['num'=>$_num]]);
					break;

					case 7://主公宝物
						$i = 0;
						while($i < $_num){
							if(!$PlayerEquipMaster->newPlayerEquipMaster($playerId, $_itemId)){
								return false;
							}
							$i++;
						}
					break;

					default:
						return false;
				}
				$dropItems[] = array('type'=>$_type, 'id'=>$_itemId, 'num'=>$_num);

			}
		}
		return $dropItems;
	}

	public function gainFromDropStr($playerId, $dropStr, $memo=''){
		$dropData = parseGroup($dropStr, false);
		//整理道具，增加发送速度
		$gainItems = array();
		foreach($dropData as $_dropData){
			list($_type, $_itemId, $_num) = $_dropData;
			@$gainItems[$_type][$_itemId] += $_num;
		}
		return $this->_gain($playerId, $gainItems, $memo);
	}

	public function setExcept($playerId, $except = array()){
		@$this->except[$playerId] = $except;
	}
	
	public function getGeneralPieceExcept($playerId){
		$General = new General;
		$pieceExceptIds = [];
		//获取所有武将
		$PlayerGeneral = new PlayerGeneral;
		$generalIds = $PlayerGeneral->getGeneralIds($playerId);
		$rootIds = [];
		foreach($generalIds as $_generalId){
			$rootIds[] = $General->getRootId($_generalId);
		}
		
		//获取武将配置
		$generals = $General->dicGetAll();
		$gs = [];
		foreach($generals as $_g){
			if(!$_g['piece_item_id']) continue;
			$gs[$_g['piece_item_id']] = $_g;
			if(in_array($_g['root_id'], $rootIds)){
				$pieceExceptIds[] = $_g['piece_item_id']*1;
			}
		}
		
		//获取背包信物
		$Item = new Item;
		$pieceIds = $Item->getAllPieceIds();
		
		$PlayerItem = new PlayerItem;
		$playerItem = $PlayerItem->getByPlayerId($playerId);
		foreach($playerItem as $_pi){
			if(!in_array($_pi['item_id'], $pieceIds) || in_array($_pi['item_id'], $pieceExceptIds)) continue;
			if($_pi['num'] >= $gs[$_pi['item_id']]['piece_required']){
				$pieceExceptIds[] = $gs[$_pi['item_id']]['piece_item_id']*1;
			}
		}
		
		//$pieceExceptIds = array_values(array_unique($pieceExceptIds));
		@$this->except[$playerId][2] = $pieceExceptIds;
		return $pieceExceptIds;
	}

	public function getTranslateInfo($dropStr, $toStr=false, $join="\r\n"){
		if(!is_array($dropStr)){
			$drop = parseGroup($dropStr, false);
		}else{
			$drop = $dropStr;
		}
		
		$ret = [];
		$resource = array_combine(Set::extract('/id', $this->resource), Set::extract('/name', $this->resource));
		foreach($drop as $_d){
			switch($_d[0]){
				case 1:
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'基础资源',
						'name'=>$resource[$_d[1]],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 2:
					$item = (new Item)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'道具',
						'name'=>$item['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 3:
					$general = (new General)->getByGeneralId($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'武将',
						'name'=>$general['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 4:
					$Equipment = (new Equipment)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'武将装备',
						'name'=>$Equipment['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 5:
					$Buff = (new Buff)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'静态buff',
						'name'=>$Buff['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 6:
					$BuffTemp = (new BuffTemp)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'静态buff',
						'name'=>$BuffTemp['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 7:
					$EquipMaster = (new EquipMaster)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'主公宝物',
						'name'=>$EquipMaster['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
				case 8:
					$Soldier = (new Soldier)->dicGetOne($_d[1]);
					$ret[] = [
						'id'=>$_d[1],
						'type'=>'士兵',
						'name'=>$Soldier['desc1'],
						'num'=>$_d[2],
						'type_id'=>$_d[0],
					];
				break;
			}
		}
		
		if($toStr){
			$str = [];
			foreach($ret as $_r){
				$str[] = $_r['name'] . 'x' . $_r['num'];
			}
			$ret = join($join, $str);
		}
		return $ret;
	}
}