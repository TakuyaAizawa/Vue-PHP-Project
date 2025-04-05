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

// データベース接続情報
$host = getenv('MYSQL_HOST') ?: 'mysql';
$dbname = getenv('MYSQL_DATABASE') ?: 'todo_db';
$user = getenv('MYSQL_USER') ?: 'todo_user';
$pass = getenv('MYSQL_PASSWORD') ?: 'todo_password';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

// データベース接続
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    error_log('データベース接続成功');
} catch (PDOException $e) {
    error_log('データベース接続エラー: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'データベース接続エラー']);
    exit;
}

// リクエストメソッドに基づいて処理
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // タスク一覧の取得
    case 'GET':
        try {
            $stmt = $pdo->query('SELECT * FROM todos ORDER BY created_at DESC');
            $todos = $stmt->fetchAll();
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
            
            $stmt = $pdo->prepare('INSERT INTO todos (id, text, completed) VALUES (UUID(), :text, FALSE)');
            $stmt->bindParam(':text', $text);
            $stmt->execute();
            
            // 追加したタスクを取得
            $stmt = $pdo->query('SELECT * FROM todos WHERE id = LAST_INSERT_ID()');
            if ($stmt->rowCount() === 0) {
                // LAST_INSERT_IDが機能しない場合は最後に挿入されたレコードを取得
                $stmt = $pdo->query('SELECT * FROM todos ORDER BY created_at DESC LIMIT 1');
            }
            $newTodo = $stmt->fetch();
            
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
                $stmt = $pdo->prepare('UPDATE todos SET text = :text WHERE id = :id');
                $stmt->bindParam(':text', $input['text']);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }
            
            // 完了状態の更新
            if (isset($input['completed'])) {
                $completed = (bool)$input['completed'];
                $stmt = $pdo->prepare('UPDATE todos SET completed = :completed WHERE id = :id');
                $stmt->bindParam(':completed', $completed, PDO::PARAM_BOOL);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }
            
            // 更新したタスクを取得
            $stmt = $pdo->prepare('SELECT * FROM todos WHERE id = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $updatedTodo = $stmt->fetch();
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
            $stmt = $pdo->prepare('SELECT id FROM todos WHERE id = :id');
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare('DELETE FROM todos WHERE id = :id');
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