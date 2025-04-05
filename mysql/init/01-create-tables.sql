-- 文字コード設定
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- TODOアプリ用のテーブル作成
CREATE TABLE IF NOT EXISTS todos (
    id VARCHAR(36) PRIMARY KEY,
    text TEXT NOT NULL,
    completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- サンプルデータの挿入（クリア後に再挿入）
TRUNCATE TABLE todos;
INSERT INTO todos (id, text, completed, created_at) VALUES
(UUID(), 'MySQLに移行したTODOアプリケーションのテスト', FALSE, NOW()),
(UUID(), 'サンプルタスク2', FALSE, NOW()),
(UUID(), '完了済みタスク', TRUE, NOW()); 