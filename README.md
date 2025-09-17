# Unlight 對戰紀錄系統

這是一個用於 **Unlight 遊戲分析** 的專案，功能包含：
- 自動匯入對戰紀錄 (JSON)
- MySQL 資料庫儲存
- PHP + Bootstrap 前端顯示
- 排行榜、角色資料、卡片資訊

---

## 專案結構
- `steam/data/`：資料匯入腳本與 JSON 檔案
- `arena_unlight`：資料庫主要表格
- `ranking_bp.php` / `ranking_qp.php`：積分排行
- `training.php`：角色訓練數據
- `.env`：環境設定檔 (不進 Git，請參考 `.env.example`)
- `database/schema.sql`：完整資料庫結構

---

## 使用方法

### 1. 複製專案
```bash
git clone git@github.com:Leway813/Unlight.git
cd Unlight
