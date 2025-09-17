# channel_watcher.py
# pip install websockets
from pathlib import Path
from json.decoder import JSONDecodeError
import json
import asyncio
import logging
import sys
import time

from websockets.asyncio.client import connect, ClientConnection
from websockets.exceptions import ConnectionClosed
from urllib.parse import urlparse

logging.basicConfig(
    level=logging.WARNING,
    format="%(asctime)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger(__name__)

data_dir = Path(__file__).parent


class BaseWatcher:
    EXPORT_PERIOD: int
    ws: ClientConnection

    def __init__(self, endpoint: str):
        self.endpoint = endpoint
        self.msg_idx = 0
        self.acks = []

    async def emit_event(self, event: str, args: list):
        now = int(time.time() * 1000)
        msg_id = f"{event}:{self.msg_idx}:{now}"
        self.msg_idx += 1
        if event in {"__pong_s", "__ping_c"}:
            args = [now]
        msg = json.dumps(
            {
                "meta": {"id": msg_id, "ACKs": self.acks},
                "event": event,
                "args": args,
            }
        )
        self.acks = []
        logger.info(msg)
        await self.ws.send(msg)
    
    async def check_msg(self, data):
        logger.info(data)
        meta = data.get("meta", {})
        msg_id = meta.get("id", "::")
        evt, msg_idx, timestamp = msg_id.split(":")
        if evt == "__ping_s":
            await self.emit_event("__pong_s", [])
        elif evt == "__pong_c":
            pass
        elif evt == "__handshake_s":
            await self.emit_event("__handshake_c", ["1.1.2", None])
        elif evt == "__connected":
            pass
        else:
            if not evt.startswith("__"):
                self.acks.append(msg_id)
            return evt


class ChannelWatcher(BaseWatcher):
    """
    監聽一般頻道 (channel_in) 事件，如 channel1_room
    """

    EXPORT_PERIOD = 10  # seconds

    def __init__(
        self, channel_id: int, endpoint: str = "wss://www.playunlight.online:11012"
    ):
        super().__init__(endpoint)
        self.channel_id = channel_id

        parsed = urlparse(self.endpoint)
        host = parsed.hostname or ""
        port = parsed.port or "default"

        # 精簡主機標籤：playunlight.online => OL，playunlight-dmm.com => JP
        if "dmm" in host:
            host_code = "JP"
        else:
            host_code = "TW"

        self.data_file = (
            data_dir / f"channel{self.channel_id}_room_{host_code}_{port}.json"
        )

        if not self.data_file.exists() or self.data_file.stat().st_size == 0:
            self.data_file.parent.mkdir(parents=True, exist_ok=True)
            with self.data_file.open("w", encoding="utf-8") as f:
                json.dump({}, f, ensure_ascii=False, indent=4)
            self.channel_data = {}
        else:
            try:
                with self.data_file.open("r", encoding="utf-8") as f:
                    self.channel_data = json.load(f)
            except JSONDecodeError:
                print(
                    f"[Ch{self.channel_id}] WARNING: 無法解析 {self.data_file.name}，初始化為空資料"
                )
                self.channel_data = {}

    async def prepare(self):
        print(f"[Ch{self.channel_id}] 連線中 → {self.endpoint}")
        self.ws = await connect(self.endpoint)
        print(f"[Ch{self.channel_id}] 已連線成功")
        await self.emit_event("channel_in", [self.channel_id])
        print(f"[Ch{self.channel_id}] 已送出 channel_in 訂閱")

    def write_room_to_file(self):
        with self.data_file.open("w", encoding="utf-8") as f:
            json.dump(self.channel_data, f, indent=4, ensure_ascii=False)

    async def handle_event(self, rooms):
        updated = False
        for room in rooms:
            room_id = room["room_id"]
            if room_id not in self.channel_data or self.channel_data[room_id] != room:
                self.channel_data[room_id] = room
                updated = True
        if updated:
            self.write_room_to_file()

    async def ping(self):
        while True:
            await asyncio.sleep(30)
            try:
                await self.emit_event("__ping_c", [])
            except Exception as e:
                print(f"[Ch{self.channel_id}] Ping error: {e}")
                break

    async def listen(self):
        async for message in self.ws:
            try:
                data = json.loads(message)
                evt = await self.check_msg(data)
                if evt == f"channel{self.channel_id}_room":
                    await self.handle_event(data.get("args", [])[0])
            except ConnectionClosed:
                print(f"[Ch{self.channel_id}] 伺服器連線已關閉")
                break
            except Exception as e:
                print(f"[Ch{self.channel_id}] 處理訊息錯誤: {e}")
                break

    async def export(self):
        while True:
            await asyncio.sleep(self.EXPORT_PERIOD)
            if self.channel_data:
                with self.data_file.open("w", encoding="utf-8") as f:
                    json.dump(self.channel_data, f, indent=4, ensure_ascii=False)

    async def start(self):
        retry = 0
        while True:
            ping = None
            exp = None
            try:
                await self.prepare()
                ping = asyncio.create_task(self.ping())
                exp = asyncio.create_task(self.export())
                await self.listen()
            except Exception as e:
                retry += 1
                print(f"[Ch{self.channel_id}] 連線中斷 {e}，第 {retry} 次嘗試")
                if ping:
                    ping.cancel()
                if exp:
                    exp.cancel()
                await asyncio.sleep(5)


class RankingWatcher(BaseWatcher):
    """
    監聽 ranking 資訊 (BP/QP)，連線到 11007
    事件: ranking_bp, ranking_qp
    """

    EXPORT_PERIOD = 30

    def __init__(
        self,
        bp_file_name="ranking_bp.json",
        qp_file_name="ranking_qp.json",
        endpoint: str = "wss://www.playunlight.online:11007/",
        event_prefix="",
    ):
        super().__init__(endpoint)
        self.event_prefix = event_prefix
        self.bp_file = data_dir / bp_file_name
        self.qp_file = data_dir / qp_file_name

        for f in (self.bp_file, self.qp_file):
            if not f.exists():
                f.parent.mkdir(parents=True, exist_ok=True)
                with f.open("w", encoding="utf-8") as jf:
                    json.dump({}, jf)

        with self.bp_file.open("r", encoding="utf-8") as bf:
            self.bp_data = json.load(bf)
        with self.qp_file.open("r", encoding="utf-8") as qf:
            self.qp_data = json.load(qf)

    async def prepare(self):
        print(f"[Ranking:{self.event_prefix or 'default'}] 連線中 → {self.endpoint}")
        self.ws = await connect(self.endpoint)
        print(f"[Ranking:{self.event_prefix or 'default'}] 已連線成功")
        await self.emit_event(f"{self.event_prefix}ranking_bp", [])
        await self.emit_event(f"{self.event_prefix}ranking_qp", [])

    async def ping(self):
        while True:
            await asyncio.sleep(30)
            try:
                await self.emit_event("__ping_c", [])
            except:
                break

    async def listen(self):
        async for message in self.ws:
            try:
                data = json.loads(message)
                evt = await self.check_msg(data)
                if evt == f"{self.event_prefix}ranking_bp":
                    self.bp_data = data.get("args", [])[0]
                elif evt == f"{self.event_prefix}ranking_qp":
                    self.qp_data = data.get("args", [])[0]
            except ConnectionClosed:
                print(f"[Ranking:{self.event_prefix or 'default'}] 伺服器已關閉")
                break
            except Exception as e:
                print(f"[Ranking:{self.event_prefix or 'default'}] 處理錯誤: {e}")
                break

    async def export(self):
        while True:
            await asyncio.sleep(self.EXPORT_PERIOD)
            with self.bp_file.open("w", encoding="utf-8") as bf:
                json.dump(self.bp_data, bf, indent=4, ensure_ascii=False)
            with self.qp_file.open("w", encoding="utf-8") as qf:
                json.dump(self.qp_data, qf, indent=4, ensure_ascii=False)

    async def request_loop(self):
        while True:
            await asyncio.sleep(self.EXPORT_PERIOD)
            await self.emit_event(f"{self.event_prefix}ranking_bp", [])
            await self.emit_event(f"{self.event_prefix}ranking_qp", [])

    async def start(self):
        retry = 0
        while True:
            try:
                await self.prepare()
                ping_task = asyncio.create_task(self.ping())
                export_task = asyncio.create_task(self.export())
                request_task = asyncio.create_task(self.request_loop())
                await self.listen()
            except Exception as e:
                retry += 1
                print(
                    f"[Ranking:{self.event_prefix or 'default'}] 連線中斷 {e}，第 {retry} 次重連"
                )
                for t in (ping_task, export_task, request_task):
                    t.cancel()
                await asyncio.sleep(5)


async def merge_room_data(channel_id: int, output_file: Path, interval: int = 30):
    """
    定期合併 channel_id 的 TW/JP 資料，寫入 output_file
    """
    input_files = list(data_dir.glob(f"channel{channel_id}_room_*.json"))
    if not input_files:
        print(f"[MERGE] 沒有找到 channel{channel_id} 的資料檔案")
        return

    while True:
        merged = {}
        for f in input_files:
            try:
                with f.open("r", encoding="utf-8") as jf:
                    data = json.load(jf)
                    merged.update(data)
            except Exception as e:
                print(f"[MERGE] 無法讀取 {f.name}: {e}")
        with output_file.open("w", encoding="utf-8") as out:
            json.dump(merged, out, indent=4, ensure_ascii=False)
        await asyncio.sleep(interval)


async def main():
    watchers = []
    for ch_id in (1, 3):
        watchers.append(ChannelWatcher(ch_id))
    for ch_id in (1, 3):
        watchers.append(
            ChannelWatcher(ch_id, endpoint="wss://www.playunlight-dmm.com:11012")
        )

    watchers.append(RankingWatcher())
    watchers.append(
        RankingWatcher(
            bp_file_name="ranking_bp_JP.json",
            qp_file_name="ranking_qp_JP.json",
            endpoint="wss://www.playunlight-dmm.com:11007/",
            event_prefix="",
        )
    )

    # 合併任務清單
    merge_tasks = [
        asyncio.create_task(
            merge_room_data(
                channel_id=1, output_file=data_dir / "channel1_room.json", interval=30
            )
        ),
        asyncio.create_task(
            merge_room_data(
                channel_id=3, output_file=data_dir / "channel3_room.json", interval=30
            )
        ),
    ]

    # 將 watcher 任務 + merge 任務全數合併等待
    await asyncio.gather(*(w.start() for w in watchers), *merge_tasks)


if __name__ == "__main__":
    logging.getLogger("asyncio").setLevel(logging.ERROR)
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("程式中止 by user")
        sys.exit(0)
