import json
#import mysql.connector
from pathlib import Path
from datetime import datetime
import shutil
from pymysql.err import MySQLError     # ← 把 MySQLError import 進來
from dotenv import load_dotenv
import os
import sys                # ← 新增這行
import pymysql
# ----------------------------------------------------------------
# 1) 基本設定
# ----------------------------------------------------------------
load_dotenv()  # 讀取 .env

# 一次性讀取所有設定
db_config = {
    "host": os.getenv("DB_HOST"),
    "user": os.getenv("DB_USER"),
    "password": os.getenv("DB_PASSWORD"),
    "database": os.getenv("DB_NAME"),
    "port": int(os.getenv("DB_PORT")),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor
}

def create_db_connection(cfg):
    """建立並回傳一個 MySQL 連線，連線失敗時結束程式"""
    try:
        conn = pymysql.connect(**cfg)
        # ping() 可以在連線已經存在時測試是否還活著
        conn.ping(reconnect=True)
        print("✅ 成功連線到 MySQL")
        return conn
    except MySQLError as e:
        print(f"❌ 連線失敗：{e}")
        sys.exit(1)

# 正式拿到連線與 cursor
db = create_db_connection(db_config)
cursor = db.cursor()

# helper：備份並讀 JSON
def backup_and_load(name):
    """
    name: 'channel1_room' / 'ranking_bp' / 'ranking_qp'
    回傳 JSON 解析後的物件
    """
    base = Path(__file__).parent
    src = base / f"{name}.json"
    backup_dir = base / f"{name}_history_backup"
    backup_dir.mkdir(exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    bak = backup_dir / f"{name}_backup_{ts}.json"
    shutil.copy(src, bak)
    print(f"🗂 已備份 {name}.json → {bak}")
    with src.open("r", encoding="utf-8") as f:
        data = json.load(f)
    return data, src

# helper：清空 JSON
def clear_json(path: Path):
    with path.open("w", encoding="utf-8") as f:
        json.dump({}, f, indent=4, ensure_ascii=False)
    print(f"🧹 已清空 {path.name}")
    

import re
from datetime import datetime, timedelta

# ----------------------------------------------------------------
# 上傳 ranking_bp 並備份（修改後）
# ----------------------------------------------------------------
bp_data, bp_path = backup_and_load("ranking_bp")

# 假設 JSON 物件本身是 list 或 { "ranking": [...] }
raw = bp_data if isinstance(bp_data, list) else bp_data.get("ranking", [])
last = raw[-1] if raw else None

if isinstance(last, str) and re.match(r"^\d{4}-\d{2}-\d{2}T", last):
    # 解析 ISO UTC，然後加 8 小時
    try:
        ts_utc = datetime.fromisoformat(last.replace("Z", "+00:00"))
    except ValueError:
        ts_utc = datetime.strptime(last, "%Y-%m-%dT%H:%M:%S.%fZ")
    ts_local = ts_utc + timedelta(hours=8)
    ts = ts_local.strftime("%Y-%m-%d %H:%M:%S")
    bp_list = raw[:-1]
else:
    # 如果沒有時間字串，就直接用現在 UTC+8
    ts = (datetime.utcnow() + timedelta(hours=8)).strftime("%Y-%m-%d %H:%M:%S")
    bp_list = raw

if not bp_list:
    print("⚠️ ranking_bp.json 無任何排行物件，跳過 DB 寫入及清空原檔")
else:
    # INSERT 語句多加了 rank_num
    insert_bp = """
      INSERT INTO `ranking_bp_history`
        (`ts`, `rank_num`, `name`, `level`, `bp`, `win_ranked`, `lose_ranked`, `draw_ranked`)
      VALUES (%s,     %s,        %s,     %s,    %s,   %s,           %s,            %s)
    """

    # enumerate 從 1 開始，i 就是我們要塞給 rank_num 的值
    for i, rec in enumerate(bp_list, start=1):
        if isinstance(rec, dict):
            name        = rec.get("name")
            level       = rec.get("level")
            bp          = rec.get("bp")
            win_ranked  = rec.get("win_ranked")
            lose_ranked = rec.get("lose_ranked")
            draw_ranked = rec.get("draw_ranked")
        else:
            # 如果遇到不是 dict（例如 null），全部欄位設為 None
            name = level = bp = win_ranked = lose_ranked = draw_ranked = None

        cursor.execute(insert_bp, (
            ts,
            i,                # rank_num
            name, level, bp,
            win_ranked, lose_ranked, draw_ranked
        ))

    db.commit()
    print(f"✅ 已插入 {len(bp_list)} 筆到 ranking_bp_history（含 rank_num）")
    clear_json(bp_path)



# ----------------------------------------------------------------
# 上傳 ranking_qp 並備份（修改後，帶入 rank_num）
# ----------------------------------------------------------------
qp_data, qp_path = backup_and_load("ranking_qp")

# 解析 JSON 內容
raw = qp_data if isinstance(qp_data, list) else qp_data.get("ranking", [])
last = raw[-1] if raw else None

if isinstance(last, str) and re.match(r"^\d{4}-\d{2}-\d{2}T", last):
    # 最後一筆是時間字串：解析成 UTC+0，再加 8 小時轉為台北時間
    try:
        ts_utc = datetime.fromisoformat(last.replace("Z", "+00:00"))
    except ValueError:
        ts_utc = datetime.strptime(last, "%Y-%m-%dT%H:%M:%S.%fZ")
    ts_local = ts_utc + timedelta(hours=8)
    ts = ts_local.strftime("%Y-%m-%d %H:%M:%S")
    qp_list = raw[:-1]
else:
    # 沒有時間字串時，直接用當下 UTC+8
    ts = (datetime.utcnow() + timedelta(hours=8)).strftime("%Y-%m-%d %H:%M:%S")
    qp_list = raw

if not qp_list:
    print("⚠️ ranking_qp.json 無任何排行物件，跳過 DB 寫入及清空原檔")
else:
    # INSERT 語句多了 rank_num 欄位
    insert_qp = """
      INSERT INTO `ranking_qp_history`
        (`ts`, `rank_num`, `name`, `level`, `qp`)
      VALUES (%s,     %s,         %s,      %s,    %s)
    """

    # 用 enumerate 產生從 1 開始的 i 當作 rank_num
    for i, rec in enumerate(qp_list, start=1):
        if isinstance(rec, dict):
            name  = rec.get("name")
            level = rec.get("level")
            qp    = rec.get("qp")
        else:
            # 如果碰到不是 dict（例如 null），全部設為 None
            name = level = qp = None

        cursor.execute(insert_qp, (
            ts,
            i,       # 這裡就是第幾名
            name, level, qp
        ))

    db.commit()
    print(f"✅ 已插入 {len(qp_list)} 筆到 ranking_qp_history（含 rank_num）")
    clear_json(qp_path)
