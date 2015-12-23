<?php
namespace Data\Models\Gongchanginfo;

class user3 extends \Data\Models\Table
{
    //源表主键
    public $primaryKey = "uid";

    //将读到的源数据传到callback函数
    public $callbackRow = 'src';

    public $fieldMap = array(
        'uid' => 'uid/myfunc',
    );

    public function myfunc($id)
    {
        //禁用数据转换，不再往新库中写数据
        return false;
    }

    //需要做的事情
    public function callback($row)
    {

        $memObj = new \Memcached();
        foreach ($row as $value) {
            //更新缓存
            $key = 'user_' . $value['uid'];
            $memObj->set($key, json_encode($value));

            //给用户发站内信等
            $emailObj = new email();
            $emailObj->send($value['uid']);
            //.....
            //anything
        }
    }
}
