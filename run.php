<?php
    require __DIR__ . '/vendor/autoload.php';

    use GitWrapper\GitWrapper;
    use requestAPI\requestAPI;

    $configFilePath = __DIR__ . '/config.php';
    
    if (!is_file($configFilePath)) {
        requestAPI::logging('Config 文件不存在: ', 'error', true);
    }

    $config = require_once($configFilePath);

    $repositoryDir      = $config['repositoryDir'];

    $defaultRepository  = $config['defaultRepository'];

    $defaultAuth        = $config['auths'];

    $cookieDirPath      = __DIR__ . '/' . $config['cookiePath'];

    if (!is_dir($repositoryDir)) {
        requestAPI::logging('创建仓库目录', 'info');
        mkdir($repositoryDir, 0777, true);
    }

    if (!is_dir($cookieDirPath)) {
        requestAPI::logging('创建 Cookie 目录', 'info');
        mkdir($cookieDirPath, 0777, true);
    }

    foreach ($defaultAuth as $codingUserName => $auth) {
        requestAPI::logging('当前用户: ' . $auth[0], 'info');

        $codingApi = requestAPI::start($codingUserName, $auth, $cookieDirPath);

        if (!$codingApi->isLogin()) {
            $bool = $codingApi->login();
            if (!$bool) {
                requestAPI::logging('用户 ' . $codingUserName . ' 登录失败, 不知道原因, 退出....', 'error', true);
            }
        }

        //检查一下Coding上面有没有项目 没有就创建
        foreach ($defaultRepository as $repositoryName => $repositoryUrl) {
            $codingApi->selectProject($repositoryName);
        }

        $wrapper = new GitWrapper();
        $wrapper->setTimeout(3600);

        $defaultRepositoryTotal = count($defaultRepository); // 总数量
        $i = 1; // 只要一个仓库新建任务就可以了

        requestAPI::logging('当前用户 ' . $codingUserName . ' 需要执行 ' . $defaultRepositoryTotal . ' 个仓库', 'info');

        foreach ($defaultRepository as $repositoryName => $repositoryUrl) {
            requestAPI::logging('执行第 ' . $i . ' 个仓库', 'info');

            $projectPathName = $repositoryDir . '/' . $repositoryName;

            // 本地没有这个库 就去 git clone 下来
            if (!is_dir($projectPathName)) {
                requestAPI::logging('本地没有此仓库: ' . $repositoryName . ' 开始从 ' . $repositoryUrl . ' Clone ....', 'info');
                $git = $wrapper->cloneRepository($repositoryUrl, $projectPathName);
                if (!$git) {
                    requestAPI::logging('Clone 失败 ' . $repositoryName . ' 退出...', 'error', true);
                }
            } else {
                $git = $wrapper->workingCopy($projectPathName);
            }

            //http push 免密码
            $codingUrl = 'https://'.$codingUserName.':'.$auth[1].'@git.coding.net/'.$codingUserName.'/'.$repositoryName.'.git';
            requestAPI::logging('当前用户的Coding远程仓库 Url: ' . $codingUrl, 'info');

            $git->remote('show');

            $output = str_replace("\n", '', $git->getOutput());
            if (strpos($output, 'coding') !== false) {
                $git->remote('rm', 'coding');
            }

            $git->remote('add', 'coding', $codingUrl);

            requestAPI::logging('开始从 Github pull ', 'info');
            $git->pull();
            requestAPI::logging('Github pull Successful', 'success');

            requestAPI::logging('Push for Coding ... ', 'info');
            $git->push('coding');
            requestAPI::logging('Push for Coding Successful', 'success');

            // 只要一个仓库新建任务就可以了
            if (1 == $i) {
                requestAPI::logging('Create Task for ' . $repositoryName, 'info');
                $codingApi->creteTask($repositoryName);
                requestAPI::logging('Create Task Ok ' . $repositoryName, 'info');
            }

            $i++;
        }

        requestAPI::logging('当前用户循环结束 : ' . $auth[0], 'success');
        requestAPI::logging('----- Next ------', 'info');
    }
