#!/bin/bash

# .envファイルが存在する場合に読み込む
if [ -f .env ]; then
    export $(cat .env | grep -v '#' | xargs)
fi

# 必要な環境変数が設定されているか確認
required_vars=("DB_HOST" "DB_NAME" "DB_USER" "DB_PASSWORD" "PROJECT_ID" "SERVICE_NAME" "REGION" "REPO_NAME")
for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "Error: $var is not set"
        echo "Please create .env file from .env.example"
        exit 1
    fi
done

# リポジトリが存在しない場合のみ作成
if ! gcloud artifacts repositories describe $REPO_NAME --location=$REGION &>/dev/null; then
    gcloud artifacts repositories create $REPO_NAME \
        --repository-format=docker \
        --location=$REGION
fi

# Dockerの認証設定
gcloud auth configure-docker $REGION-docker.pkg.dev

# フロントエンドのビルド
echo "Building frontend..."
cd ../frontend
npm install
npm run build
cd ../backend

# フロントエンドのビルド結果をバックエンドにコピー
echo "Copying frontend build to public directory..."
# 古いpublicディレクトリがあれば削除を試みるが、失敗しても続行
rm -rf public || true
# 新しいpublicディレクトリを作成
mkdir -p public
# ビルド結果をコピー
cp -r ../frontend/dist/* public/ || echo "Warning: Could not copy frontend build files"

# Dockerイメージをビルド
echo "Building Docker image..."
docker build --platform linux/amd64 -t $REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/$SERVICE_NAME .

# イメージをプッシュ
echo "Pushing Docker image..."
docker push $REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/$SERVICE_NAME

# 環境変数設定
ENV_VARS="MYSQL_HOST=${DB_HOST},MYSQL_DATABASE=${DB_NAME},MYSQL_USER=${DB_USER},MYSQL_PASSWORD=${DB_PASSWORD},APP_ENV=\"${APP_ENV}\""

# Supabase設定を追加（本番環境の場合）
if [ "${APP_ENV}" = "production" ] && [ ! -z "${SUPABASE_URL}" ]; then
    ENV_VARS="${ENV_VARS},SUPABASE_URL=${SUPABASE_URL}"
    echo "Adding Supabase configuration for production environment"
fi

# アプリケーション設定の追加
if [ ! -z "${APP_DEBUG}" ]; then
    ENV_VARS="${ENV_VARS},APP_DEBUG=${APP_DEBUG}"
fi

if [ ! -z "${APP_TIMEZONE}" ]; then
    ENV_VARS="${ENV_VARS},APP_TIMEZONE=${APP_TIMEZONE}"
fi

# Cloud Runデプロイコマンド
DEPLOY_CMD="gcloud run deploy $SERVICE_NAME \
    --image $REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/$SERVICE_NAME \
    --platform managed \
    --region $REGION \
    --allow-unauthenticated \
    --set-env-vars \"${ENV_VARS}\" \
    --port 8080"

# Cloud SQL接続オプションを追加（設定されている場合）
if [ ! -z "${CLOUDSQL_INSTANCE}" ]; then
    DEPLOY_CMD="${DEPLOY_CMD} --add-cloudsql-instances ${CLOUDSQL_INSTANCE}"
    echo "Adding Cloud SQL connection to: ${CLOUDSQL_INSTANCE}"
fi

# デプロイ実行
echo "Deploying to Cloud Run..."
eval $DEPLOY_CMD

echo "Deployment completed!" 