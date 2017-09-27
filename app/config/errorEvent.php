<?php

return [
  'VersionError'     => 9000, //版本号不一致
  'ForceOffline'     => 9001, //账号在其他地方登陆,您被挤下线
  'CancelAccount'    => 9002, //被封号
  'UnderMaintenance' => 9003, //服务器维护中,非授权的ip登入游戏
  'TimeNotSync'      => 9004, //时间未同步
  'CreatePlayerFail' => 9005, //创建玩家失败
  'InvalidTimestamp' => 9006, //无效的时间戳(可能为恶意重发)
];