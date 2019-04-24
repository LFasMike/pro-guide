<?php
/**
 * Created by PhpStorm.
 * User: maoluanjuan
 * Date: 22/08/2018
 * Time: 21:09
 */

class Groups extends BaseModel
{


    function getMarkName()
    {
        $name = $this->name;
        return substr($name, 0, 16);

    }


    function toSimpleJson($manager_id = '')
    {
        #判断是否是群主
        if ($this->isManager($manager_id)) {
            $is_manager = 1;
        } else {
            $is_manager = 0;
        }

        $group_icon = \StoreFile::getUrl($this->group_icon . '@!small');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'people_num' => $this->people_num,
            'is_manager' => $is_manager,
            'manager_id' => $this->manager_id,
            'status' => $this->status,
            'verify_status' => $this->verify_status,
            'avatar_urls' => $this->avatar_urls,
            'group_icon' => $group_icon,
            'ban_status' => $this->ban_status,
            'is_set_name' => $this->is_set_name,
            'gid' => $this->gid,
        ];
    }


    function isManager($user_id = '')
    {
        return $user_id == $this->manager_id;
    }

    function isVerifyStatusOff()
    {
        return GROUP_VERIFY_STATUS_OFF == $this->verify_status;
    }

    function getAvatarUrls()
    {
        $avatar_urls = explode(',', $this->icon_url);
        return $avatar_urls;
    }

    function getUserListKey()
    {
        return 'group_user_list_' . strval($this->id);
    }

    static function createGroup($manager_id, $user_ids)
    {
        $redis = \Groups::getHotReadCache();
        $lock_key = GROUP_CREATE_LOCK . $manager_id;
        if (!$redis->setnx($lock_key, 1)) {
            return [ERROR_CODE_FORM, 'cannot_create_group_in_5', []];
        }
        $redis->expire($lock_key, 5);

        if (!is_array($user_ids)) {
            $user_ids = explode(',', $user_ids);
        }
        #将群主自己加入第一位
        array_unshift($user_ids, $manager_id);

        if (count($user_ids) < 2 || isBlank($manager_id)) {
            return [ERROR_CODE_FORM, 'param_error', []];
        }
        $group = new \Groups();
        $group->manager_id = $manager_id;
        $group->verify_status = GROUP_VERIFY_STATUS_OFF;
        $group->status = GROUP_STATUS_ON;
        $group->ban_status = GROUP_BAN_OFF;
        $group->is_set_name = GROUP_SET_NAME;
        $group->recommend = GROUP_RECOMMEND_OFF;

        $group->gid = self::generateGid();
        if ($group->save() && isPresent($user_ids)) {
            \GroupUsers::addUsers($user_ids, $group, $manager_id, GROUP_ADD_USER, false);
        }
        if (isBlank($group)) {
            return [ERROR_CODE_FAIL, 'create_group_fail_contact', []];
        }

        $group = $group->toSimpleJson($manager_id);

        debug($group);
        return [ERROR_CODE_SUCCESS, 'success', $group];
    }

    static function editGroup($param = [], $id, $manager_id)
    {
        if (isBlank($id) || isBlank($param)) {
            return [ERROR_CODE_FORM, 'param_error'];
        }
        if (is_array($param)) {
            if (implode(',', $param) === '') {

                return [ERROR_CODE_FORM, 'param_error'];
            }
        }
        $group = \Groups::findById($id);
        if (isBlank($group)) {
            return [ERROR_CODE_FAIL, 'group_not_exists'];
        } else {
            if ($group->status == GROUP_STATUS_OUT) {
                return [ERROR_CODE_FAIL, 'group_not_exists'];
            }
        }
        if (!$group->isManager($manager_id)) {
            return [ERROR_CODE_FAIL, 'not_king'];
        }

        foreach ($param as $key => $value) {
            if ($key == 'name') {
                $censor_words = censor_word();
                $blacklist = "/" . implode("|", $censor_words) . "/i";
                if (preg_match_all($blacklist, $value, $matches)) {
                    if (isPresent(array_filter($matches[0]))) {
                        return [ERROR_CODE_FAIL, 'sensitive_word'];
                    }
                }
                $group->is_set_name = GROUP_SET_NAME_OPEN;

            }

            if ($key == 'manager_id') {
                $old_id = $group->manager_id;

                $ssdb = self::getXRedis($group->id);

                $key_id = GROUP_USER_IDS . $group->id;

                $cond = ['group_id' => $group->id, 'user_id' => $old_id];
                $group_user = \GroupUsers::findFirstBy($cond);
                if (isPresent($group_user)) {
                    $score = $group_user->created_at;
                    $ssdb->zadd($key_id, $score, $old_id);

                } else {
                    debug('此群没用该用户');
                    $ssdb->zrem($key_id, $old_id);
                }

                $ssdb->zadd($key_id, 1, $value);

            }
            debug('成功修改group: ', $key, $value);
            $group->$key = $value;
        }
        if ($group->save()) {
            return [ERROR_CODE_SUCCESS, 'success'];
        }
        return [ERROR_CODE_FAIL, 'fail'];
    }

    static function agree($chat_id, $manager_id)
    {
        if (isBlank($chat_id) || isBlank($manager_id)) {
            return [ERROR_CODE_FORM, 'param_error', ''];
        }

        $chat = \Chats::findById($chat_id);

        if (!$chat) {

            return [ERROR_CODE_FORM, 'param_error', ''];
        }

        #不在待审核状态都不予处理
        if ($chat->group_user_review != GROUP_USER_REVIEW_PENDING) {

            return [ERROR_CODE_FAIL, 'status_wrong', ''];
        }

        if (time() - $chat->created_at > \Chats::reviewExpiredTime()) {
            return [ERROR_CODE_SUCCESS, 'expired', GROUP_USER_REVIEW_EXPIRED];
        }

        $group = \Groups::findById($chat->group_id);

        if (isBlank($group)) {

            return [ERROR_CODE_FAIL, 'group_not_exists', ''];
        }

        if (!$group->isManager($manager_id)) {

            return [ERROR_CODE_FAIL, 'not_king', ''];
        }

        if (GROUP_STATUS_OFF == $group->status) {
            return [ERROR_CODE_FAIL, 'banned_group', ''];
        }

        $json = json_decode($chat->review_user_ids, true);
        $invite_id = $json['invite_id'];
        $user_ids = $json['user_ids'];

        if ($chat->action == CHAT_GROUP_ACTION_GROUP_IN_ASK) {
            $way = GROUP_ASK_USER;
        } else if ($chat->action == CHAT_GROUP_ACTION_GROUP_IN_COMMEND) {
            $way = GROUP_COMMEND_USER;
        } else {
            $way = GROUP_ADD_USER;
        }
        \GroupUsers::addUsers($user_ids, $group, $invite_id, $way, true);

        $chat->group_user_review = GROUP_USER_REVIEW_ON;
        $chat->save();
        return [ERROR_CODE_SUCCESS, 'success', GROUP_USER_REVIEW_ON];
    }

    /**
     * @param string 新增群成员ids
     * @param string 删除群成员ids
     * @return array
     */
    function updateGroup()
    {
        $group_id = $this->id;
        $ssdb = self::getXRedis($group_id);

        $keyword = GROUP_USER_IDS . $this->id;
        #更新群人数
        $this->people_num = $ssdb->zcard($keyword);

        $cond = ['conditions' => "group_id =:group_id:"];
        $cond['bind'] = ['group_id' => $group_id];
        $cond['order'] = 'id asc';
        $cond['limit'] = 4;
        $group_users = \GroupUsers::find($cond);
        $user_name = $icon_url = $user_ids = [];
        $n = 1;
        foreach ($group_users as $group_user) {
            $user = $group_user->user;
            #最多取四个人的头像
            if ($n > 4) {
                break;
            } else {
                $icon_url[] = $user->icon_small_url;
                $user_name[] = $user->nickname;
                $user_ids[] = $user->id;
            }
            $n++;
        }

        $this->user_ids = implode(',', $user_ids);

        debug('当前前四个群成员id：', $this->user_ids);

        #拼接群头像
        $img = $this->jointIcon($icon_url);

        if (isPresent($img)) {
            $this->group_icon = $img;

        } elseif (isBlank($this->group_icon)) {
            debug('未生成群头像：群id：', $this->id);

            $this->group_icon = $this->default_icon;
        }

        $icon_urls = implode(',', $icon_url);
        $user_names = implode('、', $user_name);
        $this->icon_url = $icon_urls;


        #更新群名称
        if ($this->is_set_name == GROUP_SET_NAME) {
            $this->name = $user_names;
            $this->is_set_name = GROUP_SET_NAME_START;
        }
        $this->save();
        return [ERROR_CODE_SUCCESS, 'ok', $this, $icon_url];

    }

    # 组合群头像、群名称
    function getGroupInfo()
    {
        $people_num = $this->people_num;
        if (isBlank($people_num)) {
            debug('user_ids is null');

            $group['group_icon'] = '';
            $group['avatar_urls'] = [];
            $group['name'] = '';
            return $group;
        }

        $default = \StoreFile::getUrl($this->default_icon . '@!small');
        $default_user = \StoreFile::getUrl($this->default_user_icon . '@!small');

        $group['avatar_urls'] = explode(',', $this->icon_url);

        if (isBlank($group['avatar_urls'])) {
            for ($i = 0; $i < 4; $i++) {
                $group['avatar_urls'][] = $default_user;
            }
        }

        $group['name'] = $this->name ? $this->name : '群聊';

        #获取群头像
        if (isPresent($this->group_icon)) {
            $icon = \StoreFile::getUrl($this->group_icon . '@!small');
            $group['group_icon'] = $icon;
        } else {
            debug('无群头像：群id：', $this->id);
            $group['group_icon'] = $default;

        }
        return $group;
    }

    #生成小图用于拼头像
    function generateSmallIcon($icons)
    {
        #数组中原图缩小后路径
        $icon_smalls = [
            0 => APP_ROOT . 'public/temp/' . md5(time() . uniqid(mt_rand())) . '.png',
            1 => APP_ROOT . 'public/temp/' . md5(time() . uniqid(mt_rand())) . '.png',
            2 => APP_ROOT . 'public/temp/' . md5(time() . uniqid(mt_rand())) . '.png',
            3 => APP_ROOT . 'public/temp/' . md5(time() . uniqid(mt_rand())) . '.png',
        ];

        #将原图缩小为46x46大小
        foreach ($icons as $key => $icon) {
            exec("convert -resize '46x46' " . $icon . " " . $icon_smalls[$key]);
        }
        return $icon_smalls;
    }

    function getIconSmallUrl()
    {
        return $this->group_icon_url . '@!small';
    }

    function getGroupIconUrl($icon = null)
    {
        if ($icon != null) {
            if (isBlank($icon)) {
                return \StoreFile::getUrl($this->default_icon);
            }
            return \StoreFile::getUrl($icon);
        }

        if (isBlank($this->group_icon)) {
            return \StoreFile::getUrl($this->default_icon);
        }
        return \StoreFile::getUrl($this->group_icon);
    }

    function getDefaultIcon()
    {
        return APP_NAME . '/groups/avatars/group_default.jpg';
    }

    function getDefaultUserIcon()
    {
        return APP_NAME . '/users/avatars/default_1.png';

    }

    static function groupsList($user_id, $current_id = '')
    {
        $error_reason = [
            [
                'reason' => 'param_error'
            ]
        ];
        if (isBlank($user_id)) {
            return [ERROR_CODE_FAIL, $error_reason, []];

        }
        $ssdb = \Groups::getXRedis($user_id);
        $key = USER_ON_GROUP . $user_id;
        $group_ids = $ssdb->zrange($key, 0, -1, '');

        if (isBlank($group_ids)) {
            return [ERROR_CODE_SUCCESS, 'ok', []];
        }

        foreach ($group_ids as $key => $group_id) {
            $group__ids[] = $key;
            $group_tip_status[$key] = $group_id;
        }

        $group_ids = array_reverse($group__ids); //反转数组
        $groups = \Groups::findByIds($group_ids);

        $group_info = [];
        foreach ($groups as $group) {
            #解散的群跳过
            if ($group->status == GROUP_STATUS_OUT) {
                continue;
            }
            #获取群名称
            $group_info_one = $group->getGroupInfo();
            $group_info[] = [
                'id' => $group->id,
                'name' => $group->name,
                'avatar_urls' => $group_info_one['avatar_urls'],
                'group_icon' => $group_info_one['group_icon'],
                'people_num' => $group->people_num,
                'manager_id' => $group->manager_id,
                'status' => $group->status,
                'verify_status' => $group->verify_status,
                'tip_status' => $group_tip_status[$group->id],
                'is_top' => Chats::isTopStatus($user_id, ['group_id' => $group->id]),//聊天对话是否置顶
            ];
        }
        return [ERROR_CODE_SUCCESS, 'ok', $group_info];

    }

    static function detail($group_id, $manager_id)
    {
        $error_reason = [
            [
                'reason' => 'param_error'
            ]
        ];
        if (isBlank($group_id)) {
            return [ERROR_CODE_FORM, $error_reason, ''];
        }
        $group = \Groups::findById($group_id);
        $error_reason = [
            [
                'reason' => 'group_not_exists'
            ]
        ];
        if (!$group) {
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        if (GROUP_STATUS_OUT == $group->status) {
            return [ERROR_CODE_FAIL, t('banned_group'), ''];
        }

        $groupInfo = $group->getGroupInfo();

        $group_info = $group->toSimpleJson($manager_id);
        #群消息提醒状态
        $cond = ['user_id' => $manager_id, 'group_id' => $group_id];
        $user = \GroupUsers::findFirstBy($cond);

        if (!$user) {
            $group_info['in_group'] = USER_NOT_IN_GROUP;//用户在群
            $error_reason = [
                [
                    'reason' => 'request_in_group'
                ]
            ];
            $reason = $error_reason;
        } else {
            $group_info['tip_status'] = $user->tip_status;
            #用户对群的状态
            $group_info['ban_status'] = $user->ban_status;
            $group_info['nike_name'] = $user->name;//用户在群的昵称
            $group_info['in_group'] = USER_IN_GROUP;//用户在群
            $error_reason = [
                [
                    'reason' => 'u_in_group'
                ]
            ];
            $reason = $error_reason;
        }

        $group_info['ban_group'] = $group->ban_status;
        $group_info['notice'] = $group->notice;
        $group_info['notice_time'] = $group->notice_time;
        $group_info['notice_author_id'] = $group->notice_author;
        $group_info['avatar_urls'] = $groupInfo['avatar_urls'];
        $group_info['group_icon'] = $groupInfo['group_icon'];

        #取申请加群记录
        $redis = \Groups::getHotWriteCache();
        $key = ASK_GROUP_GROUP_ID . $group->id . 'user_id' . $manager_id;
        $temp = $redis->get($key);
        $is_ask = fetch(self::$GROUP_ADD_STATUS_TEXT_LANG, GROUP_ADD_STATUS_WAIT);
        $group_info['is_ask_status'] = $temp ? $is_ask : '';

        #聊天对话是否置顶
        $group_info['is_top'] = Chats::isTopStatus($manager_id, ['group_id' => $group->id]);

        return [ERROR_CODE_SUCCESS, $reason, $group_info];

    }

    #判断群是否可用
    function isAvailable()
    {
        return GROUP_STATUS_ON == $this->status;
    }

    #快速取群成员ids
    function getGroupUserIds()
    {
        $ssdb = self::getXRedis($this->id);
        $key = GROUP_USER_IDS . $this->id;
        $user_ids = $ssdb->zrange($key, 0, -1);
        return $user_ids;
    }

    function getGroupCnames($cname_type = 'client')
    {
        $ids = $this->getGroupUserIds();
        $cnames = [];
        foreach ($ids as $id){
            $cnames[] = $id . '_' . $cname_type;
        }
        return $cnames;
    }

    static function recommend($user_id)
    {
        //return [ERROR_CODE_SUCCESS, 'ok', []];

        $redis = \Groups::getHotReadCache();

        $ssdb = \Groups::getXRedis($user_id);
        $error_reason = [
            [
                'reason' => 'param_error'
            ]
        ];
        if (isBlank($user_id)) {
            return [ERROR_CODE_FAIL, $error_reason, []];
        }

        $cond = ['conditions' => "recommend =:recommend: and status = :status: "];
        $cond['bind'] = ['recommend' => GROUP_RECOMMEND_ON, 'status' => GROUP_STATUS_ON];
        $cond['order'] = 'commend_level desc';

        try {
            $groups = \Groups::find($cond);
        } catch (\Exception $e) {
            debug($e->getMessage());
        }
        $groupIds = [];

        #遍历优先推荐的群
        foreach ($groups as $group) {
            #若用户在群内跳过

            $is_in_group = GroupUsers::isGroupMember($user_id, $group->id);
            if ($is_in_group) {
                continue;
            }

            #跳过不可用的群
            if (GROUP_STATUS_ON != $group->status) {
                continue;
            }
            $groupIds[] = $group->id;
        }
        debug('被推荐的群总数：', count($groupIds), $groupIds);

        $group_ids = [];

        #不满十条随机取剩余群作补充
        if (count($groupIds) >= 10) {

        } else {
            //        #取出所有好友id
            $cond = ['conditions' => 'user_id = :user_id:',
                'bind' => ['user_id' => $user_id]
            ];
            $friends = \Friends::findForeach($cond);

            foreach ($friends as $friend) {
                $friend_ids[] = $friend->friend_id;
            }

            //查找朋友所在群id
            if (isBlank($friend_ids)) {
                $cond = [];
            } else {
                $friend_arr = implode(',', $friend_ids);
                $cond = ['conditions' => " user_id in ( " . $friend_arr . " )"];

            }

            debug('当前查询条件是：', $cond);
            $group_users = \GroupUsers::findForeach($cond);

            foreach ($group_users as $group_user) {
                $key = GROUP_USER_IDS . $group_user->group_id;
                if (isPresent($ssdb->zscore($key, $group_user->user_id))) {
                    continue;
                }
                $friend_groups[] = $group_user->group_id;
            }

            #数组去重
            $group_ids = array_keys(array_flip($friend_groups));
            debug('有相关度的群：', $group_ids);
        }


        #合并数组 并去重 （被推荐的群，相关度的群）
        $group_ids = array_keys(array_flip(array_merge($groupIds, $group_ids)));

        $i = 1;
        $groups = \Groups::findByIds($group_ids);
        $result = [];

        foreach ($groups as $group) {
            #若用户在群内跳过
            $key = GROUP_USER_IDS . $group->id;

            #若用户在群内跳过
            $is_in_group = \GroupUsers::isGroupMember($user_id, $group->id);
            if ($is_in_group) {

                continue;
            }

            #若群内小于10人跳过
            if ($ssdb->zcard($key) < 10) {
                continue;
            }

            #跳过不可用的群
            if (GROUP_STATUS_ON != $group->status) {
                continue;
            }

            #跳过关闭推荐的群
            if (GROUP_RECOMMEND_ON != $group->recommend) {
                continue;
            }

            #补充剩余推荐数量
            if ($i > GROUP_RECOMMEND_NUM) {
                break;
            }
            $i++;

            $arr = $group->toSimpleJson();
            #取申请加群记录
            $key = ASK_GROUP_GROUP_ID . $group->id . 'user_id' . $user_id;
            $temp = $redis->get($key);
            $arr['is_ask'] = $temp ? GROUP_IS_ASK_ON : GROUP_IS_ASK_NO;

            $result[] = $arr;
        }


        return [ERROR_CODE_SUCCESS, 'ok', $result];

    }

    #解散
    static function dissolve($group_id)
    {
        $group = \Groups::findById($group_id);
        $group->status = GROUP_STATUS_OUT; //解散
        $group->gid = 0;
        $group->save();

        $sql = "delete from group_users where group_id = :group_id ";
        $db = self::di('db');
        $db->execute($sql, ['group_id' => $group->id]);
        debug('解散该群所有成员, 群id ：', $group_id);

        $ssdb = \Groups::getXRedis($group_id);
        $key = GROUP_USER_IDS . $group_id;

        $group_users = $ssdb->zrange($key, 0, -1);
        foreach ($group_users as $group_user) {
            $key_user = USER_ON_GROUP . $group_user;
            $ssdb->zrem($key_user, $group_id);
        }

        $ssdb->zclear($key);
        return [ERROR_CODE_SUCCESS, 'success'];

    }


    #用于取群用户列表
    static function toListJson($user, $remark, $is_last, $last_id, $ban_status, $is_manager)
    {
        return [
            'id' => $user->id,
            'name' => $user->nickname,
            'nickname' => $user->nickname,
            'real_name' => $user->mark_name,
            'uid' => $user->uid,
            'remark_name' => $remark,
            'mobile' => $user->mark_mobile,
            'avatar_url' => $user->icon_small_url,
            'last_id' => $last_id,
            'is_last' => $is_last,
            'ban_status' => $ban_status,
            'is_manager' => $is_manager
        ];
    }

    static function generateGid()
    {

        #避开靓号生成群号
        $ids = [];
        $str = '';
        for ($i = 0; $i < 6; $i++) {
            $id = rand(1, 9);
            $ids[] = $id;
            $str .= $id;
        }
        $number = new NiceNumberController();
        if (!$number->reserveNumber($str)) {
            $flag = $number->isNiceNumber($ids);
            if ($flag) {
                self::generateGid();
            }

            $flag = \Groups::findFirstBy(['gid' => $id]);
            if ($flag) {
                self::generateGid();
            }
            return $str;
        } else {
            self::generateGid();
        }
    }

    function toQrcodeJson($user_id, $product_channel = null)
    {
        $group_info = $this->getGroupInfo();
        $data = [
            'id' => $this->id,
            'avatar_urls' => $group_info['avatar_urls'],
            'group_icon' => $group_info['group_icon'],
            'name' => $this->name
        ];

        $data['qrcode'] = $this->getQrcodeUrl($user_id, $product_channel->domain);
        return $data;
    }

    function getQrcodeUrl($user_id, $domain = null)
    {
        $recommend_code = '';
        if (isBlank($domain)) {
            $user = \Users::findFirstByid($user_id);
            if ($user) {
                $domain = $user->product_channel->domain;
                $recommend_code = $user->recommend_code;
            }
        }
        $http = isDevelopmentEnv() ? 'http' : 'https';
        $url = $http . '://' . $domain . '/qrcode/group?group_id=' . strval($this->id) . '&invite_id=' . $user_id;
        return $url . '&qrcode=' . strval($this->qrcode) . '&inviter_code=' . $recommend_code;
    }

    function generateQrcode()
    {
        return substr(md5('group_' . $this->id . '_' . time()), 8, 16);
    }

    function isBlocked()
    {
        return GROUP_STATUS_OFF == $this->status;
    }

    function isOut()
    {
        return GROUP_STATUS_OUT == $this->status;
    }

    function isValid()
    {
        if ($this->isBlocked() || $this->isOut()) {
            return false;
        }
        return true;
    }
    static function formatGroupDetail($group_id, $user_id, $lang)
    {
        list($error, $reason, $group) = \Groups::detail($group_id, $user_id);
        $reason = reasonToLang($lang, $reason);
        if (isPresent($group)) {
            $group['is_ask_status'] = t(fetch($group, 'is_ask_status'), $lang);
        }
        return [$error, $reason, $group];
    }
}