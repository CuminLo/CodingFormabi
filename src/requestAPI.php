<?php
namespace requestAPI;
class requestAPI {
    private $ch;

    private $baseUrl = 'https://coding.net/api/';

    private $lastHeaderInfo;

    private $auth = [];
    private $codingUserName;
    private $_cookie;

    const REQUEST_POST      = 'POST';
    const REQUEST_GET       = 'GET';
    const REQUEST_DELETE    = 'DELETE';

    private function __construct() {}

    public static function start($codingUserName, array $auth, $cookiesDir)
    {
        if (!$auth || !$codingUserName) {
            self::logging('Auth or codingUserName can not blank', 'error', true);
        }

        $api = new requestAPI();

        $api->ch = curl_init();

        $api->auth = $auth;

        $api->codingUserName = $codingUserName;

        $api->_cookie = $cookiesDir . '/' . strtoupper(md5($auth[0]));

        if (!is_file($api->_cookie)) {
            if (touch($api->_cookie)) {
                self::logging('Create Cookiefile Successful ' . $api->_cookie, 'success');
            }
        }

        return $api;
    }

    public static function logging($message, $type='success', $over = false)
    {
        $styles = [
            'success'   => "\033[0;32m%s\033[0m",
            'error'     => "\033[31;31m%s\033[0m",
            'info'      => "\033[33;33m%s\033[0m",
            'default'   => "\033[33;39m%s\033[0m",
        ];

        $format = isset($styles[$type]) ? $styles[$type] : $styles['default'];

        $format .= PHP_EOL;
		
		$message = date('Y m/d H:i:s') . ' - ' . $message;

        printf($format, $message);

        if ($over) {
            printf($format, 'Exit...');die;
        }
    }

    public function login()
    {
        $params = [
            'email'      => $this->auth[0],
            'password'   => sha1($this->auth[1]),
        ];

        $apiUrl = 'account.login';

        $loginResult = $this->execute(self::REQUEST_POST, $apiUrl, $params);

        if (!$loginResult) {
            return false;
        }

        if (isset($loginResult['data'])) {
            return true;
        } else {
            return false;
        }
    }

    public function execute($method, $apiUrl, $params='')
    {
        $apiUrl = str_replace('.', '/', $apiUrl);

        $requestUrl = $this->baseUrl . $apiUrl;

        $defaultCurlOpt = [
            CURLOPT_CUSTOMREQUEST       => $method,
            CURLOPT_URL                 => $requestUrl,
            CURLOPT_HEADER              => false,
            CURLOPT_FAILONERROR         => false,
            CURLOPT_FOLLOWLOCATION      => true,
            CURLOPT_RETURNTRANSFER      => true,
            CURLOPT_AUTOREFERER         => true,
            CURLOPT_ENCODING            => '',
            CURLOPT_USERAGENT           => 'Mozilla/5.0 (X11; Linux x86_64; rv:36.0) Gecko/20100101 Firefox/36.0',
            CURLOPT_COOKIEJAR           => $this->_cookie,
            CURLOPT_COOKIEFILE          => $this->_cookie,
            CURLOPT_COOKIE              => 'sid=' . sha1($this->auth[0]),
        ];

        self::logging('请求Url:' . $requestUrl, 'info');

        if (strlen($requestUrl) > 5 && strtolower(substr($requestUrl, 0, 5)) == "https") {
            $defaultCurlOpt += [
                CURLOPT_CAINFO              => __DIR__ . '/ca.crt',
            ];
        }

        if ($method === self::REQUEST_POST) {
            $defaultCurlOpt += [
                CURLOPT_POST          => true,
                CURLOPT_POSTFIELDS    => http_build_query($params),
            ];
        } else {
            $defaultCurlOpt[CURLOPT_POST] = false;
        }

        curl_setopt_array($this->ch, $defaultCurlOpt);

        $resultData = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            $err = curl_error($this->ch);
            $this->close();
            //throw new Exception('cURL error: ' . $err);
        }

        $headerInfo = curl_getinfo($this->ch);

        $this->lastHeaderInfo = $headerInfo;

        if ($this->lastHeaderInfo['http_code'] != 200) {
            return false;
        }

        return json_decode($resultData, true);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        curl_close($this->ch);
    }

    public function getCurrentUserInfo()
    {
        $apiUrl = 'account.current_user';

        return $this->execute(self::REQUEST_GET, $apiUrl);
    }

    public function getCurrentUserId()
    {
        $userInfo = $this->getCurrentUserInfo();
        if (!isset($userInfo['data']) || !isset($userInfo['data']['id'])) {
            return false;
        }
        return $userInfo['data']['id'];
    }

    public function isLogin()
    {
        self::logging('判断 ' . $this->codingUserName . ' 是否登录', 'info');
        $userInfo = $this->getCurrentUserInfo();

        if ($userInfo['code'] != 0) {
            return false;
        }

        return true;
    }

    public function selectProject($projectName)
    {
        $apiUrl = 'user.'.$this->codingUserName.'.project.' . $projectName;

        $result = $this->execute(self::REQUEST_GET, $apiUrl);

        if ($result['code'] != 0) {
            self::logging('用户 ' . $this->codingUserName . ' 在 Coding上没有这个项目: ' . $projectName, 'info');
            $bool = $this->createProject($projectName);
            if ($bool) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    public function createProject($projectName)
    {
        self::logging('在 Coding 上创建项目: ' . $projectName, 'info');

        $apiUrl = 'user.'.$this->codingUserName.'.project';

        $params = [
            'name'              => $projectName,
            'description'       => 'for Github',
            'type'              => 2,               // 项目类型, "1" - 公有项目, "2" - 私有项目
            'gitEnabled'        => 'true',          // 是否开启git仓库, "true" - 是, "false" - 否I
            'gitReadmeEnabled'  => 'false',         // 是否启用README.md初始化项目, "true" - 是, "false" - 否
            'vcsType'           => 'git',           // vcs类型, "git" - git, "svn" - svn, "hg" - hg
        ];

        $data = $this->execute(self::REQUEST_POST, $apiUrl, $params);

        //已经存在
        if ($data['code'] == 1103) {
            self::logging('项目: ' . $projectName . ' 已经存在', 'info');
            return true;
        }

        if ($data['code'] != 0) {
            self::logging('项目: ' . $projectName . ' 创建失败, 退出...', 'error', true);
            return false;
        } else {
            self::logging('项目: ' . $projectName . ' 创建成功', 'success');
            return true;
        }
    }

    /**
     * 创建任务 0.01 码币
     * creteTask
     * @param $projectName
     * @return bool
     * @author CuminLo
     * @email CuCuCumin@gmail.com
     */
    public function creteTask($projectName)
    {
        $apiUrl = 'user.'.$this->codingUserName.'.project.'.$projectName.'.task';

        $userId = $this->getCurrentUserId();

        if (!$userId) {
            return false;
        }

        $params = [
            'deadline'      => '',                  // 任务完成期限, 格式 "yyyy-MM-dd"
            'content'       => '尽快完成',           // 任务内容
            'priority'      => 1,                   // 任务优先级，代码默认值为 1, 0 - 有空再看, 1 - 正常处理, 2 - 优先处理, 3 - 十万火急
            'owner_id'      => $userId,             // 任务执行者id
            'status'        => 1,                   // 任务状态，1 - 进行中, 2 - 已完成
            'description'   => 'for Coding Mabi',   // 任务描述,可以为空
            'project_name'  => $projectName,
            'user_name'     => $this->codingUserName,
        ];

        $this->execute(requestAPI::REQUEST_POST, $apiUrl, $params);
    }

    public function getProjectAllTask($projectName)
    {
        $apiUrl = 'user.'.$this->codingUserName.'.project.'.$projectName.'.tasks.all';

        $response = $this->execute(requestAPI::REQUEST_GET, $apiUrl);

        if (!isset($response['data']) || !isset($response['data']['list'])) {
            return false;
        }

        $taskIds = [];
        foreach ($response['data']['list'] as $item) {
            $taskIds[] = $item['id'];
        }

        return $taskIds;
    }

    public function deleteTask($projectName)
    {
        $taskIds = $this->getProjectAllTask($projectName);
        if (!is_array($taskIds)) {
            requestAPI::logging('Task is blank', 'info');
            return false;
        }

        foreach ($taskIds as $id) {
            requestAPI::logging('Delete Task id ' . $id . ' Current User ' . $this->codingUserName, 'info');
            $apiUrl = 'user.'.$this->codingUserName.'.project.'.$projectName.'.task.' . $id;
            $this->execute(requestAPI::REQUEST_DELETE, $apiUrl);
        }

        return true;
    }

    public function getLastHeaderInfo()
    {
        return $this->lastHeaderInfo;
    }

    public function getCookie()
    {
        return $this->_cookie;
    }
}
