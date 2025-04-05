<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// デバッグ情報を記録
error_log('APIが呼び出されました。メソッド: ' . $_SERVER['REQUEST_METHOD']);

// OPTIONS リクエストに対するレスポンス
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 環境設定を取得
$env = getenv('APP_ENV');
// 環境変数からコメント部分を削除
if (strpos($env, '#') !== false) {
    $env = trim(substr($env, 0, strpos($env, '#')));
}
$env = $env ?: 'development';
error_log('現在の環境: ' . $env);

// データベース接続情報 - 環境によって切り替え
if ($env === 'production' && getenv('SUPABASE_URL')) {
    // 本番環境: Supabase (PostgreSQL)
    $supabaseUrl = getenv('SUPABASE_URL');
    error_log('Supabase接続を使用します');
    
    try {
        // URLからパーツを抽出
        $matches = [];
        if (preg_match('/postgres:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/([^?]+)/', $supabaseUrl, $matches)) {
            list(, $user, $password, $host, $port, $dbname) = $matches;
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require";
            error_log("変換したPDO DSN: $dsn");
        } else {
            // 正規表現でマッチしない場合はそのまま使用
            $dsn = $supabaseUrl;
        }
        
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        error_log('Supabaseデータベース接続成功');
    } catch (PDOException $e) {
        error_log('Supabaseデータベース接続エラー: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'データベース接続エラー']);
        exit;
    }
} else {
    // 開発環境: MySQL
    $host = getenv('MYSQL_HOST') ?: 'mysql';
    $dbname = getenv('MYSQL_DATABASE') ?: 'todo_db';
    $user = getenv('MYSQL_USER') ?: 'todo_user';
    $pass = getenv('MYSQL_PASSWORD') ?: 'todo_password';
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    error_log('MySQL接続を使用します: ' . $dsn);
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        error_log('MySQLデータベース接続成功');
    } catch (PDOException $e) {
        error_log('MySQLデータベース接続エラー: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'データベース接続エラー']);
        exit;
    }
}

// テーブル存在確認とテーブルの作成（初回接続時）
try {
    // テーブルの存在確認方法はデータベースタイプによって異なる
    if ($env === 'production') {
        // PostgreSQL
        $stmt = $pdo->query("SELECT to_regclass('public.vue_todos')");
        $exists = $stmt->fetchColumn() !== null;
    } else {
        // MySQL
        $stmt = $pdo->query("SHOW TABLES LIKE 'vue_todos'");
        $exists = $stmt->rowCount() > 0;
    }
    
    if (!$exists) {
        error_log('vue_todosテーブルが存在しないため、作成します');
        if ($env === 'production') {
            // PostgreSQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id UUID PRIMARY KEY,
                    text TEXT NOT NULL,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // MySQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id VARCHAR(36) PRIMARY KEY,
                    text TEXT NOT NULL,
                    completed BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");
        }
        error_log('vue_todosテーブルを作成しました');
    }
} catch (PDOException $e) {
    error_log('テーブル確認・作成エラー: ' . $e->getMessage());
}

// リクエストメソッドに基づいて処理
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // タスク一覧の取得
    case 'GET':
        try {
            $stmt = $pdo->query('SELECT * FROM vue_todos ORDER BY created_at DESC');
            $todos = $stmt->fetchAll();
            
            // 環境によって異なるレスポンス形式を統一
            if ($env === 'production') {
                // Supabaseのレスポンスにcompletedフィールドを追加
                foreach ($todos as &$todo) {
                    $todo['completed'] = 0; // デフォルトで未完了
                }
            }
            
            error_log('TODOを取得しました。件数: ' . count($todos));
            echo json_encode($todos);
        } catch (PDOException $e) {
            error_log('TODO取得エラー: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'データベースエラー']);
        }
        break;

    // 新しいタスクの追加
    case 'POST':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $text = $input['text'] ?? '';
            
            if (empty($text)) {
                http_response_code(400);
                echo json_encode(['error' => 'テキストが必要です']);
                break;
            }
            
            if ($env === 'production') {
                // PostgreSQL
                $stmt = $pdo->prepare('INSERT INTO vue_todos (id, text) VALUES (gen_random_uuid(), :text)');
            } else {
                // MySQL
                $stmt = $pdo->prepare('INSERT INTO vue_todos (id, text, completed) VALUES (UUID(), :text, FALSE)');
            }
            
            $stmt->bindParam(':text', $text);
            $stmt->execute();
            
            // 追加したタスクを取得
            if ($env === 'production') {
                // PostgreSQL - 最後に作成されたレコードを取得
                $stmt = $pdo->query('SELECT * FROM vue_todos ORDER BY created_at DESC LIMIT 1');
            } else {
                // MySQL
                $stmt = $pdo->query('SELECT * FROM vue_todos WHERE id = LAST_INSERT_ID()');
                if ($stmt->rowCount() === 0) {
                    // LAST_INSERT_IDが機能しない場合は最後に挿入されたレコードを取得
                    $stmt = $pdo->query('SELECT * FROM vue_todos ORDER BY created_at DESC LIMIT 1');
                }
            }
            $newTodo = $stmt->fetch();

            // 環境によって異なるレスポンス形式を統一
            if ($env === 'production') {
                // Supabaseは completed カラムがないので、互換性のために追加
                $newTodo['completed'] = 0;
            }
            
            error_log('新しいTODOを追加しました: ' . $text);
            echo json_encode($newTodo);
        } catch (PDOException $e) {
            error_log('TODO追加エラー: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'データベースエラー']);
        }
        break;

    // タスクの更新
    case 'PUT':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? '';
            
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'IDが必要です']);
                break;
            }
            
            // テキストの更新
            if (isset($input['text'])) {
                $stmt = $pdo->prepare('UPDATE vue_todos SET text = :text WHERE id = :id');
                $stmt->bindParam(':text', $input['text']);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }
            
            // 完了状態の更新
            if (isset($input['completed'])) {
                if ($env === 'production') {
                    // Supabaseではcompletedカラムがないため、更新時刻だけ更新する
                    $stmt = $pdo->prepare('UPDATE vue_todos SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                    $stmt->bindParam(':id', $id);
                } else {
                    // MySQL
                    $completed = (bool)$input['completed'];
                    $stmt = $pdo->prepare('UPDATE vue_todos SET completed = :completed WHERE id = :id');
                    $stmt->bindParam(':completed', $completed, PDO::PARAM_BOOL);
                    $stmt->bindParam(':id', $id);
                }
                $stmt->execute();
            }
            
            // 更新したタスクを取得
            $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE id = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $updatedTodo = $stmt->fetch();
                
                // 環境によって異なるレスポンス形式を統一
                if ($env === 'production') {
                    // Supabaseのレスポンスにcompletedフィールドを追加
                    $updatedTodo['completed'] = isset($input['completed']) ? (int)$input['completed'] : 0;
                }
                
                error_log('TODOを更新しました: ' . $id);
                echo json_encode($updatedTodo);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Todo not found']);
                error_log('TODO更新失敗: ID ' . $id . ' が見つかりません');
            }
        } catch (PDOException $e) {
            error_log('TODO更新エラー: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'データベースエラー']);
        }
        break;

    // タスクの削除
    case 'DELETE':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? '';
            
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'IDが必要です']);
                break;
            }
            
            // 削除前にタスクの存在確認
            $stmt = $pdo->prepare('SELECT id FROM vue_todos WHERE id = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare('DELETE FROM vue_todos WHERE id = :id');
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                http_response_code(204); // No Content
                error_log('TODOを削除しました: ' . $id);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Todo not found']);
                error_log('TODO削除失敗: ID ' . $id . ' が見つかりません');
            }
        } catch (PDOException $e) {
            error_log('TODO削除エラー: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'データベースエラー']);
        }
        break;
    
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Method not allowed']);
        error_log('不正なメソッド: ' . $method);
        break;
} 