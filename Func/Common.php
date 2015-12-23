<?php
namespace Data\Func;

//数据转移过程中常用的函数
//demo1：地区ID转换
class Common
{
    public static $areaInfo; //地区转换关系存储到数组
    /**
     * 根据省、市、区ID获取新的ID
     * @param  array $p 省、市、区ID
     * @return [type]    [description]
     */
    public static function getAreaInfo($p)
    {
        $p = array_filter($p);
        if (empty($p)) {
            return array(0, 0, 0);
        }
        if (empty(self::$areaInfo)) {
            include BASEDIR . "/Func/file/area.php";
            foreach ($area as $v) {
                $id = $v['id'];
                unset($v['id']);
                $v                                = array_filter($v);
                self::$areaInfo[implode("_", $v)] = $id;
            }
            unset($area);
        }
        $key = implode("_", $p);
        if (!isset(self::$areaInfo[$key])) {
            echo "\n 未找到地区对应关系 ：" . implode("-", $p) . "\n";
            array_pop($p);
            return Common::getAreaInfo($p);
        }
        $id = self::$areaInfo[$key];

        if (strlen($id) == 6) {
            //将ID转换为省、市、区三个字段
            $provinceId = substr($id, 0, 2) . '0000';
            if (substr($id, 2, 2) == '00') {
                $cityId = 0;
            } else {
                $cityId = substr($id, 0, 4) . '00';
            }
            if (substr($id, 4, 2) == '00') {
                $areaId = 0;
            } else {
                $areaId = $id;
            }
        } else {
            $provinceId = substr($id, 0, 2) . '0000';
            if (substr($id, 2, 4) == '0000') {
                $cityId = 0;
            } else {
                $cityId = substr($id, 0, 6);
            }
            $areaId = $id;
        }
        return array($provinceId, $cityId, $areaId);
    }
}
