<?php
require_once("../lib/bootloader.php");

$my = getMe();
if (!$my) {
  http_response_code(403);
  exit("ERR:ログインしてください。");
}

if (!$my["is_broadcaster"]) {
  http_response_code(403);
  exit("ERR:あなたには配信権限がありません。");
}

if ($my["live_current_id"]) {
  header("Location: ".u("live_manage"));
  exit();
}

$slot = getAbleSlot();
if (!$slot) {
  http_response_code(503);
  exit("ERR:現在、配信枠が不足しています。");
}

if (isset($_POST["title"]) && isset($_POST["description"]) && isset($_POST["privacy_mode"])) {
  if ($_POST["privacy_mode"] != "1" && $_POST["privacy_mode"] != "2" && $_POST["privacy_mode"] != "3") {
    http_response_code(500);
    exit();
  }

  $tag = $_POST["tag_mode"] == "2" && isset($_POST["tag_custom"]) ? s($_POST["tag_custom"]) : "";
  if ($tag) {
    // thx https://qiita.com/ma7ma7pipipi/items/f4759231390921fbacdd
    if (!preg_match('/(w*[一-龠_ぁ-ん_ァ-ヴーａ-ｚＡ-Ｚa-zA-Z0-9]+|[a-zA-Z0-9_]+|[a-zA-Z0-9_]w*)/', $tag)) {
      exit("ERR:このハッシュタグは使用できません。<a href=''>やり直す</a>");
    }

    $tag = str_replace("#", "", $tag);
  }

  $random = bin2hex(random_bytes(32));
  $is_sensitive = isset($_POST["sensitive"]) ? 1 : 0;

  $mysqli = db_start();
  $stmt = $mysqli->prepare("INSERT INTO `live` (`name`, `description`, `user_id`, `slot_id`, `created_at`, `ended_at`, `is_live`, `ip`, `token`, `privacy_mode`, `custom_hashtag`, `is_sensitive`) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '1', ?, ?, ?, ?, ?);");
  $stmt->bind_param('sssssssss', s($_POST["title"]), s($_POST["description"]), $my["id"], $slot, $_SERVER["REMOTE_ADDR"], $random, s($_POST["privacy_mode"]), $tag, $is_sensitive);
  $stmt->execute();
  $stmt->close();
  $mysqli->close();

  $mysqli = db_start();
  $stmt = $mysqli->prepare("SELECT * FROM `live` WHERE (is_live = 1 OR is_live = 2) AND user_id = ?;");
  $stmt->bind_param("s", $my["id"]);
  $stmt->execute();
  $row = db_fetch_all($stmt);
  $stmt->close();
  $mysqli->close();
  setUserLive($row[0]["id"], $my["id"]);
  setSlot($slot, 1);
  node_update_conf("add", "hashtag", empty($tag) ? "default" : $tag, $row[0]["id"]);
  header("Location: ".u("live_manage"));
  exit();
} elseif ($my["misc"]["to_title"]) {
  $last = getMyLastLive($my["id"]);
}
?>
<!doctype html>
<html lang="ja">
<head>
  <?php include "../include/header.php"; ?>
  <title>配信を始める - <?=$env["Title"]?></title>
</head>
<body>
<?php include "../include/navbar.php"; ?>
<div class="container">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
    <div class="form-group">
      <label for="title">配信タイトル</label>
      <input type="text" class="form-control" id="title" name="title" aria-describedby="title_note" placeholder="タイトル" required value="<?=$last["name"]?>">
      <small id="title_note" class="form-text text-muted">100文字以下</small>
    </div>

    <div class="form-group">
      <label for="description">配信の説明</label>
      <textarea class="form-control" id="description" name="description" rows="4" required><?=$last["description"]?></textarea>
    </div>

    <div class="form-group form-check">
      <input type="checkbox" class="form-check-input" id="sensitive" name="sensitive" value="1">
      <label class="form-check-label" for="sensitive">
        センシティブな配信としてマークする<br>
        <small>ユーザーが配信画面を開く際に警告が表示されます / 後から変更できます</small>
      </label>
    </div>

    <div class="form-check">
      <input class="form-check-input" type="radio" name="privacy_mode" id="privacy_mode1" value="1" checked>
      <label class="form-check-label" for="privacy_mode1">
        公開<br>
        <small>トップページに表示され、誰でも視聴できます</small>
      </label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="privacy_mode" id="privacy_mode2" value="2">
      <label class="form-check-label" for="privacy_mode2">
        未収載<br>
        <small>トップページに表示されませんが、URLがあれば誰でも視聴できます</small>
      </label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="privacy_mode" id="privacy_mode3" value="3">
      <label class="form-check-label" for="privacy_mode3">
        非公開<br>
        <small>トップページに表示されず、視聴にはログインが必要です</small><br>
        <small>* あなたのフォロワーでなくても、KnzkLiveにログインしていれば視聴できます</small>
      </label>
    </div>

    <hr>
    <b>ハッシュタグ設定:</b><br>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="tag_mode" id="tag_mode1" value="1" checked>
      <label class="form-check-label" for="tag_mode1">
        連番を使用<br>
        <small>#knzklive_(連番) を使用します</small>
      </label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="tag_mode" id="tag_mode2" value="2">
      <label class="form-check-label" for="tag_mode2">
        カスタム<br>
        <small>他のユーザーが既に使用していないか確認した上で設定してください</small>
        <input type="text" class="form-control" id="tag_custom" name="tag_custom" placeholder="ハッシュタグ名(#は必要なし)">
      </label>
    </div>
    <hr>
    <div class="form-group form-check">
      <input type="checkbox" class="form-check-input" id="term" required>
      <label class="form-check-label" for="term"><a href="<?=u("terms")?>" target="_blank">利用規約・ガイドライン</a>に同意する</label>
    </div>

    <button type="submit" class="btn btn-primary">配信枠を取得</button>
  </form>
</div>

<?php include "../include/footer.php"; ?>
</body>
</html>
