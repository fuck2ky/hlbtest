<?php
/**
 * 静态数据(数组)类
 */
class StaticData {
    public static $_url        = '';
    public static $adminQAFlag = false; //QA下接口访问
    public static $_postData   = [];
    
    /**
     * 包头定义格式
     * @var array
     */
    public static $msgIds = [//msgId定义
                             'LoginRequest'           => 100,//登陆包请求
                             'LoginResponse'          => 101,//登陆包响应
                             'HeartBeatRequest'       => 102,//心跳包请求
                             'HeartBeatResponse'      => 103,//心跳包响应
                             'HeartBeatPauseRequest'  => 104,//心跳包暂停请求
                             'HeartBeatPauseResponse' => 105,//心跳包暂停响应                             
                             'DataRequest'            => 106,//
                             'DataResponse'           => 107,//通用的数据交互
                             'ChatSendRequest'        => 108,//聊天包请求
                             'ChatSendResponse'       => 109,//聊天包响应                             
                             'WebServerRequest'       => 500,//web服务器包请求
                             'WebServerResponse'      => 501,//web服务器包响应                             

    ];


    public static $msgIdMap = [//msgId 映射关系
        'LoginRequest'     => 'LoginResponse',
        'HeartBeatRequest' => 'HeartBeatRequest',
        'DataRequest'      => 'DataResponse',
        'WebServerRequest' => 'WebServerResponse',
        'ChatSendRequest'  => 'ChatSendResponse',
        'PauseServerHeartBeatReq' => 'No_Response',
    ];
    public static $logConfig = [//swoole log 长连接配置
                                'port'            => 6789,
                                'worker_num'      => 3,
                                'daemonize'       => false,
                                'dispatch_mode'   => 2,
                                'max_request'     => 10,
    ];

    public static $delaySocketSendFlag = false;//延迟发送标记
    public static $delaySocketSendData = [];//延迟发送的数据
}
