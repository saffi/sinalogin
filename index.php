<?php
/**
 * 用于模拟新浪微博登录!
 *@author saffi&rivsen
 */

/** 定义项目路径 */
define('PROJECT_ROOT_PATH' , dirname(__FILE__));
define('COOKIE_PATH' , PROJECT_ROOT_PATH );
define('DOMAIN' , 'http://' . $_SERVER['HTTP_HOST']);
define('TIMESTAMP', time());
// 出现问题的时候可以开启, 调试用的, 会在当前文件夹下面创建 LOG 文件
define('DEBUG', false);


if($_POST){
   // extract($_POST);
    $username  = $_POST['username'];
    $password  = $_POST['password'];
    $text = $_POST['text'];
    //$image = PROJECT_ROOT_PATH.'/images/'.$_FILES['file1']['name'];
    $image = 'http://ww2.sinaimg.cn/thumbnail/70eb479bjw1e0ir8oynlij.jpg';

    $weibo = new weibo($username, $password);
    if($image != ''){
        $results = $weibo->postMessage($text,$image);
    }else{
        $results = $weibo->postMessage($text);
    }
    if(isset($results['code']) && $results['code'] > 0){
        echo '发送失败';
        exit();
    }else{
        echo '发送成功';
        exit();
    }
}


class weibo {

    private $cookiefile;
    private $username;
    private $password;
    private $userInfo;
    private $curlResponseInfo;

    function __construct( $username, $password )
    {
        ( $username =='' ||  $password=='' ) && exit( "请填写用户名密码" );

        $this->cookiefile = COOKIE_PATH.'/cookie_sina_'.substr(base64_encode($username), 0, 10);
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * CURL请求
     * @param String $url 请求地址
     * @param Array $data 请求数据
     */
    function curlRequest($url, $data = false, $extendHeaderOption = false, $extendOption = false)
    {
        $ch = curl_init();

        $option = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => array('Accept-Language: zh-CN,zh;q=0.8,en;q=0.6',
                                        'Connection: Keep-Alive',
                                        'Cache-Control: no-cache',
                                    ),
            CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11",
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_COOKIEJAR => $this->cookiefile,
            CURLOPT_COOKIEFILE => $this->cookiefile,
        );

        if( is_array($extendOption) ) {
            foreach( $extendOption as $key => $value ) {
                $option[$key] = $value;
            }
        }

        if ( $data ) {
            $option[CURLOPT_POST] = 1;
            $option[CURLOPT_POSTFIELDS] = $data;
        }

        if( is_array($extendHeaderOption) ) {
            $option[CURLOPT_HTTPHEADER] = array_merge($option[CURLOPT_HTTPHEADER], $extendHeaderOption);
        }

        curl_setopt_array($ch, $option);
        $response = curl_exec($ch);

        if (curl_errno($ch) > 0) {
            exit("CURL ERROR:$url " . curl_error($ch));
        }

        $this->curlResponseInfo = curl_getinfo($ch);

        curl_close($ch);
        return $response;
    }

    /**  @desc CURL 模拟新浪登录 */
    function doSinaLogin()
    {
        // Step 1 : Get tickit
        $preLoginData = $this->curlRequest('http://login.sina.com.cn/sso/prelogin.php?entry=weibo&callback=sinaSSOController.preloginCallBack&su=' .
            base64_encode($this->username) . '&client=ssologin.js(v1.3.16)');
        preg_match('/sinaSSOController.preloginCallBack\((.*)\)/', $preLoginData, $preArr);
        $jsonArr = json_decode($preArr[1], true);

        $this->debug('debug_1_Tickit', $preArr[1]);

        if (is_array($jsonArr)) {
            // Step 2 : Do Certification
            $postArr = array( 'entry' => 'weibo',
                'gateway' => 1,
                'from' => '',
                'vsnval' => '',
                'savestate' => 7,
                'useticket' => 1,
                'ssosimplelogin' => 1,
                'su' => base64_encode(urlencode($this->username)),
                'service' => 'miniblog',
                'servertime' => $jsonArr['servertime'],
                'nonce' => $jsonArr['nonce'],
                'pwencode' => 'wsse',
                'sp' => sha1(sha1(sha1($this->password)) . $jsonArr['servertime'] . $jsonArr['nonce']),
                'encoding' => 'UTF-8',
                'url' => 'http://weibo.com/ajaxlogin.php?framelogin=1&callback=parent.sinaSSOController.feedBackUrlCallBack',
                'returntype' => 'META');

            $loginData = $this->curlRequest('http://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.3.19)', $postArr);

            $this->debug('debug_2_Certification_raw', $loginData);

            // Step 3 : SSOLoginState
            if ($loginData) {

                $matchs = $loginResultArr  =array();
                preg_match('/replace\(\'(.*?)\'\)/', $loginData, $matchs);

                $this->debug('debug_3_Certification_result', $matchs[1]);

                $loginResult = $this->curlRequest( $matchs[1] );
                preg_match('/feedBackUrlCallBack\((.*?)\)/', $loginResult, $loginResultArr);

                $this->userInfo = json_decode($loginResultArr[1],true);

                $this->debug('debug_4_UserInfo', $loginResultArr[1]);
            } else {
                exit('Login sina fail.');
            }
        } else {
            exit('Server tickit fail');
        }
    }

    /**  测试登录情况, 调用参考 */
    function showTestPage( $url ) {
        $file_holder = $this->curlRequest( $url );

        // 如果未登录情况, 登录后再尝试
        $isLogin = strpos( $file_holder, 'class="user_name"');
        if ( !$isLogin ){
            unset($file_holder);
            $this->doSinaLogin();
            $file_holder = $this->curlRequest( $url );
        }
        return $file_holder;
    }

    function picUpload( $filepath ) {

        $boundary = uniqid('------------------');
        $MPboundary = '--'.$boundary;
        $endMPboundary = $MPboundary. '--';
        $postData = "";

        //$filepath = './linux.jpg'; //也可以是一个网络地址
        $file = file_get_contents($filepath);
        $imageMime = image_type_to_mime_type( exif_imagetype($filepath) ); //get image's mime type

        $postData .= $MPboundary . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="pic1"; filename="' . basename($filepath) . '"'. "\r\n";
        $postData .= 'Content-Type: ' . $imageMime . "\r\n\r\n";
        $postData .= $file. "\r\n";
        $postData .= "\r\n". $endMPboundary;

        $extendHeaderOption = array(
            'Host: picupload.service.weibo.com',
            'Referer: http://weibo.com/u/' . $this->userInfo['userinfo']['uniqueid'],
            "Content-Type: multipart/form-data; boundary={$boundary}",
        );

        $extendOption = array(CURLOPT_FOLLOWLOCATION => false);

        $callback = urlencode(DOMAIN . '/');
        $nick = '@踏平这条路';

        $url = 'http://picupload.service.weibo.com/interface/pic_upload.php?cb='
            . $callback
            //. '&url=weibo.com%2Fu%2F'
            //. $this->userInfo['userinfo']['uniqueid']
            . '&markpos=1&logo=1&nick='
            . urlencode($nick)
            . '&marks=1&app=miniblog&s=rdxt';

        $status = $this->curlRequest( $url, $postData, $extendHeaderOption, $extendOption );

        // 如果未登录情况, 登录后再尝试
        $isLogin = strpos( $status, 'class="user_name"');
        if ( !$isLogin ){
            unset($status);
            $this->doSinaLogin();
            $status = $this->curlRequest( $url, $postData, $extendHeaderOption, $extendOption );
        }

        $curlInfo = $this->curlResponseInfo;

        $redirectUrl = urldecode($curlInfo['redirect_url']);
        $urlParams = parse_url($redirectUrl);

        parse_str( $urlParams['query'] );
        // $ret, $pid, $token, $path

        return $pid;
    }

    function getUserInfo() {
        var_dump($this->userInfo);
    }

    function postMessage( $message, $image ) {
        $postData = array();
        $postData['text'] = $message;
        $postData['pic_id']='';
        $postData['rank']=0;
        $postData['rankid']=0;
        $postData['_surl']='';
        $postData['hottopicid']='';
        $postData['location']='home';
        $postData['module']='stissue';
        $postData['_t']='0';
        //判断是否有图片
        if( $pid = $this->picUpload($image) ) {
            $postData['pic_id'] = $pid;
            $postData['_surl']='image';
        }

        //POST /aj/mblog/add?__rnd=1338889911775 HTTP/1.1
        $url = sprintf('http://weibo.com/aj/mblog/add?_wv=5&__rnd=%.00f',microtime(true)*1000);

        $extendHeaderOption = array(
            'Host: weibo.com',
            'Origin: http://weibo.com',
            'X-Requested-With: XMLHttpRequest',
            'Referer: http://weibo.com/u/' . $this->userInfo['userinfo']['uniqueid'],
        );

        $result = $this->curlRequest($url, $postData, $extendHeaderOption);

        return $result;
    }

    /**  调试 */
    function debug( $file_name, $data ) {
        if ( DEBUG ) {
            file_put_contents( $file_name.'.txt', $data );
        }
    }

}
