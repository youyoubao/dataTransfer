#数据迁移框架
实现多张mysql数据表的同时迁移，对数据分段并行处理。支持动态调整处理的进程数，支持迁移进度查看、增量数据同步等功能
##解决的问题：
* 封装数据迁移的公共部分
* 提供并行处理
* 同步数据迁移过程中的增量数据变更（需要借助第三方工具）
* 提供数据迁移后的后续操作，例如更新memcache缓存，通知第三方等
* 提供简易的数据迁移进度查看界面，动态调整处理进程数

##用到的技术：
* PHP
* Swoole

##适用场景：
* 将源数据库中的表迁移到新的数据库
* 将源数据库中的表进行合并迁移到新数据库
* 将源数据库中的数据按照一定规则处理后写到新的数据库
* 将源数据库中的数据按照一定规则过滤，将过滤后的结果写到新的数据库

##场景1用法介绍：
将源数据库的user表迁移到新数据库的newUser.user表

	1：vim config.php，配置源、目标数据库的ip、用户名、密码
	2：进入Models目录，创建子目录，目录名为源数据库的库名
	3：进入新创建的目录，创建文件，格式为：{$tableName}.php
	4：内容见Models\usercenter\user.php
	5：设置需要多少个进程来处理数据迁移。vim config.php，配置setting
	5：进入根目录执行：  php main.php

##场景2用法：
将源数据库的user表、user_contact合并，迁移到新数据库的newUser.user表

	代码见user1.php
##场景3、4用法：
将源数据库的user表迁移到新数据库的newUser.user表，同时将sex字段的0改为男，1改为女。如果sex为其它值，不迁移此条记录

	代码见user2.php
##callback的使用：
	代码见user2.php中的callback函数
**需要注意：callback函数接收到的参数默认是源数据库中的记录。如果需要使用转换后的记录，需要一行设置：**
	public $callbackRow  = 'desc'; 
##附：fieldmap支持的格式：
* 一对一


		'username' => 'username'
		说明：将源表的username字段原样转到新表的username字段

* 一对一，格式转换
		
		'pid' => 'pid/getNewpid'
		说明：使用自定义函数将源表的pid字段转换后存入新表的pid字段
		例 ： 
		public function getNewPid($p)
	    {
	        return ($p + 1000000001);
	    }

* 一对一，格式转换、数据过滤

		'state'  => 'status/getStatus',
		说明：使用自定义函数getstatus处理status字段。
		如果getstatus函数返回值为false，当前记录会被忽略。返回其它值，将返回值存入新表
		public function getstatus () {
			if(in_array($status, 1,2,3)) {
				//只迁移状态为1、2、3数据
				return $status;
			} else {
				//其它状态的数据不再迁移
				return false;
			}
		}
* 多对一

		'prokey' => 'prokey,prokeyword/getkey',
		说明：将两个字段经过getkey函数处理后（返回值是字符串），存入新表的prokey字段
* 多对多
		
		'cate1-cate2-cate3' => 'cate1,cate2,cate3/getCate',
		说明：getcate函数接收1个参数（数组），返回值为一个数组。
* 将新表的字段设默认值
	
		'newfieldname' => '4/returnme',
	
		
##其它用途：
	可以将此框架做为简单的并行读数据框架，callback中写自己需要做的事情。
	例：user3.php


