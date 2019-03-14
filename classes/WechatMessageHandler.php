<?php namespace Applications\Wechat\Classes;

use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use EasyWeChat\Kernel\Messages\Transfer;

/**
 * 微信账户处理器
 */
class WechatMessageHandler implements EventHandlerInterface
{
    
    private $account;
    private $message;
    private $app;
    
    public function __construct($account,$app){
        $this->account = $account;
        $this->app = $app;
    }
    
    public function handle($message = null){
        $this->message = $message;
        switch ($message["MsgType"]) {
            case 'event':
                switch ($message['Event']) {
                    case 'CLICK':
                        return $this->onClick();
                        break;
                    case 'subscribe':
                        return $this->onSubscribe();
                        break;
                    case 'unsubscribe':
                        return $this->onUnsubscribe();
                        break;
                    case 'SCAN':
                        return $this->onScan();
                        break;
                }
                break;
            case 'text':
                return $this->onText();
                break;
            
            default:
                // code...
                break;
        }
        return new Transfer();
        
    }


    private function getReply($keyword){
    	
    	if(method_exists($this,$keyword)){
    		return $this->parseReply($this->$keyword());
    	}
    	
        $model = model('message')->where("keyword",$keyword)->first();
        if($model){
            $reply = null;
            if($model->handler){
                $handlers = explode("|",$model->handler);
                foreach($handlers as $api){
                    try{
                        $result = \Api::get($api,["message"=>$this->message]);
                    }catch(\Exception $e){
                        \Log::info($e->getMessage());
                        continue;
                    }
                    if(isset($result["data"])){
                        $reply = $this->parseReply($result["data"]);
                    }
                }
            }
            if($reply){
                return $reply;
            }elseif($model->content){
                return $this->parseReply($model->content);
            }
        }
    }

    private function parseReply($data){
        return print_r($data,"r");
    }
    
    public function onText(){

    
        $keyword = explode(" ",$this->message["Content"]);
        $keyword = trim($keyword[0]);

        return $this->getReply($keyword);
    }
    
    public function onClick(){
        return $this->getReply($this->message["EventKey"]);
    }
    
    public function onSubscribe(){
        if(app_exist('user')){
            $wechatUser = $this->app->user->get($this->message['FromUserName']);
            $this->syncUser($wechatUser);
        }
        if(isset($this->message["EventKey"]) && count(explode("qrscene_",$this->message["EventKey"]))>1){
            $this->message['EventKey'] = str_replace("qrscene_","",$this->message["EventKey"]);
            return $this->onScan();
        }
        return $this->getReply("subscribe");
    }
    
    public function onUnsubscribe(){
        if(app_exist('user')){
            $openidQuery = model('openid')->where('account_id',$this->account->id)->where('openid',$this->message['FromUserName']);
            $openid = $openidQuery->first();
            if($openid){
                $openidQuery->update(["subscribe"=>0]);
            }
        }
    }
    
    public function onScan(){
        $keyword = explode(":",$this->message['EventKey'])[0];
        return $this->getReply($keyword);
    }


    public function syncUser($wechatUser){
        $openid = model('openid')->where('account_id',$this->account->id)->where('openid',$wechatUser["openid"])->first();
        if(!$openid){
            //create openid
            $openid = [];
            $openid['account_id'] = $this->account->id;
            $openid['openid'] = $wechatUser["openid"];
            $openid['subscribe'] = 1;
            model('openid')->create($openid,$openidId);
            //if unionid exist
            $user = null;
            if(isset($wechatUser["unionid"])){
                $user = model("user@user")->where('unionid',$wechatUser["unionid"])->first();
            }
            if(!$user){
                $user = [];
                $user['nickname'] = $wechatUser['nickname'];
                $user['sex'] = $wechatUser['sex'];
                $user['city'] = $wechatUser['city'];
                $user['province'] = $wechatUser['province'];
                $user['country'] = $wechatUser['country'];
                $user['unionid'] = isset($wechatUser['unionid'])?$wechatUser['unionid']:null;
                $user['metadata'] = ["wechat_headimgurl"=>$wechatUser['headimgurl']];
                $err = model("user@user")->create($user,$user_id);
                if($err){
                    \Log::error($err);
                }
            }else{
                $user_id = $user->id;
            }
            if($user_id){
                model('openid')->where("id",$openidId)->update(["user_id"=>$user_id]);
            }
        }else{
            if(isset($wechatUser["unionid"])){
                $user = model('user@user')->find($openid->user_id);
                if($user->unionid != $wechatUser["unionid"]){
                    model('user@user')->where("id",$openid->user_id)->update(["unionid"=>$wechatUser["unionid"]]);
                }
            }
            if($openid->subscribe == 0){
                model('openid')->where("id",$openid->id)->update(["subscribe"=>1]);
            }
        }
        return $openid;
    }
    
    /**
     * 公众号二维码登录回调
     */
    private function qrcodeLogin(){
    	$openid = model('openid')->where('account_id',$this->account->id)->where('openid',$this->message["FromUserName"])->first();
    	$key = $this->message['EventKey'];
    	if($openid){
    		$data['session_key'] = password_hash($user_id, PASSWORD_DEFAULT);
    		$data['user_id'] = $openid->user_id;
    		cache(md5($key),$data);	
    	}else{
    		\Log::error("找不到openid");
	    	\Log::info($this->message);
    	}
    }
}