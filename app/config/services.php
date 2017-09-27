<?php

use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Session\Adapter\Files as SessionAdapter;
use Phalcon\Flash\Direct as Flash;

use Phalcon\Logger as Logger;
use Phalcon\Logger\Adapter\File as FileLogger;

/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . "/config/config.php";
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () {
    $config = $this->getConfig();

    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
});

/**
 * Setting up the view component
 */
$di->setShared('view', function () {
    $config = $this->getConfig();

    $view = new View();
    $view->setDI($this);
    $view->setViewsDir($config->application->viewsDir);
    $view->disableLowerCase();  #by hlb 必须禁止啊，否则访问view的文件名全部都被转成小写了!!! fuck!
    $view->registerEngines([
        '.phtml' => PhpEngine::class

    ]);

    return $view;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () {
    $config = $this->getConfig();

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->database->adapter;
    $connection = new $class([
        'host'     => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'charset'  => $config->database->charset
    ]);

    return $connection;
});


/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->setShared('modelsMetadata', function () {
    return new MetaDataAdapter();
});


/**
 * Start the session the first time some component request the session service
 */
$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();

    return $session;
});


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
    return new FileLogger(APP_PATH."/logs/debug.log");
});

$di->setShared('errorEvent', function () {
    return include APP_PATH . "/config/errorEvent.php";
});

/**
 * Register the session flash service with the Twitter Bootstrap classes
 */
$di->set('flash', function () {
    return new Flash([
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning'
    ]);
});

$di->set('db_login_server', function () {
    $config = $this->getConfig();

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->login_server->database->adapter;
    $connection = new $class([
        'host'     => $config->login_server->database->host,
        'username' => $config->login_server->database->username,
        'password' => $config->login_server->database->password,
        'dbname'   => $config->login_server->database->dbname,
        'charset'  => $config->login_server->database->charset
    ]);

    return $connection;
});

$di->set('collectionManager', function(){
    return new Phalcon\Mvc\Collection\Manager();
}, true);


/*
 * redis
*/ 
// $di->setShared('redis', function(){
//     try{
//         $config = $this->getConfig();
//         $redis = new Redis();
//         $c = $config->redis->toArray();
//         $redis->connect($c['host'], $c['port'], $c['timeout']);
//         $redis->setOption(Redis::OPT_PREFIX, $c['prefix']);
//         $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
//         return $redis;
//     } catch(RedisException $e) {
//         $code = $e->getMessage();
//         echo json_encode(['code'=>$code, 'data'=>[], 'basic'=>[]], JSON_UNESCAPED_UNICODE), PHP_EOL;
//         exit;
//     }
// });


//便于某些情况下操作 redis 失败后需要重新连接
function getNewRedisConnect(){
    try{
        global $config;
        //$config = $this->getConfig();
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
