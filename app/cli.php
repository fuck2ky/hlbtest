<?php

use Phalcon\DI\FactoryDefault\CLI as CliDI,
    Phalcon\CLI\Console as ConsoleApp,
	Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
	Phalcon\Logger as Logger,
    Phalcon\Logger\Adapter\File as FileLogger,    
	Phalcon\Events\Manager as EventsManager;

$eventsManager = new EventsManager();
define('VERSION', '1.0.0');

// 使用CLI工厂类作为默认的服务容器
$di = new CliDI();

//app目录
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__)));


/**
 * 常量文件
 */
include APPLICATION_PATH . "/config/constant.php";

include APPLICATION_PATH . "/util/Common.php";


/**
 * 注册类自动加载器
 */
$loader = new \Phalcon\Loader();
$loader->registerDirs(
    array(
        APPLICATION_PATH . '/tasks',
        APPLICATION_PATH . '/util',
        APPLICATION_PATH . '/models',
    )
);
$loader->register();

// 加载配置文件（如果存在）
if (is_readable(APPLICATION_PATH . '/config/config.php')) 
{
    $config = include APPLICATION_PATH . '/config/config.php';
    $di->set('config', $config);

	/**
	 * 响应数据处理类
	 */
	$di->setShared('data', function () {
		return new Data();
	});
    /**
     * 用于文件log输出
     */
    $di->setShared('debug', function(){
        return new FileLogger(APPLICATION_PATH."/logs/debug.log");
    });

    $di->setShared('errorEvent', function () {
        return include APP_PATH . "/config/errorEvent.php";
    });

    /**
     * 数据库适配器
     */
    $di->set('db', function() use ($config){
        $connection = new DbAdapter($config->database->toArray());
        return $connection;
    });
    
    $di->set('db_login_server', function() use ($config){
        $connection = new DbAdapter($config->login_server->database->toArray());
        return $connection;
    });
    
    $di->set('collectionManager', function(){
        return new Phalcon\Mvc\Collection\Manager();
    }, true);

    /*
     * redis
    */ 
    $di->set('redis', function() use ($config){
        try{
            $redis = new Redis();
            $c = $config->redis->toArray();
            $redis->connect($c['host'], $c['port'], $c['timeout']);
            $redis->setOption(Redis::OPT_PREFIX, $c['prefix']);
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            return $redis;
        } catch(RedisException $e) {
            $code = $e->getMessage();
            echo json_encode(['code'=>$code, 'data'=>[], 'basic'=>[]], JSON_UNESCAPED_UNICODE), PHP_EOL;
            exit;
        }
    });

    $cliFlag = true;
    $redisSharedFlag = false;
	$di->set('collectionManager', function(){
		return new Phalcon\Mvc\Collection\Manager();
	}, true);

	// $di->set('mongo', function() use ($config){
	// 	$mongo = new MongoClient($config->mongo->host.":".$config->mongo->port);
	// 	return $mongo->selectDB($config->mongo->db[0]);
	// }, true);
}


// 创建console应用
$console = new ConsoleApp();
$console->setDI($di);

$di->setShared('console', $console);

//便于某些情况下操作 redis 失败后需要重新连接
function getNewRedisConnect(){
    try{
        global $config;
        $redis = new Redis();
        $c = $config->redis->toArray();
        $redis->connect($c['host'], $c['port'], $c['timeout']);
        $redis->setOption(Redis::OPT_PREFIX, $c['prefix']);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        return $redis;
    } catch(RedisException $e) {
        $code = $e->getMessage();
        echo json_encode(['code'=>$code, 'data'=>[], 'basic'=>[]], JSON_UNESCAPED_UNICODE), PHP_EOL;
        exit;
    }
}

/**
 * 处理console应用参数
 */
$arguments = array();
foreach ($argv as $k => $arg) {
    if ($k == 1) {
        $arguments['task'] = $arg;
    } elseif ($k == 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

// 定义全局的参数， 设定当前任务及动作
define('CURRENT_TASK',   (isset($argv[1]) ? $argv[1] : null));
define('CURRENT_ACTION', (isset($argv[2]) ? $argv[2] : null));

try {
    // 处理参数
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    echo $e->getMessage();
    exit(255);
}


//Cache::close();
