<?php
/**
 * Created by PhpStorm.
 * User: maoluanjuan
 * Date: 22/08/2018
 * Time: 21:09
 */

class GroupUsers extends BaseModel
{

    /**
     * @type Users
     */
    private $_user;

    /**
     * @type ProductChannels
     */
    private $_product_channel;

    /**
     * @type Groups
     */
    private $_group;

    /**
     * @type Users
     */
    private $_invite;

    static $GROUP_ADD_STATUS_TEXT = [
        GROUP_ADD_STATUS_SUCCESS => '加群成功',
        GROUP_ADD_STATUS_WAIT => '等待群主审核',
        GROUP_ADD_STATUS_FAIL => '加群失败'
    ];

    static $GROUP_ADD_STATUS_TEXT_LANG = [
        GROUP_ADD_STATUS_SUCCESS => 'add_group_success',
        GROUP_ADD_STATUS_WAIT => 'wait_group_owner_check',
        GROUP_ADD_STATUS_FAIL => 'add_group_fail'
    ];

    static $BAN_STATUS = [
        GROUP_BAN_USER_ON => '已禁言', GROUP_BAN_USER_OFF => '未禁言'
    ];

    static $TIP_STATUS = [
        GROUP_TIP_STATUS_ON => '不提醒', GROUP_TIP_STATUS_OFF => '提醒'
    ];

    static $IS_WARN = [FRIEND_IS_WARN_OFF => '不提醒', FRIEND_IS_WARN_ON => '提醒'];

    static $CHANGE_COLUMN = [
        'ban_status' => '禁言状态',
        'tip_status' => '消息提醒状态'
    ];

    static function reqAddGroup($group_id, $user_ids, $invite_id, $way)
    {
        if (isBlank($group_id) || isBlank($user_ids) || isBlank($invite_id)) {
            return [ERROR_CODE_FORM, GROUP_ADD_STATUS_FAIL, 'param_error', ''];
        }

        $redis = \Groups::getHotReadCache();
        $lock_key = GROUP_CREATE_LOCK . $invite_id;
        if (!$redis->setnx($lock_key, 1)) {
            return [ERROR_CODE_FORM, GROUP_ADD_STATUS_FAIL, 'cannot_add_users_in_5', ''];
        }
        $redis->expire($lock_key, 5);
        $user_ids = explode(',', $user_ids);

        $group = \Groups::findFirstById($group_id);

        list($error, $status, $reason) = self::verifyGroup($group);
        if ($error == ERROR_CODE_FAIL) {
            return [$error, $status, $reason, ''];
        }

        #判断邀请人是否在群
        $in_group = self::isGroupMember($invite_id, $group_id);
        if (!$in_group) {
            return [ERROR_CODE_FAIL, '', 'you_have_no_access', ''];
        }

        #默认状态
        $status = GROUP_ADD_STATUS_FAIL;


        #被踢出的人不能再邀请
        $ssdb = \Users::getSsdb();
        foreach ($user_ids as $key => $user_id) {
            $remove_key = GROUP_REMOVE_FLAG . '_' . $group_id . '_' . $user_id;

            #若是群主邀请跳过判断，并删除黑名单
            if ($group->isManager($invite_id)) {
                $ssdb->del($remove_key);
                continue;
            }

            $item = $ssdb->get($remove_key);
            if (isPresent($item) && !$group->isManager($invite_id)) {
                unset($user_ids[$key]);
                continue;
            }
        }

        if (count($user_ids) == 0) {
            return [ERROR_CODE_FAIL, '', 'only_king_can_invite', ''];
        }


        #判断是否群主邀请或不需要验证，直接邀请成功
        if ($group->isVerifyStatusOff() || $group->isManager($invite_id)) {
            self::addUsers($user_ids, $group, $invite_id, $way, true);
            $status = GROUP_ADD_STATUS_SUCCESS;
        } else {
            #非群主邀请，需要群主审核
            \GroupUsers::addCache($group_id, $user_ids);
            $result = GroupUsers::notifyTimeLock($user_ids, $group);
            if ($result != 'exist') {
                GroupPush::noticeManageAgree($invite_id, $group, $user_ids, $way);
            }
            \GroupPush::tipNeedManageAgree($group, $invite_id, $way);
            $status = GROUP_ADD_STATUS_WAIT;
        }

        $reason = fetch(self::$GROUP_ADD_STATUS_TEXT_LANG, $status);
        return [ERROR_CODE_SUCCESS, $status, $reason, ''];
    }

    #后台页面：状态标识对应汉字显示
    static function processingValue($request_change_column)
    {
        $change_column = self::$CHANGE_COLUMN;
        $ban_status = self::$BAN_STATUS;
        $tip_status = self::$TIP_STATUS;
        foreach ($request_change_column as $key => $value) {
            switch ($key) {
                case 'ban_status':
                    if (is_array($value)) {
                        $after_value = [$ban_status[$value[0]], $ban_status[$value[1]]];
                    } else {
                        $after_value = $ban_status[$value];
                    }
                    break;
                case 'tip_status':
                    if (is_array($value)) {
                        $after_value = [$tip_status[$value[0]], $tip_status[$value[1]]];
                    } else {
                        $after_value = $tip_status[$value];
                    }
                    break;
                default:
                    $after_value = $value;
                    break;
            }

            $processing_value[$change_column[$key]] = $after_value;
        }
        return $processing_value;
    }

    static function reqAskGroup($group_id, $user_ids, $invite_id, $way)
    {
        if (isBlank($group_id) || isBlank($user_ids) || isBlank($invite_id)) {
            return [ERROR_CODE_FORM, GROUP_ADD_STATUS_FAIL, 'param_error', ''];
        }

        if (!in_array($way, [GROUP_COMMEND_USER, GROUP_ASK_USER, GROUP_SHARE_USER])) {
            $way = GROUP_ASK_USER;
        }
        $user_ids = explode(',', $user_ids);

        $group = \Groups::findFirstById($group_id);

        list($error, $status, $reason) = self::verifyGroup($group);
        if ($error == ERROR_CODE_FAIL) {
            return [$error, $status, $reason, ''];
        }

        #判断是否黑名单
        $ssdb = \Users::getSsdb();
        $remove_key = GROUP_REMOVE_FLAG . '_' . $group_id . '_' . $user_ids[0];
        if ($group->isManager($invite_id)) {
            $ssdb->del($remove_key);
        } else {
            $item = $ssdb->get($remove_key);
            if (isPresent($item)) {
                return [ERROR_CODE_FAIL, '', 'only_king_can_invite', ''];
            }
        }

        #申请加群

        $in_group = self::isGroupMember($user_ids[0], $group_id);
        if ($in_group) {
            return [ERROR_CODE_SUCCESS, '', 'you_have_add_group', ['url' => 'app://groups/direct?group_id=' . $group_id]];

        }

        $in_group = self::isGroupMember($invite_id, $group_id);
        #分享这个二维码的群成员已不在群内，则判断为失效
        if (!$in_group) {
            return [ERROR_CODE_FAIL, '', 'qr_code_expire', ''];
        }

        #判断是否不需要验证，直接申请成功
        if ($group->isVerifyStatusOff()) {
            self::addUsers($user_ids, $group, $invite_id, $way, true);
            $status = GROUP_ADD_STATUS_SUCCESS;
        } else {
            #查看缓存是否已经申请过
            $result = GroupUsers::notifyTimeLock($user_ids, $group);
            if ($result == 'exist') {
                return [ERROR_CODE_SUCCESS, '', 'wait_king_review', ''];
            }

            \GroupUsers::addCache($group_id, $user_ids);
            GroupPush::noticeManageAgree($invite_id, $group, $user_ids, $way);
            #通知邀请人
//            \GroupPush::tipNeedManageAgree($group, $invite_id, $way);
            $status = GROUP_ADD_STATUS_WAIT;
        }

        $reason = fetch(self::$GROUP_ADD_STATUS_TEXT_LANG, $status);

        #若免验证直接跳进群聊
        if ($group->isVerifyStatusOff()) {
            return [ERROR_CODE_SUCCESS, $status, $reason, ['url' => 'app://groups/direct?group_id=' . $group_id, 'status' => $status]];
        } else {
            return [ERROR_CODE_SUCCESS, $status, $reason, ''];
        }
    }

    #申请加群定期存缓存
    static function addCache($group_id, $user_ids = [])
    {

        $redis = \Groups::getHotWriteCache();
        foreach ($user_ids as $user_id) {
            $key = ASK_GROUP_GROUP_ID . $group_id . 'user_id' . $user_id;
            #48小时过期
            $redis->setex($key, \Chats::reviewExpiredTime(), 1);
        }

    }

    #清除申请加群定期存缓存
    static function delCache($group_id, $user_ids = [])
    {

        $redis = \Groups::getHotWriteCache();
        foreach ($user_ids as $user_id) {
            $key = ASK_GROUP_GROUP_ID . $group_id . 'user_id' . $user_id;
            info('删除缓存记录', $key);
            $redis->del($key);
        }

    }

    static function verifyGroup($group = null)
    {
        if (isBlank($group)) {
            return [ERROR_CODE_FAIL, GROUP_ADD_STATUS_FAIL, 'group_not_exists', ''];
        }
        if ($group->people_num >= GROUP_MAX_NUM) {
            return [ERROR_CODE_FAIL, GROUP_ADD_STATUS_FAIL, 'group_full', ''];
        }

        if (GROUP_STATUS_ON != $group->status) {
            return [ERROR_CODE_FAIL, GROUP_ADD_STATUS_FAIL, 'group_cannot_use', ''];
        }
        return [ERROR_CODE_SUCCESS, '', '', ''];

    }


    static function isGroupMember($user_id, $group_id)
    {
        $ssdb = \Groups::getXRedis($group_id);
        $key = GROUP_USER_IDS . $group_id;
        $group_user = $ssdb->zscore($key, $user_id);
        if (!$group_user) {
            $cond = ['group_id' => $group_id, 'user_id' => $user_id];
            $group_user = \GroupUsers::findFirstBy($cond);
        }
        #返回群里用户存在结果
        return !!$group_user;
    }


    static function editGroupUsers($param = [], $id, $manager_id)
    {
        if (isBlank($id) || implode(',', $param) === '') {
            return [ERROR_CODE_FORM, 'param_error'];
        }
        $cond = ['user_id' => $manager_id, 'group_id' => $id];
        $group = \GroupUsers::findFirstBy($cond);
        if (isBlank($group)) {
            return [ERROR_CODE_FAIL, 'member_not_exits'];
        }
        foreach ($param as $key => $value) {
            $group->$key = $value;
            if ($key == 'name') {
                $censor_words = censor_word();
                $blacklist = "/" . implode("|", $censor_words) . "/i";
                if (preg_match_all($blacklist, $value, $matches)) {
                    if (isPresent(array_filter($matches[0]))) {
                        return [ERROR_CODE_FAIL, 'sensitive_word'];
                    }
                }

            }
        }
        if ($group->save()) {
            return [ERROR_CODE_SUCCESS, 'success'];
        }
        return [ERROR_CODE_FAIL, 'fail'];
    }


    #退群
    static function quitGroup($group_id, $user)
    {
        if (isBlank($group_id) || isBlank($user)) {
            return [ERROR_CODE_FORM, 'param_error'];
        }

        $group = \Groups::findById($group_id);

        if (isBlank($group)) {
            return [ERROR_CODE_FAIL, 'group_not_exists'];
        }

        if (GROUP_STATUS_OFF == $group->status) {
            return [ERROR_CODE_FAIL, 'banned_group'];
        }
        $ssdb = \Groups::getXRedis($group_id);
        $key_users = USER_ON_GROUP . $user->id;
        $keyword = GROUP_USER_IDS . $group_id;

        $group_user = \GroupUsers::findFirstBy(['user_id' => $user->id, 'group_id' => $group_id]);
        if (isPresent($group_user)) {

            $new_manager_id = null;
            $update_manager_status = false;

            #若群主退群，则自动选择下一位当群主
            if ($group->isManager($user->id)) {
                $cond = ['conditions' => "group_id =:group_id: and user_id <> :user_id:"];
                $cond['bind'] = ['group_id' => $group_id, 'user_id' => $user->id];
                $cond['order'] = 'id asc';
                $new_group_user = \GroupUsers::findFirst($cond);
                if ($new_group_user) {
                    $group->manager_id = $new_group_user->user_id;
                    #将群主的标志置位1
                    $ssdb->zadd($keyword, 1, $new_group_user->user_id);
                    $new_manager_id = $group->manager_id;
                } else {
                    #解散群
                    \Groups::dissolve($group_id);
                }
                $update_manager_status = $group->save();
            }

            if (isBlank($new_manager_id) || $update_manager_status) {
                $group_user->delete();

                #群成员ssdb删除
                $ssdb->zrem($keyword, $user->id);
                $ssdb->zrem($key_users, $group_id);
                
                \GroupPush::tipQuit($group, $user, $new_manager_id);

                #重定义群名称/群头像
                $group->updateGroup();
            }

        }

        \GroupUsers::notifyTimeLock([$user->id], $group, 'unlock');
        return [ERROR_CODE_SUCCESS, 'success'];
    }

    #踢人
    static function removeUser($user_ids, $group_id, $current_id)
    {
        $ssdb = \Groups::getXRedis($group_id);
        if (isBlank($group_id) || isBlank($user_ids)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FORM, $error_reason, ''];
        }
        $user_ids = explode(',', $user_ids);
        $group = \Groups::findById($group_id);

        if (isBlank($group)) {
            $error_reason = [
                [
                    'reason' => 'group_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, $group->status];
        }

        $in_group = self::isGroupMember($current_id, $group_id);
        if (!$in_group) {
            $error_reason = [
                [
                    'reason' => 'u_not_in_group'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        if (GROUP_STATUS_OFF == $group->status) {
            $error_reason = [
                [
                    'reason' => 'banned_group'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, $group->status];
        }

        if (in_array($current_id, $user_ids) && !$group->isManager($current_id)) {
            $error_reason = [
                [
                    'reason' => 'not_king'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, $group->status];
        }

        $str_names = [];
        $keyword = GROUP_USER_IDS . $group_id;

        foreach ($user_ids as $user_id) {
            #群主不能踢自己
            if ($group->isManager($user_id) || isBlank($user_id)) {
                continue;
            }
            $cond = ['user_id' => $user_id, 'group_id' => $group_id];
            $group_user = \GroupUsers::findFirstBy($cond);
            $key_users = USER_ON_GROUP . $user_id;


            if (isPresent($group_user)) {
                $group_user->delete();
                #群成员redis删除
                $ssdb->zrem($keyword, $user_id);
                $ssdb->zrem($key_users, $group_id);

                #被踢用户群消息
                \GroupPush::delay()->noticeRemoveUser($group_id, $group_user->user_id);
                array_push($str_names, $group_user->user->nickname);
            }

            #踢掉的人加个标记, 组员不能邀请
            $ssdb = \Groups::getXRedis(1);
            $remove_key = GROUP_REMOVE_FLAG . '_' . $group_id . '_' . $user_id;
            $ssdb->set($remove_key, 1);
        }
        if (isBlank($str_names)) {
            $error_reason = [
                [
                    'reason' => 'success'
                ]
            ];
            return [ERROR_CODE_SUCCESS, $error_reason, $group->status];
        }
        $str_names = implode("、", $str_names);

        #重定义群名称/群头像
        $group->updateGroup();

        if ($group->status == GROUP_STATUS_ON) {
            #给群主发个tip
            \GroupPush::tipRemoveUser($group, $str_names);
        }

        \GroupUsers::notifyTimeLock($user_ids, $group, 'unlock');
        $error_reason = [
            [
                'reason' => 'user_moved_out'
            ]
        ];
        return [ERROR_CODE_SUCCESS, $error_reason, $group->status];

    }

    static function banUser($user_ids, $group_id, $manager_id, $ban_status)
    {
        if (isBlank($group_id) || !is_string($user_ids) || isBlank($manager_id) || !in_array($ban_status,
                [GROUP_BAN_USER_ON, GROUP_BAN_USER_OFF])) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FORM, $error_reason];
        }

        $user_ids = explode(',', $user_ids);
        $group = \Groups::findById($group_id);

        if (isBlank($group)) {
            $error_reason = [
                [
                    'reason' => 'group_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason];
        }

        if (GROUP_STATUS_OFF == $group->status) {
            $error_reason = [
                [
                    'reason' => 'banned_group'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason];
        }

        if (in_array($group->manager_id, $user_ids)) {
            $error_reason = [
                [
                    'reason' => 'cannot_ban_king'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason];
        }

        if (!$group->isManager($manager_id)) {
            $error_reason = [
                [
                    'reason' => 'have_no_access'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason];
        }

        foreach ($user_ids as $user_id) {
            $cond = ['user_id' => $user_id, 'group_id' => $group_id];
            $group_user = \GroupUsers::findFirstBy($cond);
            if (isPresent($group_user)) {
                $group_user->ban_status = GROUP_BAN_USER_ON == $ban_status ? GROUP_BAN_USER_ON : GROUP_BAN_USER_OFF;
                $group_user->save();
                if ($group_user->ban_status == GROUP_BAN_USER_ON) {
                    $error_reason = [
                        [
                            'reason' => 'ban_success'
                        ]
                    ];
                    $reason = $error_reason;
                } else {
                    $error_reason = [
                        [
                            'reason' => 'release_ban_success'
                        ]
                    ];
                    $reason = $error_reason;

                }
                \GroupPush::delay()->tipUserBan($group, $group_user);
            }
        }
        return [ERROR_CODE_SUCCESS, $reason, $group_user->ban_status];
    }

    static function addUsers($user_ids, $group, $invite_id, $way, $notify_others)
    {
        $ssdb = \Groups::getXRedis($group->id);
        $low_version_user = [];

        foreach ($user_ids as $key => $user_id) {
            debug("add_group_user: ", $key, $user_id);
            $user = \Users::findById($user_id);

            if (isBlank($user)) {
                debug("不存在此用户 ,用户id ：", $user_id);
                unset($user_ids[$key]);
                continue;
            }

            #群端用户账户状态是否被封
            if ($user->status != USER_STATUS_ON) {
                debug("python_test 此用户被禁用,用户id ：", $user_id, $user->status);
                unset($user_ids[$key]);
                continue;
            }

            $keyword = GROUP_USER_IDS . $group->id;

            $in_group = self::isGroupMember($user_id, $group->id);
            if (!$in_group) {
                $group_user = new \GroupUsers();
                $group_user->user_id = $user_id;
                $group_user->name = $user->nickname;
                $group_user->group_id = $group->id;
                $group_user->invite_id = $invite_id;
                $group_user->tip_status = GROUP_TIP_STATUS_OFF;
                $group_user->ban_status = GROUP_BAN_USER_OFF;
                $group_user->save();

                #群成员存入redis中
                $score = $group_user->created_at;
                if ($user->isGroupManager($group)) {
                    $score = 1;
                }
                $ssdb->zadd($keyword, $score, $user_id);

                $score = GROUP_TIP_STATUS_OFF;
                $key_users = USER_ON_GROUP . $user_id;
                $ssdb->zadd($key_users, $score, $group->id);

                debug($ssdb->zrange($keyword, 0, -1), '新群员信息');


            } else {
                #已经在群里的直接忽略, 不再通知
                debug("此用户已在群内，用户id ：", $user_id);
            }
        }

        #重定义群名称/群头像
        $group->updateGroup();

        if (isPresent($low_version_user)) {
            \GroupPush::tipBlockUser($group, $low_version_user);
        }

        \GroupPush::delay()->tipAddUser($group, $invite_id, $user_ids, $way, $notify_others);

        #删除加群申请缓存
        \GroupUsers::delCache($group->id, $user_ids);


        \GroupUsers::notifyTimeLock($user_ids, $group, 'unlock');

        return true;
    }


    static function findUsersByGroup($group_id = 0, $page, $per_page, $ban_status = GROUP_BAN_USER_OFF, $current_id = '')
    {
        if (isBlank($group_id)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FORM, $error_reason, ''];
        }
        $group = \Groups::findFirstById($group_id);

        if (isBlank($group)) {
            $error_reason = [
                [
                    'reason' => 'group_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        if (GROUP_STATUS_OFF == $group->status) {
            $error_reason = [
                [
                    'reason' => 'banned_group'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        $cond = ['conditions' => "group_id =:group_id:"];
        $cond['bind'] = ['group_id' => $group_id];
        $cond['order'] = 'id asc';
        $group_users = \GroupUsers::findPagination($cond, $page, $per_page);

        if (isBlank($group_users->total_page)) {
            $error_reason = [
                [
                    'reason' => 'no_group_member'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        if (isPresent($current_id)) {
            $is_member = self::isGroupMember($current_id, $group_id);
            if (!$is_member) {
                $error_reason = [
                    [
                        'reason' => 'u_not_in_group'
                    ]
                ];
                return [ERROR_CODE_FAIL, $error_reason, ''];
            }
        }

        $users = $user_ids = [];
        foreach ($group_users as $group_user) {
            $user = $group_user->user;
            #取出好友昵称
            $friend = \Friends::findFirstBy(['friend_id' => $user->id, 'user_id' => $current_id]);
            if (isBlank($friend)) {
                $remark = '';
            } else {
                $remark = $friend->remark;
            }
            #群里全部用户信息
            if ($group_user->ban_status != GROUP_BAN_USER_ON) {
                $user_ids[] = $user->id;

//                $data = $group->toListJson($user,$remark,$is_last,$last_id,$group_user->ban_status,$is_manager);
                $data = [
                    'id' => $user->id,
                    'name' => $user->nickname,
                    'nickname' => $user->nickname,
                    'real_name' => $user->mark_name,
                    'uid' => $user->uid,
                    'remark_name' => $remark,
                    'mobile' => $user->mark_mobile,
                    'avatar_url' => $user->get_icon_small_url,
                    'ban_status' => $group_user->ban_status,
                    'is_manager' => $group_user->user_id == $group->manager_id ? 1 : 0
                ];

                #始终将群主放到首位
                if ($group_user->user_id == $group->manager_id) {
                    array_unshift($users, $data);
                } else {
                    $users[] = $data;
                }

                continue;
            }

            #禁言用户信息
            if ($group_user->ban_status == GROUP_BAN_USER_ON && $group_user->user_id != $group->manager_id) {
                $users[] = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'real_name' => $user->mark_name,
                    'remark_name' => $friend->remark,
                    'mobile' => $user->mark_mobile,
                    'avatar_url' => $user->get_icon_small_url
                ];
                continue;

            }
        }


        return [ERROR_CODE_SUCCESS, 'ok', $users];

    }


    static function groupUsersPaging($group_id = 0, $per_page, $current_id = '', $last_id = '')
    {
        if ($last_id == 0) {
            $last_id = '';
        }

        if (isBlank($group_id) || isBlank($current_id)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FORM, $error_reason, '', 1];
        }
        $group = \Groups::findFirstById($group_id);

        if (isBlank($group)) {
            $error_reason = [
                [
                    'reason' => 'group_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, '', 1];
        }

        if (GROUP_STATUS_ON != $group->status) {
            $error_reason = [
                [
                    'reason' => 'banned_group'
                ]
            ];

            return [ERROR_CODE_SUCCESS, $error_reason, [], 1];
        }

        if (isPresent($current_id)) {
            $is_member = self::isGroupMember($current_id, $group_id);
            if (!$is_member) {
                $error_reason = [
                    [
                        'reason' => 'u_not_in_group'
                    ]
                ];
                return [ERROR_CODE_FAIL, $error_reason, '', 1];
            }
        }

        $key = GROUP_USER_IDS . $group->id;
        $ssdb = Groups::getXRedis($group->id);
        $user_ids = $ssdb->zrangeByRankPagination($key, $last_id, $per_page);

        #若是群中最后一个用户，is_last 为1
        if ($user_ids->total_page == $user_ids->current_page || $user_ids->total_page == 1) {
            $is_last = 1;
        } else {
            $is_last = 0;
        }

        $user_id_string = [];
        foreach ($user_ids as $user_id) {
            $user_id_string[] = $user_id;
        }

        $friends = Friends::query()
            ->columns(['remark', 'friend_id'])
            ->where('user_id = :user_id:')
            ->bind(['user_id' => $current_id])
            ->execute();
        $friend_remarks = [];
        foreach ($friends as $friend) {
            $friend_remarks[$friend->friend_id] = $friend->remark;
        }

        $group_users = [];
        $users = \Users::findByIds($user_id_string);
        foreach ($users as $user) {
            $remark = fetch($friend_remarks, $user->id, '');

            $is_manager = $user->id == $group->manager_id ? 1 : 0;
            $group_users[] = $group->toListJson($user, $remark, $is_last, $user->id, 0, $is_manager);

        }
        return [ERROR_CODE_SUCCESS, 'ok', $group_users, $is_last];

    }


    static function getUserName($user_id, $group_id, $current_id = '')
    {

        if (isBlank($group_id) || isBlank($user_id)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FORM, $error_reason, ''];
        }

        $user = \Users::findById($user_id);
        if (!$user) {
            $error_reason = [
                [
                    'reason' => 'user_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];

        }
        #取出好友昵称
        if (isBlank($current_id)) {
            $remark = '';
        } else {
            $friend = \Friends::findFirstBy(['friend_id' => $user->id, 'user_id' => $current_id]);
            $remark = $friend->remark;
        }
        $data = [
            'id' => $user->id,
            'group_name' => $user->nickname,
            'nickname' => $user->nickname,
            'real_name' => $user->mark_name,
            'remark_name' => $remark,
            'mobile' => $user->mark_mobile,
            'avatar_url' => $user->get_icon_small_url,
        ];
        return [ERROR_CODE_SUCCESS, 'ok', $data];
    }

    /**
     * @param $user_name
     * @param $group_id
     * @param $current_id
     * @return array
     */
    static function searchUser($user_name, $group_id, $current_id)
    {
//        return [ERROR_CODE_SUCCESS, 'ok', ['data' => []]];
        if (isBlank($user_name) || isBlank($group_id)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }
        $ssdb = \Groups::getXRedis($group_id);

        $key = GROUP_USER_IDS . $group_id;
        $group_user_ids = $ssdb->zrange($key, 0, -1);

        #拉取在群中的好友id
        $friends = \Friends::query()
            ->andWhere("user_id=:current_id:", ['current_id' => $current_id])
            ->inWhere('friend_id', $group_user_ids)
            ->andWhere("remark like :nickname:", ['nickname' => '%' . $user_name . '%'])
            ->execute();

        $user_ids = [];
        if ($friends) {
            foreach ($friends as $friend) {
                $user_ids[] = $friend->friend_id;
            }
        }
        debug('好友的：', $user_ids);

        try {
            $results = \Users::query()
                ->inWhere('id', $group_user_ids)
                ->andWhere("nickname like :nickname:", ['nickname' => '%' . $user_name . '%'])
                ->execute();

            $user_name_ids = [];
            if ($results) {
                foreach ($results as $result) {
                    $user_name_ids[] = $result->id;
                }
            }

            debug('用户的：', $user_name_ids);


            #合并id并且去重
            $user_ids = array_unique(array_merge($user_ids, $user_name_ids));

            debug('合并后：', $user_ids);
            #拉取在群中的好友  和 拉取群中的用户备注的好友
            $friends = \Users::findByIds($user_ids);

            $data = [];
            foreach ($friends as $friend) {
                $data[] = $friend->toGroupJson();
            }

            return [ERROR_CODE_SUCCESS, 'ok', ['data' => $data, 'key' => $user_name]];
        } catch (\Exception $e) {
            $result = $e->getMessage();
            return [ERROR_CODE_FAIL, 'error', ['data' => $result]];
        }

    }

    static function userInfo($user_id, $group_id, $manager_id)
    {
        if (isBlank($user_id) || isBlank($group_id)) {
            $error_reason = [
                [
                    'reason' => 'param_error'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }

        $group_user = self::isGroupMember($user_id, $group_id);
        if (!$group_user) {
            $error_reason = [
                [
                    'reason' => 'user_not_in_group'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }
        $user = \Users::findFirstById($user_id);
        if (isBlank($user)) {
            $error_reason = [
                [
                    'reason' => 'user_not_exists'
                ]
            ];
            return [ERROR_CODE_FAIL, $error_reason, ''];
        }
        $cond = ['friend_id' => $user_id, 'user_id' => $manager_id];
        $friend = \Friends::findFirstBy($cond);
        if ($friend) {
            $remark = $friend->remark;
            $is_follow = (FRIEND_IS_FOLLOW_ON == $friend->is_follow);
            $is_warn = (FRIEND_IS_WARN_ON == $friend->is_warn);
            $is_block = (FRIEND_IS_BLOCK_ON == $friend->is_block);
        } else {
            $remark = '';
            $is_follow = false;
            $is_warn = true;
            $is_block = false;
        }
        $user_info = [
            'id' => $user->id,
            'remark' => $remark,
            'icon_url' => $user->icon_small_url,
            'avatar_url' => $user->icon_small_url,
            'nickname' => $user->nickname,
            'group_name' => $user->nickname,
            'real_name' => $user->mark_name,
            'mobile' => $user->get_mark_mobile,
            'uid' => $user->uid,
            'is_follow' => $is_follow,
            'is_warn' => $is_warn,
            'is_block' => $is_block
        ];
        $error_reason = [
            [
                'reason' => 'success'
            ]
        ];
        return [ERROR_CODE_SUCCESS, $error_reason, $user_info];
    }

    static function notifyTimeLock($user_ids, $group, $type = 'lock')
    {
        #相同的人48小时内仅邀请通知一次
        sort($user_ids);
        $str = implode(',', $user_ids);
        $lock_key = GROUP_NOTICE_LOCK . $group->id . '_' . $group->manager_id . md5($str);
        $ssdb = \Users::getSsdb();

        debug('lock_key' . $lock_key);
        debug('status:', $type);

        #解锁
        if ($type == 'unlock') {
            $ssdb->del($lock_key);
            return 'ok';
        }

        #已经存在
        if (!$ssdb->setnx($lock_key, 1)) {
            return 'exist';
        }
        $ssdb->expire($lock_key, \Chats::reviewExpiredTime());

        return 'ok';
    }

}