<!DOCTYPE html>
<?php
require_once('head.php');
date_default_timezone_set("Asia/Taipei");
$date = date("Y-m-d H:i:s");
session_start();



if ($_SESSION["username"] == 'way.lee') {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  /* print '<pre>';
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
  $combined = 2;
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

if (isset($_POST["reset"])) {
  $cost_max = 100;
  $_SESSION["cost_max"] = $cost_max;
  $cost_min = 0;
  $_SESSION["cost_min"] = $cost_min;
} elseif (isset($_POST["combined"])) {
  $combined = $_POST["combined"];
  $_SESSION["combined"] = $combined;
} elseif (isset($_POST["set_time"])) { // 處理提交表單的時間範圍
  $daterange = $_POST["daterange"];
  $_SESSION["daterange"] = $daterange; // 更新 SESSION
}
// 取得當前的日期範圍
$current_daterange = $_SESSION["daterange"];
// 解析開始與結束日期
list($start_date, $end_date) = explode(" - ", $current_daterange);
$end_date = date("Y-m-d", strtotime($end_date)) . " 23:59:59";


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


    <div class="col-md-8">
      <div class="box">
        <div class="box-header">
          <h3 class="box-title">角色使用率</h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
          <table id="example1" class="table table-bordered table-striped">
            <thead>

              <tr>
                <th style="width: 10px">#</th>
                <th>角色</th>
                <th>角色名稱</th>
                <th>使用次數</th>
                <th>勝率</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i = 1;

              $sql = "SELECT 
                    char_usage.char_id AS id,
                    b.ico AS ico,
                    b.name AS name,
                    b.level AS level,
                    SUM(char_usage.count_num) AS count_ttl,
                    SUM(char_usage.win) AS win,
                    SUM(char_usage.lose) AS lose,
                    ROUND((SUM(char_usage.win) / NULLIF(SUM(char_usage.win + char_usage.lose), 0)) * 100, 1) AS rate
                FROM (
                    -- 敵方角色統計
                    SELECT e1 AS char_id, COUNT(*) AS count_num, SUM(lose) AS win, SUM(win) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY e1
                    UNION ALL
                    SELECT e2 AS char_id, COUNT(*) AS count_num, SUM(lose) AS win, SUM(win) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY e2
                    UNION ALL
                    SELECT e3 AS char_id, COUNT(*) AS count_num, SUM(lose) AS win, SUM(win) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY e3
                    UNION ALL

                    -- 我方角色統計
                    SELECT u1 AS char_id, COUNT(*) AS count_num, SUM(win) AS win, SUM(lose) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY u1
                    UNION ALL
                    SELECT u2 AS char_id, COUNT(*) AS count_num, SUM(win) AS win, SUM(lose) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY u2
                    UNION ALL
                    SELECT u3 AS char_id, COUNT(*) AS count_num, SUM(win) AS win, SUM(lose) AS lose, MAX(update_time) AS update_time
                    FROM arena_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') GROUP BY u3
                ) char_usage
                LEFT JOIN unlight b ON char_usage.char_id = b.id
                WHERE char_usage.char_id IS NOT NULL
                GROUP BY char_usage.char_id
                HAVING count_ttl > $total_games  -- 使用 HAVING 避免影響 WHERE 條件
                ORDER BY count_ttl DESC, rate DESC LIMIT 100;
                ";
              //print "sql=$sql";
              //$sql = "SELECT * FROM leway_db.best_combined_unlight WHERE (update_time BETWEEN '$start_date' AND '$end_date') and (total_games > $total_games) order by update_time desc;;";
              //$sql = "SELECT * FROM leway_db.ranking;";
              //$sql = "SELECT * FROM leway_db.view_char_usage_unlight order by count_ttl desc;";


              $arr = $db->query("$sql");
              while ($row = $arr->fetch()) {
                $e1 = $row[0];
                $ico = $row[1];
                $count_ttl = $row[4];
                $rate = round($row[7], 0);
                $rate_color = progress_color($rate);
                $sql2 = "SELECT ico,cost,name,level FROM leway_db.unlight WHERE id='$e1';";
                //print "sql2=$sql2";
                $arr2 = $db->query("$sql2");
                while ($row2 = $arr2->fetch()) {
                  $ico = $row2[0];
                  $name = $row2[2];
                  $level = $row2[3];
                  $ico_arr .= '<img class="zoomout_rank" src="uploads/' . $ico . '" loading="lazy" style="height:80px">';
                  //$cost_sum+=$cost;
                }
                $level_name=$level.$name;
                $search_link = "ranking.php?character=" . urlencode($level_name); // 建立搜尋連結

                print "<tr>
                  <td>" . $i++ . "</td>
                  <td>$ico_arr</td>
                  <td><a href=\"$search_link\" class=\"text-dark\">$level_name</a></td>
                  <td>$count_ttl</td>
                  <td><div class=\"badge bg-$rate_color\">$rate%</div></td>
                </tr>";

                /* print "<tr>
                  <td>" . $i++ . "</td>
                  <td>$ico_arr</td>
                  <td>$level$name</td>
                  <td>$count_ttl</td>
                  <td><div class=\"badge bg-$rate_color\">$rate%</div></td>
                  </tr>"; */
                $ico_arr = '';
                //$name_arr = '';
                //$cost_sum=0;
              }
              ?>
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
    $(function() {
      $('#example1').DataTable({
        'paging': true, // 開啟分頁
        'lengthChange': true, // 啟用每頁顯示筆數的選擇功能
        'searching': true, // 啟用搜尋框
        'ordering': true, // 啟用排序功能
        'info': true, // 顯示表格資訊
        'autoWidth': false // 禁用自動寬度調整

      });
      // 調整搜尋框的寬度
      $('.dataTables_filter input').css({
        'width': '100px', // 設定搜尋框寬度為 300px
        /* 'margin-left': '10px', */ // 增加左側間距
      });
      $('#example2').DataTable({
        'paging': true,
        'lengthChange': true,
        'searching': true,
        'ordering': true,
        'info': true,
        'autoWidth': false,
        "columnDefs": [{
          "type": "num",
          "targets": 0 // 第 0 欄使用數字排序
        }],
        "order": [
          [6, "desc"]
        ] // ✅ 預設以第 4 欄 (BP) 降冪排序
      });
    })

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