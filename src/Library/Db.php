<?php
namespace dormscript\Data\Library;

//多进程来调用这个Db。如果A进程创建的mysql连接，B进程是不能使用的，所以需要标记连接是哪个进程创建的
//其实这种方法是有问题的，但在当前情景下可正常运行
//
//
//以前使用的mysql底层驱动是mysql,后来改成mysqli，部分函数未升级
//使用静态二维数组dbPool完全没用，一维就可以，稍后修改代码
class Db
{
    public static $dbPool;
    public static function exeSql($dbname, $sql, $taskid = 0)
    {
        global $db;
        $ret = array();

        if (!isset(self::$dbPool[getmypid()][$dbname]) || empty(self::$dbPool[getmypid()][$dbname])) {
            $dbConnection = mysqli_connect($db[$dbname]['host'], $db[$dbname]['user'], $db[$dbname]['pswd']) or die("连接 '" . $dbname . "'库失败");
            mysqli_query($dbConnection, "SET NAMES 'UTF8'");
            self::$dbPool[getmypid()][$dbname] = $dbConnection;
        } else {
            $dbConnection = self::$dbPool[getmypid()][$dbname];
        }

        if (!$query = mysqli_query($dbConnection, $sql)) {
            throw new \Exception("出錯的SQL：" . $sql . "\t" . mysqli_errno($dbConnection) . ": " . mysqli_error($dbConnection));
        }
        if ($query instanceof \mysqli_result) {
            while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                $ret[] = $row;
            }
            return $ret;
        } else {
            return mysqli_insert_id($dbConnection);
        }
    }
    public static function delDbPool()
    {
        if (empty(self::$dbPool[getmypid()])) {
            return true;
        }
        foreach (self::$dbPool[getmypid()] as $value) {
            mysqli_close($value);
        }
        self::$dbPool[getmypid()] = [];
    }
}
