# Supabaseデータベース連携ガイド

このドキュメントでは、TODO PHPアプリケーションをSupabase（PostgreSQL）と連携する方法について説明します。

## 目次

1. [Supabaseとは](#supabaseとは)
2. [前提条件](#前提条件)
3. [環境設定](#環境設定)
4. [開発環境と本番環境の切り替え](#開発環境と本番環境の切り替え)
5. [トラブルシューティング](#トラブルシューティング)

## Supabaseとは

Supabaseは、オープンソースのFirebase代替サービスで、PostgreSQLデータベースを提供しています。
以下の特徴があります：

- リアルタイムデータベース
- 認証とユーザー管理
- ストレージ
- サーバーレス関数
- 自動生成されるAPI

## 前提条件

- Supabaseアカウントの作成とプロジェクトのセットアップ
- PostgreSQL接続情報の取得
- PHP 7.4以上（PDO拡張機能とPDO PostgreSQLドライバーが必要）

## 環境設定

### 1. Supabaseプロジェクトの作成

1. [Supabase](https://supabase.com/)にサインアップし、新しいプロジェクトを作成します
2. 「Project Settings」→「Database」タブを選択
3. 「Connection String」セクションから「URI」形式の接続文字列をコピーします

### 2. 環境変数の設定

`.env`ファイルに以下の環境変数を設定します：

```
# 環境設定
APP_ENV=production  # development または production

# Supabaseデータベース設定（本番環境用）
SUPABASE_URL=postgres://postgres.[YOUR-PROJECT-REF]:[YOUR-PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres?sslmode=require
```

### 3. テーブルの作成

アプリケーションは初回接続時に必要なテーブルを自動的に作成しますが、手動で作成することもできます：

```sql
CREATE TABLE todos (
    id UUID PRIMARY KEY,
    text TEXT NOT NULL,
    completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
```

## 開発環境と本番環境の切り替え

このアプリケーションは、`APP_ENV`環境変数に基づいて以下のように動作します：

### 開発環境（`APP_ENV=development`）

- MySQLデータベースを使用
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`の環境変数を使用
- UUIDはMySQLの`UUID()`関数で生成

### 本番環境（`APP_ENV=production`）

- Supabase（PostgreSQL）を使用
- `SUPABASE_URL`の環境変数を使用してPostgreSQLに接続
- UUIDはPostgreSQLの`gen_random_uuid()`関数で生成

## トラブルシューティング

### 一般的な問題と解決策

1. **接続エラー**:
   - Supabaseの接続文字列が正しいか確認する
   - ネットワーク接続を確認する
   - Supabaseダッシュボードでプロジェクトのステータスを確認する

2. **"SSL is not enabled on the server"エラー**:
   - 接続文字列に`?sslmode=require`が含まれていることを確認する

3. **環境変数が読み込まれていないエラー**:
   - `.env`ファイルが正しく設定されているか確認する
   - Docker環境の場合、環境変数がコンテナに正しく渡されているか確認する

4. **テーブル作成エラー**:
   - Supabaseダッシュボードから手動でテーブルを作成してみる
   - SQLエラーの詳細をログで確認する

### ログの確認

問題のトラブルシューティングには、Apacheのエラーログを確認します：

```bash
# ローカル開発環境の場合
docker-compose exec php tail -f /var/log/apache2/error.log

# Google Cloud Runの場合
gcloud logging read "resource.type=cloud_run_revision AND resource.labels.service_name=todo-php-app"
```

---

Supabaseについての詳細は、[Supabase公式ドキュメント](https://supabase.com/docs)を参照してください。 