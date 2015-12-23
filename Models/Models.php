<?php
namespace Data\Models;

/**
 * 读取表的对象
 */
class Models
{
    public static $ModelArr;
    public static function getObj($tablename)
    {
        if (!isset(self::$ModelArr[md5($tablename)])) {
            self::$ModelArr[md5($tablename)] = self::readFile($tablename);
        }
        return self::$ModelArr[md5($tablename)];
    }

    /**
     * 根据tablename获取Models对象
     * @param  [type] $tablename [description]
     * @return [type]            [description]
     */
    public static function readFile($tablename)
    {
        $className = __NAMESPACE__ . '\\' . str_replace(".", '\\', $tablename);
        $obj       = new $className;
        return $obj;
    }
}
