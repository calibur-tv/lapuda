### 前提：

>1. 所有页面都要去替换 {{ version }} 为当前 APP 的版本号
>2. 所有页面都要去替换 {{ name }} 为当前系统的名称`ios`或`Android`（大小写不敏感）

### 方案：

1. Android 和 iOS 在打开的模板页面里都要注入自己的 JS 到 window 下面
2. Android 的 namespace 是：__AndroidBridge
3. iOS 的 namespace 是：__WebBridge
4. 以上两个命名空间前都有**两个下划线**，大小写敏感

### 异步与同步：

1. 因为 Android 和 iOS 的设备差异，所以所有的调用都使用异步回调方法，不走同步
2. 因此无论是 JS call APP 还是 APP call JS 都要通过 callback 获取返回值
3. callback 通过 callbackId 的方式传递，各端在自己那里维护一个`id <--> function`的对象
4. 返回值或者参数的传递都以`Json-Object`的方式传递

### 实现：
1. JsCallApp 的方法是：
    1. Android：window.__AndroidBridge.handleMessageFromJS(data)
    2. iOS：window.__WebBridge.handleMessageFromJS(data)
    3. 方法名：`handleMessageFromJS`
    4. 参数：`data` 是一个 Json-Object，包括：（类型 | 注释 | 默认值）
    ```json
    {
       "func": "<String> | 函数名 | ''",
       "params": "<Object> | 参数 | {}",
       "callbackId": "<String> | 回调函数id | ''"
    }
    ```
    **（以下步骤 iOS 不需要执行，iOS 也没有 callbackId 这个参数）**
    
    5. 当 APP 获得函数的执行结果后，通过调用以下方法回调 JS：
    `M.invoker.JsCallAppCallback(jsonObj)`
    其中 `jsonObj` 有以下参数：
    ```json
    {
       "params": "<Object> | APP执行的返回值",
       "callbackId": "<String> | 回调函数id"
    }
    ```
    **此处的 callbackId 就是 `handleMessageFromJS` 时传的 callbackId**

2. AppCallJS 的方法是：
    1. `M.invoker.appCallJs(jsonObj)`
    2. 参数 `jsonObj` 是一个 Json-Object，包括：（类型 | 注释 | 默认值）
    ```json
    {
       "func": "<String> | 函数名 | ''",
       "params": "<Object> | 参数 | {}",
       "callbackId": "<String> | 回调函数id | ''"
    }
    ```
    **（以下步骤 iOS 不需要执行，iOS 也没有 callbackId 这个参数）**
    
    3. 当 JS 执行完`func`之后，如果 callbackId 不为空，就会调用：
    4. iOS：window.__WebBridge.handleCallbackFromJS(data)
    5. Android：window.__AndroidBridge.handleCallbackFromJS(data)
    其中 `data` 有以下参数：
    ```json
    {
       "params": "<Object> | JS执行的返回值",
       "callbackId": "<String> | 回调函数id"
    }
    ```
    **此处的 callbackId 就是 `handleCallbackFromJS` 时传的 callbackId**
    
    
### 接口：
#### 1. `getDeviceInfo`
> 介绍，略

#### 2. `getUserInfo`
> 返回当前登录用户的所有信息，信息来自 `door/current_user` 这个接口

#### 3. `setUserInfo`
> 更新当前登录用户的信息，key与`getUserInfo`获取到的完全相同，只是部分字段被更新了

#### 4. `toNativePage`
> 跳转到一个 native 的页面，如果页面不存在，则不相应。参数：
```json
{
  "uri": "<String> | native 的路由"
}
```

#### 5. `previewImages`
> 图片预览，参数：
```json
{
  "images": "<Array> | 图片对象列表",
  "index": "<Number> | 当前点击图片的索引"
}
```
image 对象的格式如下
```json
{
  "url": "<String> | 图片链接",
  "width": "<Number> | 图片宽度",
  "height": "<Number> | 图片高度",
  "size": "<Number> | 图片文件大小",
  "type": "<String> | 图片mime类型"
}
```

#### 6. `createMainComment`
> 打开评论框，发表一个主评论，参数：
```json
{
  "model_type": "<String> | 发表的类型，如：post、image...",
  "model_id": "<Number> | 帖子的id 或相册的 id..."
}
```
> 目前支持的 model_type 有：
1. post
2. image
3. score
4. video
5. question
6. answer
7. role
> APP 的评论框被唤醒后，等待用户输入文字（上传图片）之后去调用 API 发评论，评论发表
> 完成后，调用 JS 的 func 来通知页面更新评论列表：
```json
{
  "func": "createMainComment",
  "data": "API返回的数据直接传过来"
}
```

#### 7. `createSubComment`
> 打开评论框，发表一个子评论，参数：
```json
{
    "model_type": "<String> | 发表的类型",
    "parent_comment_id": "<Number> | 主评论的id",
    "target_user_id": "<Number> | 回复的用户id",
    "target_user_name": "<String> | 回复的用户昵称"
}
```
> APP 的评论框被唤醒后，等待用户输入文字之后去调用 API 发评论，评论发表
> 完成后，调用 JS 的 func 来通知页面更新评论列表：
```json
{
  "func": "createSubComment",
  "params": "API返回的数据直接传过来"
}
```
> 注意点：如果`target_user_name`为空字符串，则不显示"回复：xxx"，显示"回复："
> `target_user_name`为空字符串时，`target_user_id`不一定为**0**

#### 8. `toggleClick`
> 当用户在H5进行投食、点赞、收藏等操作时，通知客户端，参数：
```json
{
  "model": "<String> | 操作的模型",
  "type": "<String> | 操作的类型",
  "id": "<Number> | 操作的id",
  "result": "<Object> | 操作的结果"
}
```
> `model`可能是：post、image、video、role...
> `type`可能是：reward、like、mark、follow...
> `result` 是一个对象，不同参数返回的值是不一样的

> 当用户在APP进行相同操作的时候，APP也要通知H5：
```json
{
  "func": "app-invoker-toggleClick",
  "params": "<Object> | 与我传给你的类似的数据结构"
}
```

#### 9. `showConfirm`
> 弹出一个确认框，参数：
```json
{
    "title": "<String> | 弹窗的标题",
    "message": "<String> | 弹窗的段落文本",
    "cancelButtonText": "<String> | 取消键的文字",
    "submitButtonText": "<String> | 确认键的文字"
}
```
> 返回一个 boolean 值