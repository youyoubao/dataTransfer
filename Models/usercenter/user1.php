<?php
namespace Data\Models\Gongchanginfo;

class user1 extends \Data\Models\Table
{
    //源表主键
    public $primaryKey = "uid";
    //目标数据库、表名
    public $descTable = "newUser.user";

    //添加此行定义
    public $srcSql = "select a.*, b.* from usercenter.user a left join usercenter.user_contact b on a.uid = b.uid ";

    //数据转移时的字段对应关系
    //格式：{目标数据表的字段名} => {源表的字段名}
    public $fieldMap = array(
        'uid'      => 'uid',
        'username' => 'name',
        'sex'      => 'sex',
        'password' => 'passwd',
        'mobile'   => 'tel',
        'tel'      => 'mobile',
        'address'  => 'address',
        'province' => 'province',
        'city'     => 'city',
        'zone'     => 'zone',
    );
}
