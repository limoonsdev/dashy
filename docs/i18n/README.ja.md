[English](../../README.md) · [Français](./README.fr.md) · [Español](./README.es.md) · [Deutsch](./README.de.md) · **日本語** · [简体中文](./README.zh-cn.md)

<div align="center">

<img src="../assets/dashy-mark.svg" alt="Dashy" width="88" />

# Dashy

**自分でホストするコントロールプレーン。** アプリケーション、データベース、サービス、サーバー、仮想マシンをひとつのダッシュボードから。

</div>

## 概要

Dashy は一度インストールすれば、あなたのインフラに残ります。Git からのデプロイ、データベースの管理、ワンクリックでのサービス導入、SSH 経由のサーバー管理、Proxmox VE 上での仮想マシン作成を行います。

管理対象のホストにエージェントは不要です。SSH だけで十分です。Dashy をやめてもワークロードは動作を続け、手作業で管理できます。

## インストール

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh | sudo bash
```

インストーラーはホストを確認し、必要なら Docker を導入し、イメージをビルドまたは取得して起動し、ダッシュボードの URL を表示します。`http://<サーバー>:8000` を開き、最初のアカウントを作成してください。そのアカウントがインスタンス管理者になります。

## 機能

| 領域 | 内容 |
| --- | --- |
| アプリケーション | Git、Dockerfile、Nixpacks、Railpack、docker-compose、ブランチごとのプレビュー、即時ロールバック |
| データベース | PostgreSQL、MySQL、MariaDB、MongoDB、Redis、KeyDB、ClickHouse、Dragonfly、S3 バックアップ |
| サービス | ワンクリックのカタログ。compose ファイル不要 |
| サーバー | SSH で追加、Docker とプロキシを自動設定、エージェントなしのメトリクス |
| 仮想マシン | ディストリビューション選択、自動サイジング、cloud-init、**DashyVMToolkit** によるゲストエージェントと VirtIO 調整 |
| セキュリティ | パスワード、OAuth、パスキー、監査ログ、チーム権限、UFW、fail2ban、CrowdSec、WireGuard |
| ツールキット | ドメインと DNS、アーカイブ取り込み、データベース URL、セキュリティコマンド生成、Minecraft 向け Modrinth |

## テーマ

28 種類のテーマを同梱しています。すべて 4 色（サイドバー、選択、アクセント、背景）から生成されます。テーマは 4 色コードとして共有できるため、チームで同じ画面を一度の貼り付けで再現できます。

## 生成されるドメイン

既定では生成名に `sslip.io` を使うため、サーバーの IP が名前に含まれます。避けたい場合は所有ドメインを設定します。

```env
DASHY_WILDCARD_DOMAIN=example.com
```

生成名は `shop-a1b2c3.example.com` のようになり、アドレスが漏れません。

## ドキュメント

- [互換性](../compatibility.md)
- [開発](../../DEVELOPMENT.md)
- [コントリビュート](../../CONTRIBUTING.md)
- [セキュリティ](../../SECURITY.md)

## ライセンス

Apache-2.0。
