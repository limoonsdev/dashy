[English](../../README.md) · [Français](./README.fr.md) · [Español](./README.es.md) · [Deutsch](./README.de.md) · [日本語](./README.ja.md) · **简体中文**

<div align="center">

<img src="../assets/dashy-mark.svg" alt="Dashy" width="88" />

# Dashy

**属于自己的控制平面。** 在一个仪表盘中管理应用、数据库、服务、服务器和虚拟机。

</div>

## 概述

Dashy 安装一次，就留在你的基础设施里。它从 Git 部署应用、管理数据库、一键安装服务、通过 SSH 管理服务器，并在 Proxmox VE 上创建虚拟机。

被管理的服务器不需要安装代理，SSH 就够了。即使不再使用 Dashy，工作负载仍会继续运行，也可以手动管理。

## 安装

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh | sudo bash
```

安装程序会检查主机、按需安装 Docker、构建或拉取镜像、启动服务并输出仪表盘地址。打开 `http://<服务器>:8000` 并创建第一个账号，该账号即实例管理员。

## 功能

| 领域 | 说明 |
| --- | --- |
| 应用 | Git、Dockerfile、Nixpacks、Railpack、docker-compose、按分支的预览环境、即时回滚 |
| 数据库 | PostgreSQL、MySQL、MariaDB、MongoDB、Redis、KeyDB、ClickHouse、Dragonfly、S3 备份 |
| 服务 | 一键目录，无需编写 compose 文件 |
| 服务器 | 通过 SSH 添加，自动安装 Docker 和代理，无代理采集指标 |
| 虚拟机 | 选择发行版、自动配置规格、cloud-init，**DashyVMToolkit** 配置来宾代理和 VirtIO |
| 安全 | 密码、OAuth、通行密钥、审计日志、团队权限、UFW、fail2ban、CrowdSec、WireGuard |
| 工具箱 | 域名与 DNS、压缩包导入、数据库 URL、安全命令生成器、Minecraft 的 Modrinth 浏览器 |

## 主题

内置 28 个主题，全部由四种颜色（侧边栏、选中项、强调色、背景）生成。主题可以四色代码分享，团队粘贴一次即可复现相同界面。

## 生成的域名

默认使用 `sslip.io`，生成的域名会包含服务器 IP。要避免这一点，请设置自己的域名：

```env
DASHY_WILDCARD_DOMAIN=example.com
```

生成的域名形如 `shop-a1b2c3.example.com`，不再暴露地址。

## 文档

- [兼容性](../compatibility.md)
- [开发](../../DEVELOPMENT.md)
- [贡献](../../CONTRIBUTING.md)
- [安全](../../SECURITY.md)

## 许可证

Apache-2.0。
