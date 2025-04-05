FROM --platform=linux/amd64 php:8.0-apache

# 必要なPHP拡張機能をインストール
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql

# Apache設定を有効化
RUN a2enmod rewrite

# アプリケーションファイルをコピー
COPY . /var/www/html/

# パーミッション設定
RUN chown -R www-data:www-data /var/www/html

# Apacheの設定ファイルを作成
RUN echo '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# 8080ポートを公開
EXPOSE 8080

# Apacheをforegroundで実行（ポートを8080に変更）
CMD sed -i "s/Listen 80/Listen 8080/g" /etc/apache2/ports.conf && apache2-foreground 