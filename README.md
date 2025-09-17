<p align="center">
  <img src="assets/banner.png" alt="Unlight 對戰紀錄系統 Banner" width="800"/>
</p>

# Unlight 對戰紀錄系統

這是一個用於 **Unlight 遊戲分析** 的專案，功能包含：

- 自動匯入對戰紀錄 (JSON)
- MySQL 資料庫儲存
- PHP + Bootstrap 前端顯示
- 排行榜、角色資料、卡片資訊

## 專案結構

```
unlight/
    icon/
        god.ico
    assets/
        style.css
        script.js
    steam/
        data/
            .env.example
            channel_watcher_unlight.py
            import_and_compare.py
            ranking_bp.json
            ranking_qp.json
    database/
        schema.sql
    bp/
    qp/
    README.md
    .gitignore
    config.php
    settings.php
    ranking_bp.php
    ranking_qp.php
    eventindex.php
    weapon.php
```

## 使用方法

1. 複製專案
   ```bash
   git clone git@github.com:Leway813/Unlight.git
建立資料庫

bash
複製程式碼
mysql -u <user> -p unlight < database/schema.sql
建立環境檔案

bash
複製程式碼
cp steam/data/.env.example steam/data/.env
啟動服務器，並開啟瀏覽器查看排行榜與對戰紀錄

yaml
複製程式碼

---
