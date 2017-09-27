<?php

class PlayerGemLog extends ModelBase{
    /**
     * 玩家元宝增加记录
     * param  int $playerId player id
     * return bool           sucess or not
     */
    public function add($playerId, $rmbGem, $giftGem, $memo='', $dropId=0){
        $this->player_id = $playerId;
		$this->gem_rmb = $rmbGem;
		$this->gem_gift = $giftGem;
		$this->drop_id = $dropId;
		$this->memo = $memo;
        $this->create_time = date('Y-m-d H:i:s', time());
        return $this->save();
    }
}
