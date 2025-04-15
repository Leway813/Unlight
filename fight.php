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

/* if ($_SESSION["username"] == 'way.lee') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  print '<pre>';
  var_dump($_POST);
  var_dump($_SESSION);
  print '</pre>';
} */


$username = $_SESSION["username"];
$permission = $_SESSION["ack"] ?? 0;




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
$player_name = '';
if (isset($_POST["player_name"]) && $_POST["player_name"]) {
  $player_name = $_POST["player_name"];
  $_SESSION["player_name"] = $_POST["player_name"];
} else {
  $player_name = $_SESSION["player_name"];
}

if ($show_all) {
  $sql = "SELECT * FROM leway_db.unlight where ico <> '' and id > 0;";
} else {
  $sql = "SELECT * FROM leway_db.use_rate_unlight where count_ttl >(SELECT sum(count_ttl)/800 FROM leway_db.use_rate_unlight) and id>0 order by id;";
}

$i = 0;
$arr_sl = ""; // 確保變數已初始化

$arr1 = $db->query($sql); // 假設 $sql 是安全的，建議使用預備語句
$p_name = ""; // 避免未初始化的變數

while ($row = $arr1->fetch()) {
  $id = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8'); // 避免 XSS
  $ico = htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8'); // 處理圖片路徑
  $name = htmlspecialchars($row[2], ENT_QUOTES, 'UTF-8'); // 處理名稱

  // 確保相同名稱的項目不重複顯示
  if ($p_name != $name) {
    $paddedStr = str_pad($name, 18, "　", STR_PAD_RIGHT);
    $paddedStr = "<span class='padded-name'>$paddedStr</span>";

    $arr_sl .= "<br>" . $paddedStr;
  }

  // 生成圖片 HTML
  $ico_html = '<img class="cropped-img" src="uploads/' . $ico . '" loading="lazy" height="100px">';

  // 生成 checkbox 和 label
  $arr_sl .= "<input type=\"checkbox\" id=\"$id\" name=\"ch[]\" value=\"$id\"><label for=\"$id\">$ico_html</label>";

  $i++;
  $p_name = $name; // 更新前一個名稱
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
      padding: 3px 6px;
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
      background: rgb(60, 153, 253);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
      /* transform: scale(1.1); */
      width: 70px;
    }

    /* 勝利按鈕 */
    .queue-btn {
      background: rgb(42, 163, 95);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
      transform: scale(1.1);
      width: 70px;
    }

    .player-win-btn {
      background: #ff4e4e;
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 5px 15px;
      /* transform: scale(1.1); */
      width: 70px;
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
      /* margin-top: 10px; */
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
  </script>
  //✅ 隱藏的表單輸入框
  <form id="teamForm" action="fight.php" method="POST">
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

    if (isset($_POST['upload_player'])) {
      $playerA_str = $_POST['playerA'] ?? '';
      $playerB_str = $_POST['playerB'] ?? '';

      $playerA = array_map('intval', explode(',', str_replace(' ', '', $playerA_str)));
      $playerB = array_map('intval', explode(',', str_replace(' ', '', $playerB_str)));

      // 指定變數
      $e1 = $playerA[0] + 1 ?? 0;
      $e2 = $playerA[1] + 1 ?? 0;
      $e3 = $playerA[2] + 1 ?? 0;
      $u1 = $playerB[0] + 1 ?? 0;
      $u2 = $playerB[1] + 1 ?? 0;
      $u3 = $playerB[2] + 1 ?? 0;

      echo "<h4>導入成功</h4>";
      /* echo "e1: $e1, e2: $e2, e3: $e3<br>";
      echo "u1: $u1, u2: $u2, u3: $u3<br>"; */

      // 若你有要儲存 session：
      $_SESSION['e1'] = $e1;
      $_SESSION['e2'] = $e2;
      $_SESSION['e3'] = $e3;
      $_SESSION['u1'] = $u1;
      $_SESSION['u2'] = $u2;
      $_SESSION['u3'] = $u3;
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


    } elseif (isset($_POST['first_win']) || isset($_POST['enemy_win'])) {
      //上傳自身隊伍

      $_SESSION['e1'] = $_POST['e1'];
      $_SESSION['e2'] = $_POST['e2'];
      $_SESSION['e3'] = $_POST['e3'];
      $_SESSION['u1'] = $_POST['u1'];
      $_SESSION['u2'] = $_POST['u2'];
      $_SESSION['u3'] = $_POST['u3'];
      $name_p1 = !empty($_POST['name_p1']) ? $_POST['name_p1'] : null;
      $name_p2 = !empty($_POST['name_p2']) ? $_POST['name_p2'] : null;
      $bp_p1 = !empty($_POST['bp_p1']) ? $_POST['bp_p1'] : null;
      $bp_p2 = !empty($_POST['bp_p2']) ? $_POST['bp_p2'] : null;
      $_SESSION["rank_name1"] = '';
      $_SESSION["rank_name2"] = '';


      $first_win = $_POST['first_win'] ?? 0;
      $enemy_win = $_POST['enemy_win'] ?? 0;

      for ($i = 0; $i < 3; $i++) {
        $j = $i + 1;
        ${"e" . $j} = $_POST["e" . $j];
        $_SESSION["e" . $j] = ${"e" . $j};
      }
      for ($i = 0; $i < 3; $i++) {
        $j = $i + 1;
        ${"u" . $j} = $_POST["u" . $j];
        $_SESSION["u" . $j] = ${"u" . $j};
      }

      // **✅ 檢查角色人數是否滿 3 人**
      $enemy_team = array_filter([$e1, $e2, $e3], function ($char) {
        return $char > 0;
      });
      $user_team = array_filter([$u1, $u2, $u3], function ($char) {
        return $char > 0;
      });



      if ($first_win) {
        $win = 1;
        $lose = 0;
      } elseif ($enemy_win) {
        $win = 0;
        $lose = 1;
      }

      if (count($enemy_team) < 3 || count($user_team) < 3) {
        echo "<script>alert('隊伍人數不足 3 人，請確認選擇的角色!');</script>";
        /* exit; // 直接終止程式 */
      } elseif (!isset($_POST['upload_token']) || $_POST['upload_token'] !== $_SESSION['upload_token']) {
        echo "<script>alert('請勿重複提交！');</script>";
      } elseif ($same_ch_flag) {
        echo "<script>alert('隊伍角色相同，請確認選擇的角色!');</script>";
      } else {
        // ✅ 沒有相同的對戰紀錄，允許插入
        $sql_insert = "INSERT INTO `leway_db`.`arena_unlight` 
                   (`e1`, `e2`, `e3`, `u1`, `u2`, `u3`, `win`, `lose`, `update_time`, `username`, `name_p1`, `bp_p1`, `name_p2`, `bp_p2`) 
                   VALUES (:e1, :e2, :e3, :u1, :u2, :u3, :win, :lose, NOW(), :username, :name_p1, :bp_p1, :name_p2, :bp_p2)";

        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute([
          ':e1' => $e1,
          ':e2' => $e2,
          ':e3' => $e3,
          ':u1' => $u1,
          ':u2' => $u2,
          ':u3' => $u3,
          ':win' => $win,
          ':lose' => $lose,
          ':username' => $username,
          ':name_p1' => $name_p1,
          ':bp_p1' => $bp_p1,
          ':name_p2' => $name_p2,
          ':bp_p2' => $bp_p2
        ]);

        echo "<script>alert('感謝上傳!')</script>";

        // 🚀 **成功上傳後，更新 Token，防止 F5 重新提交**
        $_SESSION['upload_token'] = bin2hex(random_bytes(32));
      }
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





    if (isset($_POST['ch']) || isset($_POST['e1'])  || isset($_SESSION["e1"])) {
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
      print '<h4>對戰統計【History】- 近期統計</h4>';
      $flag = 1;
      $use_flag = 1;
      $index = 0; // 追蹤行數
      //$player_name = '';
      //print "combined=$combined";
      if ($combined == 2) {
        if($permission==2){
          $time_range=0;
        }else{
          $time_range=20;
        }
        $sql = "SELECT * FROM (
          SELECT 
              `arena_unlight`.`e1` AS `e1`,
              `arena_unlight`.`e2` AS `e2`,
              `arena_unlight`.`e3` AS `e3`,
              `arena_unlight`.`u1` AS `u1`,
              `arena_unlight`.`u2` AS `u2`,
              `arena_unlight`.`u3` AS `u3`,
              `arena_unlight`.`win` AS `win`,
              `arena_unlight`.`lose` AS `lose`,
              `arena_unlight`.`update_time` AS `update_time`,
              `arena_unlight`.`username` AS `username`,
              `arena_unlight`.`name_p1` AS `player_name1`,
              `arena_unlight`.`bp_p1` AS `bp1` ,
              `arena_unlight`.`name_p2` AS `player_name2`,
              `arena_unlight`.`bp_p2` AS `bp2` ,
              `arena_unlight`.`ack1` AS `ack1` ,
              `arena_unlight`.`ack2` AS `ack2` 
          FROM `arena_unlight`
          
          UNION ALL 

          SELECT 
              `arena_unlight`.`u1` AS `e1`,
              `arena_unlight`.`u2` AS `e2`,
              `arena_unlight`.`u3` AS `e3`,
              `arena_unlight`.`e1` AS `u1`,
              `arena_unlight`.`e2` AS `u2`,
              `arena_unlight`.`e3` AS `u3`,
              `arena_unlight`.`lose` AS `win`,
              `arena_unlight`.`win` AS `lose`,
              `arena_unlight`.`update_time` AS `update_time`,
              `arena_unlight`.`username` AS `username`,
              `arena_unlight`.`name_p2` AS `player_name1`,
              `arena_unlight`.`bp_p2` AS `bp1`,
              `arena_unlight`.`name_p1` AS `player_name2`,
              `arena_unlight`.`bp_p1` AS `bp2`,
              `arena_unlight`.`ack1` AS `ack1` ,
              `arena_unlight`.`ack2` AS `ack2` 
          FROM `arena_unlight`
      ) AS temp
      WHERE (temp.`player_name1` = '$player_name') AND (temp.update_time < (NOW() - INTERVAL $time_range MINUTE))
      ORDER BY temp.`update_time` DESC LIMIT 20;
      ";
      } elseif ($combined == 1) {
        $sql = "SELECT * FROM leway_db.arena_statistic_unlight  WHERE (e1='$e1' and e2='$e2')or(e2='$e1' and e3='$e2')or(e1='$e1' and e3='$e2') ORDER BY `update_time` desc LIMIT 20;";
      } else { //$combined == 0 3人組
        $sql = "SELECT * FROM leway_db.arena_statistic_unlight  WHERE e1='$e1' and e2='$e2' and e3='$e3' ORDER BY `update_time` desc LIMIT 20;";
      }
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

        $win = $row[6];
        $win_sum += $win;
        $lose = $row[7];
        $lose_sum += $lose;
        // 右方(組合2)勝率按鈕
        if (($win + $lose) > 0) {
          $rate = round($win / ($win + $lose) * 100, 0);
        } else {
          $rate = 0; // 或你也可以用 null 或 "-"
        }
        $wr = round($rate, 0);
        $wr_left = 100 - $wr; // (組合1)勝率按鈕
        //print '<button type="button" class="btn btn-success winrate-btn">組合2勝率 : ' . $wr . '%</button>';
        $date = $row[8];
        $last_username = $row[9] ?? ''; // 確保不會是未定義的變數
        $last_username_bp = $row[10] ?? ''; // 確保不會是未定義的變數
        $last_enemy = $row[11] ?? ''; // 確保不會是未定義的變數
        $last_enemy_bp = $row[12] ?? ''; // 確保不會是未定義的變數
        $masked_username = maskUsername($last_username);

        //$player_name1 = $row[14];
        if ($player_name && $combined == 2) {
          $bp1 = $row[11];
          $player_name2 = $row[12];
          $bp2 = $row[13];
          $ack1 = $row[14];
          $ack2 = $row[15];
        }

        print '<form action="#" method="POST" class="fight-form">';
        print '<div class="fight-container ' . $bg_class . '">'; // ✅ 加入背景顏色

        $cost_sum = 0;
        $img_arr = '';
        // 組合1組合
        print '<div class="team">';
        for ($i = 0; $i <= 2; $i++) {
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

        print '<div>';
        //print '<button type="submit" name="enemy_win" value="' . $id . '" class="btn btn-danger enemy-win-btn">勝利</button>';
        // ✅ 增加玩家名稱 & BP 輸入框
        if ($player_name && $combined == 2) {
          print '<div class="player-input">';
          if ($ack1 == 0 or $ack2 == 0) {
            print '<button type="button" class="btn btn-light wr-btn queue-btn">未判</button>';
          } elseif ($wr_left == 100) {
            print '<button type="button" class="btn btn-light wr-btn enemy-win-btn">勝</button>';
          } else {
            print '<button type="button" class="btn btn-light wr-btn player-win-btn">負</button>';
          }
          print  $player_name;
          print '<button type="button" class="btn btn-light wr-btn">BP ' . $bp1 . '</button>';
          //print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';
          print '</div>';
        } else {
          $rate_color = progress_color($wr_left);
          print '<div class="d-flex flex-column align-items-start">';
          print '<button type="button" class="btn btn-light wr-btn bg-' . $rate_color . '">' . $wr_left . '%</button>';
          print '<button type="button" class="btn btn-light wr-btn mt-1">' . $lose . '場</button>';
          print '</div>';
        }
        print '</div>';
        print '</div>';
        // VS 標示
        print '<div class="vs-container">
                  <span class="timestamp">' . $date . '</span>
                  <br>
                  <strong>VS</strong>                  
              </div>';

        // 組合2組合
        print '<div class="team">';
        for ($i = 3; $i <= 5; $i++) {
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


        // ✅ 增加玩家名稱 & BP 輸入框
        /* if ($player_name && $combined == 2) {
          } */
        if ($player_name && $combined == 2) {
          print '<div class="player-input">';
          if ($ack1 == 0 or $ack2 == 0) {
            print '<button type="button" class="btn btn-light wr-btn queue-btn">未判</button>';
          } elseif ($wr == 100) {
            print '<button type="button" class="btn btn-light wr-btn enemy-win-btn">勝</button>';
          } else {
            print '<button type="button" class="btn btn-light wr-btn player-win-btn">負</button>';
          }
          print $player_name2;
          print '<button type="button" class="btn btn-light wr-btn">BP ' . $bp2 . '</button>';
          print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';
          print '</div>';
        } else {
          $rate_color = progress_color($wr);
          print '<div class="d-flex flex-column align-items-start">';
          print '<button type="button" class="btn btn-light wr-btn bg-' . $rate_color . '">' . $wr . '%</button>';
          print '<button type="button" class="btn btn-light wr-btn mt-1">' . $win . '場</button>';
          print '</div>';
        }
        print '</div>';
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
      if (($lose_sum + $win_sum) > 0) {
        $rate_sum = round($lose_sum / ($lose_sum + $win_sum) * 100, 0);
      } else {
        $rate_sum = 0; // 或者設定為 50% / -1 代表無數據
      }
      // 判斷顏色
      $rate_color = progress_color($rate_sum);
      //print "<td><div class='badge bg-$rate_color'>$rate%</div></td>";
      // 顯示進度條
      print '<div class="progress mt-3" style="height: 33px;margin: 0px 60px;">';
      print '<div class="badge bg-' . $rate_color . '" role="progressbar" style="width: ' . $rate_sum . '%;padding: 6px 0px;font-size: 18px;">
          勝率 ' . $rate_sum . '%
       </div>';
      print '</div>';
      //print "勝率:$rate_sum%";


      //上傳組合1/2
      $img_arr = '';
      $cost_sum = 0;
      $rank_name1 = '';
      if ($permission) {
        print '<hr><h4>設定對戰組合【Setting Records】</h4>';

        print '<form action="#" method="POST" class="fight-form">';
        $cost = 0;
        if (isset($_POST["enemy"]) && $_POST["enemy"]) {

          for ($i = 0; $i < 3; $i++) {
            $j = $i + 1;
            ${"e" . $j} = $_POST['ch'][$i] ?? 0;
          }
          $row[1] = $e1;
          $row[2] = $e2;
          $row[3] = $e3;
          $_SESSION["e1"] = $e1;
          $_SESSION["e2"] = $e2;
          $_SESSION["e3"] = $e3;
          //print "if1<br>";
        } elseif (isset($_POST["e1"]) && $_POST["e1"]) {
          $row[1] = (int) $_POST["e1"];
          $row[2] = (int) $_POST["e2"];
          $row[3] = (int) $_POST["e3"];

          $_SESSION['e1'] = $row[1];
          $_SESSION['e2'] = $row[2];
          $_SESSION['e3'] = $row[3];
          $rank_name1 = $player_name;
          $_SESSION["rank_name1"] = $rank_name1;
          //print "if2<br>";
        } else {
          $row[1] = $_SESSION['e1'] ?? 0;
          $row[2] = $_SESSION['e2'] ?? 0;
          $row[3] = $_SESSION['e3'] ?? 0;
          $e1 = $row[1];
          $e2 = $row[2];
          $e3 = $row[3];
          $rank_name1 = $_SESSION["rank_name1"] ?? '';
          /* print "_SESSION[1]=$_SESSION[1]<br>";
          print "_SESSION[2]=$_SESSION[2]<br>";
          print "_SESSION[3]=$_SESSION[3]<br>";  */
          //print "if3<br>";
        }

        print '<div class="fight-container">';

        // 組合1組合
        print '<div class="team">';
        if (isset($row[1])) {
          for ($i = 1; $i <= 3; $i++) {
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
          unset($cost_arr);
          $cost_sum = 0;
          print '</div>';
          print '<div class="player-input">';
          print '<button type="submit" name="enemy_win" value="1" class="btn btn-danger enemy-win-btn">勝利</button>';
          // ✅ 增加玩家名稱 & BP 輸入框
          print '<input type="text" name="name_p1" class="form-control" placeholder="玩家名稱" value="' . $rank_name1 . '">';
          print '<input type="number" name="bp_p1" class="form-control" placeholder="BP 分數" min="0">';
          print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';

          print '</div>';

          //print '<button type="button" class="btn btn-light wr-btn" style="visibility: hidden;"></button>';
        }


        print '</div>';/* class="team"-end */

        // VS 標示
        print '<div class="vs-container">
                    <strong>VS</strong>
                </div>';

        // 組合2組合
        $cost_sum = 0;
        $rank_name2 = '';
        unset($cost_arr);
        if (isset($_POST["u1"]) && $_POST["u1"]) {
          $row[6] = $_POST['u1'];
          $row[7] = $_POST['u2'];
          $row[8] = $_POST['u3'];
          $_SESSION['u1'] = $row[6];
          $_SESSION['u2'] = $row[7];
          $_SESSION['u3'] = $row[8];
          $rank_name2 = $player_name;
          $_SESSION["rank_name2"] = $rank_name2;
        } else {
          $row[6] = $_SESSION['u1'] ?? 0;
          $row[7] = $_SESSION['u2'] ?? 0;
          $row[8] = $_SESSION['u3'] ?? 0;
          $rank_name2 = $_SESSION["rank_name2"] ?? '';
        }

        print '<div class="team">';
        if (isset($row[6])) {
          for ($i = 6; $i <= 8; $i++) {
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
          print '</div>';

          print '<div class="player-input">';
          print '<button type="submit" name="first_win" value="1" class="btn btn-primary player-win-btn">勝利</button>';
          // ✅ 增加玩家名稱 & BP 輸入框
          print '<input type="text" name="name_p2" class="form-control" placeholder="玩家名稱" value="' . $rank_name2 . '">';
          print '<input type="number" name="bp_p2" class="form-control" placeholder="BP 分數" min="0">';
          print '<input type="hidden" name="upload_token" value="' . $_SESSION['upload_token'] . '">';
          print '</div>';
        }

        print '</div>';/* class="team"-end */
        // 隱藏欄位
        print '<input type="hidden" name="e1" value="' . $e1 . '">';
        print '<input type="hidden" name="e2" value="' . $e2 . '">';
        print '<input type="hidden" name="e3" value="' . $e3 . '">';
        print '<input type="hidden" name="u1" value="' . $row[6] . '">';
        print '<input type="hidden" name="u2" value="' . $row[7] . '">';
        print '<input type="hidden" name="u3" value="' . $row[8] . '">';

        print '</div>'; // fight-container

        print '</form>';
      }
    }

    ?>

  </div>




  <!-- <h3 style="margin:5px"> 對手(多選)</h3> -->
  <div class="abgne-menu" id="about" style="margin:10px">
    <?php
    if ($permission) {        //<!-- 固定置頂按鈕區 -->
      print '<form action="#" method="POST">      
      <div class="text-center">
      playerA<input type="text" name="playerA" placeholder="代碼">
      playerB<input type="text" name="playerB" placeholder="代碼">
      <button type="submit" class="btn btn-outline-dark" name="upload_player" value="1">設定</button>
      </div>
    </form>';

      print '<form action="#" method="POST">
      <div class="sticky-top bg-light p-2 shadow rounded text-center">
        <!-- <button type="button" class="btn btn-secondary" onclick="clearAll()">清除</button>-->
        <button type="submit" class="btn btn-outline-dark" name="show_part" value="1">常用</button>
        <button type="submit" class="btn btn-outline-dark" name="show_all" value="1">所有</button>
        <button type="submit" class="btn btn-warning btn-lg" name="enemy" value="1">設為組合1</button>
        <button type="submit" class="btn btn-primary btn-lg" name="up" value="1">設為組合2</button>
      </div>
      <input type="hidden" name="e1" value="' . htmlspecialchars($e1 ?? '') . '">
      <input type="hidden" name="e2" value="' . htmlspecialchars($e2 ?? '') . '">
      <input type="hidden" name="e3" value="' . htmlspecialchars($e3 ?? '') . '">
      

      <br>' . $arr_sl . '<br>
      <br>
    </form>';
    }

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