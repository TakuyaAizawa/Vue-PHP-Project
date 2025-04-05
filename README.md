# TODOアプリケーション

Vue.js、PHP、MySQL、Nginxを使用したシンプルなTODOアプリケーションです。

## システム構成

- フロントエンド: Vue.js
- バックエンド: PHP
- データベース: MySQL
- Webサーバー: Nginx
- データベース管理: phpMyAdmin

## 環境設定

本プロジェクトは`.env`ファイルを使用して環境変数を管理しています。初回セットアップ時は`.env.template`から`.env`を作成してください：

```bash
cp .env.template .env
# その後、.envファイル内のパスワードなどを適切に変更してください
```

主な設定項目：

```
# データベース設定
MYSQL_HOST=mysql
MYSQL_DATABASE=todo_db
MYSQL_USER=your_user
MYSQL_PASSWORD=your_password
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_CHARSET=utf8mb4
MYSQL_COLLATION=utf8mb4_unicode_ci

# phpMyAdmin設定
PMA_HOST=mysql
PMA_USER=root
PMA_PASSWORD=your_root_password

# ポート設定
NGINX_PORT=80
MYSQL_PORT=3306
PMA_PORT=8080
```

## 起動方法

以下のコマンドでアプリケーションを起動できます：

```
docker-compose up -d
```

## アクセス方法

- TODOアプリケーション: http://localhost
- phpMyAdmin: http://localhost:8080
  - ユーザー名: root
  - パスワード: .envで設定したMYSQL_ROOT_PASSWORDの値

## 開発方法

### フロントエンド開発

フロントエンドのソースコードは`frontend`ディレクトリにあります。
変更を加えた後は、以下のコマンドでビルドを実行します：

```
docker-compose up --build frontend-build
```

### バックエンド開発

バックエンドのソースコードは`backend`ディレクトリにあります。
APIは`backend/api`ディレクトリに実装されています。

### データベース

データベースの初期化スクリプトは`mysql/init`ディレクトリにあります。
変更を反映するには、以下のコマンドでボリュームを削除してから再起動します：

```
docker-compose down
docker volume rm study_mysql_data
docker-compose up -d
```

## データの永続化について

このアプリケーションはJSONファイルからMySQLデータベースに移行されました。
TODOデータはMySQLデータベースの`todos`テーブルに保存されており、
Dockerボリューム`mysql_data`を通じて永続化されています。

## 機能

- タスクの一覧表示
- タスクの追加
- タスクの完了/未完了の切り替え
- タスクの削除

## 技術スタック

- フロントエンド: Vue.js 3
- バックエンド: PHP (フレームワークなし)
- データベース: MySQL 8.0
- Webサーバー: Nginx
- インフラ: Docker + Docker Compose

## 実行方法

1. リポジトリをクローンします
```
git clone <リポジトリURL>
cd <リポジトリ名>
```

2. 環境変数を設定します
```
cp .env.template .env
# .envファイルを編集して適切な値を設定
```

3. Dockerコンテナを起動します
```
docker-compose up -d
```

4. ブラウザで http://localhost にアクセスすると、TODOアプリケーションが表示されます

## プロジェクト構成

```
.
├── .env                  # 環境変数設定ファイル
├── .env.template         # 環境変数のテンプレート
├── docker-compose.yml    # Dockerコンテナの設定
├── nginx/                # Nginxの設定
│   ├── Dockerfile
│   └── default.conf      # Nginxの設定ファイル
├── backend/              # PHPバックエンド
│   ├── Dockerfile
│   └── api/              # APIエンドポイント
│       └── index.php     # TODOアプリのAPI
├── frontend/             # Vue.jsフロントエンド
│   ├── Dockerfile
│   ├── package.json      # パッケージ依存関係
│   ├── babel.config.js   # Babel設定
│   ├── vue.config.js     # Vue.js設定
│   ├── public/           # 静的ファイル
│   │   └── index.html    # HTMLテンプレート
│   └── src/              # ソースコード
│       ├── main.js       # アプリケーションエントリポイント
│       └── App.vue       # メインコンポーネント
├── mysql/                # MySQLの設定
│   └── init/             # 初期化スクリプト
│       └── 01-create-tables.sql  # テーブル作成SQL
```

## APIエンドポイント

- `GET /api` - タスク一覧の取得
- `POST /api` - 新しいタスクの追加
- `PUT /api` - タスクの更新
- `DELETE /api` - タスクの削除 