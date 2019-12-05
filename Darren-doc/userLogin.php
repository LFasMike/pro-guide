<?php
namespace app\modules\app\controllers;

use app\common\CurlUtil;
use app\modules\app\components\FallManager;
use app\modules\app\components\UserUtils;
use app\modules\app\Services\UserService;
use Elasticsearch\ClientBuilder;
use laoyuegou\model\app\IndexFeed;
use laoyuegou\model\app\PlayGodGames;
use laoyuegou\model\app\PlayGods;
use laoyuegou\model\app\UserApp;
use laoyuegou\redis\Redis;
use Yii;
use yii\helpers\ArrayHelper;

class TasksController extends AppBaseController {


//连接等级    /task/SetHotGods?user_id=9
//连接等级    /task/SetNewStarGods?user_id=9
    public function actionIndex(){
        echo 'test is connect！';
        return true;
    }

    public function actionSetHotGods($limit = 900, $offset = 0)
    {
        $women = $this->getHotDataByes(1);
        $men = $this->getHotDataByes(2);
    }

    public function getScore($H, $T, $A = 36000, $t = 28800)
    {
        $scale = 4; //保留小数点位数
        $score = bcadd(log($H), ($T - $t) / $A, $scale);
        return $score;
    }

    //获取热门公式中 T 值
    public function getTime($time){
        if(strpos('.',$time)>0){
            $before = explode('.', $time)[0];
        }else{
            $before = explode('+', $time)[0];
        }
        list($data, $times) = explode('T', $before);
        $god_day = explode('-', $data)[2];
        $T = 0;
//        $god_day = 20;
        //如果当天没有接单，
        $day = date('d');
        if ((int)$god_day == (int)$day) {
            list($hours, $minutes, $seconds) = explode(':', $times);
            $hour = $hours * 3600;
            $minute = $minutes * 60;
            $second = $seconds;
            $total_seconds = $hour * 3600 + $minute * 60 + $second;
            $T = $total_seconds - 0;
        }
        return $T;
    }

    public function redis(){
        $redis = Redis::i('redisUser');
        return $redis;
    }
    public function clientES()
    {
        $host = [
            [
                'host' => Yii::$app->params['appConfig']['elasticSearch'],
                'port' => '9200',
                'scheme' => 'http',
                'user' => Yii::$app->params['appConfig']['es_user'],
                'pass' => Yii::$app->params['appConfig']['es_pass'],
            ]
        ];

        $client = ClientBuilder::create()->setHosts($host)
            ->setRetries(2)
            ->build();


        return $client;
    }

    private function getHotDataByes($sex){

        $client = $this->clientES();
        $redis = $this->redis();

        //数据分页
        $size = 2000;
        $from = 0;

        $sort[] = ['lfo' => ['order' => 'desc']];
        $sort[] = ['lts' => ['order' => 'desc']];

        $filter[] = ['range' => ['reject_order' => [
            'lt'=>4
        ]]]; //拒绝接单数小于4

        $params = [
            'index' => Yii::$app->params['appConfig']['es_index_redefine'],
            'type' => 'peiwan_stats',
            'client' => ['ignore' => [400, 404]],
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['gender' => $sex]],
                            ['match' => ['peiwan_status' => 1]],
                        ],
                        'filter'=>$filter
                    ]
                ],
                'sort' => $sort,
            ],
            'from' => $from,
            'size' => $size
        ];

        $back_data = $client->search($params)['hits'];

        $responses = $back_data['hits'];

        $s = intval(date('i') / 10) + 1;

        $redis_key_man = 'hot_gods_' . date('d') . '_' . date('H') . '_' . $s.'_man';
        $redis_key_woman = 'hot_gods_' . date('d') . '_' . date('H') . '_' . $s.'_woman';

        $unique_ids = [];
        foreach ($responses as $key => $response) {
            $tmp = $responses[$key]['_source'];

            $god_id = $tmp['god_id'];
            $game_id = $tmp['game_id'];

            //ES去重 大神数据
            if(in_array($god_id,$unique_ids)){
                continue;
            }else{
                $unique_ids[] = $god_id;
            }

            $play_god = PlayGods::findOne(['userid' => $god_id]);
            //做筛选 : 账户异常和冻结
            if ($play_god['status'] != 1) {
                continue;
            }

            $user = UserApp::find()
                ->where(['id' => $god_id])
                ->one();
            if(is_null($user)){
                continue;
            }

            $god_game = PlayGodGames::find()
                ->where(['userid' => $god_id, 'gameid' => $game_id])
                ->one();


            //获取关注数
            $counts = UserUtils::getUserFollowCount($god_id);
            $count = $counts['vermicelli_count'];

            $H = $god_game['god_level'] * 10 + $count * 5;

            //优先取最近完成订单时间，没有则取最近登录时间
            $T = 0;
            if (isset($tmp['lfo'])) {
                $T = $this->getTime($tmp['lfo']);
            }


            $god_score = $this->getScore($H,$T);

            $month = date('m', time()) - date('m', $user->birthday);
            $month_add = $month > 0 ? 1 : 0;

            $array = [
                'id'=> $response['_id'],
                'aac'=> $god_game['aac'],
                'age'=>empty($user->birthday) ? '' : ((date('Y', time()) - date('Y', $user->birthday) - $month_add)),
                'game_id'=> $game_id,
                'god_id'=> $god_id,
                'gl'=> 't_'.$god_score,//狗粮
                'god_icon'=> $user->avatar(),
                'god_name'=> $user['username'],
                'imgs'=> json_decode($god_game['images'],true),
                'order_cnt'=> $tmp['order_cnt'],
                'order_cnt_desc'=> '', //
                'room_id'=> '', //
                'sex'=> $tmp['gender'],
                'status'=> $user['status'],
                'status_desc'=> $tmp['peiwan_status'],
                'uniprice'=> 0, //
                'voice'=> $god_game['voice'],
                'voice_duration'=> $god_game['voice_duration'],

            ];


            $json = json_encode($array);

            if($tmp['gender'] == 2){
                $re = $redis->zadd($redis_key_woman,$god_score,$json);
            }else{
                $re = $redis->zadd($redis_key_man,$god_score,$json);
            }
        }

        if($sex == 1){
            $redis->expire($redis_key_man, 60*26);
            $redis->expire($redis_key_man.'_ids', 60*26);

        }elseif($sex ==2){

            $redis->expire($redis_key_woman, 60*26);
            $redis->expire($redis_key_woman.'_ids', 60*26);
        }
    }

}