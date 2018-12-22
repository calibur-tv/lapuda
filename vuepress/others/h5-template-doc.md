## html 模板参数文档

[所有模板文件](https://api.calibur.tv/app/template)

[v1版模板文件](https://api.calibur.tv/app/template?version=1)

[v1版消息通知模板](https://api.calibur.tv/app/template?version=1&page=notifications)

传参规律：
1. `API`：`https://api.calibur.tv/app/template`
2. `params`: 如果传了`version`，就发放指定版本的所有模板，如果传了`version`和`page`就显示当前页面当前版本的模板

使用方法：
1. 模板文件只会有两个需要替换的地方：`{ { { data } } }` 和 `{ { token } }`
2. `token` 指的是 JWT-AUTH-TOKEN，在网络请求的请求头里面有
3. `data` 是你在 preload 当前页面时要去调用的接口返回数据
4. 所有页面都有`token`，需要去替换
5. 部分页面不需要 preload，所以没有 data 的插槽，这个时候也不需要去替换
6. 模板提供了最基础的页面标题，如果这个页面是需要传`id`才能访问的，那么这个标题就应该由APP来复写
7. 部分页面可能不需要展示标题，比如说消息通知页面，这个时候APP应该不展示 mustache 的标题
8. **如果上面的逻辑导致代码书写太麻烦，那就可以修改，做到统一处理**
9. 设计师应该为APP提供一个统一的loading效果图，APP应该在需要 preload 的页面先展示原生的 loading 页面
10. 以下 preload 的接口，详细参数请查看[接口文档](/api/v1/)
11. 所有页面都要去替换 {{ version }} 为当前 APP 的版本号
12. 所有页面都要去替换 {{ name }} 为当前系统的名称`ios`或`Android`（大小写不敏感）

各个页面介绍（[接口文档](/api/v1/)）:
1. 帖子详情页
    1. **page**：post
    2. **preload**：`/post/${id}/show`
2. 图片详情页
    1. **page**：image
    2. **preload**：`/image/${id}/show`
    3. Android 用这个模板替换相册页面，不替换漫画阅读器页面
3. 漫评详情页
    1. **page**：review
    2. **preload**：`/review/${id}/show`
4. 消息通知页
    1. **page**：notification
    2. **preload**：`/user/notification/list`
    3. 因为这个页面放在了主tab上，所以最好是APP进入后，网络不阻塞后去加载一次
5. 我的收藏页
    1. **page**：bookmarks
    2. **preload**：无
6. 公告页面
    1. **page**：notice
    2. **preload**：无
7. 编辑器页面
    1. **page**：editor
    2. **preload**：这个版本先不加这个页面，之后再讨论
8. 交易记录页
    1. **page**：transactions
    2. **preload**：`/user/transactions`
9. 偶像详情页
    1. **page**: role
    2. **preload**: `/cartoon_role/${id}/show`
10.我的个人页
    1. **page**: home
    2. **preload**: 无
11. 评论详情页
    1. **comment**: comment
    2. **preload**: `/comment/item`