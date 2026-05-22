# 系统拓扑架构

```mermaid
flowchart TB
    subgraph clients["客户端层"]
        flutter["Flutter Web<br/>PC管理后台"]
        harmony["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph gateway["网关层"]
        nginx["Nginx<br/>HTTPS反向代理<br/>Gzip压缩"]
    end

    subgraph app["应用层 - webman v2"]
        auth["AdminAuth<br/>JWT验证"]
        perm["AdminPermission<br/>RBAC鉴权"]
        admin["管理端Controller<br/>Dashboard/User/Role/Permission"]
        public["公开Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["存储层"]
        mysql[("MySQL 8.0<br/>主存储 - erik_前缀")]
        es[("Elasticsearch<br/>全文检索 - erik_前缀")]
        redis[("Redis<br/>Session/缓存/Captcha")]
    end

    flutter --> nginx
    harmony --> nginx
    nginx --> auth
    auth --> perm
    perm --> admin
    auth --> public
    admin --> common
    public --> common
    admin --> mysql
    public --> mysql
    admin --> es
    public --> es
    auth --> redis
    public --> redis

    style flutter fill:#1677FF,color:#fff
    style harmony fill:#1677FF,color:#fff
    style nginx fill:#722ED1,color:#fff
    style auth fill:#FA8C16,color:#fff
    style perm fill:#FA8C16,color:#fff
    style common fill:#52C41A,color:#fff
    style mysql fill:#1890FF,color:#fff
    style es fill:#1890FF,color:#fff
    style redis fill:#1890FF,color:#fff
```
