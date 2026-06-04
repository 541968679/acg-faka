# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**KaynLab AI**（原 ACG-Faka）— 基于 PHP 8 的虚拟商品自动发卡系统，底层开源项目来自 [lizhipay/acg-faka](https://github.com/lizhipay/acg-faka) v3.2.6，前端已改造为 AI 大模型 API 中转站风格。

线上地址：`https://shop.kaynstech.com`

## Local Development（Docker）

项目使用 Docker Compose 搭建本地开发环境，代码通过 volume 挂载实时同步，改完模板/CSS/JS 刷新浏览器即可看到效果。

### 启动 / 停止

```bash
# 首次启动（构建镜像 + 初始化数据库）
docker-compose up -d --build

# 日常启动
docker-compose up -d

# 停止
docker-compose down

# 完全重置（清除数据库数据和 vendor 缓存）
docker-compose down -v
```

### 本地访问地址

| 服务 | 地址 |
|------|------|
| **前台首页** | http://127.0.0.1:8080 |
| **后台管理** | http://127.0.0.1:8080/admin |
| **MySQL** | `localhost:3307`（用户 `faka`，密码来自 `DB_PASSWORD`） |

### 本地后台管理员

管理员账号与生产环境一致（邮箱 `huangkeuestc@gmail.com`，昵称 `kayn`）

### 常用 Docker 命令

```bash
# 查看容器日志
docker-compose logs -f app     # PHP/Apache 日志
docker-compose logs -f db      # MySQL 日志

# 进入容器
docker exec -it acgfaka-app bash

# 本地数据库连接
mysql -h 127.0.0.1 -P 3307 -u faka -p"$DB_PASSWORD" acgfaka
```

### 调试模式

Docker 环境默认开启 `APP_DEBUG=1`（在 `docker-compose.yml` 中设置）。DEBUG 模式下会加载未合并的独立 JS 文件而非 `assets/common/js/_.js`。

### 开发流程

1. 本地编辑文件（模板、CSS、JS）
2. 浏览器刷新 http://127.0.0.1:8080 查看效果（代码通过 volume 实时同步）
3. 确认无误后部署到线上：`bash deploy.sh`

### 重要约定

- **只修改 `Theme/AiHub/` 和 `assets/aihub/` 目录**，其余文件仅供参考
- 需要查看原系统代码时直接本地 `Read`，不需要 SSH 到服务器

## Production Deployment

### 一键部署

```bash
bash deploy.sh
```

`deploy.sh` 会在生产服务器拉取 GitHub fork 的 `main` 分支，在远端构建 Docker 镜像，切换到最新镜像并执行健康检查。失败时使用 `deploy/update.sh rollback` 回滚到上一版镜像。

### 服务器信息

| 组件 | 详情 |
|------|------|
| **GCP 项目** | `myvps-2606to2608` |
| **VM 实例** | `acgfaka-hk`，`asia-east2-a`，外部 IP `34.96.139.162` |
| **OS** | Debian 12 (Bookworm) |
| **Web Server** | Caddy 2 → Docker Compose `app`（PHP 8.2 Apache） |
| **Database** | Docker Compose `db`（MySQL 8.0），数据库名 `acgfaka`，表前缀 `acg_`，用户 `faka` |
| **域名** | `shop.kaynstech.com`，`kaynstech.com`/`www.kaynstech.com` 重定向到主域名 |
| **项目路径** | `/opt/acgfaka/repo`，生产环境文件 `/opt/acgfaka/.env.prod` |

### SSH 访问与运维

```bash
gcloud compute ssh acgfaka-hk --project=myvps-2606to2608 --zone=asia-east2-a

# 重启服务
sudo docker compose -p acgfaka \
  -f /opt/acgfaka/repo/docker-compose.prod.yml \
  -f /opt/acgfaka/docker-compose.override.yml \
  --env-file /opt/acgfaka/.env.prod up -d

# 查看日志
sudo tail -f /opt/acgfaka/deploy.log
sudo docker logs -f acgfaka-app

# 生产数据库直连
sudo docker exec -it acgfaka-db mysql -u faka -p acgfaka
```

### Cron Job

每分钟执行 `close_expired_orders.php`：将超过 10 分钟未支付的订单（`status=0`）自动关闭（`status=2`）。

## Architecture

自研 MVC 框架（非 Laravel/Symfony），引入 `illuminate/database`（Eloquent ORM）和 Smarty 模板引擎。

### 入口流程

```
index.php → kernel/Kernel.php
  1. 解析 URL（?s=/module/controller/action 格式）
  2. 初始化 Eloquent 数据库连接
  3. 加载插件系统（Plugin.php + Hook 机制）
  4. 通过反射实例化 Controller，注解收集 → 依赖注入 → 方法调用
  5. 返回 JSON 或 HTML（Smarty 渲染）
```

### 关键目录

```
app/
  Controller/          # Admin/（后台）、User/（前台）、Shared/（分站）、Base/（基类）
  Model/               # Eloquent 模型
  Service/             # 业务逻辑层
  Interceptor/         # 中间件（Session 校验、权限、WAF）
  Consts/              # 常量（Hook 点位、支付常量）
  Pay/                 # 支付接口层（Base.php、Pay.php 接口、Epay/ 易支付实现）
  View/User/Theme/     # 前台主题（AiHub/ 当前、Cartoon/ 原始）

kernel/                # 框架核心（不修改）
config/                # 配置文件（database.php 支持环境变量覆盖）
assets/aihub/          # AiHub 主题静态资源（CSS/JS/图片）
docker/                # Docker 配置（Dockerfile、entrypoint、MySQL 初始化）
```

### 核心机制

**路由**: `?s=/模块/控制器/方法`，Nginx/Apache rewrite 伪静态。特殊路径：`/item/{id}` → 商品详情，`/cat/{id}` → 分类列表。

**插件 Hook 系统**: `hook()` 函数触发插件逻辑（定义在 `App\Consts\Hook`），涵盖视图渲染、订单生命周期、用户认证、支付流程等 60+ 个挂载点。

**依赖注入**: PHP 8 Attribute `#[Inject]`，框架通过反射自动注入 Service 层依赖。

**支付系统**: `App\Pay\Pay` 接口定义 `trade()` 方法，支持重定向/本地渲染/POST 表单三种方式。内置 Epay（易支付），其他通道通过插件扩展。

**模板引擎**: Smarty + 前台 Theme 主题系统，后台使用 layui 框架。

**数据库配置**: `config/database.php` 优先读取环境变量（`DB_HOST`、`DB_PORT` 等），Docker 环境通过 `docker-compose.yml` 注入。

### 用户角色

- **超级管理员 (Super)**: 后台全部权限
- **管理员 (Manage)**: 后台部分权限，`ManageSession` 拦截器鉴权
- **商户 (Business)**: 拥有自己的店铺，可上架商品
- **普通用户 (User)**: 前台浏览购买，`UserSession` 拦截器鉴权
- **游客 (Visitor)**: 可浏览和购买，部分商品可强制登录

## AiHub 主题

当前自定义主题，将前端改造为 AI 大模型 API 中转站风格。

### 文件位置
- **模板**: `app/View/User/Theme/AiHub/`（Smarty 模板）
- **样式**: `assets/aihub/css/aihub.css`（Tailwind CSS 自定义组件）
- **JS**: `assets/aihub/js/aihub-index.js`（首页商品卡片渲染）
- **Logo**: `assets/aihub/images/logo.svg`

### 技术栈
- Tailwind CSS（CDN Play 模式）+ Font Awesome 6
- 保留原有 jQuery + layui + layer.js + trade.js 业务逻辑
- 深色科技风配色：主色 `indigo-600`、背景 `slate-950`

### 切换主题
```sql
UPDATE acg_config SET value='AiHub' WHERE `key`='user_theme';   -- AiHub
UPDATE acg_config SET value='Cartoon' WHERE `key`='user_theme'; -- 原主题
```

### 改造约束
- 原主题 `Theme/Cartoon/` 完全保留，可随时切回
- 零 PHP 后端改动、零 JS 业务逻辑改动
- 所有 Smarty 变量和 Hook 点位完整保留

## Development Notes

- PHP >= 8.0（大量使用注解、match 表达式、联合类型）
- 配置读取 `config()` 函数，写入 `setConfig()` 生成 PHP 数组文件
- 日志输出到 `runtime.log`（通过 `debug()` 函数）
- 前台 JS 加载：`ready.js` 定义 `ready()` → 等 DOM Ready + layui 初始化 → 动态加载业务 JS
- 非 DEBUG 模式下 `assets/common/js/_.js` 是合并后的大文件（含 jQuery 3.6 + layui + util）
