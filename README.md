# EasySDK

抽取移植 EasyWechat 核心，去除无用模块，使其可以支持其他类型SDK，作为第三方其他类型SDK的基包。

[![Build Status](https://travis-ci.com/larvatecn/easysdk.svg?branch=master)](https://travis-ci.com/larvatecn/easysdk)

## Installation

```bash
composer require larva/easysdk -vv
```

## Usage

```php
$options = [

    'log' => [
        'level' => 'debug',
        'file'  => '/tmp/easysdk.log',
    ],
    // ...
];
$app = new \Larva\EasySDK\ServiceContainer($options);
// 一般是 继承 \Larva\EasySDK\ServiceContainer 类来扩展出API
```