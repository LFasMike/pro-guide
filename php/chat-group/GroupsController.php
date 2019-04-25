<?php
/**
 * Created by PhpStorm.
 * User: maoluanjuan
 * Date: 22/08/2018
 * Time: 21:16
 */

namespace api;

class GroupsController extends BaseController
{
    function createAction()
    {
        $user_ids = $this->params('user_ids');
        list($error, $reason, $group) = \Groups::createGroup($this->currentUserId(), $user_ids);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, $group);
    }

    function detailAction()
    {
        $group_id = $this->params('id');
        $group_ids = array_filter(explode(',', $this->params('ids')));
        $groups = [];
        if (count($group_ids) > 1) {
            foreach ($group_ids as $group_id) {
                list($error,$reason,$group) = \Groups::formatGroupDetail($group_id, $this->currentUserId(), $this->currentLang());
                $groups['groups'][]=$group;
            }
            return $this->renderJSON(ERROR_CODE_SUCCESS, '', $groups);
        }

        list($error, $reason, $group) = \Groups::detail($group_id, $this->currentUserId());
        $reason = reasonToLang($this->currentLang(), $reason);
        if (isPresent($group)) {
            $group['is_ask_status'] = t(fetch($group, 'is_ask_status'), $this->currentLang());
        }
        return $this->renderJSON($error, $reason, $group);
    }

    /**
     * user邀请加入群组
     * @return bool
     */
    function addAction()
    {
        $group_id = $this->params('group_id');
        $user_ids = $this->params('user_ids');
        $manager_id = $this->currentUserId();
        list($error, $status, $reason) = \GroupUsers::reqAddGroup($group_id, $user_ids, $manager_id, GROUP_ADD_USER);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['status' => $status]);
    }


    /**
     * user申请进入群组
     * @return bool
     */
    function askAction()
    {
        $group_id = $this->params('group_id');
        $invite_id = $this->params('invite_id');
        $from_page = $this->params('from_page');
        $user_id = $this->currentUserId();
        list($error, $status, $reason, $url) = \GroupUsers::reqAskGroup($group_id, $user_id, $invite_id, $from_page);
        $reason = reasonToLang($this->currentLang(), $reason);
        if (isBlank($url)) {
            return $this->renderJSON($error, $reason, ['status' => $status]);
        } else {
            return $this->renderJSON($error, $reason, $url);
        }
    }

    /**
     * 进群审核
     * chat_id ;群消息通知id
     * @return bool
     */
    function agreeAction()
    {
        $chat_id = $this->params('chat_id');
        $manager_id = $this->currentUserId();

        list($error, $reason, $status) = \Groups::agree($chat_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['group_user_review' => $status]);
    }

    /**
     * 退群
     * @return bool
     */
    function quitAction()
    {
        $group_id = $this->params('id');
        $current_user = $this->currentUser();
        list($error, $reason) = \GroupUsers::quitGroup($group_id, $current_user);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    /**
     * 踢人
     * @return bool
     */
    function removeAction()
    {
        $group_id = $this->params('id');
        $user_ids = $this->params('user_ids');
        $current_id = $this->currentUserId();
        list($error, $reason, $data) = \GroupUsers::removeUser($user_ids, $group_id, $current_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['status' => $data]);
    }


    #群成员
    function usersAction()
    {
        $group_id = $this->params('id');
        $current_id = $this->currentUserId();
        $page = $this->params('page', 1);
        $per_page = $this->params('per_page', 500);

        list($error, $reason, $users) = \GroupUsers::findUsersByGroup($group_id, $page, $per_page, GROUP_BAN_USER_OFF, $current_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['data' => $users]);
    }


    #自动分页 群成员
    function usersPagingAction()
    {
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
        $per_page = $this->params('per_page', 20);
        $last_id = $this->params('last_id', '');
        debug($last_id, $per_page, $manager_id, $group_id);
        list($error, $reason, $users, $is_last) = \GroupUsers::groupUsersPaging($group_id, $per_page, $manager_id, $last_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['data' => $users, 'is_last' => $is_last]);
    }

    #编辑群名称
    function editNameAction()
    {
        $name = $this->params('name');
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();

        #如果是空name 则返回默认名称
        if (isBlank($name)) {
//            $cond = ['is_set_name' => GROUP_SET_NAME_DEFAULT];
            $group = \Groups::findById($group_id);
            $groupName = $group->getGroupInfo();
            $name = $group->name = $groupName['name'];
            $cond = ['name' => $name];
//            $group->save();
        } else {
            $cond = ['name' => $name];
        }

        list($error, $reason) = \Groups::editGroup($cond, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['name' => $name]);
    }

    /**
     * 入群审核状态
     * verify 1 不审核  2 审核
     * @return bool
     */
    function verifyAction()
    {
        $verify = $this->params('verify');
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
        $reason = reasonToLang($this->currentLang(), 'param_error');
        if (!in_array($verify, [GROUP_VERIFY_STATUS_OFF, GROUP_VERIFY_STATUS_ON])) {
            return $this->renderJSON(ERROR_CODE_FAIL, $reason);
        }
        list($error, $reason) = \Groups::editGroup(['verify_status' => $verify], $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    function userInfoAction()
    {
        $user_id = $this->params('user_id');
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
        list($error, $reason, $users_info) = \GroupUsers::userInfo($user_id, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, $users_info);
    }

    /**
     * #禁言 群
     * @return bool
     */
    function banGroupAction()
    {
        $group_id = $this->params('id');
        $ban_status = $this->params('ban_status');
        $manager_id = $this->currentUserId();

        $cond = ['ban_status' => $ban_status];
        list($error, $reason) = \Groups::editGroup($cond, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    /**
     * #禁言 用户
     * @return bool
     */
    function banUserAction()
    {
        $group_id = $this->params('id');
        $user_ids = $this->params('user_ids');
        $ban_status = $this->params('ban_status');
        $manager_id = $this->currentUserId();
        list($error, $reason) = \GroupUsers::banUser($user_ids, $group_id, $manager_id, $ban_status);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    /**
     * #消息提醒 用户
     * @return bool
     */
    function infoTipAction()
    {
        $group_id = $this->params('id');
        $current_id = $this->currentUserId();
        $tip_status = $this->params('tip_status', GROUP_TIP_STATUS_OFF);
        $cond = ['tip_status' => $tip_status];

        list($error, $reason) = \GroupUsers::editGroupUsers($cond, $group_id, $current_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        //更新缓存
        $ssdb = \Groups::getXRedis($group_id);
        $key = USER_ON_GROUP . $current_id;
        $ssdb->zadd($key, $tip_status, $group_id);
        return $this->renderJSON($error, $reason);
    }

    /**
     * #群列表
     * 若传入 type参数 和id参数 则结果是该用户id所在的所有群列表
     * @return bool
     */
    function listAction()
    {
        #若无参数，则默认自己所在的群组，有参数type和id时则取对应id用户所在群组列表
        if ($this->params('type', 1) == 1) {
            $user_id = $this->currentUserId();
        } else {
            $user_id = $this->params('id');
        }
        list($error, $reason, $result) = \Groups::groupsList($user_id, $this->currentUserId());
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['data' => $result]);

    }

    #请求群二维码
    function qrcodeAction()
    {
        $group_id = $this->params('id');
        $group = \Groups::findFirstById($group_id);
        if (!$group || !$group->isValid()) {
            return $this->renderJSON(ERROR_CODE_FAIL, t('group_not_exist_or_blocked', $this->currentLang()));
        }
        return $this->renderJSON(
            ERROR_CODE_SUCCESS, 'ok',
            $group->toQrcodeJson($this->currentUserId(), $this->currentProductChannel())
        );
    }

    #搜索群成员
    function searchUserAction()
    {
        $group_id = $this->params('id');
        $user_name = $this->params('name');
        $current_id = $this->currentUserId();

        list($error, $reason, $result) = \GroupUsers::searchUser($user_name, $group_id, $current_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, $result);
    }

    #通讯录
    function usersListAction()
    {
        $group_id = $this->params('id');
        $user_id = $this->currentUserId();
        list($error, $reason, $result) = \Friends::usersList($user_id, $group_id);
        return $this->renderJSON($error, $reason, $result);
    }

    #转让群
    function ownerAction()
    {
        $group_id = $this->params('group_id');
        $user_id = $this->params('id');
        $manager_id = $this->currentUserId();
        $cond = ['manager_id' => $user_id];
        if (isBlank($user_id) || isBlank($group_id)) {
            return $this->renderJSON(ERROR_CODE_FORM, t('param_error', $this->currentLang()));
        }
        list($error, $reason) = \Groups::editGroup($cond, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        $user = $this->currentUser();
        $group = \Groups::findById($group_id);
        \GroupPush::tipQuit($group, $user, $user_id);

        return $this->renderJSON($error, $reason);
    }

    #编辑群公告
    function noticeAction()
    {
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
        $notice = $this->params('notice', ' ');
        $notice_time = time();
        $cond = ['notice' => $notice, 'notice_time' => $notice_time, 'notice_author' => $manager_id];
        debug($notice, $notice_time, $manager_id);
        list($error, $reason) = \Groups::editGroup($cond, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    #修改用户群昵称
    function userNameAction()
    {
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
        $name = $this->params('name');
        $cond = ['name' => $name];
        list($error, $reason) = \GroupUsers::editGroupUsers($cond, $group_id, $manager_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason);
    }

    #解散群
    function dissolveAction()
    {
        $group_id = $this->params('id');
        $manager_id = $this->currentUserId();
//        list($error, $reason) = \Groups::dissolve();

    }

    #推荐群列表
    function recommendsAction()
    {
        $user_id = $this->currentUserId();
        list($error, $reason, $result) = \Groups::recommend($user_id);
        $reason = reasonToLang($this->currentLang(), $reason);
        return $this->renderJSON($error, $reason, ['data' => $result]);

    }


}