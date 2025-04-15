<!DOCTYPE html>
<?php
date_default_timezone_set("Asia/Taipei");
$date = date("Y-m-d H:i:s");
ob_start(); // 啟動輸出緩衝
session_start(); // 如果有使用 session
//重置
if (isset($_POST["reset"])) {
  $cost_max = 100;
  $_SESSION["cost_max"] = $cost_max;
  $cost_min = 0;
  $_SESSION["cost_min"] = $cost_min;
  $_SESSION["player_search"] = '';
  $player_search='';
  header("Location: ranking.php"); // ✅ 將網址中的 ?player_search 清除
  exit; // ⛳ 必須中止程式，不然會繼續往下執行
} elseif (isset($_POST["combined"])) {
  $combined = $_POST["combined"];
  $_SESSION["combined"] = $combined;
} elseif (isset($_POST["set_time"])) { // 處理提交表單的時間範圍
  $daterange = $_POST["daterange"];
  $_SESSION["daterange"] = $daterange; // 更新 SESSION
}

require_once('head.php');

if ($_SESSION["username"] == 'way.lee') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  /*   print '<pre>';
  var_dump($_GET);
  var_dump($_POST);
  var_dump($_SESSION);
  print '</pre>'; */
}

$ack = $_SESSION["ack"] ?? 0;

function progress_color($rate)
{
  if ($rate < 25) {
    $color = 'red';
  } elseif ($rate < 50) {
    $color = 'yellow';
  } elseif ($rate < 75) {
    $color = 'blue';
  } else {
    $color = 'green';
  }
  return $color;
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
$name_arr = '';
$ico_arr = '';
$cost_sum  = 0;

if (isset($_SESSION["cost_min"])) {
  $cost_min = $_SESSION["cost_min"];
} else {
  $cost_min = 0;
}
if (isset($_SESSION["cost_max"])) {
  $cost_max = $_SESSION["cost_max"];
} else {
  $cost_max = 100;
}
if (isset($_SESSION["combined"])) {
  $combined = $_SESSION["combined"];
} else {
  $combined = 2; //預設玩家紀錄
}

// 預設顯示本月
if (!isset($_SESSION["daterange"])) {
  $_SESSION["daterange"] = date('Y-m-01') . " - " . date('Y-m-t');
}


if (isset($_SESSION["total_games"])) {
  $total_games = $_SESSION["total_games"];
} else {
  $total_games = 0;
  $_SESSION["total_games"] = 0;
  //print "total_games=$total_games";
}


$search_team = 0;
$twovtwo = 0;
if (isset($_POST["cost_min"])) {
  $cost_min = $_POST["cost_min"];
  $_SESSION["cost_min"] = $cost_min;
  $cost_max = $_POST["cost_max"];
  $_SESSION["cost_max"] = $cost_max;
  $total_games = $_POST["total_games"];
  $_SESSION["total_games"] = $total_games;
} elseif (isset($_POST["search_team"])) {

  $id1 = $_POST["id1"];
  $id2 = $_POST["id2"];
  $id3 = $_POST["id3"];
  if ($id3 == 0) {
    $twovtwo = 1;

    // 放入陣列並排序
    $ids = [$id1, $id2, $id3];
    sort($ids); // 從小到大排序

    // 排序後重新分配回 $id1, $id2, $id3
    //$id1 = $ids[0];
    $id1 = $ids[1];
    $id2 = $ids[2];
  } else {
    // 放入陣列並排序
    $ids = [$id1, $id2, $id3];
    sort($ids); // 從小到大排序

    // 排序後重新分配回 $id1, $id2, $id3
    $id1 = $ids[0];
    $id2 = $ids[1];
    $id3 = $ids[2];
  }
  $search_team = 1;
}
// 取得當前的日期範圍
$current_daterange = $_SESSION["daterange"];
//print "combined=$combined";
//print "total_games=$total_games";

$player_search = '';

// 優先使用 POST 提交的值（如搜尋欄位送出）
if (isset($_POST['player_search'])) {
  $player_search = trim($_POST['player_search']);
  $_SESSION["player_search"] = $player_search;
}
// 若沒有 POST，但 Session 中已有保存過的搜尋條件
elseif (isset($_SESSION["player_search"])) {
  $player_search = $_SESSION["player_search"];
}


?>
<html lang="en">

<head>
  <style>
    /* 調整 DataTables 搜尋框的寬度 */
    /* 調整搜尋欄位的寬度 */
    .box .dataTables_filter input {
      width: 100px;
      /* 設定你需要的寬度 */
    }
  </style>
  <style>
    /* 按鈕樣式 */
    #backToTop {
      position: fixed;
      bottom: 30px;
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

    #backToTop:hover {
      background-color: #0056b3;
    }

    .zoomout_rank {
      transition: all 0.2s linear;
    }

    .zoomout_rank:hover {
      transform: scale(2);
      transition: all 0.2s linear;
    }

    .image-flex {
      display: flex;
      flex-direction: row;
      align-items: center;
      /* position: relative; */
    }

    .image-flex img {
      height: auto;
      transition: transform 0.3s ease-in-out;
    }

    .image-flex img:first-child {
      margin-left: 0;
      /* 第一張圖片不偏移 */
    }

    .image-flex img:hover {
      transform: scale(1.2);
      /* 滑鼠移上去放大 */
      z-index: 10;
    }


    .flip-container {
      perspective: 1000px;
      display: inline-block;
      position: relative;
      /*  */
    }

    .flipper {
      position: relative;
      width: 70px;
      height: 110px;
      transform-style: preserve-3d;
      transition: transform 0.6s;
    }

    .flip-container.flipped .flipper {
      transform: rotateY(180deg);
    }

    .front,
    .back {
      position: absolute;
      width: 100%;
      height: 100%;
      backface-visibility: hidden;
    }

    /* 翻轉後的背面 */
    .back {
      transform: rotateY(180deg);
      z-index: 11;
      position: absolute;
      top: -40px;
      /* 向上移動，避免被文字擋住 */
      left: 30px;
      /* width: 140px; 
  height: 220px; */
    }

    /* 背面圖片放大 */
    .flip-container.flipped .back img {
      transform: scale(2.1);
      width: 100%;
      height: 100%;
    }

    /* 查閱按鈕 */
    .chk-btn {
      background: rgb(171, 170, 170);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 1px 10px;
      margin: 2px 1px;
      transform: scale(1.1);
    }

    /* 勝利按鈕 */
    .enemy-win-btn {
      background: #ff4e4e;
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 1px 2px;
      margin: 2px -4px;
      transform: scale(1.1);
    }

    .player-win-btn {
      background: rgb(60, 153, 253);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      padding: 1px 2px;
      margin: 2px -4px;
      transform: scale(1.1);
    }
  </style>
</head>

<body>
  <div class="navbar">
    <?php
    print $head_bar;
    ?>
  </div>
  <button id="backToTop" onclick="scrollToTop()">▲</button>

  <script>
    // 監聽滾動事件，決定是否顯示按鈕
    window.onscroll = function() {
      let button = document.getElementById("backToTop");
      if (document.documentElement.scrollTop > 200) {
        button.style.display = "flex"; // 顯示按鈕
      } else {
        button.style.display = "none"; // 隱藏按鈕
      }
    };

    // 點擊按鈕回到頂部
    function scrollToTop() {
      window.scrollTo({
        top: 0,
        behavior: "smooth" // 平滑滾動效果
      });
    }
  </script>
  <div class="row" style="margin:auto 20px">
    <div class="col-md-12">
      <div class="box">
        <div class="box-header">
          <div class="container-fluid my-3">
            <form method="POST" class="row g-3 align-items-center" style="margin: 0px -30px;">

              <!-- 第一列 -->
              <div class="col-12 d-md-flex align-items-center justify-content-between" style="margin-bottom: 50px;">

                <!-- 時間選取 -->
                <div class="col-md-4">
                  <div class="input-group">
                    <button type="button" class="btn btn-outline-secondary" id="daterange-btn">
                      <i class="fa fa-calendar"></i> 時間選取 <i class="fa fa-caret-down"></i>
                    </button>
                    <input type="hidden" name="daterange" id="daterange" value="<?php echo $current_daterange; ?>">
                    <button type="submit" name="set_time" value="1" class="btn btn-primary">提交</button>
                  </div>
                </div>

                <!-- 雙人 / 3V3 切換 -->
                <div class="col-md-4 d-flex justify-content-md-end mt-2 mt-md-0">
                  <?php
                  print '<button type="submit" name="combined" value="0" class="btn btn-success">3人組合</button>';
                  print '<button type="submit" name="combined" value="1" class="btn btn-info">雙人組</button>';
                  print '<button type="submit" name="combined" value="2" class="btn btn-default">玩家統計</button>';
                  ?>
                </div>
                <!-- 玩家名稱查詢 -->
                <div class="col-md-4 mt-2 mt-md-0">
                  <div class="input-group">
                    <input type="text" name="player_search" id="player_search" class="form-control" placeholder="輸入玩家名稱查詢" value="<?php echo htmlspecialchars($player_search ?? ''); ?>">
                    <div class="input-group-btn">
                      <button type="button" class="btn btn-outline-secondary" id="clearSearch" style="display: none;">✖</button>
                      <button type="submit" class="btn btn-outline-primary">搜尋</button>
                    </div>
                  </div>
                </div>



              </div>

              <!-- 第二列 -->
              <div class="col-12 d-md-flex align-items-center justify-content-between">

                <!-- COST區間 -->
                <div class="col-md-2 d-flex align-items-center" style="margin-top: 8px;">
                  <label class="me-2">COST區間 :</label>
                </div>
                <div class="col-md-2 d-flex align-items-center">
                  <input type="number" name="cost_min" class="form-control me-2" placeholder="最小值" value="<?php echo $cost_min; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-center">
                  <input type="number" name="cost_max" class="form-control" placeholder="最大值" value="<?php echo $cost_max; ?>">
                </div>

                <!-- 總場次 -->
                <div class="col-md-1 d-flex align-items-center mt-2 mt-md-0" style="margin-top: 8px;">
                  <label class="me-2">總場></label>
                </div>
                <div class="col-md-2 d-flex align-items-center mt-2 mt-md-0">
                  <input type="number" name="total_games" class="form-control" placeholder="總場次" value="<?php echo $total_games; ?>">
                </div>

                <!-- 設定 / 重置按鈕 -->
                <div class="col-md-3 d-flex justify-content-end mt-2 mt-md-0">
                  <button type="submit" class="btn btn-primary me-2">設定</button>
                  <button type="submit" name="reset" value="1" class="btn btn-warning">重置</button>
                </div>

              </div>

            </form>

          </div>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
          <table id="example2" class="table table-bordered table-striped">
            <thead>

              <tr>
                <th style="width: 10px">#</th>

                <?php
                $orderation = 7;
                if ($combined == 2) { //玩家統計
                  print "
                  <th>最近使用</th>
                  <th>角色名稱</th>
                  <th>cost</th>
                  <th>勝</th>
                  <th>負</th>
                  <th>勝率</th>
                  <th>BP</th>
                  <th>玩家</th>";
                  $orderation = 7;
                } else {
                  print "
                  <th>Deck 排組</th>
                  <th>角色名稱</th>
                  <th>cost</th>
                  <th>勝</th>
                  <th>負</th>
                  <th>總</th>
                  <th>勝率</th>";
                  $orderation = 6;
                }
                ?>
                <th>更新日期</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i = 1;
              $total_games = intval($total_games ?? 0);
              $limit = 100;

              // 設定預設 SQL 查詢（無篩選條件）
              // 解析開始與結束日期
              list($start_date, $end_date) = explode(" - ", $current_daterange);
              $end_date = date("Y-m-d", strtotime($end_date)) . " 23:59:59";
              // 確保日期格式正確，避免 SQL 錯誤
              if (strtotime($start_date) && strtotime($end_date)) {
                if ($player_search !== '') {
                  $combined = 2;
                  $_SESSION["combined"] = 2;
                  $sql = "WITH combined AS (
                      SELECT 
                        e1 AS char1, e2 AS char2, e3 AS char3,
                        lose AS win, win AS lose,
                        bp_p1 AS bp,
                        name_p1 AS player_name,
                        name_p2 AS enemy_name,
                        bp_p2 AS enemy_bp,
                        draw_p1 AS tie,
                        update_time, username
                      FROM arena_unlight
                      WHERE update_time BETWEEN '$start_date' AND '$end_date'
                        AND ack1 = 1 AND ack2 = 1

                      UNION ALL

                      SELECT 
                        u1 AS char1, u2 AS char2, u3 AS char3,
                        win, lose,
                        bp_p2 AS bp,
                        name_p2 AS player_name,
                        name_p1 AS enemy_name,
                        bp_p1 AS enemy_bp,
                        tie AS tie,
                        update_time, username
                      FROM arena_unlight
                      WHERE update_time BETWEEN '$start_date' AND '$end_date'
                        AND ack1 = 1 AND ack2 = 1
                    ),
                    latest AS (
                      SELECT *
                      FROM (
                        SELECT *, ROW_NUMBER() OVER (PARTITION BY player_name ORDER BY update_time DESC) AS rn
                        FROM combined
                      ) AS ranked
                      WHERE rn = 1
                    ),
                    summary AS (
                      SELECT 
                        player_name,
                        SUM(win) AS total_win,
                        SUM(lose) AS total_lose
                      FROM combined
                      GROUP BY player_name
                    )
                    SELECT 
                      latest.char1, latest.char2, latest.char3,
                      summary.total_win, summary.total_lose,
                      latest.bp, latest.player_name, latest.update_time, latest.username,
                      ROUND(summary.total_win / (summary.total_win + summary.total_lose) * 100, 1) AS win_rate,
                      CASE WHEN latest.win = 1 THEN 1 ELSE 0 END AS last_win,
                      CASE WHEN latest.tie = 1 THEN 1 ELSE 0 END AS last_tie,
                      latest.enemy_bp
                    FROM latest
                    JOIN summary ON latest.player_name = summary.player_name
                    WHERE latest.player_name LIKE :search";
                } elseif ($search_team) {
                  if ($combined) {
                    $sql = "SELECT * FROM leway_db.best_combined_unlight WHERE (char1='$id1' and char2='$id2') and (update_time BETWEEN '$start_date' AND '$end_date') order by update_time desc LIMIT $limit;";
                  } elseif ($twovtwo == 1) {
                    $sql = "SELECT * FROM leway_db.best_3v3_teams_unlight WHERE ((char1='$id1' and char2='$id2') or (char1='$id1' and char3='$id2')or(char2='$id1' and char3='$id2'))and (update_time BETWEEN '$start_date' AND '$end_date') order by update_time desc LIMIT $limit;";
                  } else {
                    $sql = "SELECT * FROM leway_db.best_3v3_teams_unlight WHERE (char1='$id1' and char2='$id2' and char3='$id3') and (update_time BETWEEN '$start_date' AND '$end_date') order by update_time desc LIMIT $limit;";
                  }
                } elseif ($combined == 1) {
                  $sql = "SELECT * FROM leway_db.best_combined_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') and (total_games > $total_games) order by update_time desc LIMIT $limit;";
                } elseif ($combined == 2) { //玩家統計                  
                  $sql = "WITH combined AS (
                  SELECT 
                    e1 AS char1, e2 AS char2, e3 AS char3,
                    lose AS win, win AS lose,
                    bp_p1 AS bp,
                    name_p1 AS player_name,
                    name_p2 AS enemy_name,
                    bp_p2 AS enemy_bp,
                    draw_p1 AS tie,
                    update_time, username
                  FROM arena_unlight
                  WHERE update_time BETWEEN '$start_date' AND '$end_date'
                    AND ack1 = 1 AND ack2 = 1

                  UNION ALL

                  SELECT 
                    u1 AS char1, u2 AS char2, u3 AS char3,
                    win, lose,
                    bp_p2 AS bp,
                    name_p2 AS player_name,
                    name_p1 AS enemy_name,
                    bp_p1 AS enemy_bp,
                    tie AS tie,
                    update_time, username
                  FROM arena_unlight
                  WHERE update_time BETWEEN '$start_date' AND '$end_date'
                    AND ack1 = 1 AND ack2 = 1
                ),
                latest AS (
                  SELECT *
                  FROM (
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY player_name ORDER BY update_time DESC) AS rn
                    FROM combined
                  ) AS ranked
                  WHERE rn = 1
                ),
                summary AS (
                  SELECT 
                    player_name,
                    SUM(win) AS total_win,
                    SUM(lose) AS total_lose
                  FROM combined
                  GROUP BY player_name
                )
                SELECT 
                  latest.char1, latest.char2, latest.char3,
                  summary.total_win, summary.total_lose,
                  latest.bp, latest.player_name, latest.update_time, latest.username,
                  ROUND(summary.total_win / (summary.total_win + summary.total_lose) * 100, 1) AS win_rate,
                  CASE WHEN latest.win = 1 THEN 1 ELSE 0 END AS last_win,
                  CASE WHEN latest.tie = 1 THEN 1 ELSE 0 END AS last_tie,
                  latest.enemy_bp
                FROM latest
                JOIN summary ON latest.player_name = summary.player_name
                ORDER BY bp DESC
                LIMIT $limit;";
                } else {
                  $sql = "SELECT * FROM leway_db.best_3v3_teams_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') and (total_games > $total_games) order by update_time desc LIMIT $limit;";
                }
              }
              // 執行查詢
              $stmt = $db->prepare($sql);

              // 若有日期範圍，綁定參數
              if (isset($start_date) && isset($end_date)) {
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
              }
              if ($player_search !== '') {
                $stmt->execute([':search' => '%' . $player_search . '%']);
                //print "sql =$sql ";
              } else {
                $stmt->execute();
              }

              // 取得結果
              while ($row = $stmt->fetch()) {
                // 處理數據...
                $e1 = $row[0];
                $e2 = $row[1];
                $sql2 = '';
                $player_name = '';
                if ($combined == 1) { //2人組合
                  $e3 = 0;
                  $win = $row[2];
                  $lose = $row[3];
                  $all = $row[4];
                  $rate = round($row[5], 0);
                  $update_time = $row[6];
                  $last_username = $row[7];
                  $masked_username = maskUsername($last_username);
                  $rate_color = progress_color($rate);
                  $sql2 = "SELECT ico,cost,name,level,ico_back FROM leway_db.unlight WHERE id='$e1' or id='$e2';";
                } elseif ($combined == 2) { //玩家紀錄

                  $e3 = $row[2];
                  $win = $row[3];
                  $lose = $row[4]; //bp
                  $bp = $row[5]; //bp
                  $player_name = $row[6];
                  $update_time = $row[7];
                  $last_username = $row[8];
                  $masked_username = maskUsername($last_username);
                  $rate = round($row[9], 0);
                  $rate_color = progress_color($rate);

                  $result_win = $row[10];
                  $result_tie = $row[11];
                  $opponent_bp = $row[12];
                  $result = 0;
                  if ($result_win == 1) {
                    $result = 1;
                  } elseif ($result_tie == 1) {
                    $result = 0.5;
                  } else {
                    $result = 0;
                  }
                  $bp_after = round($bp + 32 * ($result - 1 / (1 + pow(10, (($opponent_bp - $bp) / 500)))), 0);
                  //$bp_after = ceil($bp + 32 * ($result - 1 / (1 + pow(10, (($opponent_bp - $bp) / 400)))));
                  //$rate_color = progress_color($rate);
                  $sql2 = "SELECT ico,cost,name,level,ico_back FROM leway_db.unlight WHERE id='$e1' or id='$e2'or id='$e3';";
                } else { //3人組合
                  $e3 = $row[2];
                  $win = $row[3];
                  $lose = $row[4];
                  $all = $row[5];
                  $rate = round($row[6], 0);
                  $update_time = $row[7];
                  $last_username = $row[8];
                  $masked_username = maskUsername($last_username);
                  $rate_color = progress_color($rate);
                  $sql2 = "SELECT ico, cost, name, level, ico_back 
                  FROM leway_db.unlight 
                  WHERE id IN ('$e1', '$e2', '$e3') 
                  ORDER BY FIELD(id, '$e1', '$e2', '$e3');";
                }
                /* if ($_SESSION["username"] == 'way.lee') {
                  error_reporting(E_ALL);
                  ini_set('display_errors', 1);
                
                print "<br>sql2=$sql2";
                } */
                $arr2 = $db->query("$sql2");
                while ($row2 = $arr2->fetch()) {
                  $ico_front = 'uploads/' . $row2[0];  // 正面圖片
                  $ico_back = !empty($row2[4]) ? 'uploads/' . $row2[4] : ''; // 背面圖片
                  $cost = $row2[1];
                  $name = $row2[2];
                  $level = $row2[3];
                  $name_arr .= $level . $name . "<br>";
                  if (!empty($ico_back)) {
                    // 如果有背面圖，加入翻轉效果
                    $ico_arr .= "
                    <div class='flip-container' onclick='this.classList.toggle(\"flipped\");'>
                        <div class='flipper'>
                            <div class='front'>
                                <img class='zoomout_rank' src='$ico_front' loading='lazy' style='height:110px'>
                            </div>
                            <div class='back'>
                                <img class='zoomout_rank' src='$ico_back' loading='lazy' style='height:110px'>
                            </div>
                        </div>
                    </div>";
                  } else {
                    // 沒有背面圖，只顯示正面
                    $ico_arr .= "<img class='zoomout_rank' src='$ico_front' loading='lazy' style='height:110px'>";
                  }
                  $cost_sum += $cost;
                  $cost_arr[] = $cost;
                }
                if (!is_array($cost_arr) || count($cost_arr) < 3) {
                  $punishment = 0;
                } else {
                  $punishment = cost_punish($cost_arr[0], $cost_arr[1], $cost_arr[2]);
                }
                $cost_sum += $punishment;
                $cost_color = ($punishment > 0) ? 'text-danger' : ''; // 設定紅色類別

                if ($cost_sum >= $cost_min && $cost_sum <= $cost_max) {
                  print "<tr>
                    <td>" . $i++;
                  print "<form action='fight.php' method='POST'>
                          <input class='chk-btn' type='submit' value='查'>
                          <input type='hidden' name='e1' value='$e1'>
                          <input type='hidden' name='e2' value='$e2'>
                          <input type='hidden' name='e3' value='$e3'>   
                          <input type='hidden' name='player_name' value='$player_name'>         
                        </form>";
                  if ($ack) {
                    print '<div class="popup-buttons">';
                    print "                    
                            <button class='enemy-win-btn' type='button' onclick=\"selectTeam(1, '$e1', '$e2', '$e3', '$player_name')\">組合 1</button>
                            <button class='player-win-btn' type='button' onclick=\"selectTeam(2, '$e1', '$e2', '$e3', '$player_name')\">組合 2</button>
                            ";
                    print '</div>';
                  }
                  echo "</td>";
                  print "
                    <td class=\"image-flex\">$ico_arr</td>
                    <td>$name_arr</td>
                    <td class='$cost_color'>$cost_sum</td> <!-- 如果有懲罰則變紅色 -->";
                  if ($combined == 2) { //玩家統計
                    print "
                    <td>$win</td>
                    <td>$lose</td>
                    <td><div class=\"badge bg-$rate_color\">$rate%</div></td>
                    <td>$bp_after</td>";
                    print "<td>$player_name</td>";
                  } else {
                    $sum = $win + $lose;
                    print "
                    <td>$win</td>
                    <td>$lose</td>
                    <td>$sum</td>
                    <td><div class='badge bg-$rate_color'>$rate%</div></td>";
                  }
                  print "<td>$update_time<br>
                    by $masked_username</td>
                </tr>";
                }

                $ico_arr = '';
                $name_arr = '';
                $cost_sum = 0;
                unset($cost_arr);
              }
              ?>
              <script>
                // ✅ 只在 POPUP 視窗內顯示 按鈕
                if (window.opener) {
                  document.querySelectorAll(".popup-buttons").forEach(function(el) {
                    el.style.display = "block"; // 顯示按鈕
                  });
                } else {
                  document.querySelectorAll(".popup-buttons").forEach(function(el) {
                    el.style.display = "none"; // 隱藏按鈕
                  });
                }

                function selectTeam(team, e1, e2, e3, player_name) {
                  if (window.opener) {
                    window.opener.setSelectedCharacters(team, e1, e2, e3, player_name);
                    window.close(); // ✅ 選擇完成後關閉 POP 視窗
                  }
                }
              </script>

            </tbody>
            <!-- <tfoot>
                <tr>
                  <th>Rendering engine</th>
                  <th>Browser</th>
                  <th>Platform(s)</th>
                  <th>Engine version</th>
                </tr>
              </tfoot> -->
          </table>
        </div>
        <!-- /.box-body -->
      </div>
      <!-- /.box -->
    </div>
    <!-- /.col -->


  </div>



  <!-- jQuery 3 -->
  <script src="../AdminLTE-master/bower_components/jquery/dist/jquery.min.js"></script>
  <!-- Bootstrap 3.3.7 -->
  <script src="../AdminLTE-master/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  <!-- DataTables -->
  <script src="../AdminLTE-master/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
  <script src="../AdminLTE-master/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
  <!-- SlimScroll -->
  <script src="../AdminLTE-master/bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>
  <!-- FastClick -->
  <script src="../AdminLTE-master/bower_components/fastclick/lib/fastclick.js"></script>
  <!-- AdminLTE App -->
  <script src="../AdminLTE-master/dist/js/adminlte.min.js"></script>
  <!-- AdminLTE for demo purposes -->
  <script src="../AdminLTE-master/dist/js/demo.js"></script>

  <!-- date-range-picker -->
  <script src="../AdminLTE-master/bower_components/moment/min/moment.min.js"></script>
  <script src="../AdminLTE-master/bower_components/bootstrap-daterangepicker/daterangepicker.js"></script>
  <!-- bootstrap datepicker -->
  <script src="../AdminLTE-master/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>

  <!-- page script -->
  <script>
    // 取得網址中的 character 值
    function getQueryParam(param) {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.get(param);
    }

    $(function() {
      const playerSearch = getQueryParam("character");

      const table2 = $('#example2').DataTable({
        'paging': true,
        'lengthChange': true,
        'searching': true,
        'ordering': true,
        'info': true,
        'autoWidth': false,
        "columnDefs": [{
          "type": "num",
          "targets": 0
        }],
        "order": [
          [<?php echo $orderation; ?>, "desc"]
        ]
      });

      // ✅ 自動將搜尋值套入 DataTables 搜尋框
      if (playerSearch) {
        table2.search(playerSearch).draw();
      }

      // ✅ 可選：調整搜尋框寬度
      $('.dataTables_filter input').css({
        'width': '200px'
      });
    });


    //Date range picker
    $(document).ready(function() {
      // 檢查 localStorage 是否已儲存上次選擇的日期範圍
      let sessionRange = "<?php echo $current_daterange; ?>";

      let start = moment(sessionRange.split(" - ")[0]); // 解析開始日期
      let end = moment(sessionRange.split(" - ")[1]); // 解析結束日期

      $('#daterange-btn').daterangepicker({
        locale: {
          format: 'YYYY-MM-DD',
          applyLabel: '確定',
          cancelLabel: '取消',
          customRangeLabel: '自訂範圍'
        },
        startDate: start,
        endDate: end,
        ranges: {
          '今天': [moment(), moment()],
          '昨天': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          '最近 7 天': [moment().subtract(6, 'days'), moment()],
          '最近 30 天': [moment().subtract(29, 'days'), moment()],
          '本月': [moment().startOf('month'), moment().endOf('month')],
          '上個月': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        }
      }, function(start, end) {
        // 設定選擇的日期範圍到隱藏的 input
        $('#daterange').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));

        // 更新按鈕顯示的日期範圍
        $('#daterange-btn').html('<i class="fa fa-calendar"></i> ' + start.format('YYYY-MM-DD') + ' 至 ' + end.format('YYYY-MM-DD') + ' <i class="fa fa-caret-down"></i>');

        // 儲存選擇的日期範圍到 localStorage
        localStorage.setItem('daterange', start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
      });

      // 初始化按鈕顯示上次選擇的日期範圍
      $('#daterange-btn').html('<i class="fa fa-calendar"></i> ' + start.format('YYYY-MM-DD') + ' 至 ' + end.format('YYYY-MM-DD') + ' <i class="fa fa-caret-down"></i>');
      $('#daterange').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
    });
  </script>

</body>

</html>