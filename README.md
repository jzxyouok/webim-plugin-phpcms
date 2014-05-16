webim-plugin-phpcms
===================

WebIM for PHPCMS v9

Require
=======

PHP > 5.3.10

Usage
=====

1. 上传webim到phpcms站点根目录;

2. 导入webim/install.sql表到phpcms数据库;

3. 配置webim/config.php; 

4. 编码webim/PHPCMS_Plugin.php，集成站点用户、群组、通知等数据;

5. phpcms需要显示webim的页面，footer嵌入:

```javascript

<script type="text/javascript" src="webim/index.php?action=boot"></script>

```

PHPCMS_Plugin.php
================

WebIM-for-PHPCMS插件类, 参考示例代码，实现下述接口:

1. user() 获取当前登录用户UID, 默认从PHPCMS的"param::get_cookie('_userid')"读取; 初始化WebIM当前的用户对象, 默认从PHPCMS的member_db读取;

2. buddies($uid) 读取当前用户的在线好友列表

3. buddiesByIds($uid, $ids) 根据ids列表读取好友列表

4. rooms($uid) 读取当前用户所属的群组，以支持群聊

5. roomsByIds($uid, $ids) 根据id列表读取群组列表

6. members($room) 根据群组Id，读取群组成员信息

7. notifications($uid) 读取当前用户的通知信息

8. menu($uid) 读取当前用户的菜单


Router.php
==============================

WebIM应用AJAX请求分发处理


Model.php
==============================

WebIM数据模型类


App.php
==============================

WebIM应用入口类


Install.sql
==============================


webim_settings
--------------

用户设置表，保存用户界面个性化设置


webim_histories
----------------

历史消息表，保存聊天历史消息


webim_rooms
----------------

临时讨论组表


webim_members
----------------

临时讨论组成员表

webim_visitors
--------------

访客表


Author
======

http://nextalk.im

ery.lee at gmail.com

nextalk at qq.com

