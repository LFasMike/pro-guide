灰度流程
./jump phpgray
/var/www/env.master.prod/app/app.git


11.20
访问接口：
开发环境 API_ROOT = 172.16.164.220:3201

测试环境 API_ROOT = 172.16.164.248:3201

STAG 172.16.164.182:3201

VPC生产环境  API_ROOT = 172.16.163.178:8001

客户端访问地址
开发环境 API_ROOT = http://gateway-dev.ly  gou.cc

测试环境 API_ROOT = http://gateway-test.lyg  ou.cc

预发布  API_ROOT = http://gateway-stag.ly  gou.cc

生成环境 API_ROOT = http://gateway.ly   gou.cc

9.20
查询redis 及时约派单大神池 的数据 {品类id  性别id}
zrange JSYPaiDan:{44}:{1}:Gods 0 -1

8.31
app仓库，php灰度机标识  IS_HUIDU=1
位置：~/.bash_profile


8.26
查看当前服务器php版本信息
phpbrew list

laravel 线上添加异步队列queue 库 
步骤如下：
php artisan queue:table  
php artisan queue:failed-table
注意：这里写到了app数据库中
php artisan migrate
Php artisan  queue:work
Php artisan  queue:restart

8.15
删除服务器发布脚本的缓存目录：
rm -rf /alidata/neutronbuild.....

8.6
先查看服务状态，
sudo service supervisord status
配置文件位置：vim /etc/supervisor.d/....
go服务 /home/land/etc/conf.d/.....
编辑后记得要 supervisorctl reload
最后在重启任务  supervisorctl restart order-test:

检查任务列表，找到任务名称就可以重启
supervisorctl status
重启 任务
supervisorctl restart brush-prod:

   7.31

删除下架品类缓存数据
PHP order仓库  app/Models/PlayGame.php
在artisan 中执行 php artisan tinker  =》   PlayGame::resetRedisGames()

   7.23
   重启phpbrew
   sudo service phpbrew-fpm-56 restart
   当前有哪些版本：
   phpbrew list

   7.12

   常用任务命令：
   App\Models\ModeGame::query()->select('id')->where('gouhao',80268132)->get();

   App\Models\PlayGodAcceptSetting::refreshGod()

   读取所有余额账户
    prod_god_lyg_order_only_read order_pay

   SELECT * FROM `purse_account` WHERE (`gou_liang` > '10000') AND (`phone` IS NOT NULL) AND (`phone` LIKE '1000000%') AND (`gou_hao` IS NOT NULL) AND (`gou_hao` != '0') AND (`identity_card` LIKE '') AND (`favor` = '0') ORDER BY `phone` LIMIT 0,1000;

   
   5.21
   supervisor日志目录：
   /var/log/supervisor/supervisord.log

   4.21
   1 读取redis 要选对应库，还要注意key的前缀，不然导致后果就是查不到数据!!!

   2 连接 测试服务器的redis需要在服务器上连接，本地是没有权限的。

   3 连接go服务器 api 返回有信息，要注意请求方式get / post

   4  http_proxy=http://172.31.0.82:9090 https_proxy=http://172.31.0.82:9090
   代理忽略： 192.168.0.0/16、10.0.0.0/8、127.0.0.1、localhost、*.local、172.16.0.0/12

   
   5、数据库连接不上，请用curl ip 检查当前网络，可能不是公司内网

   6 ios 接收不到消息解决方案 1、将当前设备重启下，使得用户token绑定成功，  2、 在app设置中将消息通知开关打开即可。

   队列系统：
   // 以下的任务将被委派到默认队列...
   dispatch(new Job);

   // 以下任务将被委派到 "emails" 队列...
   dispatch((new Job)->onQueue('emails'));

   默认到high高优先级队列中  先高级后低级
   php artisan queue:work --queue=high,low

   创建任务
   php artisan make:job ProcessPodcast

   延时分发任务
    ProcessPodcast::dispatch($podcast)->delay(now()->addMinutes(10));

   分发到指定队列
    ProcessPodcast::dispatch($podcast)->onQueue('processing');

   最大失败次数
    php artisan queue:work --tries=3
   或者在job类中加变量

        * The number of times the job may be attempted.
        *
        * @var int
        */
       public $tries = 5;

   
   php artisan queue:restart

   删除所有失败任务
   php artisan queue:flush

   
   根据订单查对应游戏账户id
   get O:{20190508145129485411129066371}:GameAccount

   
   
   狗好    id             phone 
    11959048  1896  10000001313 个人常用大神  ab9bc018ef64fcc77fcfa15a6f72378c
    12248261  1992576   10000001086 个人常用大神 9d3e82865336a1e78ed734ca9c3e03eb

prod自己的 狗号 79597782
Id 206749673

E57929B0-B386-41F7-9D05-400B9BAF94A4
6A9C6AC2-CB0A-43BF-94F5-8CDA8BE0937C
6778C8BCFC0C4974A66D1CFDB5AC4EDC

另一个  狗号：
id ˜  79371117

   12447327       1998658  16602112573
    
   10000     1992031  10000000009

   13215398  2000856  100000019

   10029783  282  10000000008

   11958387  1992429  10000001416

   12266070  1992428  10000001415

   10064379   234  10000000102

   11972206       1469    10000002001
   11958387       1992429   10000000025

    12999870   1999564  10000003012

    11975779   1475 10000001003  不是大神

   10004170  160  10000000046
   10057595 152  10000000026

   10012123  164  10000000099

   11938656  13922936870  1992326

测试环境ı
1 魔兽世界_old
2 DOTA2
4 英雄联盟
9 守望先锋001
14  300英雄
15  王者荣耀
19  绝地求生
23  无限法则
31  QQ飞车
32  和平精英
33  声优聊天
34  声音鉴定
35  虚拟恋人
36  第五人格
37  音乐
38  小游戏
39  堡垒之夜
40  使命召唤
43  APEX英雄
44  连麦观影
45  说方言
46  小闹钟
47  情感咨询
48  Dota自走棋
49  明星大神
50  云顶之弈
51  故事电台
52  多多自走棋
53  王牌战士
54  魔兽世界
55  王者模拟战
100 租个女友
118 测试品类
142 热血传奇
