# CodingFormabi

## Use

* 同目录新建 `config.php`， 内容如下

```
<?php
return [

    // 从Github上面随便选择一些天天提交的开源项目, 项目尽量不要太大, 也不要选择太多, 几个足矣。
    // array
    // key Coding 仓库名, 本地的仓库名, 必填
    // val 仓库地址, git协议会比较快一点。 必填

    'defaultRepository' => [
        'flarum'            => 'git://github.com/flarum/flarum.git',
        'wechat'            => 'git://github.com/overtrue/wechat.git',
        'hacker-menu'       => 'git://github.com/jingweno/hacker-menu.git',
        'v2ray-core'        => 'git://github.com/v2ray/v2ray-core.git',
        'Android-Tips'      => 'git://github.com/tangqi92/Android-Tips.git',
    ],

    'repositoryDir'     => '/path/to/project', // 存放本地仓库的目录

    // Coding 用户
    // array 二维
    // key Coding 的唯一用户昵称
    'auths' => [
        'deere' => [
            'deere_marchi@qq.net',      // 邮箱
            'deere20xx.net'             // 密码
        ],
    ],

    'cookiePath' => 'cookies',          // 存放 cookie 的目录
];
```

* `php -f run.php`
> `Linux` 加入定时任务 ` 15 6,23 * * * /usr/local/php/bin/php -f /path/to/CodingFormabi/run.php`
> 第一次运行会比较慢


* `php -f delete_task.php`
> 定时任务 ` * * */3 * * /usr/local/php/bin/php -f /path/to/CodingFormabi/delete_task.php`
