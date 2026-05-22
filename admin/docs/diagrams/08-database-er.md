# 数据库 ER 关系

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake生成"
        VARCHAR username UK "用户名"
        VARCHAR password "bcrypt哈希"
        VARCHAR real_name "真实姓名"
        VARCHAR avatar "头像URL"
        VARCHAR email "加密存储"
        VARCHAR phone "加密存储"
        VARCHAR id_card "加密存储"
        TINYINT status "0禁用1启用"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "软删除"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake生成"
        VARCHAR name "角色名称"
        VARCHAR slug UK "角色标识"
        VARCHAR description "描述"
        TINYINT status "0禁用1启用"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake生成"
        BIGINT parent_id FK "父级权限ID"
        VARCHAR name "权限名称"
        VARCHAR slug "权限标识"
        TINYINT type "1菜单2按钮3API"
        VARCHAR icon "菜单图标"
        VARCHAR path "路由路径"
        INT sort "排序"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "用户ID"
        BIGINT role_id PK_FK "角色ID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "角色ID"
        BIGINT permission_id PK_FK "权限ID"
    }

    erik_operation_log {
        BIGINT id PK "Snowflake生成"
        BIGINT user_id FK "操作用户"
        VARCHAR action "操作动作"
        VARCHAR method "请求方法"
        VARCHAR path "请求路径"
        VARCHAR ip "操作IP"
        TEXT input "请求参数脱敏"
        DATETIME created_at "操作时间"
    }

    erik_system_config {
        BIGINT id PK "Snowflake生成"
        VARCHAR group_name "配置分组"
        VARCHAR key_name "配置键"
        TEXT value "配置值"
        VARCHAR type "值类型"
        VARCHAR description "说明"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : user_id
    erik_admin_role ||--o{ erik_admin_user_role : role_id
    erik_admin_role ||--o{ erik_admin_role_permission : role_id
    erik_admin_permission ||--o{ erik_admin_role_permission : permission_id
    erik_admin_user ||--o{ erik_operation_log : user_id
    erik_admin_permission ||--o{ erik_admin_permission : parent_id
```
