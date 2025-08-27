## 手順

1. **Docker および Docker Compose のインストール (Amazon Linux2の場合)**

    1.1 Docker インストール
    ```bash
    sudo yum install -y docker
    ```

    1.2 Docker サービス起動 & 自動起動設定
    ```bash
    sudo systemctl start docker
    sudo systemctl enable docker
    ```

    1.3 Docker Compose インストール
    ```bash
    sudo mkdir -p /usr/local/lib/docker/cli-plugins/
    sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
    sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
    ```

    1.4 インストール確認
    ```bash
    docker compose version
    ```

2. **ソースコード類の配置**

    ```text
    dockertest/
    ├── Dockerfile
    ├── docker-compose.yml
    ├── nginx/
    │   └── conf.d/
    │       └── default.conf
    └── public/
        └── bbsimagetest.php
    ```

3. **`docker compose build` とは**

    `設定ファイル変更後に使用。

    ```bash
    docker compose build
    ```

    **`docker compose up` とは**

    `起動時に使用。

    ```bash
    docker compose build
    ```

## BBS テーブル作成手順

以下の SQLで作成。

```sql
CREATE TABLE IF NOT EXISTS `bbs_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `body` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `image_filename` TEXT DEFAULT NULL
);

