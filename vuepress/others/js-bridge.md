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
    