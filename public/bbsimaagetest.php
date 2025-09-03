<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      // アップロードされたものが画像ではなかった場合
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimaagetest.php");
      return;
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  // ここを bbsimaagetest.php に修正
  header("Location: ./bbsimaagetest.php");
  return;
}

// いままで保存してきたものを取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <title>画像投稿できる掲示板</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* 1. スマートフォン対応デザインのCSS */
    body {
      font-family: sans-serif;
      margin: 0;
      padding: 1em;
    }
    .container {
      max-width: 800px;
      margin: 0 auto;
    }
    h1 {
      text-align: center;
    }
    form {
      display: block;
    }
    textarea {
      width: 100%;
      box-sizing: border-box;
      max-width: 100%;
    }
    .post {
      border: 1px solid #ccc;
      margin-bottom: 1em;
      padding: 1em;
    }
    .post img {
      max-width: 100%;
      height: auto;
    }
    @media (max-width: 600px) {
      body {
        padding: 0.5em;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>画像投稿できる掲示板</h1>
    <form method="POST" action="./bbsimaagetest.php" enctype="multipart/form-data">
      <textarea name="body" required></textarea>
      <div style="margin: 1em 0;">
        <input type="file" accept="image/*" name="image" id="imageInput">
      </div>
      <button type="submit">送信</button>
    </form>

    <hr>

    <?php foreach($select_sth as $entry): ?>
      <dl class="post" id="<?= htmlspecialchars($entry['id']) ?>">
        <dt>ID</dt>
        <dd>
          <a href="#<?= htmlspecialchars($entry['id']) ?>"><?= htmlspecialchars($entry['id']) ?></a>
        </dd>
        <dt>日時</dt>
        <dd><?= htmlspecialchars($entry['created_at']) ?></dd>
        <dt>内容</dt>
        <dd>
          <?= nl2br(htmlspecialchars($entry['body'])) ?>
          <?php if(!empty($entry['image_filename'])): ?>
            <div>
              <img src="/image/<?= htmlspecialchars($entry['image_filename']) ?>">
            </div>
          <?php endif; ?>
        </dd>
      </dl>
    <?php endforeach ?>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const imageInput = document.getElementById("imageInput");

      imageInput.addEventListener("change", async (event) => {
        if (event.target.files.length < 1) {
          return;
        }
        
        const file = event.target.files[0];
        if (!file.type.startsWith('image/')) {
          alert("画像ファイルを選択してください。");
          imageInput.value = "";
          return;
        }

        const MAX_SIZE_MB = 5;
        const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;

        if (file.size > MAX_SIZE_BYTES) {
          try {
            const resizedFile = await resizeImage(file, MAX_SIZE_BYTES);
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(resizedFile);
            imageInput.files = dataTransfer.files;
            alert(`画像を${(resizedFile.size / 1024 / 1024).toFixed(2)}MBに自動縮小しました。`);
          } catch (error) {
            console.error("画像のリサイズ中にエラーが発生しました:", error);
            alert("画像のリサイズに失敗しました。ファイルが大きすぎます。");
            imageInput.value = "";
          }
        }
      });

      function resizeImage(file, maxBytes) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = (readerEvent) => {
            const image = new Image();
            image.onload = () => {
              const canvas = document.createElement('canvas');
              let width = image.width;
              let height = image.height;
              let quality = 0.9;

              if (file.size > maxBytes) {
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(image, 0, 0, width, height);

                canvas.toBlob((blob) => {
                  let resizedBlob = blob;
                  if (resizedBlob.size > maxBytes) {
                    const ratio = Math.sqrt(maxBytes / resizedBlob.size);
                    width = Math.floor(width * ratio);
                    height = Math.floor(height * ratio);
                  }

                  canvas.width = width;
                  canvas.height = height;
                  const ctx2 = canvas.getContext('2d');
                  ctx2.drawImage(image, 0, 0, width, height);
                  
                  canvas.toBlob((finalBlob) => {
                    const newFile = new File([finalBlob], file.name, {
                      type: file.type,
                      lastModified: Date.now()
                    });
                    resolve(newFile);
                  }, file.type);

                }, file.type, quality);
              } else {
                resolve(file);
              }
            };
            image.src = readerEvent.target.result;
          };
          reader.onerror = reject;
          reader.readAsDataURL(file);
        });
      }
    });
  </script>
</body>
</html>
