import requests
import json
import logging
import time
from datetime import datetime, timedelta

# ログの設定
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# 定数の定義
import os

LINE_ACCOUNT_UUID = os.getenv("LINE_ACCOUNT_UUID")
ITEM_GROUP_UUID = os.getenv("ITEM_GROUP_UUID")
AUTH_TOKEN = os.getenv("AUTH_TOKEN")

BASE_URL = f"https://line-saas.auka.jp/api/externals/{LINE_ACCOUNT_UUID}"

def get_headers():
    return {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {AUTH_TOKEN}"
    }

def get_job_status(job_id):
    status_url = f"{BASE_URL}/jobs/{job_id}"
    logging.info(f"ジョブステータスを取得中: {status_url}")

    response = requests.get(status_url, headers=get_headers())

    if response.status_code == 200:
        status_data = response.json()
        logging.info(f"ジョブステータス: {status_data['job']['status']}")
        return status_data['job']
    else:
        logging.error(f"ジョブステータス取得エラー: {response.status_code}")
        return None

def create_base_item():
    return {
        "title": "サンプル物件",
        "description": "これは説明文です。",
        "label": "分譲住宅 | 東京駅",
        "label_color": "#E67050",
        "image_url": "https://th.bing.com/th/id/OIG1.54KCbwld1CFqClrd_Rb0?pid=ImgGn",
        "tags": ["xxx小学校", "東京駅"],
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-02T00:00:00Z",
        "url": "http://auka.jp/",
        "custom_fields": {
            "price": "5,000万円",
            "address": "東京都千代田区千代田1-1-1"
        },
        "button_label": "詳細を見る",
        "position": 1
    }

def generate_items(count):
    base_item = create_base_item()
    items = []
    for i in range(1, count + 1):
        item = base_item.copy()
        item["external_id"] = f"EXTERNAL_{12345 + i}"
        item["title"] = f"サンプル物件{i}"
        item["description"] = f"これは説明文です。{i}"
        items.append(item)
    return items

def send_sync_update_request(items):
    url = f"{BASE_URL}/item_groups/{ITEM_GROUP_UUID}/sync_update"
    data = {"items": items}

    logging.info("Request JSON: %s", json.dumps(data, ensure_ascii=False, indent=2))
    logging.info(f"Sending POST request to {url}")

    response = requests.post(url, headers=get_headers(), json=data)

    logging.info(f"Status Code: {response.status_code}")
    logging.info(f"Response Body: {response.text}")

    return response

def wait_for_job_completion(job_id):
    start_time = datetime.now()
    timeout = timedelta(hours=1)

    while True:
        status = get_job_status(job_id)
        if status['status'] == 'success':
            logging.info("ジョブが完了しました")
            logging.info(f"レスポンスボディ: {status}")
            break
        elif status['status'] == 'failure':
            logging.error("ジョブが失敗しました")
            logging.error(f"レスポンスボディ: {status}")
            break

        # 1時間経過したかチェック
        if datetime.now() - start_time > timeout:
            logging.error("ジョブが1時間以内に完了しませんでした")
            raise TimeoutError("ジョブ完了のタイムアウト")

        time.sleep(5)  # 5秒待機してから再度ステータスを確認

def main():
    items = generate_items(20)
    response = send_sync_update_request(items)

    if response.status_code == 200:
        job_id = response.json().get('job', {}).get('id')
        logging.info(f"ジョブID: {job_id}")
        wait_for_job_completion(job_id)
    else:
        logging.error(f"リクエスト失敗: {response.status_code}")
        logging.error(f"レスポンスボディ: {response.json()}")

if __name__ == "__main__":
    main()