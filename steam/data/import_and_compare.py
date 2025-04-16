import json
import mysql.connector
from pathlib import Path
from datetime import datetime
import shutil

try:
    conn = mysql.connector.connect(
        host="***.***.***.***",  # 或你的外部 IP //127.0.0.1
        user="user",
        password="password",
        database="database",
        port=port
    )
    print("✅ 成功連線到 MySQL")
    conn.close()
except mysql.connector.Error as err:
    print("❌ 連線失敗：", err)


# 資料庫連線設定
db = mysql.connector.connect(
    host="220.130.247.88",  # 或你的外部 IP //127.0.0.1
    user="root",
    password="Uve%12345",
    database="leway_db",
    port=3307
)
cursor = db.cursor(dictionary=True)

# JSON 路徑
#json_path = Path("channel1_room.json")
# JSON 路徑修正
json_path = Path(__file__).parent / "channel1_room.json"

# 建立 backup 資料夾並建立備份路徑
backup_dir = json_path.parent / "backup"
backup_dir.mkdir(exist_ok=True)  # 若不存在則建立

backup_filename = f"channel1_room_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
backup_path = backup_dir / backup_filename

shutil.copy(json_path, backup_path)
print(f"🗂 已備份原始 JSON 至：{backup_path}")

with open(json_path, "r", encoding="utf-8") as f:
    data_list = json.load(f)

inserted = 0
skipped = 0
updated = 0
logs = []

def update_previous_match(name, win_now, draw_now, lose_now, room_id):
    cursor.execute("""
        SELECT * FROM arena_unlight
        WHERE (name_p1 = %s OR name_p2 = %s)
          AND room_id != %s AND (ack1 = 0 OR ack2 = 0)
        ORDER BY update_time DESC LIMIT 1
    """, (name, name, room_id))
    prev = cursor.fetchone()
    if not prev:
        return None

    dw = win_now - (prev['win_p1'] if prev['name_p1'] == name else prev['win_p2'])
    dl = lose_now - (prev['lose_p1'] if prev['name_p1'] == name else prev['lose_p2'])
    dd = draw_now - (prev['draw_p1'] if prev['name_p1'] == name else prev['draw_p2'])

    if dw >= 1 and dl == 0 and dd == 0:
        if prev['name_p2'] == name:
            cursor.execute("UPDATE arena_unlight SET win=1, ack1=1, ack2=1 WHERE id=%s", (prev['id'],))
            result = f"✅ 更新前一筆（{name}(勝) VS {prev['name_p1']} ID {prev['id']}）"
        else:
            cursor.execute("UPDATE arena_unlight SET lose=1, ack1=1, ack2=1 WHERE id=%s", (prev['id'],))
            result = f"✅ 更新前一筆（{name}(勝) VS {prev['name_p2']} ID {prev['id']}）"
    elif dl >= 1 and dw == 0 and dd == 0:
        if prev['name_p2'] == name:
            cursor.execute("UPDATE arena_unlight SET lose=1, ack1=1, ack2=1 WHERE id=%s", (prev['id'],))
            result = f"✅ 更新前一筆（{name}(負) VS {prev['name_p1']} ID {prev['id']}）"
        else:
            cursor.execute("UPDATE arena_unlight SET win=1, ack1=1, ack2=1 WHERE id=%s", (prev['id'],))
            result = f"✅ 更新前一筆（{name}(負) VS {prev['name_p2']} ID {prev['id']}）"
    elif dd >= 1 and dw == 0 and dl == 0:
        cursor.execute("UPDATE arena_unlight SET tie=1, ack1=1, ack2=1 WHERE id=%s", (prev['id'],))
        result = f"✅ 更新前一筆平手（{name} VS {prev['name_p1'] if prev['name_p2'] == name else prev['name_p2']} ID {prev['id']}）"
    else:
        if prev['name_p1'] == name:
            cursor.execute("UPDATE arena_unlight SET ack1=1 WHERE id=%s", (prev['id'],))
            result = f"⚠️ 無法判斷勝負（{name} VS {prev['name_p2']} ID {prev['id']}），已標記該紀錄失效"
        else:
            cursor.execute("UPDATE arena_unlight SET ack2=1 WHERE id=%s", (prev['id'],))
            result = f"⚠️ 無法判斷勝負（{name} VS {prev['name_p1']} ID {prev['id']}），已標記該紀錄失效"

    db.commit()
    return result

for room in data_list.values():
    room_id = room.get("room_id")
    cursor.execute("SELECT COUNT(*) as c FROM arena_unlight WHERE room_id=%s", (room_id,))
    if cursor.fetchone()['c'] > 0:
        skipped += 1
        continue

    update_time = datetime.fromtimestamp(room.get("date", 0) / 1000).strftime('%Y-%m-%d %H:%M:%S')
    nameA = room.get("playerA", {}).get("name", "")
    bpA = room.get("playerA", {}).get("bp", 0)
    winA = room.get("playerA", {}).get("win", 0)
    drawA = room.get("playerA", {}).get("draw", 0)
    loseA = room.get("playerA", {}).get("lose", 0)

    nameB = room.get("playerB", {}).get("name", "")
    bpB = room.get("playerB", {}).get("bp", 0)
    winB = room.get("playerB", {}).get("win", 0)
    drawB = room.get("playerB", {}).get("draw", 0)
    loseB = room.get("playerB", {}).get("lose", 0)

    e = [x + 1 for x in room.get("deckA", {}).get("charaIndex", [-1, -1, -1])]
    u = [x + 1 for x in room.get("deckB", {}).get("charaIndex", [-1, -1, -1])]

    cursor.execute("""
        INSERT INTO arena_unlight (
            room_id, name_p1, bp_p1, win_p1, draw_p1, lose_p1, e1, e2, e3,
            name_p2, bp_p2, win_p2, draw_p2, lose_p2, u1, u2, u3,
            update_time, username, ack1, ack2
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s,
                  %s, %s, %s, %s, %s, %s, %s, %s,
                  %s, %s, 0, 0)
    """, (
        room_id, nameA, bpA, winA, drawA, loseA, *e,
        nameB, bpB, winB, drawB, loseB, *u,
        update_time, "way.lee_py"
    ))
    inserted += 1

    logA = update_previous_match(nameA, winA, drawA, loseA, room_id)
    logB = update_previous_match(nameB, winB, drawB, loseB, room_id)
    if logA:
        logs.append(logA)
        updated += 1
    if logB:
        logs.append(logB)
        updated += 1

db.commit()
cursor.close()
db.close()

# 清空 JSON 檔
with open(json_path, "w", encoding="utf-8") as f:
    json.dump({}, f, indent=4, ensure_ascii=False)

print(f"\n✅ 匯入完成：新增 {inserted} 筆，跳過 {skipped} 筆，更新 {updated} 筆")
for log in logs:
    print(log)
print("🧹 已清空 channel1_room.json")

