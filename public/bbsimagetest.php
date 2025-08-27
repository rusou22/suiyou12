<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
      return;
    }
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BBS Image Test</title>
  <style>
    html, body {
      margin: 0; padding: 0;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
      line-height: 1.6;
      background: #fafafa;
    }
    .wrap {
      max-width: 800px;
      margin: 0 auto;
      padding: 16px;
    }
    textarea, input[type="file"], button {
      font-size: 16px;
    }
    textarea {
      box-sizing: border-box;
      width: 100%;
      min-height: 8rem;
      padding: 8px;
    }
    button {
      display: inline-block;
      width: 100%;
      padding: 10px 14px;
      margin-top: 8px;
    }
    img { max-width: 100%; height: auto; }

    /* ── ここから一覧の見やすさ強化 ── */
    .entries { margin-top: 16px; }
    .entry {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,.03);
    }
    .entry-header{
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .badge{
      display: inline-block;
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px;
      line-height: 1;
      border: 1px solid #e5e7eb;
      background: #f8fafc;
      color: #334155;
    }
    .badge-id{ font-weight: 700; }
    .entry-body{
      font-size: 15.5px;
      white-space: pre-wrap; /* nl2brと合わせて安全に折り返し */
      word-break: break-word;
      margin: 6px 0;
    }
    .entry-image{
      margin-top: 8px;
      border-radius: 8px;
      display: block;
      max-height: 280px; /* 高さを取りすぎないように */
      object-fit: contain;
      border: 1px solid #f1f5f9;
      background: #f8fafc;
    }
    /* 小さめ画面での余白最適化 */
    @media (max-width: 420px){
      .entry { padding: 12px; }
      .entry-body { font-size: 15px; }
    }

    /* 既存のDLスタイルは使わないので薄く */
    dl { display: none; }
  </style>
</head>
<body>
<div class="wrap">
  <!-- フォーム -->
  <form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
      <input type="file" accept="image/*" name="image" id="imageInput">
    </div>
    <button type="submit">送信ボタン</button>
  </form>

  <hr style="border:none; height:12px;">

  <script>
  document.getElementById('imageInput').addEventListener('change', function (event) {
    const file = event.target.files[0];
    if (file && file.size > 5 * 1024 * 1024) {
      alert('5MB以上の画像はアップロードできません。');
      event.target.value = '';
    }
  });
  </script>

  <!-- 投稿一覧（カード風に再構成して表示） -->
  <div class="entries">
    <?php foreach($select_sth as $entry): ?>
      <article class="entry">
        <div class="entry-header">
          <span class="badge badge-id">#<?= htmlspecialchars($entry['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <time class="badge">
            <?= htmlspecialchars($entry['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </time>
        </div>
        <div class="entry-body">
          <?= nl2br(htmlspecialchars($entry['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
        </div>
        <?php if(!empty($entry['image_filename'])): ?>
          <img
            src="/image/<?= htmlspecialchars($entry['image_filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            class="entry-image" alt="">
        <?php endif; ?>
      </article>
    <?php endforeach ?>
  </div>
</div>
</body>
</html>





