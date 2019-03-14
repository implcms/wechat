## 微信+


### 环境要求

 - PHP >= 7.0
 - PHP cURL 扩展
 - PHP OpenSSL 扩展
 - PHP SimpleXML 扩展
 - PHP fileinfo 拓展

### 接口

微信接口统一传输`client_id`参数，也就是微信应用在本系统中的ID


#### 微信后台开发者配置地址

```
https://www.domain.com/wechat?client_id=x
```
`token` 和其他参数请查阅微信账号

#### 更新公众号菜单

```
wechat@main.update-menu
```
参数
- `menus`

#### 在微信网页端公众号登录
```
https://www.domain.com/wechat/auth
```
改地址可以传`url`参数，如果不传则跳转的上一个页面(REFERER)地址


#### 公众号带参数二维码
```
wechat@main.keyword-qrcode
```
参数
- `keyword`

#### 公众号二维码登录
```
wechat@main.qrcode-login
```

#### 公众号二维码登录轮询
```
wechat@main.qrcode-login-check
```
参数
- `key`


#### 小程序用户登录
```
wechat@main.miniprogram-login
```
参数
- `code`


#### 小程序用户注册
```
wechat@main.miniprogram-register
```
参数
- `session_key`
- `iv`
- `encryptData`


