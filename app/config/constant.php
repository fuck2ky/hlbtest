<?php 

/**
 * 常量存于此处，常量不要重复，以功能命名分类，注释需要写的详尽
 */
//顶级常量
defined('ENCODE_FLAG') || define('ENCODE_FLAG', true);    //对url进行加密压缩

defined('DEBUG_LOG_ON') || define('DEBUG_LOG_ON', true);  //debug log文件开启
defined('CLI_LOG_ON') || define('CLI_LOG_ON', true);      //log4cli方法显示开关
defined('ACCESS_LOG_FLAG') || define('ACCESS_LOG_FLAG', false); //访问log开关

//swoole 长连接消息头标识
defined('SWOOLE_MSG_HEAD') || define('SWOOLE_MSG_HEAD', "SGMB");//数据包头的验证码

//redis 缓存定义
defined('CACHEDB_PLAYER') || define('CACHEDB_PLAYER', 'cache');
defined('CACHEDB_STATIC') || define('CACHEDB_STATIC', 'static');
defined('CACHEDB_SWOOLE') || define('CACHEDB_SWOOLE', 'server');
defined('CACHEDB_CHAT') || define('CACHEDB_CHAT', 'chat');

defined('REDIS_KEY_ONLINE') || define('REDIS_KEY_ONLINE', 'ServOnline');  //玩家在线时间戳key
defined('REDIS_KEY_ONLINE_MAX') || define('REDIS_KEY_ONLINE_MAX', 5*60);  //超过时间算离线
