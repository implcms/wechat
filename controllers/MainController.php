<?php namespace Applications\Wechat\Controllers;

use Symfony\Component\Yaml\Yaml;
use EasyWeChat\Factory;
use Applications\Wechat\Classes\WechatMessageHandler;
/**
 * 主控制器，统一传输`id`参数，也就是微信应用在本系统中的ID
 */
class MainController{
    
    public $app;
    public $account;
    
    public function __construct($param){
        require_once dirname(__DIR__).'/lib.phar';
        input("client_id",$client_id);
        $account = model('account')->find($client_id);
        if($account === null){
            throw new \Exception("账号不存在",1);
        }
        $config = [
            'app_id' => $account->app_id,
            'secret' => $account->secret,
            'token' => $account->token,
            'aes_key'=> $account->aes_key,
            'response_type' => 'array',
        ];

        
        $type = "officialAccount";
        switch ($account->type) {
            case 3:
                $type = "miniProgram";
                break;
            default:
                #code
                break;
        }
        $config['log'] = [
            'default' => config('cms.env')=="dev"?"dev":"prod", // 默认使用的 channel，生产环境可以改为下面的 prod
            'channels' => [
                // 测试环境
                'dev' => [
                    'driver' => 'single',
                    'path' => base_dir('log').'easywechat.log',
                    'level' => 'debug',
                ],
                // 生产环境
                'prod' => [
                    'driver' => 'daily',
                    'path' => base_dir('log').'easywechat.log',
                    'level' => 'info',
                ],
            ],
        ];

        $this->app = Factory::$type($config);
        $this->account = $account;
    }

    /**
     * 微信自动回复服务(为微信服务器提供服务，公众平台填写的页面上调研此函数) [了解更多](https://www.implcms.com/apps?app=wechat#server-url)
     */
    public function apiServer(){
        $this->app->server->push(new WechatMessageHandler($this->account,$this->app));
        $response = $this->app->server->serve();
        $response->send();exit;
    }
    
    
    /**
     * 设置微信菜单
     */
    public function apiUpdateMenu(){

        //return mr("接口测试");
        // if(isset($this->config['menus'])){
        //     $menus = $this->config['menus'];
        // }
        // $result = $this->app->menu->create($menus);
        // if($result["errcode"] == 0){
        //     return mr(null);
        // }else{
        //     \Log::info(print_r($menus,'r'));
        //     return mr(null,-1,$result["errmsg"]);
        // }
    }


    public function apiAuthCallback($param){
        input('scope',$scope);
        $url = session('wechat_auth_origin_url');
        
        $user = $this->app->oauth->user();
        
        if($scope == "snsapi_base"){
            $openid = model('openid')->where('openid',$user['id'])->first();
            if($openid){
            	session("user_id",$openid->user_id);
            	$sessionKey = password_hash($user_id, PASSWORD_DEFAULT);
            	session("session_key",$sessionKey);
                \Response::redirect($url);
            }else{
                if(strpos($url,"?") !== false){
                    $url = $url."&scope=snsapi_userinfo";
                }else{
                    $url = $url."?scope=snsapi_userinfo";
                }
                $scope = "snsapi_userinfo";
                $oauth = $this->app->oauth;
                $url = url('?impl[api]=wechat@main.auth-callback&client_id='.$this->account->id."&scope=".$scope);
                $oauth->scopes([$scope]);
                $oauth->redirect($url)->send();
            }
        }elseif($scope == "snsapi_userinfo"){
            $handler = new WechatMessageHandler($this->account,$this->app);
            $openid = $handler->syncUser($user->getOriginal());
            if(isset($openid)){
                session("user_id",$openid->user_id);
            	$sessionKey = password_hash($user_id, PASSWORD_DEFAULT);
            	session("session_key",$sessionKey);
                \Response::redirect($url);
            }else{
                \Response::error("授权错误");
            }
        }
    }
    /**
     * 公众号网页授权中间件
     */
    public function apiAuth(){
        if($this->account->type != 1){
            session()->remove('user_id');
            \Response::error("提供的账号不是服务号，请核对再试");
        }
        
        if(input('url')){
        	$url = urldecode(input('url'));
        }else{
        	$url = isset($_SERVER["HTTP_REFERER"])?$_SERVER["HTTP_REFERER"]:url();
        }
        
        if(!cms('session')->get('user_id')){
            cms('session')->store('wechat_auth_origin_url',$url);
            input('scope',$scope);
            $scope = $scope?$scope:'snsapi_base';
            $oauth = $this->app->oauth;
            $url = url('?impl[api]=wechat@main.auth-callback&client_id='.$this->account->id."&scope=".$scope);
            $oauth->scopes([$scope]);
            $oauth->redirect($url)->send();
        }else{
        	\Response::redirect($url);
        }
    }

    /**
    * 小程序用户登录
    * @param code|小程序授权code|是--modelConfig|绑定的模型配置|否
    */
    public function apiMiniprogramLogin($param){
        input('code',$code);
        input('modelConfig',$modelConfig);
        if(!$code){
            return mr(null,-1,"缺少code参数");
        }
        $baseInfo = $this->app->auth->session($code);
        $openid = model('openid')->where('openid',$baseInfo['openid'])->first();
        if(!$openid){
            $data["session_key"] = $baseInfo['session_key'];
            return mr($data,-2,"微信用户不存在");
        }
        //$relationModel = model($modelConfig)->metaData('wechat@user',$user->id)->first();
        //\Log::info($relationModel);
        
    }

    /**
    * 小程序用户注册
    * @param session_key|会话|是--iv|矩阵|是--encryptData|数据|是
    */
    public function apiMiniprogramRegister($param){
        input('session_key',$session);
        input('iv',$iv);
        input('encryptedData',$encryptedData);
        $rules = ['session_key'=>null,'iv'=>null,'encryptedData'=>null];
        if(!vld($errors,$rules)){
            return mr(null,-1,$errors[0]);
        }
        $decryptedData = $this->app->encryptor->decryptData($session, $iv, $encryptedData);
        \Log::info($decryptedData);
        //$handler = new WechatMessageHandler($this->account,$this->app);
        //$openid = $handler->syncUser($decryptedData);
        return mr($decryptedData);
    }

    /**
    * 生成带参数二维码
    */

    public function apiKeywordQrcode($param){	
        input("keyword",$keyword);
        $result = $this->app->qrcode->forever($keyword.":");
        if(!isset($result['ticket'])){
            \Log::error($result);
            return mr(null,-1,"生成是出现错误，请稍后再试!");
        }
        $url = $this->app->qrcode->url($result['ticket']);
        return mr($url,1,"生成成功");
    }
    
    
    /**
     * 公众号登录
     */
    public function apiLogin(){
    	
    	if(strpos($_SERVER['HTTP_USER_AGENT'],"MicroMessenger") !== false){
    		$data = mr(null,-2,"公众号登录");
    		$data['redirect'] = url('wechat/auth?client_id='.input('client_id'));
    		return $data;
    	}
    	
    	$key = rand();
    	$result = $this->app->qrcode->temporary("qrcodeLogin:".$key,1800);
        if(!isset($result['ticket'])){
            \Log::error($result);
            return mr(null,-1,"生成是出现错误，请稍后再试!");
        }
        $url = $this->app->qrcode->url($result['ticket']);
        $data['url'] = $url;
        $data['key'] = md5("qrcodeLogin:".$key);
        return mr($data,1,"生成成功");
    }
    
    /**
     * 公众号二维码登录轮询
     */
    public function apiQrcodeLoginCheck(){
    	input('key',$key);
    	$data = cache($key);
    	if(strlen($key)==32 && $data){
    		session("user_id",$data["user_id"]);
    		session("session_key",$data["session_key"]);
    		return mr($data);
    	}else{
    		return mr(null,-1,"钥匙不存在哦");
    	}
    }
}