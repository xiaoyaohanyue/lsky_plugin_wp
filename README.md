# LskyPro（兰空图床）插件

将 WordPress 媒体图片上传到 LskyPro（兰空图床），并替换媒体库中的图片访问地址。

## 环境依赖

* WordPress 6.0+
* PHP >= 7.4
* fileinfo（必须）
* curl（必须）
* imagemagick（可选）
* Bash、tar、awk、zip（仅构建发布包时需要）

## API 格式

插件设置页只需要填写 LskyPro 域名，不需要手动填写 `/api/v1` 或 `/api/v2`。

示例：

```text
https://img.example.com
```

插件会根据你选择的 API 版本自动生成接口地址：

```text
v1 -> https://img.example.com/api/v1
v2 -> https://img.example.com/api/v2
```

如果误填了完整 API 地址，例如：

```text
https://img.example.com/api/v2
```

插件保存时也会自动归一化为域名，并按当前 API 版本重新生成接口地址。

## 使用方式

下载对应渠道的 release zip 包，解压到 WordPress 的 `wp-content/plugins` 目录。

两个渠道的包结构不同：

* GitHub/自建发布包：目录名保持为 `lsky_plugin_wp`，入口文件保持为 `LskyPro.php`，用于兼容老版本用户。
* WordPress.org 审核包：目录名为 `lsky-plugin-wp`，入口文件为 `lsky-plugin-wp.php`，符合官方插件目录 slug 习惯。

进入 WordPress 后台，在插件页面启用 `LskyPro（兰空图床）插件`，然后打开 `Lsky Pro设置` 配置：

* API 版本：选择 `v1` 或 `v2`
* LskyPro 域名：填写兰空图床站点域名
* Tokens：填写或通过用户名密码临时获取
* 隐私设置：`1 = 公开`，`0 = 私有`
* v2 可选择相册和存储策略

## 外部服务说明

本插件会在启用并配置后，将 WordPress 媒体图片上传到用户自行填写的 LskyPro（兰空图床）服务。

可能发送到 LskyPro（兰空图床）服务的数据包括：

* 图片文件
* 图片文件名
* MIME 类型
* 用户配置的 Token
* 开源版 V1 获取 Token 时临时提交的用户名和密码

插件不会保存开源版 V1 的密码，密码只在点击保存或更新 Tokens 时用于本次请求。

请在使用前确认你配置的 LskyPro（兰空图床）服务条款和隐私政策。若使用自建 LskyPro（兰空图床）服务，请以你的自建服务规则为准；若使用第三方 LskyPro（兰空图床）服务，请遵守该第三方服务的条款。

## 本地开发

当前仓库可直接放入 WordPress 的 `wp-content/plugins/lsky_plugin_wp` 目录中开发。

如果使用本项目根目录提供的 Docker 环境：

* WordPress：http://localhost:8080
* phpMyAdmin：http://localhost:8081
* 插件目录：`plugins/lsky_plugin_wp`

常用命令：

```bash
docker compose up -d
docker compose run --rm wpcli plugin status lsky_plugin_wp
docker compose logs -f wordpress
```

若需要在本地构建发布包，Debian/Ubuntu 可安装构建依赖：

```bash
sudo apt-get update
sudo apt-get install -y zip tar gawk
```

## 双渠道发布

本插件支持两个发布渠道：

* WordPress.org 审核包：不包含 `Update URI` 和自托管更新器，由 WordPress.org 官方更新系统处理。
* GitHub/自建发布包：包含 `Update URI` 和自托管更新器，用于 `2.0.5` 及之后版本的自建渠道更新。

构建命令：

```bash
bash build-channel.sh wporg
bash build-channel.sh github
```

构建产物：

```text
build/lsky-plugin-wp-wporg.zip
build/lsky_plugin_wp-github.zip
```

包结构：

```text
lsky-plugin-wp-wporg.zip
└── lsky-plugin-wp/
    └── lsky-plugin-wp.php

lsky_plugin_wp-github.zip
└── lsky_plugin_wp/
    └── LskyPro.php
```

GitHub Actions 会在每次 push / pull request 时构建两个渠道的包作为 artifacts；当推送 `v*` tag 时，会自动将 `build/lsky_plugin_wp-github.zip` 上传到 GitHub Release。

旧版本用户的第一次升级路径需要单独注意：`2.0.4` 及更早版本使用旧更新器读取 GitHub `releases/latest` 接口，并下载 GitHub 自动生成的 Source code zip（`zipball_url`），不是 Release asset 中的 `lsky_plugin_wp-github.zip`。因此发布 `v2.0.5` tag 本身仍然是必须的，旧更新器会通过该 tag 的 Source code zip 升级到 `2.0.5`。

## GitHub/自建渠道更新

从 `2.0.5` 开始，GitHub/自建发布包默认通过以下地址检查更新：

```text
https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp/update.json
```

更新清单可参考仓库中的 `update.example.json`：

```json
{
  "version": "2.0.5",
  "requires": "6.0",
  "tested": "6.8",
  "requires_php": "7.4",
  "homepage": "https://github.com/xiaoyaohanyue/lsky_plugin_wp",
  "download_url": "https://github.com/xiaoyaohanyue/lsky_plugin_wp/releases/download/v2.0.5/lsky_plugin_wp-github.zip",
  "description": "将 WordPress 媒体上传到 LskyPro（兰空图床）。",
  "changelog": "<ul><li>优化设置页与安全校验。</li></ul>"
}
```

其中 `download_url` 应指向 GitHub Release 中的 `lsky_plugin_wp-github.zip`。这是 `2.0.5` 及之后版本默认使用的新更新通道；老版本用户从 `2.0.4` 升级到 `2.0.5` 时，仍由旧更新器下载 GitHub Source code zip 完成过渡。

GitHub/自建包的后台设置页提供更新渠道选择：

* `fjwr.xyz 更新服务`：默认选项，读取 `https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp/update.json`。
* `GitHub Source code`：读取 GitHub `releases/latest`，并下载 GitHub 自动生成的 Source code zip。
* `自定义 Manifest`：填写自己的 `update.json` 地址，格式与 `update.example.json` 一致。

WordPress.org 审核包不会包含 `Update URI`、自托管更新器或更新渠道设置。

## 更新细节

2025.1.14 更新：

2025.5.16 更新：

* 支持开源版以及付费版切换
* 增加插件商店更新功能
* 增加开源版通过用户名密码自动获取 token 以及刷新 token 功能
* 插件列表页面增加设置跳转
* 迁移设置页面入口
* 适配商业版 2.x 版本
* 增加移除插件自动清理配置
* 修复设置页面顶部警告

2025.5.17 更新：

* 修复部分命名规则无法正确获取图片名称的问题

2025.5.22 更新：

* 修复媒体库缩略图显示问题

2025.12.1 更新：

* 修复错误变量名导致的上传失败问题

2026.5.29 更新：

* 增加后台设置页权限校验和 nonce 校验
* 增加 AJAX 接口 nonce 与用户权限校验
* 优化配置保存方式，兼容旧版序列化配置
* 增加 WordPress.org 与 GitHub/自建渠道双构建方案
* GitHub/自建渠道支持 `Update URI` 与自托管更新清单
* WordPress.org 包使用 `lsky-plugin-wp/lsky-plugin-wp.php` 结构
* GitHub/自建包保留 `lsky_plugin_wp/LskyPro.php` 结构兼容老用户
* 设置页只需填写 LskyPro 域名，插件自动生成 `/api/v1` 或 `/api/v2`
* 不再保存开源版 V1 密码，密码只用于本次获取 Tokens
* 优化上传失败时的错误处理，避免写入异常附件数据
* 修复数据库表名硬编码问题，改用 WordPress 标准接口更新附件
* 优化远程请求错误处理，补充 curl、fileinfo、API、Token 等前置检查
* 兼容 PHP 8.5 中 `finfo_close` 和 `curl_close` 的弃用变更
* 优化一键替换脚本，支持后端传入 AJAX 地址和安全 nonce
* 修复 V2 相册和存储策略下拉选项的前端拼接风险
* 优化设置页面 UI

插件名称：LskyPro（兰空图床）插件

写的不好，大佬们勿喷。
