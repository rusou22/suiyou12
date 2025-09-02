# サービス構築手順書

使用ツール：**AWS EC2 / Windows PowerShell**

 
> 以降の `<PUBLIC_IP>` は **EC2 の自動割当てパブリック IP**、`<KEY_PATH>` は **作成したキーペア(.pem)のフルパス** を指します。
> 例：<KEY_PATH> ⇒ C:\Users\you\Desktop\mykey.pem

---

## 0. 前提

- AWS アカウントを保有していること
- セキュリティグループで **22/tcp(SSH)** が自分のIPから許可されていること

---

## 1. EC2 の作成（参照記事）

以下の記事に従って **「11.インスタンスのステータスのチェック」まで** 実施してください。  
https://qiita.com/hiro_10/items/3ee98a7842c3c74e0d29

---

## 2.ラボ（AWS Academy）を起動（作成者の場合）

```text
1.AWS Academy ⇒ コース ⇒ AWS Academy ⇒ AWS Academy Learner Lab を起動

2.Start Lab をクリック

3.左の AWS の右の〇が 緑色 になったらクリック

4.EC2 サービス ⇒ インスタンス

5.作成した インスタンスの ID をクリック

6.表示される 自動割当てパブリック IP をコピー（のちに使用）
```

---

## 3. PowerShell から SSH 接続（Amazon Linux へ）


### 3.1 接続コマンド

```powershell
ssh ec2-user@<PUBLIC_IP> -i <KEY_PATH>
#パスに空白がある場合はダブルクォートで囲みます：
ssh ec2-user@<PUBLIC_IP> -i "C:\Users\you\Desktop\my key.pem"
```

> プロンプトが `[ec2-user@ip-xxx-xxx-xxx-xxx ~]$` になればログイン成功。

---

## 4. 基本ツールのインストール

> 以下は **Amazon Linux 2023（dnf）** を優先。yum/apt も併記。

### 4.1 vim

```bash
# Amazon Linux 2023 の場合
sudo dnf install -y vim || sudo yum install -y vim
# Ubuntu / Debian の場合
sudo apt update && sudo apt install -y vim
```

### 4.2 screen

```bash
# Amazon Linux 2023 の場合
sudo dnf install -y screen || sudo yum install -y screen
# Ubuntu / Debian の場合
sudo apt update && sudo apt install -y screen
```

---

## 5. Docker のインストールと自動起動

```bash
# Docker 本体
sudo dnf install -y docker || sudo yum install -y docker

# 起動 & 自動起動化
sudo systemctl start docker
sudo systemctl enable docker

# ec2-user を docker グループへ追加（sudo なしで docker を使うため）
sudo usermod -a -G docker ec2-user
exit   # ← 一度ログアウト
```

PowerShell から **再度 SSH 接続**：

```powershell
ssh ec2-user@<PUBLIC_IP> -i <KEY_PATH>
```

---

## 6. Docker Compose v2 インストール

```bash
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# 確認
docker compose version
```

---

## 7. プロジェクト作成（例：dockertest）

```bash
mkdir -p ~/dockertest/nginx/conf.d
mkdir -p ~/dockertest/public
cd ~/dockertest
```

> **Dockerfile / docker-compose.yml / nginx/conf.d/default.conf / public/bbsimagetest.php** の内容は **リポジトリからコピー**してください。

### 7.1 各種ファイルの作成(内容はリポジトリからコピーしてください。)

```bash
vim Dockerfile
vim docker-compose.yml
vim nginx/conf.d/default.conf
vim public/bbsimagetest.php
```

### 7.2 完成ディレクトリ構成

```
dockertest/
├── Dockerfile
├── docker-compose.yml
├── nginx/
│   └── conf.d/
│       └── default.conf
└── public/
    └── bbsimagetest.php
```

---

## 8. screen を使用

```bash
# 新規セッション開始
screen

# （起動後のキーバインド）
# 新規タブ：Ctrl + a → c
# 次/前タブ：Ctrl + a → n / p
# デタッチ：Ctrl + a → d
# 復帰：screen -r
```

---

## 9. コンテナのビルド & 起動

```bash
# プロジェクト直下（dockertest/）で
docker compose build
docker compose up
```

> screen 内で `docker compose up` を実行しておくと、回線が切れても裏で動かせます。

---

起動完了後、キーボードのctrl + a を押してcで新しいscreenタブ

## 10. MySQL にテーブル作成

### 10.1 MySQL コンテナに入って DB を指定して実行します。

```bash
docker compose exec mysql mysql example_db
```

```sql
CREATE TABLE IF NOT EXISTS `bbs_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `body` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `image_filename` TEXT DEFAULT NULL
);
```

---

## 11. 動作確認（ブラウザ）

```
http://<PUBLIC_IP>/bbsimagetest.php
```

ページが表示され、投稿/画像機能が動作すれば成功です。

---


---

以上で構築完了です。
