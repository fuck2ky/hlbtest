<?php
/*
 * Modified: preppend directory path of current file, because of this file own different ENV under between Apache and command line.
 * NOTE: please remove this comment.
 */

defined('QA') || define('QA', false);//QA 环境 默认false,调试时手动改为true(允许网页get访问,方便调试), 在ControllerBase.php/auth()
defined('BASE_PATH') || define('BASE_PATH', getenv('BASE_PATH') ?: realpath(dirname(__FILE__) . '/../..'));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');

return new \Phalcon\Config([
    'login_server' => [
        /**
        * 登录服的数据库配置
        */
        'database' => [
            'adapter'     => 'Mysql',
            'host'        => 'localhost', //登录服地址
            'username'    => 'root',
            'password'    => 'root',
            'dbname'      => 'hlb_login',
            'charset'     => 'utf8',
        ],
    ],

    //本游戏服配置
    'server_id'       => 1, //对应于login数据,表login_server_list中的字段id , 表示第几区(服)
    'database' => [
        'adapter'     => 'Mysql',
        'host'        => 'localhost',
        'username'    => 'root',
        'password'    => 'root',
        'dbname'      => 'hlbtest',
        'charset'     => 'utf8',
    ],
    'application' => [
        'appDir'         => APP_PATH . '/',
        'controllersDir' => APP_PATH . '/controllers/',
        'modelsDir'      => APP_PATH . '/models/',
        'migrationsDir'  => APP_PATH . '/migrations/',
        'viewsDir'       => APP_PATH . '/views/',
        'utilDir'        => APP_PATH . '/util/',
        'taskDir'        => APP_PATH . '/task/',        
        'pluginsDir'     => APP_PATH . '/plugins/',
        'libraryDir'     => APP_PATH . '/library/',
        'cacheDir'       => BASE_PATH . '/cache/',
        'baseUri'        => '/',
    ],

    /**
     * 游戏服务器redis配置
     */
    'redis' => [
        'host'       => '127.0.0.1', //游戏服务器redis服务器地址
        'port'       => 6379, //游戏服务器redis服务器端口
        'timeout'    => 0, //游戏服务器redis 过期失效时间
        'persistent' => false, //redis是否支持持久化
        'prefix'     => 'rds_', //redis的key值默认前缀
        'index'      => [
            'cache'        => 0, //用户数据,开发中用的比较多的db
            'static'       => 1, //字典表
            'server'       => 2, //存客户端连接上swoole的socket信息
            'bufftemp'     => 3, //buff_temp表存储
            'chat'         => 4, //聊天信息存储
        ],
    ], 

    //长连接配置
    'swoole' => [
        'host'           => '192.168.216.131',//'127.0.0.1', //swoole服务器地址,填写真实的外部地址(linux主机地址), 不是127.0.0.1
        'port'           => 9501,        //swoole服务端口
        'server_setting' => [
            'worker_num'        => 4,//启动的worker进程数, 官方建议设置为CPU的1-4倍最合理
            'task_worker_num'   => 4, //配置task进程的数量，配置此参数后将会启用task功能
            'daemonize'         => true, //守护进程化, 标准输入和输出会被重定向到 log_file 
            'dispatch_mode'     => 2, //根据连接的文件描述符分配worker。这样可以保证同一个连接发来的数据只会被同一个worker处理 
            'log_file'          => APP_PATH . '/logs/swoole.log', //指定swoole错误日志文件
            'max_request'       => 2000,// 防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启 (注: 0,不自动重启)
        ]
    ],


]);
