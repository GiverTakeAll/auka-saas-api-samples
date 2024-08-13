<?php

// ログの設定
function logInfo($message) {
    echo date('Y-m-d H:i:s') . " - INFO - " . $message . PHP_EOL;
}

function logError($message) {
    echo date('Y-m-d H:i:s') . " - ERROR - " . $message . PHP_EOL;
}

// 定数の定義
define('LINE_ACCOUNT_UUID', getenv('LINE_ACCOUNT_UUID'));
define('ITEM_GROUP_UUID', getenv('ITEM_GROUP_UUID'));
define('AUTH_TOKEN', getenv('AUTH_TOKEN'));
define('BASE_URL', 'https://line-saas.auka.jp/api/externals/' . LINE_ACCOUNT_UUID);

function getHeaders() {
    return [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AUTH_TOKEN
    ];
}

function getJobStatus($jobId) {
    $statusUrl = BASE_URL . "/jobs/{$jobId}";
    logInfo("ジョブステータスを取得中: {$statusUrl}");

    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getHeaders());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $statusData = json_decode($response, true);
        logInfo("ジョブステータス: {$statusData['job']['status']}");
        return $statusData['job'];
    } else {
        logError("ジョブステータス取得エラー: {$httpCode}");
        return null;
    }
}

function createBaseItem() {
    return [
        'title' => 'サンプル物件',
        'description' => 'これは説明文です。',
        'label' => '分譲住宅 | 東京駅',
        'label_color' => '#E67050',
        'image_url' => 'https://th.bing.com/th/id/OIG1.54KCbwld1CFqClrd_Rb0?pid=ImgGn',
        'tags' => ['xxx小学校', '東京駅'],
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-02T00:00:00Z',
        'url' => 'http://auka.jp/',
        'custom_fields' => [
            'price' => '5,000万円',
            'address' => '東京都千代田区千代田1-1-1'
        ],
        'button_label' => '詳細を見る',
        'position' => 1
    ];
}

function generateItems($count) {
    $baseItem = createBaseItem();
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $item = $baseItem;
        $item['external_id'] = "EXTERNAL_" . (12345 + $i);
        $item['title'] = "サンプル物件{$i}";
        $item['description'] = "これは説明文です。{$i}";
        $items[] = $item;
    }
    return $items;
}

function sendSyncUpdateRequest($items) {
    $url = BASE_URL . "/item_groups/" . ITEM_GROUP_UUID . "/sync_update";
    $data = ['items' => $items];

    logInfo("Request JSON: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    logInfo("Sending POST request to {$url}");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getHeaders());
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logInfo("Status Code: {$httpCode}");
    logInfo("Response Body: {$response}");

    return ['status' => $httpCode, 'body' => $response];
}

function waitForJobCompletion($jobId) {
    $startTime = time();
    $timeout = 3600; // 1時間

    while (true) {
        $status = getJobStatus($jobId);
        if ($status['status'] === 'success') {
            logInfo("ジョブが完了しました");
            logInfo("レスポンスボディ: " . json_encode($status));
            break;
        } elseif ($status['status'] === 'failure') {
            logError("ジョブが失敗しました");
            logError("レスポンスボディ: " . json_encode($status));
            break;
        }

        if (time() - $startTime > $timeout) {
            logError("ジョブが1時間以内に完了しませんでした");
            throw new Exception("ジョブ完了のタイムアウト");
        }

        sleep(5);
    }
}

function main() {
    $items = generateItems(20);
    $response = sendSyncUpdateRequest($items);

    if ($response['status'] === 200) {
        $responseData = json_decode($response['body'], true);
        $jobId = $responseData['job']['id'];
        logInfo("ジョブID: {$jobId}");
        waitForJobCompletion($jobId);
    } else {
        logError("リクエスト失敗: {$response['status']}");
        logError("レスポンスボディ: {$response['body']}");
    }
}

main();