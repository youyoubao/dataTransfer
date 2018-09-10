<?php
namespace dormscript\Data\Models\{$schemaName};

class {$modelName} extends \Data\Models\Table
{

    public $primaryKey = "{$primaryKey}";
    public $descTable  = "{$descSchema}.{$descTableName}";

    //字段对应关系
    public $fieldMap = array(
    	{$fieldMap}
    );
}
