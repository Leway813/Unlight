<!DOCTYPE html>

<?php
// 其他程式碼……
require_once('head.php');
$date = date("Y-m-d H:i:s");
session_start();
/* 確保 upload_token 是唯一的，並且在每次載入 fight.php 時都存在。 */
if (!isset($_SESSION['upload_token'])) {
  $_SESSION['upload_token'] = bin2hex(random_bytes(32)); // 產生隨機 Token
}
$upload_token = $_SESSION['upload_token']; // 取得 Token

if ($_SESSION["username"] == 'way.lee') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  /*   print '<pre>';
  var_dump($_POST);
  var_dump($_SESSION);
  print '</pre>'; */
}
$username = $_SESSION["username"];
$ack = $_SESSION["ack"] ?? 0;




function progress_color($rate)
{
  if ($rate < 25) {
    $color = 'red';
  } elseif ($rate < 50) {
    $color = 'yellow';
  } elseif ($rate < 75) {
    $color = 'green';
  } else {
    $color = 'aqua';
  }
  return $color;
}
function all_equal($x, $y, $z)
{
  if ($x === $y || $y === $z || $x === $z) {
    return 1;
  }
}

function cost_punish(int $id1_cost, int $id2_cost, int $id3_cost)
{
  $punish = 0;
  $dif_a = abs($id1_cost - $id2_cost);
  $dif_b = abs($id1_cost - $id3_cost);
  $dif_c = abs($id2_cost - $id3_cost);
  if ($dif_a > 6) {
    $punish += 5;
    if ($dif_a > 13) {
      $punish += 5;
    }
  }
  if ($dif_b > 6) {
    $punish += 5;
    if ($dif_b > 13) {
      $punish += 5;
    }
  }
  if ($dif_c > 6) {
    $punish += 5;
    if ($dif_c > 13) {
      $punish += 5;
    }
  }

  return $punish;
}


$i = 0;
$count = 0;
$same_ch_flag = 0;
//print "<br>same_ch_flag0=$same_ch_flag";
if (isset($_POST["enemy"]) && $_POST["enemy"]) {

  $_SESSION["combined"] = 0;
  $combined = 0;
  for ($i = 0; $i < 3; $i++) {
    $j = $i + 1;
    ${"e" . $j} = $_POST['ch'][$i] ?? 0;
  }
  $_SESSION["e1"] = $e1;
  $_SESSION["e2"] = $e2;
  $_SESSION["e3"] = $e3;
  $x = floor(($e1 - 1) / 10);
  $y = floor(($e2 - 1) / 10);
  $z = floor(($e3 - 1) / 10);
  $same_ch_flag += all_equal($x, $y, $z);
  //print "<br>same_ch_flag1=$same_ch_flag";
}
$show_all = $_SESSION["show_all"] ?? 0;
if (isset($_POST["show_all"])) {
  $_SESSION["show_all"] = 1;
  $show_all = 1;
} elseif (isset($_POST["show_part"])) {
  $_SESSION["show_all"] = 0;
  $show_all = 0;
}

// 如果 POST 有 combined，則設定 session
if (isset($_POST["combined"])) {
  $_SESSION["combined"] = $_POST["combined"];
}
// 從 session 取得 combined，如果不存在則預設為 false
$combined = $_SESSION["combined"] ?? false;

if (isset($_POST["set_team"])) {
  $set_team = $_POST["set_team"];

  if ($set_team == "組合1") {
    $_SESSION["e1"] = $_POST["e1"];
    $_SESSION["e2"] = $_POST["e2"];
    $_SESSION["e3"] = $_POST["e3"];
  } elseif ($set_team == "組合2") {
    $_SESSION["u1"] = $_POST["u1"];
    $_SESSION["u2"] = $_POST["u2"];
    $_SESSION["u3"] = $_POST["u3"];
  }
}

if ($show_all) {
  $sql = "SELECT * FROM leway_db.unlight where ico <> '' and id > 0;";
} else {
  $sql = "SELECT * FROM leway_db.use_rate_unlight where count_ttl >(SELECT sum(count_ttl)/800 FROM leway_db.use_rate_unlight) and id>0 order by id;";
}

if (isset($_POST['upload_json'])) {
  $json_str = $_POST['ws_json'];
  $dataList = json_decode($json_str, true);

  $logs = [];
  $updated = 0;
  $total = count($dataList);

  function updatePreviousMatchResult($db, $name, $winNow, $drawNow, $loseNow, $room_id)
  {
    $stmt_prev = $db->prepare("SELECT * FROM arena_unlight WHERE (name_p1 = :name OR name_p2 = :name) AND room_id != :room_id AND (ack1 = 0 OR ack2 = 0) ORDER BY update_time DESC LIMIT 1");
    $stmt_prev->execute([':name' => $name, ':room_id' => $room_id]);
    $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);

    if (!$prev) return null;

    $log = '';
    $dw = $winNow - ($prev['name_p1'] === $name ? $prev['win_p1'] : $prev['win_p2']);
    $dl = $loseNow - ($prev['name_p1'] === $name ? $prev['lose_p1'] : $prev['lose_p2']);
    $dd = $drawNow - ($prev['name_p1'] === $name ? $prev['draw_p1'] : $prev['draw_p2']);

    if ($dw >= 1 && $dl === 0 && $dd === 0) {
      if ($prev['name_p2'] === $name) {
        $db->prepare("UPDATE arena_unlight SET win=1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆（$name(勝) VS {$prev['name_p1']} ID {$prev['id']}）";
      } else {
        $db->prepare("UPDATE arena_unlight SET lose=1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆（$name(勝) VS {$prev['name_p2']} ID {$prev['id']}）";
      }
    } elseif ($dw === 0 && $dl >= 1 && $dd === 0) {
      if ($prev['name_p2'] === $name) {
        $db->prepare("UPDATE arena_unlight SET lose = 1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆（$name(負) VS {$prev['name_p1']} ID {$prev['id']}）";
      } else {
        $db->prepare("UPDATE arena_unlight SET win = 1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆（$name(負) VS {$prev['name_p2']} ID {$prev['id']}）";
      }
    } elseif ($dd >= 1 && $dw === 0 && $dl === 0) {
      if ($prev['name_p2'] === $name) {
        $db->prepare("UPDATE arena_unlight SET tie=1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆平手（$name VS {$prev['name_p1']} ID {$prev['id']}）";
      } else {
        $db->prepare("UPDATE arena_unlight SET tie=1,ack1 = 1,ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "✅ 更新前一筆平手（$name VS {$prev['name_p2']} ID {$prev['id']}）";
      }
    } else {
      if ($prev['name_p1'] === $name) {
        $db->prepare("UPDATE arena_unlight SET ack1 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "⚠️ 無法判斷勝負（$name VS {$prev['name_p2']} ID {$prev['id']}），已標記該紀錄失效";
      } else {
        $db->prepare("UPDATE arena_unlight SET ack2 = 1 WHERE id = :id")->execute([':id' => $prev['id']]);
        $log = "⚠️ 無法判斷勝負（$name VS {$prev['name_p1']} ID {$prev['id']}），已標記該紀錄失效";
      }
    }

    return $log;
  }

  if (json_last_error() === JSON_ERROR_NONE && is_array($dataList)) {
    $short_room_id = '';
    $count_short_room = 0;
    foreach ($dataList as $data) {
      $room_id = $data['room_id'] ?? '';
      //print "room_id=$room_id";
      $date = $data['date'] ?? 0;
      $datetime = date("Y-m-d H:i:s", $date / 1000);

      // 防止重複上傳
      $stmt_check = $db->prepare("SELECT COUNT(*) FROM arena_unlight WHERE room_id = :room_id");
      $stmt_check->execute([':room_id' => $room_id]);
      $exists = $stmt_check->fetchColumn();

      if ($exists > 0) {
        $short_room_id .= substr($room_id, 0, 3) . '..';
        $count_short_room++;
        continue;
      }

      // Player A
      $nameA = $data['playerA']['name'] ?? '';
      $bpA = $data['playerA']['bp'] ?? 0;
      $winA = $data['playerA']['win'] ?? 0;
      $drawA = $data['playerA']['draw'] ?? 0;
      $loseA = $data['playerA']['lose'] ?? 0;
      $e1 = ($data['deckA']['charaIndex'][0] ?? -1) + 1;
      $e2 = ($data['deckA']['charaIndex'][1] ?? -1) + 1;
      $e3 = ($data['deckA']['charaIndex'][2] ?? -1) + 1;

      // Player B
      $nameB = $data['playerB']['name'] ?? '';
      $bpB = $data['playerB']['bp'] ?? 0;
      $winB = $data['playerB']['win'] ?? 0;
      $drawB = $data['playerB']['draw'] ?? 0;
      $loseB = $data['playerB']['lose'] ?? 0;
      $u1 = ($data['deckB']['charaIndex'][0] ?? -1) + 1;
      $u2 = ($data['deckB']['charaIndex'][1] ?? -1) + 1;
      $u3 = ($data['deckB']['charaIndex'][2] ?? -1) + 1;

      // 資料庫插入
      $sql = "INSERT INTO arena_unlight 
              (room_id, name_p1, bp_p1, win_p1, draw_p1, lose_p1, e1, e2, e3, 
               name_p2, bp_p2, win_p2, draw_p2, lose_p2, u1, u2, u3, update_time, username, ack1, ack2)
              VALUES 
              (:room_id, :name_p1, :bp_p1, :win_p1, :draw_p1, :lose_p1, :e1, :e2, :e3,
               :name_p2, :bp_p2, :win_p2, :draw_p2, :lose_p2, :u1, :u2, :u3, :update_time, :username, :ack1, :ack2)";

      $stmt_insert = $db->prepare($sql);

      try {
        $success = $stmt_insert->execute([
          ':room_id' => $room_id,
          ':name_p1' => $nameA,
          ':bp_p1' => $bpA,
          ':win_p1' => $winA,
          ':draw_p1' => $drawA,
          ':lose_p1' => $loseA,
          ':e1' => $e1,
          ':e2' => $e2,
          ':e3' => $e3,
          ':name_p2' => $nameB,
          ':bp_p2' => $bpB,
          ':win_p2' => $winB,
          ':draw_p2' => $drawB,
          ':lose_p2' => $loseB,
          ':u1' => $u1,
          ':u2' => $u2,
          ':u3' => $u3,
          ':update_time' => $datetime,
          ':username' => $username ?? '',
          ':ack1' => 0,
          ':ack2' => 0
        ]);

        if ($success) {
          echo "<div class='alert alert-success'>✅ 上傳成功！$nameA VS $nameB Room ID: $room_id</div>";
        } else {
          echo "<div class='alert alert-danger'>❌ 插入失敗 ($nameA VS $nameB Room ID: $room_id)</div>";
          print_r($stmt_insert->errorInfo());
        }
      } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ 發生例外錯誤: " . $e->getMessage() . "</div>";
      }

      $logA = updatePreviousMatchResult($db, $nameA, $winA, $drawA, $loseA, $room_id);
      $logB = updatePreviousMatchResult($db, $nameB, $winB, $drawB, $loseB, $room_id);
      if ($logA) {
        $logs[] = $logA;
        $updated++;
      }
      if ($logB) {
        $logs[] = $logB;
        $updated++;
      }

      $logs[] = "📌 新增資料：$datetime $nameA vs $nameB";
    }

    echo "<div class='alert alert-warning'>⚠️ 已存在 $count_short_room 間 (Room ID: $short_room_id)</div>";
  } else {
    echo "<div class='alert alert-danger'>❌ JSON 格式錯誤或不是陣列</div>";
  }

  foreach ($logs as $log) echo "<div class='alert alert-info'>$log</div>";
  echo "<div class='alert alert-success'>📦 本次上傳共處理 $total 筆資料（更新 $updated 筆）</div>";
}





?>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unlight</title>

  <!-- <script>
        var divs= $("input[name='skill[]']:checked").map(function() { return $(this).val(); }).get();
    </script> -->
  <script>
    function clearAll(ch) {
      var myCheckBox = document.getElementsByTagName('input');
      for (var i = 0; i < myCheckBox.length; i++) {
        if (myCheckBox[i].type == "checkbox") {
          myCheckBox[i].checked = false;
        }
      }
    }
  </script>

  <style>
    /* 回到頂部按鈕 */
    #backToTop,
    #goToBottom {
      position: fixed;
      right: 30px;
      width: 50px;
      height: 50px;
      background-color: #007bff;
      color: white;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: none;
      /* 預設隱藏 */
      justify-content: center;
      align-items: center;
      font-size: 18px;
      box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
      transition: opacity 0.3s ease;
      z-index: 999;
      /* 確保按鈕在最上層 */
    }

    /* 回到頂部按鈕 (▲) */
    #backToTop {
      bottom: 90px;
    }

    /* 滾到底部按鈕 (▼) */
    #goToBottom {
      bottom: 30px;
    }

    #backToTop:hover,
    #goToBottom:hover {
      background-color: #0056b3;
    }


    /* ✅ 浮動按鈕 */
    #historyBtn {
      position: fixed;
      right: 32px;
      bottom: 110px;
      transform: translateY(50%);
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 16px;
      cursor: pointer;
      box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
      transition: background-color 0.3s ease, transform 0.3s ease;
      z-index: 999;
      /* 確保在最上層 */
    }

    /* ✅ 滑鼠移上去時改變顏色 */
    #historyBtn:hover {
      background-color: #218838;
    }

    /* ✅ 讓按鈕固定在「頂部按鈕」與「底部按鈕」之間 */
    @media (max-width: 768px) {
      #historyBtn {
        right: 15px;
        padding: 10px 14px;
        font-size: 14px;
      }
    }




    .fight-form {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    /* 對戰區塊 */
    .fight-container {
      display: flex;
      align-items: center;
      gap: 20px;
      justify-content: center;
      margin: 10px 0px;
      padding: 3px 5px;
      border-radius: 6px;
    }

    /* ✅ 偶數行灰色 */
    .gray-bg {
      background-color: rgba(181, 181, 181, 0.51);
    }

    /* ✅ 奇數行白色 */
    .white-bg {
      background-color: #ffffff;
    }

    /* 組合1 & 組合2組合 */
    .team {
      display: flex;
      /* flex-direction: column; */
      align-items: center;
      gap: 10px;
    }

    /* 角色卡片排列成一行 */
    .characters {
      display: flex;
      flex-direction: row;
      gap: 10px;
    }

    /* VS 置中 */
    .vs {
      font-size: 24px;
      font-weight: bold;
      /* padding: 10px; */
    }

    .timestamp {
      font-size: 12px;
      background-color: #f8f9fa;
      padding: 0px 6px;
      margin: 3px 0px;
      border-radius: 5px;
      display: inline-block;
    }

    .vs-container {
      text-align: center;
      /* margin-right: 40px; */
    }

    /* 角色圖片 */
    .character-img {
      height: 120px;
      border-radius: 8px;
      border: 1px solid #ccc;
    }

    /* COST 按鈕 */
    .cost-btn {
      width: auto;
      padding: 5px 15px;
      font-weight: bold;
      background: white;
      border-radius: 8px;
      border: 2px solid #ddd;
      box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* win rate 按鈕 */
    .wr-btn {
      width: 70px;
      padding: 5px 0px;
      font-weight: bold;
      background: white;
      border-radius: 8px;
      border: 2px solid #ddd;
      box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* 勝利按鈕 */
    .enemy-win-btn {
      background: #ff4e4e;
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
      transform: scale(1.1);
      width: 70px;
    }

    .player-win-btn {
      background: rgb(60, 153, 253);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
      transform: scale(1.1);
      width: 70px;
    }


    .skip1-btn {
      background: rgb(124, 105, 105);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 6px;
      margin: 0px 2px;
      transform: scale(1.1);
      width: 44px;
    }

    .skip2-btn {
      background: rgb(181, 181, 191);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 6px;
      margin: 0px 2px;
      transform: scale(1.1);
      width: 44px;
    }

    .tie-btn {
      background: rgb(0, 193, 61);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 8px;
      margin: 0px 5px;
      transform: scale(1.1);
      width: 33px;
    }

    /* 勝率按鈕 */
    .winrate-container {
      margin-left: 40px;
    }

    .winrate-btn {
      background: green;
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
    }



    /* 媒體查詢：當螢幕寬度小於 768px 時 */
    @media (max-width: 900px) {
      .fight-container {
        flex-direction: column;
        gap: 2px;
        margin: 10px 0px;
      }

      .vs {
        margin-right: 52px;
      }
    }

    .abgne-menu input[type="checkbox"] {
      display: none;
      /* 隱藏 checkbox */
    }

    .abgne-menu input[type="checkbox"]+label {
      display: inline-block;
      background-color: #ccc;
      cursor: pointer;
      padding: 5px;
      margin: 0 2px;
      border-radius: 5px;
      position: relative;
      width: 75px;
      height: 75px;
      overflow: hidden;
      transition: background-color 0.3s ease, border 0.3s ease;
      border: 5px solid transparent;
      /* 預設無外框 */
      box-sizing: border-box;
      /* 確保邊框內縮，不影響內部內容 */
    }

    .abgne-menu input[type="checkbox"]:checked+label {
      background-color: pink;
      border: 5px solid red;
      /* 勾選時加上粉紅色外框 */
    }

    /* 讓圖片放大 150% 但不影響 input checkbox */
    .cropped-img {
      width: 130%;
      height: 100%;
      object-fit: cover;
      object-position: top left;
      /* 讓圖片從左上角開始顯示 */
      transform: scale(1.2);
      /* 放大 150% */
      transition: transform 0.3s ease;
      /* 平滑過渡效果 */
    }

    .sticky-top {
      position: sticky;
      top: 0;
      z-index: 1000;
      background-color: dimgray;
      border-bottom: 2px solid #ddd;
      padding: 10px;
      border-radius: 5px;
      width: 380px;
      margin: auto;
      /* 自動左右外邊距，水平置中 */
    }

    .character-list {
      text-align: center;
    }

    .player-input {
      margin-top: 10px;
      display: flex;
      flex-direction: column;
      gap: 5px;
      width: 90px;
      align-items: center;
    }

    .player-input input {
      /* width: 70%; */
      padding: 5px;
      border-radius: 5px;
    }
  </style>

</head>
<div class="navbar">
  <?php
  print $head_bar;
  ?>
</div>

<body>
  <!-- ✅ 浮動按鈕 -->
  <!-- <button id="historyBtn" onclick="openHistoryPopup()">
    <i class="fa fa-history"></i>
  </button> -->

  <!-- <script>
    let popup; // 🔹 確保不會重複打開視窗

    function openHistoryPopup() {
      if (!popup || popup.closed) {
        popup = window.open("ranking.php", "歷史戰績", "width=800,height=600,resizable=yes,scrollbars=yes");
      } else {
        popup.focus();
      }

      if (!popup) {
        alert("請允許彈出視窗，否則無法選擇歷史戰績!");
      }
    }

    function setSelectedCharacters(team, e1, e2, e3, player_name) {
      if (team === 1) {
        document.getElementById("e1").value = e1;
        document.getElementById("e2").value = e2;
        document.getElementById("e3").value = e3;
      } else if (team === 2) {
        document.getElementById("u1").value = e1;
        document.getElementById("u2").value = e2;
        document.getElementById("u3").value = e3;
      }
      document.getElementById("player_name").value = player_name; // ✅ 確保 `player_name` 也設定了
      document.getElementById("teamForm").submit(); // ✅ 自動提交
    }

    function selectTeam(team, e1, e2, e3) {
      if (window.opener && typeof window.opener.setSelectedCharacters === "function") {
        window.opener.setSelectedCharacters(team, e1, e2, e3);
        window.close(); // ✅ 選擇完成後關閉 POP 視窗
      } else {
        alert("❌ 無法回傳數據，請手動填寫!");
      }
    }
  </script> -->

  <!-- ✅ 隱藏的表單輸入框 -->
  <!-- <form id="teamForm" action="fight.php" method="POST">
    <input type="hidden" id="e1" name="e1">
    <input type="hidden" id="e2" name="e2">
    <input type="hidden" id="e3" name="e3">
    <input type="hidden" id="u1" name="u1">
    <input type="hidden" id="u2" name="u2">
    <input type="hidden" id="u3" name="u3">
    <input type="hidden" id="player_name" name="player_name">
  </form> -->



  <!-- Go To Top Button -->
  <button id="backToTop" onclick="scrollToTop()">▲</button>

  <!-- Go To Bottom Button -->
  <button id="goToBottom" onclick="scrollToBottom()">▼</button>

  <script>
    // 監聽滾動事件，決定是否顯示按鈕
    window.onscroll = function() {
      let topButton = document.getElementById("backToTop");
      let bottomButton = document.getElementById("goToBottom");
      let scrollTop = document.documentElement.scrollTop;
      let scrollHeight = document.documentElement.scrollHeight;
      let clientHeight = document.documentElement.clientHeight;

      if (scrollTop > 200) {
        topButton.style.display = "flex"; // 顯示回到頂部按鈕
      } else {
        topButton.style.display = "none"; // 隱藏按鈕
      }

      if (scrollTop + clientHeight < scrollHeight - 200) {
        bottomButton.style.display = "flex"; // 顯示滾到底部按鈕
      } else {
        bottomButton.style.display = "none"; // 隱藏按鈕
      }
    };

    // 點擊按鈕回到頂部
    function scrollToTop() {
      window.scrollTo({
        top: 0,
        behavior: "smooth" // 平滑滾動效果
      });
    }

    // 點擊按鈕滾到底部
    function scrollToBottom() {
      window.scrollTo({
        top: document.documentElement.scrollHeight,
        behavior: "smooth" // 平滑滾動效果
      });
    }
  </script>



  <div style="margin: 10px;">
    <?php
    if (isset($_POST['e1'])) {
      $e1 = $_POST['e1'];
    }
    if (isset($_POST['e2'])) {
      $e2 = $_POST['e2'];
    }
    if (isset($_POST['e3'])) {
      $e3 = $_POST['e3'];
    }
    if (isset($_POST['u1'])) {
      $u1 = $_POST['u1'];
    }
    if (isset($_POST['u2'])) {
      $u2 = $_POST['u2'];
    }
    if (isset($_POST['u3'])) {
      $u3 = $_POST['u3'];
    }



    /* print "<br>u1=$u1";
    print "<br>u2=$u2";
    print "<br>u3=$u3"; */

    $count_en = 0;
    $e1 = $_SESSION['e1'] ?? $_POST['e1'] ?? '';
    $e2 = $_SESSION['e2'] ?? $_POST['e2'] ?? '';
    $e3 = $_SESSION['e3'] ?? $_POST['e3'] ?? '';
    for ($i = 1; $i <= 3; $i++) {
      if (${"e" . $i}) {
        $count_en++;
      }
    }
    if (isset($e1)) {
      // 檢查並轉換 $e1, $e2, $e3 的值
      $e1 = is_numeric($e1) ? (int)$e1 : 0;
      $e2 = is_numeric($e2) ? (int)$e2 : 0;
      $e3 = is_numeric($e3) ? (int)$e3 : 0;

      // 使用 floor 函數計算
      $x = floor(($e1 - 1) / 10);
      $y = floor(($e2 - 1) / 10);
      $z = floor(($e3 - 1) / 10);

      // 比較是否相同
      $same_ch_flag += all_equal($x, $y, $z);
    }
    if (isset($u1)) {

      $u1 = is_numeric($u1) ? (int)$u1 : 0;
      $u2 = is_numeric($u2) ? (int)$u2 : 0;
      $u3 = is_numeric($u3) ? (int)$u3 : 0;

      $x = floor(($u1 - 1) / 10);
      $y = floor(($u2 - 1) / 10);
      $z = floor(($u3 - 1) / 10);
      $same_ch_flag += all_equal($x, $y, $z);
      //print "<br>same_ch_flag3=$same_ch_flag";
    }

    if (isset($_POST["up"])) {
      //設定自身隊伍

      $_SESSION['e1'] = $_POST['e1'];
      $_SESSION['e2'] = $_POST['e2'];
      $_SESSION['e3'] = $_POST['e3'];



      $count_us = 0; // 初始化計數
      for ($i = 0; $i < 3; $i++) {
        $j = $i + 1;

        // 檢查 $_POST['ch'][$i] 是否存在
        if (isset($_POST['ch'][$i])) {
          ${"u" . $j} = $_POST['ch'][$i];
          $count_us++;
        } else {
          ${"u" . $j} = null; // 預設為 null 或其他適當值
        }
      }
      //檢驗隊伍人數
      $count_flag = 0;
      if ($count_en != 3 || $count_us != 3) {
        $count_flag = 1;
      }

      $x = floor(($_SESSION['e1'] - 1) / 10);
      $y = floor(($_SESSION['e2'] - 1) / 10);
      $z = floor(($_SESSION['e3'] - 1) / 10);
      $same_ch_flag += all_equal($x, $y, $z);
      //print "<br>same_ch_flag4=$same_ch_flag";

      $_SESSION['u1'] = $u1;
      $_SESSION['u2'] = $u2;
      $_SESSION['u3'] = $u3;

      $x = floor(($u1 - 1) / 10);
      $y = floor(($u2 - 1) / 10);
      $z = floor(($u3 - 1) / 10);
      $same_ch_flag += all_equal($x, $y, $z);
      //print "<br>same_ch_flag5=$same_ch_flag";
      //print "same_ch_flag3";


    } elseif (isset($_POST['enemy_win']) || isset($_POST['first_win'])) {



      $first_win = $_POST['first_win'] ?? 0;
      $enemy_win = $_POST['enemy_win'] ?? 0;


      if ($first_win) {
        $id = $first_win;
        $win = 1;
        $lose = 0;
      } elseif ($enemy_win) {
        $id = $enemy_win;
        $win = 0;
        $lose = 1;
      }
      $sql_insert = "UPDATE `leway_db`.`arena_unlight` 
      SET `win` = :win, `lose` = :lose , `ack1` = :ack1 , `ack2` = :ack2
      WHERE `id` = :id";

      $stmt_insert = $db->prepare($sql_insert);
      $stmt_insert->execute([
        ':win' => $win,
        ':lose' => $lose,
        ':ack1' => 1,
        ':ack2' => 1,
        ':id' => $id,
      ]);

      echo "<script>alert('感謝上傳!')</script>";

      // 🚀 **成功上傳後，更新 Token，防止 F5 重新提交**
      $_SESSION['upload_token'] = bin2hex(random_bytes(32));
    } elseif (isset($_POST['skip1'])) {
      $id = $_POST['skip1'];
      $sql_insert = "UPDATE `leway_db`.`arena_unlight` 
      SET `ack1` = :ack1 
      WHERE `id` = :id";
      /* print "ack=$ack";
      print "id=$id"; */

      $stmt_insert = $db->prepare($sql_insert);
      $stmt_insert->execute([
        ':ack1' => 1,
        ':id' => $id,
      ]);

      echo "<script>alert('感謝上傳!')</script>";

      // 🚀 **成功上傳後，更新 Token，防止 F5 重新提交**
      $_SESSION['upload_token'] = bin2hex(random_bytes(32));
    } elseif (isset($_POST['skip2'])) {
      $id = $_POST['skip2'];
      $sql_insert = "UPDATE `leway_db`.`arena_unlight` 
      SET `ack2` = :ack2 
      WHERE `id` = :id";
      /* print "ack=$ack";
      print "id=$id"; */

      $stmt_insert = $db->prepare($sql_insert);
      $stmt_insert->execute([
        ':ack2' => 1,
        ':id' => $id,
      ]);

      echo "<script>alert('感謝上傳!')</script>";

      // 🚀 **成功上傳後，更新 Token，防止 F5 重新提交**
      $_SESSION['upload_token'] = bin2hex(random_bytes(32));
    } elseif (isset($_POST['tie'])) {
      $id = $_POST['tie'];
      $sql_insert = "UPDATE `leway_db`.`arena_unlight` 
      SET `ack1` = :ack1 ,`ack2` = :ack2,`tie` = :tie
      WHERE `id` = :id";
      /* print "ack=$ack";
      print "id=$id"; */

      $stmt_insert = $db->prepare($sql_insert);
      $stmt_insert->execute([
        ':ack1' => 1,
        ':ack2' => 1,
        ':tie' => 1,
        ':id' => $id,
      ]);

      echo "<script>alert('感謝上傳!')</script>";

      // 🚀 **成功上傳後，更新 Token，防止 F5 重新提交**
      $_SESSION['upload_token'] = bin2hex(random_bytes(32));
    }


    if (isset($_POST['win'])) {
      $id = $_POST['win'];
      $arr4 = $db->query("SELECT win FROM leway_db.arena_unlight WHERE id='$id';");
      while ($row = $arr4->fetch()) {
        $win = $row[0] + 1;
      }
      $db->exec("UPDATE `leway_db`.`arena_unlight` SET `win` = '$win', `update` = '$date' WHERE (`id` = '$id');");
      echo "<script>alert('感謝上傳!')</script>";
    } elseif (isset($_POST['lose'])) {
      $id = $_POST['lose'];
      $arr4 = $db->query("SELECT lose FROM leway_db.arena_unlight WHERE id='$id';");
      while ($row = $arr4->fetch()) {
        $lose = $row[0] + 1;
      }
      $db->exec("UPDATE `leway_db`.`arena_unlight` SET `lose` = '$lose', `update` = '$date' WHERE (`id` = '$id');");
      echo "<script>alert('感謝上傳!')</script>";
    }







    ?>

  </div>




  <!-- <h3 style="margin:5px"> 對手(多選)</h3> -->
  <div class="abgne-menu" id="about" style="margin:10px">
    <?php
    if ($ack) {        //<!-- 固定置頂按鈕區 -->
      print '<form method="POST">
        <label>請貼上對戰 JSON：</label><br>
        <textarea name="ws_json" rows="10" cols="100" placeholder="請貼上 WebSocket 資料"></textarea><br>
        <button type="submit" name="upload_json">解析並上傳佇列</button>
      </form>';
    }


    print '<div class="main"></div>';
    if (isset($_POST['ch']) && !isset($_POST['up']) && !isset($_POST['show_all'])) {
      $_SESSION["combined"] = 0;
      $combined = 0;
      $status = "status1";
      for ($i = 0; $i < 3; $i++) {
        $j = $i + 1;
        ${"e" . $j} = $_POST['ch'][$i] ?? 0;
      }
    } elseif (isset($_POST["e1"])) {
      $status =  "state2";
      $e1 = $_POST['e1'];
      $e2 = $_POST['e2'];
      $e3 = $_POST['e3'];
    } elseif (isset($_SESSION["e1"])) {
      $status =  "state3";
      $e1 = $_SESSION['e1'];
      $e2 = $_SESSION['e2'];
      $e3 = $_SESSION['e3'];
    }
    $sql = "SELECT COUNT(*) FROM leway_db.arena_unlight WHERE ack1 = 0 OR ack2 = 0;";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    print "<h4>佇列【Queue】- 共 $count 筆。</h4>";
    $flag = 1;
    $use_flag = 1;
    $index = 0; // 追蹤行數
    $player_name = '';
    $sql = "SELECT * FROM leway_db.arena_unlight  WHERE ack1=0 or ack2=0 order by update_time desc;";
    //print "sql=$sql";
    //print "player_name=$player_name";
    $win_sum = 0;
    $lose_sum = 0;
    $arr2 = $db->query("$sql");
    while ($row = $arr2->fetch()) {
      $use_flag = 0;
      $flag = 0;
      $bg_class = ($index % 2 == 0) ? "gray-bg" : "white-bg"; // 設定背景顏色
      $index++; // 增加索引計數
      $id = $row[0];
      $date = $row[11];
      $last_username = $row[12] ?? ''; // 確保不會是未定義的變數
      $masked_username = maskUsername($last_username);
      $player_name1 = $row[13];
      $bp1 = $row[14];
      $win1 = $row[15];
      $draw1 = $row[16];
      $lose1 = $row[17];
      $player_name2 = $row[18];
      $bp2 = $row[19];
      $win2 = $row[20];
      $draw2 = $row[21];
      $lose2 = $row[22];
      $ack1 = $row[23];
      $ack2 = $row[24];

      print '<form action="#" method="POST" class="fight-form">';
      print '<div class="fight-container ' . $bg_class . '">'; // ✅ 加入背景顏色

      $cost_sum = 0;
      $img_arr = '';
      // 組合1組合
      print '<div class="team">';
      for ($i = 2; $i <= 4; $i++) {
        $id_count = $row[$i];
        $sql = "SELECT ico,cost FROM leway_db.unlight WHERE id='$id_count';";
        $arr = $db->query("$sql");
        while ($row1 = $arr->fetch()) {
          $ico = $row1[0];
          $cost = $row1[1];
          $cost_sum += $cost;
          $cost_arr[] = $cost;
          $img_arr .= '<img class="character-img" src="uploads/' . $ico . '" loading="lazy">';
        }
        if (!is_array($cost_arr) || count($cost_arr) < 3) {
          $punishment = 0;
        } else {
          $punishment = cost_punish($cost_arr[0], $cost_arr[1], $cost_arr[2]);
        }
        $cost_sum += $punishment;
        $cost_color = ($punishment > 0) ? 'text-danger' : ''; // 設定紅色類別
      }
      print '<button type="button" class="btn btn-light cost-btn ' . $cost_color . '">COST ' . $cost_sum . '</button>';
      print '<div class="characters">';
      print $img_arr;
      $img_arr = '';
      $cost_sum = 0;
      unset($cost_arr);
      print '</div>';
      if ($ack) {
        print '<div class="player-input">';
        //print '<button type="submit" name="enemy_win" value="' . $id . '" class="btn btn-danger enemy-win-btn">勝利</button>';
        // ✅ 增加玩家名稱 & BP 輸入框
        print  $player_name1;
        print '<button type="button" class="btn btn-light wr-btn">BP ' . $bp1 . '</button>';
        print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';
        print '</div>';
      }
      if ($player_name) {
        print "$player_name<br>BP:$bp1";
      }
      print '<div style="display: flex; flex-direction: column; gap: 5px;">';
      print '<button type="button" class="btn btn-light wr-btn">' . $win1 . '勝</button>';
      print '<button type="button" class="btn btn-light wr-btn">' . $lose1 . '負</button>';
      print '<button type="button" class="btn btn-light wr-btn">' . $draw1 . '平</button>';
      print '</div>';
      print '</div>';

      // VS 標示
      print '<div class="vs-container">
      <span class="timestamp">' . $date . '</span><br>';

      // Skip 1
      if ($ack1 == 1) {
        print '<button type="button" class="btn btn-secondary skip1-btn" disabled>已略</button>';
      } else {
        print '<button type="submit" name="skip1" value="' . $id . '" class="btn btn-default skip2-btn" disabled>略1</button>';
      }

      // Skip 2
      if ($ack2 == 1) {
        print '<button type="button" class="btn btn-secondary skip1-btn" disabled>已略</button>';
      } else {
        print '<button type="submit" name="skip2" value="' . $id . '" class="btn btn-default skip2-btn" disabled>略2</button>';
      }

      // Tie always available
      //print '<button type="submit" name="tie" value="' . $id . '" class="btn btn-success tie-btn">平</button>';

      print '<br>by ' . $masked_username . '</div>';

      // 組合2組合
      print '<div class="team">';
      if (isset($row[5])) {
        for ($i = 5; $i <= 7; $i++) {
          $sql = "SELECT ico,cost FROM leway_db.unlight WHERE id='$row[$i]';";
          $arr = $db->query("$sql");
          while ($row1 = $arr->fetch()) {
            $ico = $row1[0];
            $cost = $row1[1];
            $cost_sum += $cost;
            $cost_arr[] = $cost;
            $img_arr .= '<img class="character-img" src="uploads/' . $ico . '" loading="lazy">';
          }
          if (!is_array($cost_arr) || count($cost_arr) < 3) {
            $punishment = 0;
          } else {
            $punishment = cost_punish($cost_arr[0], $cost_arr[1], $cost_arr[2]);
          }
          $cost_sum += $punishment;
          $cost_color = ($punishment > 0) ? 'text-danger' : ''; // 設定紅色類別
        }
        print '<button type="button" class="btn btn-light cost-btn ' . $cost_color . '">COST ' . $cost_sum . '</button>';
        print '<div class="characters">';
        print $img_arr;
        $img_arr = '';
        $cost_sum = 0;
        unset($cost_arr);
        print '</div>';
        if ($ack) {
          print '<div class="player-input">';
          //print '<button type="submit" name="first_win" value="' . $id . '" class="btn btn-primary player-win-btn">勝利</button>';
          // ✅ 增加玩家名稱 & BP 輸入框
          print $player_name2;
          print '<button type="button" class="btn btn-light wr-btn">BP ' . $bp2 . '</button>';
          print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';
          print '</div>';
        }
        if ($player_name) {
          print "$player_name2<br>BP:$bp2";
        }
        print '<div style="display: flex; flex-direction: column; gap: 5px;">';
        print '<button type="button" class="btn btn-light wr-btn">' . $win2 . '勝</button>';
        print '<button type="button" class="btn btn-light wr-btn">' . $lose2 . '負</button>';
        print '<button type="button" class="btn btn-light wr-btn">' . $draw2 . '平</button>';
        print '</div>';
      }
      print '</div>';
      print '</div>'; // fight-container



      print '<input type="hidden" name="e1" value="' . $e1 . '">';
      print '<input type="hidden" name="e2" value="' . $e2 . '">';
      print '<input type="hidden" name="e3" value="' . $e3 . '">';
      print '<input type="hidden" name="u1" value="' . $row[5] . '">';
      print '<input type="hidden" name="u2" value="' . $row[6] . '">';
      print '<input type="hidden" name="u3" value="' . $row[7] . '">';
      print '</form>';
    }
    /* if (($lose_sum + $win_sum) > 0) {
      $rate_sum = round($lose_sum / ($lose_sum + $win_sum) * 100, 0);
    } else {
      $rate_sum = 0; // 或者設定為 50% / -1 代表無數據
    } */
    //print "勝率:$rate_sum%";

    ?>



  </div>

  <!-- jQuery 3 -->
  <script src="../AdminLTE-master/bower_components/jquery/dist/jquery.min.js"></script>
  <!-- jQuery UI 1.11.4 -->
  <script src="../AdminLTE-master/bower_components/jquery-ui/jquery-ui.min.js"></script>
  <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
  <script>
    $.widget.bridge('uibutton', $.ui.button);
  </script>
  <!-- Bootstrap 3.3.7 -->
  <script src="../AdminLTE-master/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  <!-- Morris.js charts -->
  <script src="../AdminLTE-master/bower_components/raphael/raphael.min.js"></script>
  <script src="../AdminLTE-master/bower_components/morris.js/morris.min.js"></script>
  <!-- Sparkline -->
  <script src="../AdminLTE-master/bower_components/jquery-sparkline/dist/jquery.sparkline.min.js"></script>
  <!-- jvectormap -->
  <script src="../AdminLTE-master/plugins/jvectormap/jquery-jvectormap-1.2.2.min.js"></script>
  <script src="../AdminLTE-master/plugins/jvectormap/jquery-jvectormap-world-mill-en.js"></script>
  <!-- jQuery Knob Chart -->
  <script src="../AdminLTE-master/bower_components/jquery-knob/dist/jquery.knob.min.js"></script>
  <!-- daterangepicker -->
  <script src="../AdminLTE-master/bower_components/moment/min/moment.min.js"></script>
  <script src="../AdminLTE-master/bower_components/bootstrap-daterangepicker/daterangepicker.js"></script>
  <!-- datepicker -->
  <script src="../AdminLTE-master/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
  <!-- Bootstrap WYSIHTML5 -->
  <script src="../AdminLTE-master/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js"></script>
  <!-- Slimscroll -->
  <script src="../AdminLTE-master/bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>
  <!-- FastClick -->
  <script src="../AdminLTE-master/bower_components/fastclick/lib/fastclick.js"></script>
  <!-- AdminLTE App -->
  <script src="../AdminLTE-master/dist/js/adminlte.min.js"></script>
  <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
  <script src="../AdminLTE-master/dist/js/pages/dashboard.js"></script>
  <!-- AdminLTE for demo purposes -->
  <script src="../AdminLTE-master/dist/js/demo.js"></script>

  <!-- Bootstrap CSS (若已引入可略過) -->
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->

  <!-- Bootstrap JavaScript (必要，讓 collapse 運作) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>