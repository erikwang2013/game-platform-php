# 请求生命周期

```mermaid
sequenceDiagram
    actor C as 客户端
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS 请求
    N->>MW1: 转发请求

    alt Token缺失或无效
        MW1-->>C: 401 Unauthorized
    else Token有效
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: 设置 $request->adminId
    end

    alt 无权限
        MW2-->>C: 403 Forbidden
    else 有权限
        MW2->>CTL: 进入控制器
    end

    CTL->>CTL: 参数验证
    CTL->>CTL: decodeId(hashid)

    opt 敏感操作
        CTL->>CTL: confirmPassword()
        alt 密码错误
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable自动解密
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid字符串

    CTL-->>C: 200 JSON
```
