<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  $image_filename = null;

  // 1) base64(縮小済み) が送られてきた場合：これを最優先で保存
  if (!empty($_POST['image_base64'])) {
    // 先頭の data:...;base64, を削除してデコード
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);
    $image_binary = base64_decode($base64);

    // 新しいファイル名（JPEG固定：toDataURL('image/jpeg', ...)に合わせる）
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.jpg';
    $filepath =  '/var/www/upload/image/' . $image_filename;

    // 画像を書き込み
    file_put_contents($filepath, $image_binary);

  // 2) base64が無ければ、従来どおりのファイルアップロードを処理
  } elseif (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // 画像MIMEチェック
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      header("HTTP/1.1 302 Found");
      header("Location: ./post_site.php");
      return;
    }
    // 拡張子を取得（なければ png 扱い）
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'] ?? 'png';

    // ランダムな新ファイル名
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;

    // 保存
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // DBへINSERT
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // PRG
  header("HTTP/1.1 302 Found");
  header("Location: ./post_site.php");
  return;
}

$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();

/**
 * 本文を安全に整形して、" >>番号 " を該当投稿へのアンカーに変換する
 */
function bodyFilter (string $body): string
{
  // エスケープ（必須）
  $safe = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  // 改行 → <br>
  $safe = nl2br($safe);

  // レスアンカー >>123 → <a href="#entry123">…</a>
  // htmlspecialchars後なので '>>' は '&gt;&gt;' になっている点に注意
  $safe = preg_replace(
    '/&gt;&gt;(\d+)/',
    '<a href="#entry$1" class="reply-anchor">&gt;&gt;$1</a>',
    $safe
  );

  return $safe;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Post Site</title>
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

    /* ── 見やすさ強化 ── */
    .entries { margin-top: 16px; }
    .entry {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,.03);
      scroll-margin-top: 10px; /* 固定ヘッダがある場合のズレ対策 */
    }
    .entry:target{
      outline: 2px solid #7aa3ff;
      background: rgba(122,163,255,0.08);
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
      white-space: normal; /* bodyFilter内でnl2br済みなのでpre-wrap不要 */
      word-break: break-word;
      margin: 6px 0;
    }
    .entry-image{
      margin-top: 8px;
      border-radius: 8px;
      display: block;
      max-height: 280px;
      object-fit: contain;
      border: 1px solid #f1f5f9;
      background: #f8fafc;
    }
    .reply-anchor{
      text-decoration: underline;
      cursor: pointer;
    }

    /* スマートフォン用 */
    @media (max-width: 420px){
      .entry { padding: 12px; }
      .entry-body { font-size: 15px; }
    }

    dl { display: none; }
  </style>
</head>
<body>
<div class="wrap">
  <!-- フォーム -->
  <form method="POST" action="./post_site.php" enctype="multipart/form-data">
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
      <input type="file" accept="image/*" name="image" id="imageInput">
      <!-- 選択状態の案内（視覚的に「選択されていません」対策） -->
      <div id="selectedInfo" aria-live="polite" style="margin-top:6px;color:#475569;font-size:13px;"></div>
    </div>
    <!-- 縮小後のbase64を送るためのhidden -->
    <input id="imageBase64Input" type="hidden" name="image_base64">
    <!-- 縮小描画用canvas（非表示）-->
    <canvas id="imageCanvas" style="display:none;"></canvas>
    <button type="submit">送信</button>
  </form>

  <hr style="border:none; height:12px;">

  <script>
  // 「5MB超は縮小→可能なら input.files を置換。ダメなら base64 で送信」
  document.addEventListener("DOMContentLoaded", () => {
    const imageInput = document.getElementById("imageInput");
    const imageBase64Input = document.getElementById("imageBase64Input");
    const info = document.getElementById("selectedInfo");
    const canvas = document.getElementById("imageCanvas");
    const ctx = canvas.getContext("2d");
    const LIMIT = 5 * 1024 * 1024; // 5MB

    imageInput.addEventListener("change", () => {
      info.textContent = ""; // 表示クリア
      if (imageInput.files.length < 1) return;

      const file = imageInput.files[0];
      if (!file.type.startsWith('image/')) return;

      const reader = new FileReader();
      const img = new Image();

      reader.onload = () => {
        img.onload = async () => {
          // 縮小サイズの計算（長辺2000px）
          const maxLength = 2000;
          const w = img.naturalWidth, h = img.naturalHeight;
          if (w <= maxLength && h <= maxLength) {
            canvas.width = w; canvas.height = h;
          } else if (w > h) {
            canvas.width = maxLength; canvas.height = Math.round(maxLength * h / w);
          } else {
            canvas.width = Math.round(maxLength * w / h); canvas.height = maxLength;
          }

          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

          // Blob化（JPEG 品質 0.9）
          const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.9));
          if (!blob) return;

          // base64 も hidden に格納（サーバは base64 を優先保存）
          const b64Reader = new FileReader();
          b64Reader.onloadend = () => { imageBase64Input.value = b64Reader.result; };
          b64Reader.readAsDataURL(blob);

          // 5MB超の元ファイルは処理：縮小後が5MB以下なら input.files を差し替え、見た目の「未選択」を回避
          if (file.size > LIMIT) {
            if (blob.size <= LIMIT) {
              const dt = new DataTransfer();
              const newName = (file.name.replace(/\.\w+$/, '') || 'image') + '.jpg';
              const tinyFile = new File([blob], newName, { type: 'image/jpeg' });
              dt.items.add(tinyFile);
              imageInput.files = dt.files; // ← 「選択済み」を維持
              info.textContent = `${newName}（縮小して送信します）`;
            } else {
              // それでも大きい場合は input は空にして base64 のみ送信（UXのため案内を表示）
              imageInput.value = "";
              info.textContent = `${file.name}（縮小して送信します）`;
            }
          } else {
            // もともと5MB以下：通常ファイル送信（案内表示）
            info.textContent = `${file.name}（そのまま送信します）`;
          }
        };
        img.src = reader.result;
      };

      reader.readAsDataURL(file);
    });
  });
  </script>

  <!-- 投稿一覧（カード風で表示） -->
  <div class="entries">
    <?php foreach($select_sth as $entry): ?>
      <?php
        $id = htmlspecialchars($entry['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $created = htmlspecialchars($entry['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $image = isset($entry['image_filename']) && $entry['image_filename'] !== ''
          ? htmlspecialchars($entry['image_filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
          : null;
      ?>
      <article class="entry" id="entry<?= $id ?>">
        <div class="entry-header">
          <span class="badge badge-id">#<?= $id ?></span>
          <time class="badge"><?= $created ?></time>
        </div>
        <div class="entry-body">
          <?= bodyFilter($entry['body']) ?>
        </div>
        <?php if($image): ?>
          <img src="/image/<?= $image ?>" class="entry-image" alt="">
        <?php endif; ?>
      </article>
    <?php endforeach ?>
  </div>
</div>
</body>
</html>
