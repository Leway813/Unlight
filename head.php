<?php

header("Cache-Control:private");
session_start();

/* print '<pre><br><br><br>';
var_dump($_POST);
var_dump($_SESSION);
print '</pre>'; */

function maskUsername($username)
{
  $length = mb_strlen($username); // 取得字串長度
  if ($length <= 2) {
    $first = mb_substr($username, 0, 1); // 取前 1 個字
    return $first . '*';
  }

  $first = mb_substr($username, 0, 2); // 取前 2 個字
  $last = mb_substr($username, -2); // 取最後 2 個字

  return $first . '***' . $last;
}

$ip = '192.168.50.3';
$dbname = 'leway_db';
try {
  $db = new PDO("mysql:host=$ip;dbname=$dbname", 'root', 'Uve%12345');
} catch (PDOException $e) {
  print "Could not connect to the database: " . $e->getMessage();
  exit();
}
date_default_timezone_set("Asia/Taipei");
$logout_button = ''; // 預設為空
$username = "登入";
if (!empty($_POST["username"])) {
  $logout_button = '<button type="submit" name="logout" value="1" class="btn btn-primary">登出</button>';
  $username = $_POST["username"];
  $_SESSION["username"] = $username;
  $sql = "SELECT * FROM leway_db.game_user WHERE LOWER(username) = LOWER('$username');";
  $stmt = $db->query("$sql");
  while ($row = $stmt->fetch()) {
    $result = $row[1];
    $ack = $row[4];
    $_SESSION["ack"] = $ack;
  }
  /* echo "sql=$sql<br>";
  echo "ack=$ack<br>";
  echo "result=$result<br>"; */
  if (isset($_POST["apply"])) {
    if ($ack) {
      echo "<script>alert('此帳號已開通...')</script>";
    } elseif ($result) {
      echo "<script>alert('請稍後，後台即將為您開通。或來信:gbgbdavidlee@gmail.com')</script>";
      echo "sql=$sql<br>";
      $logout_button = ''; // 預設為空
      $username = "登入";
      $_SESSION["username"] = $username;
    } else {

      $sql = "INSERT INTO `leway_db`.`game_user` (`username`, `update_time`, `apply`)  
            VALUES (LOWER(:username), NOW(), 1);";

      $stmt = $db->prepare($sql);
      $stmt->execute([":username" => $username]);

      echo "<script>alert('歡迎加入!等待後台開通。')</script>";
      echo "sql=$sql<br>";
      $logout_button = ''; // 預設為空
      $username = "登入";
      $_SESSION["username"] = $username;
    }
  } elseif (!$result) {
    $sql = "INSERT INTO `leway_db`.`game_user` (`username`, `update_time`)  VALUES (LOWER('$username'),NOW());";
    //print "sql=$sql";
    $db->exec("$sql");
    $logout_button = ''; // 預設為空
    $username = "登入";
    echo "<script>alert('查無使用者')</script>";
  }
} elseif (!empty($_POST["logout"])) {
  //print "logout";
  $username = "登入";
  $_SESSION["username"] = $username;
  $_SESSION["ack"] = 0;
} elseif ($_SESSION["username"]) {
  $username = $_SESSION["username"];
  $logout_button = '<button type="submit" name="logout" value="1" class="btn btn-primary">登出</button>';
}
$ack = $_SESSION["ack"];
$head_bar1 = '
  <div>
    <a style="text-decoration: none;" href="ranking.php">排行榜</a>
    <a style="text-decoration: none;" href="use_rate.php">使用率</a>
    <a style="text-decoration: none;" href="cost.php">COST計算機</a>
  </div>';
if ($ack == 1) {
  $head_bar1 = '
  <div>
    <a style="text-decoration: none;" href="fight.php">對戰紀錄</a>
    <a style="text-decoration: none;" href="ranking.php">排行榜</a>
    <a style="text-decoration: none;" href="use_rate.php">使用率</a>
    <a style="text-decoration: none;" href="cost.php">COST計算機</a>
  </div>';
} elseif ($ack == 2) {
  $head_bar1 = '
  <div>
    <a style="text-decoration: none;" href="fight.php">對戰紀錄</a>
    <a style="text-decoration: none;" href="ranking.php">排行榜</a>
    <a style="text-decoration: none;" href="use_rate.php">使用率</a>
    <a style="text-decoration: none;" href="cost.php">COST計算機</a>
    <a style="text-decoration: none;" href="training.php">詳細資料</a>
    <a style="text-decoration: none;" href="card_back.php">卡背進度</a>
    <a style="text-decoration: none;" href="queue.php">佇列</a>
  </div>';
}

//<a style="text-decoration: none;" href="#about">組隊</a>

$head_bar2 = '
<form action="#" method="POST">
  <div style="float: right;color:#f2f2f2">
    <a data-toggle="modal" data-target="#exampleModal">
      ' . $username . '
    </a>
    ' . $logout_button . '
  </div>
</form>
';
$head_bar = $head_bar1 . $head_bar2;




?>
<!DOCTYPE html>

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title>Unlight</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="icon" href="icon/icon.ico" type="image/x-icon" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/font-awesome/css/font-awesome.min.css">

  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/font-awesome/css/font-awesome.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/Ionicons/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../AdminLTE-master/dist/css/AdminLTE.min.css">
  <!-- AdminLTE Skins. Choose a skin from the css/skins
       folder instead of downloading all of them to reduce the load. -->
  <link rel="stylesheet" href="../AdminLTE-master/dist/css/skins/_all-skins.min.css">
  <!-- Morris chart -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/morris.js/morris.css">
  <!-- jvectormap -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/jvectormap/jquery-jvectormap.css">
  <!-- Date Picker -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/bootstrap-daterangepicker/daterangepicker.css">
  <!-- bootstrap wysihtml5 - text editor -->
  <link rel="stylesheet" href="../AdminLTE-master/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="../AdminLTE-master/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">

  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

  <!-- Google Font -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">



  <style type="text/css">
    .abgne-menu input[type="radio"] {
      display: none;
    }

    .abgne-menu input[type="radio"]+label {
      display: inline-block;
      background-color: #ccc;
      cursor: pointer;
      padding: 4px 4px;
      margin: 1px 1px;
      border-radius: 5px;
    }

    .abgne-menu input[type="radio"]:checked+label {
      background-color: pink;
    }


    .navbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      /* 讓內容均勻分布 */
      background-color: #333;
      top: 0;
      width: 100%;
      padding: 8px 15px;
      z-index: 1000;
      box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
      position: sticky;
      border-top-left-radius: 0;
      border-top-right-radius: 0;
    }

    .navbar a {
      color: #f2f2f2;
      text-align: center;
      padding: 10px 20px;
      text-decoration: none;
      font-size: 16px;
      transition: all 0.3s ease-in-out;
    }

    .navbar a:hover {
      background: #ddd;
      color: black;
      border-radius: 5px;
      /* 只讓按鈕有圓角 */
    }

    /* 修正左上和右上缺角 */
    body {
      margin: 0;
      /* 確保整個頁面不會產生邊距 */
    }



    /* 響應式設計：小螢幕時改為垂直排列 */
    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        align-items: flex-start;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
      }

      .navbar a {
        width: 100%;
        text-align: left;
        padding: 12px;
      }
    }




    .zoomout {
      transition: all 0.2s linear;
    }

    .zoomout:hover {
      transform: scale(3.5);
      transition: all 0.2s linear;
    }

    .zoomout_fight {
      transition: all 0.2s linear;
    }

    .zoomout_fight:hover {
      transform: scale(2.5);
      transition: all 0.2s linear;
      z-index: 999;
      position: relative;
    }

    /* .main {
      padding: 16px;
      height: 30px;
    } */

    #ip_box {
      vertical-align: middle;
      height: 38px;
      width: 85px;
      border-radius: 0.25rem;
      padding: 8px;
    }

    #select_box {
      vertical-align: middle;
      height: 38px;
    }

    #but {
      border: 1px #585858 solid;
      width: 45px;
    }

    #select1 {
      filter: grayscale(50%);
      border: solid red;
      border-radius: 5px;
    }

    /* id="select1" */
    #select2 {
      filter: sepia(50%);
      border: solid blue;
      border-radius: 5px;
    }

    .modal-footer {
      display: flex;
      justify-content: space-between;
    }

    /* .btn{
      margin:0.2rem;
    } */
  </style>
</head>

<body>
  <!-- Modal -->
  <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">輸入帳號</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form action="#" method="POST">
          <div class="modal-body">
            <div class="input-group">
              <input type="text" class="form-control" name="username" placeholder="帳號">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="apply" class="btn btn-success" style="margin-right: auto; ">申請加入</button>
            <button type="submit" class="btn btn-primary">登入</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>

</html>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>