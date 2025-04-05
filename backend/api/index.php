<?php
// マルチバイト文字列設定（日本語対応）
mb_language('Japanese');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// デバッグモード有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// カスタムログ関数（日本語対応）
function log_jp($message) {
    // 日本語ログを適切に処理
    error_log(mb_convert_encoding($message, 'UTF-8', 'auto'));
}

// リクエスト情報のログ記録
log_jp('詳細なリクエスト情報: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' ' . file_get_contents('php://input'));
log_jp('リクエストヘッダー: ' . json_encode(getallheaders(), JSON_UNESCAPED_UNICODE));

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

// JWT用のシークレットキー
$jwtSecret = getenv('JWT_SECRET') ?: 'your_secret_key';

// JWTを生成する関数
function generateJWT($userId, $email) {
    global $jwtSecret;
    
    $issuedAt = time();
    $expirationTime = $issuedAt + 86400; // 24時間
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'user_id' => $userId,
        'email' => $email
    ];
    
    $header = json_encode([
        'typ' => 'JWT',
        'alg' => 'HS256'
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $jwtSecret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// JWTを検証する関数
function validateJWT($token) {
    global $jwtSecret;
    
    $tokenParts = explode('.', $token);
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signatureProvided = $tokenParts[2];
    
    // ヘッダーとペイロードから署名を再計算
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $jwtSecret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // 署名の検証
    if ($base64UrlSignature !== $signatureProvided) {
        return false;
    }
    
    $payload = json_decode($payload);
    
    // 有効期限の確認
    if (isset($payload->exp) && $payload->exp < time()) {
        return false;
    }
    
    return $payload;
}

// 認証が必要なエンドポイントのためのミドルウェア関数
function authenticate() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => '認証が必要です']);
        exit;
    }
    
    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    
    $payload = validateJWT($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => '無効なトークンです']);
        exit;
    }
    
    return $payload;
}

// テーブル存在確認とテーブルの作成（初回接続時）
try {
    // フラグファイルの確認（初回実行かどうか）
    $flagFile = '/tmp/db_initialized.flag';
    $isFirstRun = !file_exists($flagFile);
    
    // ユーザーテーブルの確認
    if ($env === 'production') {
        // PostgreSQL
        $stmt = $pdo->query("SELECT to_regclass('public.users')");
        $usersExists = $stmt->fetchColumn() !== null;
    } else {
        // MySQL
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $usersExists = $stmt->rowCount() > 0;
    }
    
    if (!$usersExists || $isFirstRun) {
        error_log('usersテーブルが存在しないため、作成します');
        if ($env === 'production') {
            // PostgreSQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id UUID PRIMARY KEY,
                    email TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    name TEXT,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // MySQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id VARCHAR(36) PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    name VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");
        }
        error_log('usersテーブルを作成しました');
    }
    
    // Todoテーブルの確認
    if ($env === 'production') {
        $stmt = $pdo->query("SELECT to_regclass('public.vue_todos')");
        $todoExists = $stmt->fetchColumn() !== null;
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'vue_todos'");
        $todoExists = $stmt->rowCount() > 0;
    }
    
    // 既存のTodoテーブルを削除（初回のみ）
    if (($isFirstRun || !$todoExists) && $isFirstRun) {
        if ($env === 'production') {
            $pdo->exec("DROP TABLE IF EXISTS vue_todos");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS vue_todos");
        }
        error_log('vue_todosテーブルをリセットしました（初回実行時のみ）');
        
        // 新しいTodoテーブルを作成
        if ($env === 'production') {
            // PostgreSQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id UUID PRIMARY KEY,
                    user_id UUID REFERENCES users(id),
                    text TEXT NOT NULL,
                    completed BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // MySQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id VARCHAR(36) PRIMARY KEY,
                    user_id VARCHAR(36) NOT NULL,
                    text TEXT NOT NULL,
                    completed BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");
        }
        error_log('新しいvue_todosテーブルを作成しました');
        
        // フラグファイルを作成して初回実行を記録
        file_put_contents($flagFile, date('Y-m-d H:i:s'));
        error_log('初回実行フラグを設定しました');
    } else if (!$todoExists) {
        // テーブルが存在しない場合は作成（リセットなし）
        if ($env === 'production') {
            // PostgreSQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id UUID PRIMARY KEY,
                    user_id UUID REFERENCES users(id),
                    text TEXT NOT NULL,
                    completed BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            // MySQL用のテーブル作成
            $pdo->exec("
                CREATE TABLE vue_todos (
                    id VARCHAR(36) PRIMARY KEY,
                    user_id VARCHAR(36) NOT NULL,
                    text TEXT NOT NULL,
                    completed BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");
        }
        error_log('vue_todosテーブルを作成しました');
    }
} catch (PDOException $e) {
    error_log('テーブル確認・作成エラー: ' . $e->getMessage());
}

// APIのルーティング
$requestUri = $_SERVER['REQUEST_URI'];
$endpoint = '';

if (preg_match('/\/api\/([^\/\?]+)/', $requestUri, $matches)) {
    $endpoint = $matches[1];
}

// リクエストメソッドに基づいて処理
$method = $_SERVER['REQUEST_METHOD'];

// 認証関連のエンドポイント
if ($endpoint === 'register' && $method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $name = $input['name'] ?? '';
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'メールアドレスとパスワードが必要です']);
            exit;
        }
        
        // メールアドレスの重複チェック
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'このメールアドレスは既に登録されています']);
            exit;
        }
        
        // パスワードのハッシュ化
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // ユーザーの登録
        if ($env === 'production') {
            // PostgreSQL
            $stmt = $pdo->prepare('INSERT INTO users (id, email, password, name) VALUES (gen_random_uuid(), :email, :password, :name)');
        } else {
            // MySQL
            $stmt = $pdo->prepare('INSERT INTO users (id, email, password, name) VALUES (UUID(), :email, :password, :name)');
        }
        
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        
        // 登録したユーザー情報を取得
        $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();
        
        // JWTトークンの生成
        $token = generateJWT($user['id'], $user['email']);
        
        echo json_encode([
            'user' => $user,
            'token' => $token
        ]);
    } catch (PDOException $e) {
        error_log('ユーザー登録エラー: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラー']);
    }
    exit;
} elseif ($endpoint === 'login' && $method === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'メールアドレスとパスワードが必要です']);
            exit;
        }
        
        // ユーザーの検索
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['error' => 'メールアドレスまたはパスワードが正しくありません']);
            exit;
        }
        
        $user = $stmt->fetch();
        
        // パスワードの検証
        if (!password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'メールアドレスまたはパスワードが正しくありません']);
            exit;
        }
        
        // パスワードを除外したユーザー情報
        $userWithoutPassword = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ];
        
        // JWTトークンの生成
        $token = generateJWT($user['id'], $user['email']);
        
        echo json_encode([
            'user' => $userWithoutPassword,
            'token' => $token
        ]);
    } catch (PDOException $e) {
        error_log('ログインエラー: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラー']);
    }
    exit;
} elseif ($endpoint === 'me' && $method === 'GET') {
    // 現在のユーザー情報を取得
    $payload = authenticate();
    
    try {
        $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE id = :id');
        $stmt->bindParam(':id', $payload->user_id);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'ユーザーが見つかりません']);
            exit;
        }
        
        echo json_encode(['user' => $user]);
    } catch (PDOException $e) {
        error_log('ユーザー情報取得エラー: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラー']);
    }
    exit;
} elseif ($endpoint === '') {
    // Todo関連のエンドポイント
    switch ($method) {
        // タスク一覧の取得
        case 'GET':
            // 認証
            $payload = authenticate();
            
            try {
                $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE user_id = :user_id ORDER BY created_at DESC');
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->execute();
                $todos = $stmt->fetchAll();
                
                echo json_encode($todos);
            } catch (PDOException $e) {
                error_log('TODO取得エラー: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'データベースエラー']);
            }
            break;

        // 新しいタスクの追加
        case 'POST':
            // 認証
            $payload = authenticate();
            
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
                    $stmt = $pdo->prepare('INSERT INTO vue_todos (id, user_id, text, completed) VALUES (gen_random_uuid(), :user_id, :text, FALSE)');
                } else {
                    // MySQL
                    $stmt = $pdo->prepare('INSERT INTO vue_todos (id, user_id, text, completed) VALUES (UUID(), :user_id, :text, FALSE)');
                }
                
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->bindParam(':text', $text);
                $stmt->execute();
                
                // 追加したタスクを取得
                if ($env === 'production') {
                    // PostgreSQL - 最後に作成されたレコードを取得
                    $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1');
                    $stmt->bindParam(':user_id', $payload->user_id);
                } else {
                    // MySQL
                    $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1');
                    $stmt->bindParam(':user_id', $payload->user_id);
                }
                
                $stmt->execute();
                $newTodo = $stmt->fetch();
                
                echo json_encode($newTodo);
            } catch (PDOException $e) {
                error_log('TODO追加エラー: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'データベースエラー']);
            }
            break;

        // タスクの更新
        case 'PUT':
            // 認証
            $payload = authenticate();
            
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? '';
                $completed = isset($input['completed']) ? (bool)$input['completed'] : null;
                
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'IDが必要です']);
                    break;
                }
                
                if ($completed === null) {
                    http_response_code(400);
                    echo json_encode(['error' => '完了状態が必要です']);
                    break;
                }
                
                // タスクの所有者確認
                $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE id = :id AND user_id = :user_id');
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'タスクが見つからないか、アクセス権限がありません']);
                    break;
                }
                
                // タスクの更新
                $stmt = $pdo->prepare('UPDATE vue_todos SET completed = :completed WHERE id = :id AND user_id = :user_id');
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':completed', $completed, PDO::PARAM_BOOL);
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log('TODO更新エラー: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'データベースエラー']);
            }
            break;

        // タスクの削除
        case 'DELETE':
            // 認証
            $payload = authenticate();
            
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? '';
                
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'IDが必要です']);
                    break;
                }
                
                // タスクの所有者確認
                $stmt = $pdo->prepare('SELECT * FROM vue_todos WHERE id = :id AND user_id = :user_id');
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'タスクが見つからないか、アクセス権限がありません']);
                    break;
                }
                
                // タスクの削除
                $stmt = $pdo->prepare('DELETE FROM vue_todos WHERE id = :id AND user_id = :user_id');
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':user_id', $payload->user_id);
                $stmt->execute();
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log('TODO削除エラー: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'データベースエラー']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => '許可されていないメソッドです']);
            break;
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'エンドポイントが見つかりません']);
} 