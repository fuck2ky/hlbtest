<?php
/**
 * 数据回传类
 */
class Data {
    /**
     * 成功code码
     */
    const SUCCESS = 0;

    //php执行时间预警关闭

    //在ControlerBase中初始化，如果没有则直接返回错
    public $playerId = 0;

    //通知前端的basic码
    public $basic = [];
	
	public $extra = [];
	
	
    /**
     * commit后需要清除的缓存
     */
	public $datas = [];
	
    /**
     * 黑名单
     */
	public $blacklist = ['PlayerDrawCard', 'Map', 'PlayerCommonLog', 'PlayerCosumeLog', 'PlayerGemLog', 'PlayerOrder'];

    /**
     * 手动更改player_id
     */
    public function setPlayerId($playerId) {
        $this->playerId = $playerId;
    }

    /**
     * 设置需要更改的基础数据
     * param string $basic e.g. Player, PlayerGeneral
     */
    public function setBasic($basic) {
        if(is_array($basic)) {
            $this->basic = array_unique(array_merge($this->basic, $basic));
        } 
        else {
            $this->basic[] = $basic;
            $this->basic = array_unique($this->basic);
        }
    }
    
    /**
     * 过滤器
     * 
     * param <type> $whiteList 
     * param <type> $reverse  true:whiteList为黑名单；false：whiteList为白名单
     * 
     * return <type>
     */
    public function filterBasic($whiteList=array(), $reverse = false) {
        $ar = array();
        foreach($this->basic as $_basic){
			if($reverse){
				if(!in_array($_basic, $whiteList)){
					$ar[] = $_basic;
				}	
			}
            else{
				if(in_array($_basic, $whiteList)){
					$ar[] = $_basic;
				}			
			}
        }
        $this->basic = $ar;
    }
    /**
     * 发送错误码
     * 
     * param  int $err error code
     * return string      json string
     */
    public function sendErr ($err) {
        if(QA) {
            $errMsg  = '';
            $errcode = (new ErrorCode)->dicGetOne($err);
            if ($errcode) {
                $errMsg = $errcode['zhcn'];
            }
            $data = json_encode(['code' => $err, 'errMsg' => $errMsg, 'data' => [], 'basic' => []], JSON_UNESCAPED_UNICODE);
        } 
        else {
            $data = json_encode(['code' => $err, 'data' => [], 'basic' => []], JSON_UNESCAPED_UNICODE);
        }
        
        $this->basic = [];//清空
		$this->extra = [];
        if(isset($_POST['inner'])) { //如果是内部访问则不加密,以便进一步封装数据
            return $data;
        }
        $data = encodeResponseData($data);
        return $data;
    }

    /**
     * 发送数据给客户端
     */
    public function send(array $data=[], $filter = false) {
        $this->playerId = 111; //test
        if($this->playerId) 
        {
            $code = self::SUCCESS;
            if(is_array($filter))
            {
                $this->filterBasic($filter);
            }
            //$this->basic = array_unique($this->basic);
            //$basic = DataController::get($this->playerId, $this->basic);
            $sendData = json_encode(['code'=>$code, 'data'=>$data, 'basic'=>[], 'extra'=>[]], JSON_UNESCAPED_UNICODE);
            //logSend($this->playerId, json_decode($sendData, true));
            $this->basic = [];//清空
            $this->extra = [];

            if(isset($_POST['inner'])) { //如果是内部访问则不加密,以便进一步封装数据
                return $sendData;
            }
            $sendData = encodeResponseData($sendData); 
            return $sendData; 
        } 
        else 
        {
            trace();
            exit('[ERROR]not exists playerId When Send Data');
        }
    }

	/**
     * 发送原始数据       
     * param  array  $data 
     * return string
     */
    public function sendRaw(array $data) {
        $data        = json_encode(['code' => self::SUCCESS, 'data' => $data, 'basic' => [], 'extra'=>$this->extra]);
        $this->basic = [];//清空
		$this->extra = [];
        if(isset($_POST['inner'])) { //如果是内部访问则不加密,以便进一步封装数据
            return $data;
        }
        $data = encodeResponseData($data);
        return $data;
    }
}
