<?php

class GroupPush extends BaseModel
{

    #群通知，群主待审核
    static function noticeManageAgree($invite_id, $group, $user_ids, $way)
    {
        $invite_user = \Users::findById($invite_id);
        $review_count = count($user_ids);
        $lang = $group->manager->user_lang;
        if (isBlank($review_count)) {
            return;
        }

        $arr = ['invite_id' => $invite_id, 'user_ids' => $user_ids];
        $json = json_encode($arr);

//        $join_users = \Users::findByIds($user_ids);
//        $nickname = [];
//        foreach ($join_users as $join_user){
//            $nickname[] = $join_user->nickname;
//        }
//        $nickname = implode('、',$nickname);
        debug($json, $group->id);
        $content_check = '';
        if (GROUP_ADD_USER == $way) {
            $action = CHAT_GROUP_ACTION_GROUP_IN_INVITE;
            $content = t('want_invite_friend_in_group', $lang, ['who' => $invite_user->nickname, 'count' => $review_count]);
            $content_check = t('invite_friend_in_group', $lang, ['count' => $review_count]);
        } else if (GROUP_COMMEND_USER == $way) {
            $action = CHAT_GROUP_ACTION_GROUP_IN_COMMEND;
            $user_id = $user_ids[0];
            $user = \Users::findById($user_id);
            $content = $user->nickname . t('apply_group', $lang);

        } else if (GROUP_SHARE_USER == $way) {
            $action = CHAT_GROUP_ACTION_GROUP_IN_SHARE;
            $user_id = $user_ids[0];
            $user = \Users::findById($user_id);
            $content = $user->nickname . t('apply_group', $lang);
            $content_check = t('apply_through_name_card', $lang, ['who' => $invite_user->nickname]);

        } else {
            $action = CHAT_GROUP_ACTION_GROUP_IN_ASK;
            $user_id = $user_ids[0];
            $user = \Users::findById($user_id);
            $content_check = t('apply_through_qr_code', $lang, ['who' => $invite_user->nickname]);;
            $content = $user->nickname . t('apply_group', $lang);
        }

        $params = [
            'type' => CHAT_TYPE_ADD_USER,
            'group_id' => $group->id,
            'receiver_id' => $group->manager_id,
            'content' => $content,
            'content_check' => $content_check,
            'review_user_ids' => $json,
            'action' => $action,
            'group_user_review' => GROUP_USER_REVIEW_PENDING
        ];
        debug($params, $group->id);
        \Chats::createChat($params);
    }

    #群通知，踢人通知被踢的用户
    static function noticeRemoveUser($group_id, $user_id)
    {
        $user = \Users::findFirstById($user_id);
        if (!$user) {
            return;
        }
        $params = [
            'type' => CHAT_TYPE_TIP,
            'group_id' => $group_id,
            'receiver_id' => $user_id,
            'action' => CHAT_GROUP_ACTION_REMOVE_USER,
            'group_active_user_id' => $user_id,
            'content' => t('you_removed_out', $user->user_lang),
        ];
        \Chats::createChat($params);
    }

    /*
     * 邀请人
     * 群主聊天框系统提示："你邀请了立夏、周波加入了群”
     * 群成员聊天框提示：“ “张晨”邀请你加入了群”
     * 其他群成员：“周波”邀请“立夏”、“周波22”、“张晨”加入了群
     * */
    static function tipAddUser($group, $invite_id, $user_ids, $way, $notify_others = false)
    {
        debug('通知加群_' . $group->id);

        $invite_user = \Users::findById($invite_id);
        $users = \Users::findByIds($user_ids);
        $user_names = [];

        #被邀请人
        foreach ($users as $user) {
            #排除邀请人（自己邀请自己没有推送）
            if ($user->id == $invite_id && GROUP_SHARE_USER != $way) {
                continue;
            }

            if ($way == GROUP_ASK_USER) {
                $content = t('you_add_group_through_qr_code', $user->user_lang);
            } else if ($way == GROUP_COMMEND_USER) {
                $content = t('you_have_add_group', $user->user_lang);
            } else if ($way == GROUP_SHARE_USER) {
                $content = $invite_user->nickname . t('invite_you_into_group', $user->user_lang);
            } else {
                $content = $invite_user->nickname . t('invite_you_into_group', $user->user_lang);
            }

            $user_names[] = $user->nickname;
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $user->id,
                'action' => CHAT_GROUP_ACTION_ADD_USER,
                'group_active_user_id' => $user->id,
                'content' => $content
            ];
            \Chats::createChat($params);
        }

        if (isBlank($user_names)) {
            return;
        }

        #邀请人
        #查询是否还在群内
        $group_user = \GroupUsers::isGroupMember($invite_id, $group->id);
        if ($group_user) {
            $str_names = implode('、', $user_names);
            $inviter = \Users::findFirstById($invite_id);
            if ($way == GROUP_ASK_USER) {
                $content = $str_names . t('add_group_through_your_qr_code', $inviter->user_lang);
            } else if ($way == GROUP_COMMEND_USER) {
                $content = $str_names . t('add_group', $inviter->user_lang);
            } else if ($way == GROUP_SHARE_USER) {
                $content = t('you_invite_in_group', $inviter->user_lang, ['who' => $str_names]);
            } else {
                $content = t('you_invite_in_group', $inviter->user_lang, ['who' => $str_names]);
            }
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $invite_id,
                'action' => CHAT_GROUP_ACTION_ADD_USER,
                'content' => $content
            ];
            \Chats::createChat($params);
        }

        #其他群成员消息
        if ($notify_others) {
            $group_users = \GroupUsers::findForeach(['conditions' => 'group_id = :group_id:', 'bind' => ['group_id' => $group->id]]);
            foreach ($group_users as $group_user) {
                #排除邀请人和被邀请人推送消息
                if ($group_user->user_id == $invite_id || in_array($group_user->user_id, $user_ids)) {
                    continue;
                }

                if ($way == GROUP_ASK_USER) {
                    $content = '"' . $str_names . '"' . t('add_group_through_whose_qr_code', $group_user->user_lang, ['who' => $invite_user->nickname]);
//                    $content = "\"{$str_names}\"申请加入群";
                } else if ($way == GROUP_COMMEND_USER) {
                    $content = '"' . $str_names . '"' . t('add_group', $group_user->user_lang);
                } else if ($way == GROUP_SHARE_USER) {
                    $content = t('who_invite_other_in_group', $group_user->user_lang, ['who' => $invite_user->nickname, 'other' => $str_names]);
                } else {
                    $content = t('who_invite_other_in_group', $group_user->user_lang, ['who' => $invite_user->nickname, 'other' => $str_names]);
                }
                $params = [
                    'type' => CHAT_TYPE_TIP,
                    'group_id' => $group->id,
                    'receiver_id' => $group_user->user_id,
                    'action' => CHAT_GROUP_ACTION_ADD_USER,
                    'content' => $content
                ];
                \Chats::createChat($params);

            }
        }
    }

    /*
     * 通知群主低版本不能入群
     * */
    static function tipBlockUser($group, $user_ids)
    {
        $users = \Users::findByIds($user_ids);
        $user_names = [];

        #被邀请人
        foreach ($users as $user) {
            $user_names[] = "\"$user->nickname\"";
        }

        if (isBlank($user_names)) {
            return;
        }

        $str_names = implode('、', $user_names);
        $params = [
            'type' => CHAT_TYPE_TIP,
            'group_id' => $group->id,
            'receiver_id' => $group->manager_id,
            'content' => $str_names . t('version_low_cannot_group', $group->manager->user_lang)
        ];
        \Chats::createChat($params);
    }


    /*  需要群主审核
        群邀请已发送给群主，请等待群主确认
     * */
    static function tipNeedManageAgree($group, $user_id, $way)
    {
        #群主本人就不用发了
        if ($group->manager_id == $user_id) {
            return;
        }

        if (GROUP_ADD_USER != $way) {
            return;
        }
        $user = \Users::findFirstById($user_id);
        if (!$user) {
            return;
        }
        $content = t('send_invite_wait_confirm', $user->user_lang);

        $params = [
            'type' => CHAT_TYPE_TIP,
            'group_id' => $group->id,
            'receiver_id' => $user_id,
            'content' => $content
        ];
        \Chats::createChat($params);
    }

    /*  修改群名称
        群主聊天框提示：您修改群名为“比特钱包”
        普通成员聊天框提示：“立夏”修改群名为“比特钱包”
     *  @param $group
     */
    static function tipEditGroupName($group)
    {
        $group_users = \GroupUsers::findForeach(['conditions' => 'group_id = :group_id:', 'bind' => ['group_id' => $group->id]]);
        foreach ($group_users as $group_user) {
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $group_user->user_id,
                'action' => CHAT_GROUP_ACTION_UPDATE_NAME,
            ];
            if ($group_user->user_id == $group->manager_id) {
                $params['content'] = t('you_change_group_name', $group->manager->user_lang) . $group->name;
            } else {
                $params['content'] = $group->manager->nickname . t('change_group_name', $group_user->user_lang) . $group->name;
            }
            \Chats::createChat($params);

        }
    }


    /*  修改群群主id
    群主聊天框提示：您修改群名为“比特钱包”
    普通成员聊天框提示：“立夏”修改群名为“比特钱包”
 *  @param $group
 */
    static function tipEditGroupManager($group)
    {
        $group_users = \GroupUsers::findForeach(['conditions' => 'group_id = :group_id:', 'bind' => ['group_id' => $group->id]]);
        foreach ($group_users as $group_user) {
            if ($group_user->user_id == $group->manager_id) {
                continue;
            }
            $lang = $group_user->user->getUserLang();

            $content = $group->manager->nickname . t('be_king', $lang);
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $group_user->user_id,
                'content' => $content,
                'action' => CHAT_GROUP_ACTION_UPDATE_MANAGER,
            ];
            \Chats::createChat($params);
        }
    }


    /*  删除群成员
        群主收到消息：您把“立夏”移出了群
     *  @param $group
     *  @param $user
     */
    static function tipRemoveUser($group, $names)
    {
        $params = [
            'type' => CHAT_TYPE_TIP,
            'group_id' => $group->id,
            'receiver_id' => $group->manager_id,
            'content' => t('you_move_who_out', $group->manager->user_lang, ['who' => $names])
        ];
        \Chats::createChat($params);
    }

    /*  成员主动退群
        群主聊天框提醒：“立夏”退出了群
        群主退群，提醒新群主：您已成为新群主
        其他群成员不收到消息推送
     */
    static function tipQuit($group, $user, $new_manager_id = null)
    {
        if ($group->status != GROUP_STATUS_ON) {
            return;
        }
//        if (!$new_manager_id) {
//            return;
//        }
        if ($new_manager_id) {
            $new_manager = \Users::findFirstById($new_manager_id);
            if (!$new_manager) {
                return;
            }
            #新群主
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $new_manager_id,
                'content' => t('you_are_new_king', $new_manager->user_lang)
            ];
            \Chats::createChat($params);

        } else {
            #群主消息
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $group->manager_id,
                'content' => $user->nickname . t('quit_group', $group->manager->user_lang),
                'action' => CHAT_GROUP_ACTION_GROUP_QUIT
            ];
            \Chats::createChat($params);

        }
    }

    /*  群禁言
        群主：您开启了全员禁言
        群成员：“立夏”开启了全员禁言
     *  @param $group
     */
    static function tipBan($group)
    {
        $group_users = \GroupUsers::findForeach(['conditions' => 'group_id = :group_id:', 'bind' => ['group_id' => $group->id]]);

        foreach ($group_users as $group_user) {

            if ($group->ban_status == GROUP_BAN_ON) {
                $ban_text = t('on', $group_user->user_lang);
                $action = CHAT_GROUP_ACTION_GROUP_BAN_OPEN;
            } else {
                $ban_text = t('off', $group_user->user_lang);
                $action = CHAT_GROUP_ACTION_GROUP_BAN_CLOSE;
            }

            if ($group_user->user_id == $group->manager_id) {
                $name_text = t('you', $group_user->user_lang);
                $action = ''; #群主不要action
            } else {
                $name_text = "\"{
                $group->manager->nickname}\"";
            }

            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $group_user->user_id,
                'content' => $name_text . $ban_text . t('all_ban', $group_user->user_lang),
                'group_ban_status' => $group->ban_status,
                'action' => $action
            ];
            \Chats::createChat($params);
        }
    }

    /*  用户禁言
        被禁言者：您被“立夏”禁言
        群主："方芳"被您禁言
        群其他成员：“方芳”被“立夏”禁言
     *  @param $group
     *  @param $ban_user
     */
    static function tipUserBan($group, $group_user)
    {
        $ban_user = $group_user->user;
        debug('banUser: ', $group_user->ban_status, GROUP_BAN_ON);
        $group_users = \GroupUsers::findForeach(['conditions' => 'group_id = :group_id:', 'bind' => ['group_id' => $group->id]]);

        if ($group_user->ban_status == GROUP_BAN_USER_ON) {
            $ban_text = '';
            $action = CHAT_GROUP_ACTION_GROUP_USER_BAN_OPEN;
        } else {
            $ban_text = t('relieve');
            $action = CHAT_GROUP_ACTION_GROUP_USER_BAN_CLOSE;
        }

        foreach ($group_users as $group_user) {
            $params = [
                'type' => CHAT_TYPE_TIP,
                'group_id' => $group->id,
                'receiver_id' => $group_user->user_id,
                'group_user_ban_status' => $group_user->ban_status,
                'action' => $action
            ];
            debug('content: ', $group_user->user_id, $group->manager_id);
            if ($group_user->user_id == $group->manager_id) {
                $params['content'] = $ban_user->nickname . t('you_are_ban_or_relieve', $group_user->user_lang, ['oper' => $ban_text]);
            } elseif ($ban_user->id == $group_user->user_id) {
                $params['content'] = t('you_are_ban_or_relieve_by', $group_user->user_lang, ['oper' => $ban_text, 'who' => $group->manager->nickname]);
            } else {
                $params['content'] = t('who_are_ban_or_relieve_by', $group_user->user_lang, ['user' => $ban_user->nickname,
                    'oper' => $ban_text, 'who' => $group->manager->nickname]);
            }
            \Chats::createChat($params);
        }
    }

    /*  公告被发出后，群成员都会收到消息提醒
     *  @param $group
     */
    static function textNoticeContent($group)
    {
        $params = [
            'type' => CHAT_TYPE_TEXT,
            'group_id' => $group->id,
            'sender_id' => $group->manager_id,
            'receiver_id' => 0,
            'content' => $group->notice,
            'mention_ids' => '',
            'action' => CHAT_GROUP_ACTION_GROUP_AT_ALL
        ];
        \Chats::createChat($params);
    }


}