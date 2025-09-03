# DockerとDocker Composeのインストールと設定

---

## Dockerのインストールと自動起動化

まず、Dockerをインストールし、システム起動時に自動で立ち上がるように設定します。

```bash:Dockerのインストールと起動
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker
```

## デフォルトのユーザーでもdockerコマンドを使えるように、dockerグループに追加する

```
sudo usermod -a -G docker ec2-user
```

usermodを反映するために一度ログアウトする
(sshの場合は一度ログアウトしログインしなおすことで反映させることができる)

screenのウィンドウ等もすべて一度終了させる(exitコマンド)

# Docker Compose インストール方法

```
 sudo mkdir -p /usr/local/lib/docker/cli-plugins/
 sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
 sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
```

インストールできたかを確認したい場合
```
docker compose version
```

# docker compose 使用方法

## 作業ディレクトリ、設定ファイルを作成する

```
 mkdir web
 cd web
 vim compose.yml
```

設定ファイルの中身

```
services:
  web:
    image: nginx
    ports:
      - 80:80
```

起動
```
docker compose up
```

# ファイルを配信する
---
## 設定ファイル用のディレクトリと設定ファイルを作成する

```
mkdir nginx
 mkdir nginx/conf.d
 vim nginx/conf.d/default.conf
```

設定ファイルの内容
```
server {
    listen       0.0.0.0:80;
    server_name  _;
    charset      utf-8;

    root /var/www/public;
}
```

## 配信するファイルを置くディレクトリを作成する

```
 mkdir public
```

compose.ymlを編集する
```
services:
  web:
    image: nginx:latest
    ports:
      - 80:80
    volumes:     ←ここから追記
      - ./nginx/conf.d/:/etc/nginx/conf.d/
      - ./public/:/var/www/public/
```

最後に doker compose を再起動して確認する
docker compose upしたままの場合はCtrl+Cで終了させて、 再度docker compose upを叩いて起動


## php-fpmを動かすためのコンテナを起動するために追記する
```
vim compose.yml
```

ファイルの内容
```
services:
  web:
    image: nginx:latest
    ports:
      - 80:80
    volumes:
      - ./nginx/conf.d/:/etc/nginx/conf.d/
      - ./public/:/var/www/public/
    depends_on:　　　←ここから追記
      - php
  php:
    container_name: php
    image: php:8.4-fpm-alpine
    volumes:
      - ./public/:/var/www/public/
```

compose.ymlの11~15行目の部分が、php-fpmを動かすためのコンテナについての部分。

## nginxの設定ファイルにphp-fpmと連携するための記述を追記

```
vim nginx/conf.d/default.conf
```

ファイルの内容
```
server {
    listen       0.0.0.0:80;
    server_name  _;
    charset      utf-8;

    root /var/www/public;

    location ~ \.php$ {
        fastcgi_pass  php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include       fastcgi_params;
    }
}
```

7行目から13行目までが，今回の追記箇所


# MySQLをDockerで動かす
---
## compose.yml を編集

```
vim compose.yml
```

ファイルの内容
```
services:
  web:
    image: nginx:latest
    ports:
      - 80:80
    volumes:
      - ./nginx/conf.d/:/etc/nginx/conf.d/
      - ./public/:/var/www/public/
    depends_on:
      - php
  php:
    container_name: php
    build:
      context: .
      target: php
    volumes:
      - ./public/:/var/www/public/
  mysql:    ←ここから追記
    container_name: mysql
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: example_db
      MYSQL_ALLOW_EMPTY_PASSWORD: 1
      TZ: Asia/Tokyo
    volumes:
      - mysql:/var/lib/mysql
    command: >
      mysqld
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --max_allowed_packet=4MB
volumes:
  mysql:
  image:
```

exitして、docker compose upでもう一度立ち上げる


# PHPからMySQLサーバーに接続
---
## PDO拡張を導入するために，Dockerfileを書き換える

```
vim Dockerfile
```

ファイルの内容
```
FROM php:8.4-fpm-alpine AS php
RUN docker-php-ext-install pdo_mysql

```

exitして、docker compose upでもう一度立ち上げる

テストデータをmysqlに入れる
```
 docker compose exec mysql mysql example_db

CREATE TABLE `hoge`(
    `text` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

exit
```
