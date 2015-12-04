<?php
require __DIR__ . '/vendor/autoload.php';

use requestAPI\requestAPI;

$configFilePath = __DIR__ . '/config.php';

if (!is_file($configFilePath)) {
    requestAPI::logging('Config 文件不存在: ', 'error', true);
}

$config = require_once($configFilePath);

$defaultRepository  = $config['defaultRepository'];

$defaultAuth        = $config['auths'];

$cookieDirPath      = __DIR__ . '/' . $config['cookiePath'];

foreach ($defaultAuth as $codingUserName => $auth) {

    $codingApi = requestAPI::start($codingUserName, $auth, $cookieDirPath);

    $defaultRepositoryTotal = count($defaultRepository); // 总数量
    $i = 1; // 只要一个仓库新建任务就可以了

    foreach ($defaultRepository as $repositoryName => $repositoryUrl) {

        // 删除第一个仓库的所有任务
        if (1 == $i) {
            requestAPI::logging('Delete Task for ' . $repositoryName, 'info');
            $codingApi->deleteTask($repositoryName);
        }

        $i++;
    }

    requestAPI::logging('当前用户循环结束 : ' . $auth[0], 'success');
    requestAPI::logging('----- Next ------', 'info');
}
