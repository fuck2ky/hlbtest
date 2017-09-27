<?php
class MainTask extends \Phalcon\CLI\Task {
    private $map = null;


    public function mainAction() 
    {
        echo "=======mainAction\r\n";
        $this->console->handle(array('task'=>'main', 'action'=>'test'));
    }

    public function testAction() 
    {
        echo "=======testAction\r\n";
    }


    /**
     * 模拟客户端请求
     */
    public function simulateClientRequestAction()
    {
            //post参数
            $data = ['combo'=>[
                ['url'=>'king/getinfo','field'=>[]],
                ['url'=>'guild/comboguildmemberinfo','field'=>[]],
                ['url'=>'lottery/checkplayerlotteryinfo','field'=>[]],
                ['url'=>'limit_match/showlimitmatch','field'=>[]],
                ['url'=>'player/getbuff','field'=>[]],
            ]];
            //请求链接url
            $url  = 'common/combo';
            $uuid = '1459665_dsuc';
            $re   = simulateClientPostRequest($uuid, $url, $data);
            dump($re, 1);
            exit;

    }


	/**
	 * 安全清除cache脚本
	 * /usr/local/php/bin/php /opt/htdocs/sanguomobile2/app/cli.php main clearDictAndPlayerCache
	 */
	public function clearDictAndPlayerCacheAction()
    {
		global $config;
		$redisIndex = $config->redis->index->toArray();
		$needClearIndex = ['cache', 'static', 'bufftemp', 'login_server'];
		foreach($redisIndex as $k=>$v) {
			if(in_array($k, $needClearIndex)) {
				echo "Clear cache[{$k}={$v}]: ";
				Cache::db($k)->flushDB();
				echo "ok!\n";
			}
		}
	}




	
	public function startServerAction()
    {
        $this->map = new Map;
		set_time_limit(0);
		global $config;
		echo "当前的server_id为 " . color($config->server_id, 'red', 1) . ", 请确认app.ini里的server_id已经正确更改过了?" . color("[输入yes继续/输入no退出]", 'brown'). PHP_EOL;		
		$remFlag = trim(fgets(STDIN));
		if($remFlag=='no') {
			echo "还好客官记起了,这就给您退了开服程序\n";
			exit;
		} elseif($remFlag=='yes') {
			echo "您记性真好!\n";
		} else {
			echo "输入非法:{$remFlag},开服不能!\n";
			exit;
		}

		//1 执行sql清空数据
		echo "开始清空表\r\n";
		$sqlPath = APP_PATH.'/app/tools/ResetAllPlayerData_BackupFirst.sql';
		$f = file_get_contents($sqlPath);
		$fs = explode(';', $f);
		$ModelBase = new ModelBase;
		foreach($fs as $_f){
			if(trim($_f) == '') continue;
			echo $_f."\r\n";
			$ModelBase->sqlExec($_f);
		}
		
		Cache::clearAllCache();
		
		//2 map表数据添加
		echo "map表数据添加\r\n";
		include(APP_PATH.'/app/tools/map_generate/map_shell.php');
		// system("php ".str_replace(['\\', 'tasks'], ['/', ''], __DIR__)."tools/map_generate/map_shell.php", $ret);
		
		//3 player_buff表从buff表导入字段
		echo "player_buff表从buff表导入字段\r\n";
		$re = (new Buff)->dicGetAll();
        $re = Set::sort($re, "{n}.id", "asc");

        $re1= (new PlayerBuff)->sqlGet('DESC `player_buff`');
        $re1 = Set::combine($re1, '{n}.Field', '{n}.Type');
        //$sql2 = '';            
        foreach($re as $k=>$v) {
            if($v['name'] && !array_key_exists($v['name'], $re1)) {
                $sql2 = "ALTER TABLE  `player_buff` ADD  `{$v['name']}` INT( 11 ) DEFAULT  '{$v['starting_num']}' COMMENT '{$v['desc1']}'";
				$ModelBase->sqlExec($sql2);
            }
        }
		
		//创建mongo索引
		//$this->createMongoIndex();
		
		//执行初始化脚本
		echo "执行初始化脚本\r\n";
		$this->mainAction();
	}



    /**
     * 模拟客户端长连
     *
     * ```
     *  php cli.php main client 1000234
     * ```
     * @param $params
     */
    public function clientAction($params)
    {
        if(count($params)<1) {
            echo "msgId:" . var_export(StaticData::$msgIds, true),PHP_EOL;
            log4cli('Please Input playerId:');
            $playerId = fgets(STDIN);
        } else {
            $playerId = $params[0];
        }
        if(!iquery("select id from player where id={$playerId}")) {//Not Exists!
            exit("playerId={$playerId} Not Exists!");
        }

        //connect first
        global $config;
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $config->swoole->host, $config->swoole->port);

        //case a :登录
        $data = ['player_id'=>$playerId, 'hash_code'=>hashMethod($playerId)];
        $sendData = packData($data, 10000);
        socket_write($socket, $sendData, strlen($sendData));

        log4task('Login...');
        sleep(1);
        while($loginResp=socket_read($socket, 12)) {
            $loginResp = unpackData($loginResp);
            if($loginResp['msgId']!=10001) {
                log4task("login fail");
                return;
            }
            log4task("login success");
            break;
        }
        log4task('receiving data...');
        //每秒接受数据
        while(true) {
            try {
                //case b: 心跳包
                $hbData = packData([], 10002);
                socket_write($socket, packData([], 10002), strlen($hbData));

                //case c: receive数据
                $recvDataOrigin = socket_read($socket, 12);//3字节数据
                if(strlen($recvDataOrigin)>0) {//收到数据
                    $recvData = unpackData($recvDataOrigin);
                    $length   = $recvData['length'];
                    if ($length > 12) {
                        $_buf = $recvDataOrigin;
                        $_buf     .= socket_read($socket, $length);
                        $recvData = unpackData($_buf);
                        $log      = 'response msgId:' . $recvData['msgId'] . ' ---> content:' . arr2str($recvData['content']);
                        log4task($log);
                    }
                }
                sleep(1);
            } catch(Exception $e) {
                log4task($e, 1);
                break;
            }
        }
        socket_close($socket);
    }
}