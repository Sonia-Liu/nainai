<?php
/**
 * DouPHP
 * --------------------------------------------------------------------------------------------------
 * 版权所有 2013-2015 漳州豆壳网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.douco.com
 * --------------------------------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在遵守授权协议前提下对程序代码进行修改和使用；不允许对程序代码以任何形式任何目的的再发布。
 * 授权协议：http://www.douco.com/license.html
 * --------------------------------------------------------------------------------------------------
 * Author: DouCo
 * Release Date: 2015-10-16
 */
define('IN_DOUCO', true);
define('NO_CHECK', true);

require (dirname(__FILE__) . '/include/init.php');

// rec操作项的初始化
$rec = $check->is_rec($_REQUEST['rec']) ? $_REQUEST['rec'] : 'default';

// 赋值给模板
$smarty->assign('rec', $rec);

/**
 * +----------------------------------------------------------
 * 登录页
 * +----------------------------------------------------------
 */
if ($rec == 'default') {
    // 赋值给模板
    $smarty->assign('page_title', $_LANG['login']);
    $smarty->display('login.htm');
} 

/**
 * +----------------------------------------------------------
 * 登录验证
 * +----------------------------------------------------------
 */
elseif ($rec == 'login') {
    if ($check->is_captcha(trim($_POST['captcha'])) && $_CFG['captcha'])
        $_POST['captcha'] = strtoupper(trim($_POST['captcha']));
    
    if (!$_POST['user_name']) {
        $dou->dou_msg($_LANG['login_input_wrong'], 'login.php', 'out');
    } elseif (md5($_POST['captcha'] . DOU_SHELL) != $_SESSION['captcha'] && $_CFG['captcha']) {
        $dou->dou_msg($_LANG['login_captcha_wrong'], 'login.php', 'out');
    }
    
    $_POST['user_name'] = $check->is_username(trim($_POST['user_name'])) ? trim($_POST['user_name']) : '';
    $_POST['password'] = $check->is_password(trim($_POST['password'])) ? trim($_POST['password']) : '';
    
    $query = $dou->select($dou->table(admin), '*', "user_name = '$_POST[user_name]'");
    $user = $dou->fetch_array($query);
    
    if (!is_array($user)) {
        $dou->create_admin_log($_LANG['login_action'] . ': ' . $_POST['user_name'] . " ( " . $_LANG['login_user_name_wrong'] . " ) ");
        $dou->dou_msg($_LANG['login_input_wrong'], 'login.php', 'out');
        // 登录失败清除验证码
        unset($_SESSION['captcha']);
    } elseif (md5($_POST['password']) != $user['password']) {
        if ($_POST['password']) {
            $dou->create_admin_log($_LANG['login_action'] . ': ' . $_POST['user_name'] . " ( " . $_LANG['login_password_wrong'] . " ) ");
        }
        $dou->dou_msg($_LANG['login_input_wrong'], 'login.php', 'out');
        // 登录失败清除验证码
        unset($_SESSION['captcha']);
    }
    
    $_SESSION[DOU_ID]['user_id'] = $user['user_id'];
    $_SESSION[DOU_ID]['shell'] = md5($user['user_name'] . $user['password'] . DOU_SHELL);
    $_SESSION[DOU_ID]['ontime'] = time();
    
    $last_login = time();
    $last_ip = $dou->get_ip();
    $sql = "update " . $dou->table('admin') . " SET last_login = '$last_login', last_ip = '$last_ip' WHERE user_id = " . $user['user_id'];
    $dou->query($sql);
    $dou->create_admin_log($_LANG['login_action'] . ': ' . $_LANG['login_success']);
    $dou->dou_header(ROOT_URL . ADMIN_PATH . '/index.php');
} 

/**
 * +----------------------------------------------------------
 * 退出登录
 * +----------------------------------------------------------
 */
elseif ($rec == 'logout') {
    unset($_SESSION[DOU_ID]);
    $dou->dou_header(ROOT_URL . ADMIN_PATH . '/login.php');
}

/**
 * +----------------------------------------------------------
 * 密码重置
 * +----------------------------------------------------------
 */
elseif ($rec == 'password_reset') {
    $user_id = $check->is_number($_REQUEST['uid']) ? $_REQUEST['uid'] : '';
    $code = preg_match("/^[a-zA-Z0-9]+$/", $_REQUEST['code']) ? $_REQUEST['code'] : '';

    // CSRF防御令牌生成
    $smarty->assign('token', $firewall->set_token('password_reset'));
    
    if ($user_id && $code) {
        if (!$dou->check_password_reset($user_id, $code)) {
            $dou->dou_msg($_LANG['login_password_reset_fail'], ROOT_URL . ADMIN_PATH . '/login.php？rec=password_reset', 'out');
        }
        $smarty->assign('user_id', $user_id);
        $smarty->assign('code', $code);
        $smarty->assign('action', 'reset');
    } else {
        $smarty->assign('action', 'default');
    }
    
    // 赋值给模板
    $smarty->assign('page_title', $_LANG['login_password_reset']);
    $smarty->display('login.htm');
}

/**
 * +----------------------------------------------------------
 * 重置密码提交
 * +----------------------------------------------------------
 */
elseif ($rec == 'password_reset_post') {
    $action = $_POST['action'] == 'reset' ? 'reset' : 'default';
    
    if ($action == 'default') {
        // 验证用户名
        if (!$dou->field_exists('admin', 'user_name', $_POST['user_name']) || !$dou->field_exists('admin', 'email', $_POST['email']))
            $dou->dou_msg($_LANG['login_password_reset_wrong'], ROOT_URL . ADMIN_PATH . '/login.php?rec=password_reset', 'out');
    
        // CSRF防御令牌验证
        $firewall->check_token($_POST['token'], 'password_reset');
        
        // 生成密码找回码
        $user = $dou->fetch_array($dou->select($dou->table('admin'), '*', "user_name = '$_POST[user_name]' AND email = '$_POST[email]'"));
        $time = time();
        $code = substr(md5($user['user_name'] . $user['email'] . $user['password'] . $time . $user['last_login'] . DOU_SHELL) , 0 , 16) . $time;
        $site_url = rtrim(ROOT_URL, '/');
        
        $body = $user['user_name'] . $_LANG['login_password_reset_body_0'] . ROOT_URL . ADMIN_PATH . '/login.php?rec=password_reset' . '&uid=' . $user['user_id'] . '&code=' . $code . $_LANG['login_password_reset_body_1'] . $_CFG['site_name'] . '. ' . $site_url;
        
        // 发送密码重置邮件
        if ($dou->send_mail($user['email'], $_LANG['login_password_reset'], $body)) {
            $dou->dou_msg($_LANG['login_password_mail_success'] . $user['email'], ROOT_URL . ADMIN_PATH . '/login.php', 'out', '30');
        } else {
            $dou->dou_msg($_LANG['mail_send_fail'], ROOT_URL . ADMIN_PATH . '/login.php?rec=password_reset', 'out', '30');
        }
    } elseif ($action == 'reset') {
        // 验证密码
        if (!$check->is_password($_POST['password'])) {
            $dou->dou_msg($_LANG['manager_password_cue'], '', 'out');
        } elseif (($_POST['password_confirm'] !== $_POST['password'])) {
            $dou->dou_msg($_LANG['manager_password_confirm_cue'], '', 'out');
        }

        $user_id = $check->is_number($_POST['user_id']) ? $_POST['user_id'] : '';
        $code = preg_match("/^[a-zA-Z0-9]+$/", $_POST['code']) ? $_POST['code'] : '';
        
        if ($dou->check_password_reset($user_id, $code)) {
            // 重置密码
            $sql = "UPDATE " . $dou->table('admin') . " SET password = '" . md5($_POST['password']) . "' WHERE user_id = '$user_id'";
            $dou->query($sql);
            $dou->dou_msg($_LANG['login_password_reset_success'], ROOT_URL . ADMIN_PATH . '/login.php', 'out', '15');
        } else {
            $dou->dou_msg($_LANG['login_password_reset_fail'], ROOT_URL . ADMIN_PATH . '/login.php', 'out', '15');
        }
    }
}
?>