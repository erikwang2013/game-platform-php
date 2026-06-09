<?php
return [
    // 是否开启插件
    'enable' => true,
    // 控制器tag Name生成规则，生成swagger.json中tags的name字段，通过app、group组合来避免多应用/多分组下同名控制器分组错误问题
    // 包含app则tag中会包含app的key，包含group则tag中会包含group名称。如配置为['app','group']，生成tag为：demo.user.Index
    'controller_tags_rule'=>['app','group'],
    // swagger.json配置，需符合openapi 3的规范
    'swagger_options'=>[
        "host"=> "",
        "basePath"=> "",
        "schemes"=> ["http"],
        // 安全认证配置
//        "components"=>[
//            "securitySchemes"=>[
//                "api_key"=> [
//                    "type"=> "apiKey",
//                    "name"=> "Authorization",
//                    "in"=> "header"
//                ]
//            ]
//        ],
//        "security"=>[
//            [
//                'api_key'=>[],
//            ]
//        ]
    ]
];

