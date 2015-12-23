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

require (dirname(__FILE__) . '/include/init.php');

// 验证并获取合法的ID，如果不合法将其设定为-1
$cat_id = $firewall->get_legal_id('zhaopin_category', $_REQUEST['id'], $_REQUEST['unique_id']);
if ($cat_id == -1) {
    $dou->dou_msg($GLOBALS['_LANG']['page_wrong'], ROOT_URL);
} else {
    $where = ' WHERE cat_id IN (' . $cat_id . $dou->dou_child_id('zhaopin_category', $cat_id) . ')';
}
    
// 获取分页信息
$page = $check->is_number($_REQUEST['page']) ? trim($_REQUEST['page']) : 1;
$limit = $dou->pager('zhaopin', ($_DISPLAY['zhaopin'] ? $_DISPLAY['zhaopin'] : 10), $page, $dou->rewrite_url('zhaopin_category', $cat_id), $where);

/* 获取招聘列表 */
$sql = "SELECT id, title, salary , content, image, cat_id, add_time, click, description FROM " . $dou->table('zhaopin') . $where . " ORDER BY id DESC" . $limit;
$query = $dou->query($sql);

while ($row = $dou->fetch_array($query)) {
    $url = $dou->rewrite_url('zhaopin', $row['id']);
    $add_time = date("Y-m-d", $row['add_time']);
    $add_time_short = date("m-d", $row['add_time']);
    $image = $row['image'] ? ROOT_URL . $row['image'] : '';
    
    // 如果描述不存在则自动从详细介绍中截取
    $description = $row['description'] ? $row['description'] : $dou->dou_substr($row['content'], 200);
    
    // 格式化薪资 (新)
    /*$salary = $row['salary'] > 0 ? $dou->salary_format($row['salary']) : $_LANG['salary_discuss'];*/

    $zhaopin_list[] = array (
            "id" => $row['id'],
            "cat_id" => $row['cat_id'],
            "title" => $row['title'],
            "image" => $image,
            "salary" => $salary,//新增
            "add_time" => $add_time,
            "add_time_short" => $add_time_short,
            "click" => $row['click'],
            "description" => $description,
            "url" => $url 
    );
}

// 获取分类信息
$sql = "SELECT cat_id, cat_name, parent_id FROM " . $dou->table('zhaopin_category') . " WHERE cat_id = '$cat_id'";
$query = $dou->query($sql);
$cate_info = $dou->fetch_array($query);

// 赋值给模板-meta和title信息
$smarty->assign('page_title', $dou->page_title('zhaopin_category', $cat_id));
$smarty->assign('keywords', $cate_info['keywords']);
$smarty->assign('description', $cate_info['description']);

// 赋值给模板-导航栏
$smarty->assign('nav_top_list', $dou->get_nav('top'));
$smarty->assign('nav_middle_list', $dou->get_nav('middle', '0', 'zhaopin_category', $cat_id, $cate_info['parent_id']));
$smarty->assign('nav_bottom_list', $dou->get_nav('bottom'));

// 赋值给模板-数据
$smarty->assign('ur_here', $dou->ur_here('zhaopin_category', $cat_id));
$smarty->assign('cate_info', $cate_info);
$smarty->assign('zhaopin_category', $dou->get_category('zhaopin_category', 0, $cat_id));
$smarty->assign('zhaopin_list', $zhaopin_list);

$pageBar = getPageBar($smarty->_tpl_vars['pager']);


$smarty->assign('pageBar', $pageBar);
$smarty->display('zhaopin_category.dwt');

function getPageBar( $pager ){
    $href = substr($pager['last'], 0, strrpos($pager['last'], 'page=')) . 'page='; //页面链接
    
    $curr = $pager['page']; //当前页码

    $count = $pager['page_count']; //总页数

    $left = max($curr-2, 1); //初步计算最左边页码

    $right = min($left + 4, $count); //计算最右边页码
    $left = max($right-4, 1);// 计算最终左边的页码
    $pageBar = array();
    for($i=$left; $i<=$right; $i++){
        $pageBar[$i]['code'] = $i;
        $pageBar[$i]['link'] = $href.$i;
    }

    return $pageBar;
}
?>