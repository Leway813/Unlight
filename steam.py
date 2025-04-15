# pip install python-socketio[asyncio_client]  # 安裝非同步 Socket.IO 客戶端套件


from pathlib import Path  # 導入處理檔案路徑的模組
import json  # 導入處理 JSON 資料的模組
import asyncio  # 導入非同步程式設計的模組
import logging  # 導入日誌記錄功能
import time  # 導入時間處理功能

import socketio  # 導入 Socket.IO 客戶端，用於即時通訊

# 設定日誌
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


data_dir = Path("steam/data")
#data_dir = Path("")  # 設定資料儲存目錄，目前設為當前目錄


class ChannelWatcher:
    ENDPOINT = "https://www.playunlight.online:11012"  # 連接的伺服器端點
    EXPORT_PERIOD = 5  # 資料匯出的時間間隔，單位為秒
    RECONNECT_DELAY = 3  # 重新連接延遲，單位為秒
    MAX_RETRY_COUNT = 5  # 最大重試次數，達到後會增加延遲

    def __init__(self, channel_id: int):
        self.channel_id = channel_id  # 初始化要監控的頻道 ID
        self.sio = socketio.AsyncClient(reconnection=True, reconnection_attempts=10)  # 啟用自動重連
        self.running = True  # 控制是否繼續運行
        self.connected = False  # 連接狀態標記
        self.retry_count = 0  # 重試計數器
        
        # 註冊事件處理函數
        self.sio.on(f"channel{channel_id}_room", self.channel_rooms)
        self.sio.on("connect", self.on_connect)
        self.sio.on("disconnect", self.on_disconnect)
        self.sio.on("connect_error", self.on_connect_error)
        
        # 設定資料儲存路徑
        self.data = data_dir / f"channel{channel_id}_room.json"
        
        # 如果資料檔案不存在，建立一個空的 JSON 檔案
        if not self.data.exists():
            self.data.parent.mkdir(parents=True, exist_ok=True)  # 確保目錄存在
            with self.data.open("w", encoding="utf-8") as f:
                json.dump({}, f)  # 寫入空的 JSON 物件
        
        # 讀取現有的資料檔案
        with self.data.open("r", encoding="utf-8") as f:
            self.channel_data = json.load(f)

    async def on_connect(self):
        # 連接成功時的處理
        logger.info(f"Connected to server, channel {self.channel_id}")
        self.connected = True
        self.retry_count = 0
        # 連接成功後發送頻道進入事件
        try:
            await self.sio.emit("channel_in", self.channel_id)
            logger.info(f"Sent channel_in event for channel {self.channel_id}")
        except Exception as e:
            logger.error(f"Error sending channel_in event: {str(e)}")

    async def on_disconnect(self):
        # 連接斷開時的處理
        logger.warning(f"Disconnected from server, channel {self.channel_id}")
        self.connected = False

    async def on_connect_error(self, error):
        # 連接錯誤時的處理
        logger.error(f"Connection error: {str(error)}")
        self.connected = False

    async def channel_rooms(self, rooms):
        # 當接收到房間資訊時，更新記錄的資料
        try:
            logger.debug(f"Received room data for channel {self.channel_id}, rooms count: {len(rooms)}")
            # 使用 room_id 作為鍵，整個 room 物件作為值
            self.channel_data.update({room["room_id"]: room for room in rooms})
        except Exception as e:
            logger.error(f"Error processing room data: {str(e)}")

    async def connect_with_retry(self):
        # 實現帶有重試機制的連接方法
        while self.running:
            try:
                if not self.connected:
                    delay = self.RECONNECT_DELAY
                    if self.retry_count >= self.MAX_RETRY_COUNT:
                        # 增加重連延遲以避免過度請求
                        delay = self.RECONNECT_DELAY * (1 + (self.retry_count - self.MAX_RETRY_COUNT) // 2)
                        delay = min(delay, 60)  # 設定最大延遲為60秒
                    
                    logger.info(f"Attempting to connect (retry #{self.retry_count})...")
                    await self.sio.connect(self.ENDPOINT)
                    # 連接事件處理程式會處理連接成功的情況
                    self.retry_count += 1
                    
                return  # 連接成功時返回
            except socketio.exceptions.ConnectionError as e:
                logger.error(f"Failed to connect: {str(e)}")
                await asyncio.sleep(delay)
            except Exception as e:
                logger.error(f"Unexpected error during connection: {str(e)}")
                await asyncio.sleep(delay)

    async def start(self):
        # 啟動監控的主要方法
        try:
            # 建立定期匯出資料的任務
            export_task = asyncio.create_task(self.export())
            
            # 首次連接
            await self.connect_with_retry()
            
            # 主循環，處理可能的連接中斷
            while self.running:
                try:
                    await asyncio.sleep(1)  # 輕量檢查
                    if not self.connected and self.running:
                        await self.connect_with_retry()
                except Exception as e:
                    # 捕捉循環中的任何錯誤，避免整個程式崩潰
                    logger.error(f"Error in main loop: {str(e)}")
                    await asyncio.sleep(self.RECONNECT_DELAY)
            
            # 停止運行時，取消匯出任務
            export_task.cancel()
            
        except Exception as e:
            logger.error(f"Fatal error in start method: {str(e)}")
        finally:
            # 確保在任何情況下都嘗試斷開連接
            if self.connected:
                await self.sio.disconnect()
                logger.info("Disconnected from server")

    async def export(self):
        # 定期將記錄的資料匯出到檔案
        while self.running:
            try:
                await asyncio.sleep(self.EXPORT_PERIOD)  # 等待指定的時間
                # 將目前的資料寫入檔案，使用美化格式和支援中文字元
                with self.data.open("w", encoding="utf-8") as f:
                    json.dump(self.channel_data, f, indent=4, ensure_ascii=False)
                logger.debug(f"Data exported for channel {self.channel_id}")
            except asyncio.CancelledError:
                logger.info("Export task cancelled")
                break
            except Exception as e:
                logger.error(f"Error exporting data: {str(e)}")
                # 即使匯出失敗也要繼續嘗試

    async def stop(self):
        # 提供一個方法來停止監控
        self.running = False
        if self.connected:
            await self.sio.disconnect()


async def main():
    # 要監控的頻道 ID 列表，可以根據需要修改
    channel_ids = [1]  # 替換為您實際要監控的頻道 ID
    # 為每個頻道建立監控器
    watchers = [ChannelWatcher(channel_id) for channel_id in channel_ids]
    
    try:
        # 同時啟動所有監控器並等待它們完成
        await asyncio.gather(*(watcher.start() for watcher in watchers))
    except KeyboardInterrupt:
        logger.info("Program interrupted by user")
        # 優雅地停止所有監控器
        for watcher in watchers:
            await watcher.stop()
    except Exception as e:
        logger.error(f"Unexpected error in main: {str(e)}")
    finally:
        logger.info("Program terminated")


if __name__ == "__main__":
    # 程式入口點，運行主函數
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nProgram interrupted by user")
    except Exception as e:
        print(f"Fatal error: {str(e)}")