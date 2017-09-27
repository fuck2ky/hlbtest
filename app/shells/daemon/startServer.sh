#!/bin/sh
nohup /usr/local/hlb/php/bin/php /usr/local/games/hlbtest/app/cli.php server > /usr/local/games/hlbtest/app/logs/swoole.log 2>&1 &
