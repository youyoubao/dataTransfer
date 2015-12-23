<?php
namespace Data\Models;

class Table
{
    public $srcTable  = "";
    public $descTable = "";

    public $primaryKey   = ""; //源表的主键名称
    public $srcSql       = ""; // 从源数据表读数据的SQL,可选项
    public $readDbName   = ""; //源表所在Mysql服务器名称
    public $writeDbName  = "write"; //目标所有mysql服务器名称
    public $callbackRow  = 'src'; //callback调取源数据 desc(调取新数据)
    protected $swooleSer = ''; //在task中获取server

    //字段对应关系
    public $fieldMap = array();

    //获取数据库表的名称
    public function getTablename()
    {
        if (!empty($this->srcTable)) {
            return $this->srcTable;
        }
        $className = get_class($this);
        $tableName = str_replace("\\", ".", str_replace(__NAMESPACE__ . '\\', "", $className));
        return strtolower($tableName);
    }
    public function returnme($p)
    {
        return $p;
    }
    public function setSwooleSer($ser)
    {
        $this->swooleSer = $ser;
    }
    public function getSwooleSer()
    {
        return $this->swooleSer;
    }
    public function callback($row)
    {
        return true;
    }

    public function int10($p)
    {
        $p = intval($p);
        if ($p > 4294967296) {
            $p = 0;
        }
        return $p;
    }
    public function __call($funname, $arguments)
    {
        return call_user_func_array($funname, $arguments);
    }
}
