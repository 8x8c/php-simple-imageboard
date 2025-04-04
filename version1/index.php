<?php
declare(strict_types=1);

$db = new SQLite3(__DIR__ . '/board.db');
$db->exec('PRAGMA journal_mode = WAL');
$db->exec('CREATE TABLE IF NOT EXISTS threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    filename TEXT,
    created_at INTEGER NOT NULL,
    bumped_at INTEGER NOT NULL
)');
$db->exec('CREATE TABLE IF NOT EXISTS replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    created_at INTEGER NOT NULL
)');

if (!is_dir(__DIR__.'/uploads')) {
    mkdir(__DIR__.'/uploads', 0777, true);
}

// Create a new thread
if (isset($_POST['action']) && $_POST['action'] === 'newthread') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($title !== '' && $message !== '') {
        $filename = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png','image/gif','image/jpeg','video/mp4'];
            if (in_array($_FILES['file']['type'], $allowed, true)) {
                $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $uniq = uniqid('upload_', true).'.'.$ext;
                move_uploaded_file($_FILES['file']['tmp_name'], __DIR__.'/uploads/'.$uniq);
                $filename = $uniq;
            }
        }
        $time = time();
        $stmt = $db->prepare('INSERT INTO threads (title, message, filename, created_at, bumped_at)
                              VALUES (:t, :m, :f, :c, :b)');
        $stmt->bindValue(':t', $title, SQLITE3_TEXT);
        $stmt->bindValue(':m', $message, SQLITE3_TEXT);
        $stmt->bindValue(':f', $filename, SQLITE3_TEXT);
        $stmt->bindValue(':c', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':b', $time, SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Reply to a thread
if (isset($_POST['action']) && $_POST['action'] === 'reply') {
    $thread_id = (int)($_POST['thread_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($thread_id > 0 && $message !== '') {
        $time = time();
        $stmt = $db->prepare('INSERT INTO replies (thread_id, message, created_at)
                              VALUES (:tid, :msg, :c)');
        $stmt->bindValue(':tid', $thread_id, SQLITE3_INTEGER);
        $stmt->bindValue(':msg', $message, SQLITE3_TEXT);
        $stmt->bindValue(':c', $time, SQLITE3_INTEGER);
        $stmt->execute();

        $stmt = $db->prepare('UPDATE threads SET bumped_at = :b WHERE id = :tid');
        $stmt->bindValue(':b', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':tid', $thread_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?thread='.$thread_id);
    exit;
}

// Display
$thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PHP Imageboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
if ($thread_id > 0):
    $stmt = $db->prepare('SELECT * FROM threads WHERE id = :tid');
    $stmt->bindValue(':tid', $thread_id, SQLITE3_INTEGER);
    $tRes = $stmt->execute();
    $thread = $tRes->fetchArray(SQLITE3_ASSOC);
    if ($thread):
?>
    <div class="reply-form">
      <h2>Reply to Thread</h2>
      <form method="post" action="?thread=<?php echo $thread_id; ?>">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
        <textarea name="message" rows="4" cols="50" placeholder="Your reply..."></textarea><br>
        <button type="submit">Reply</button>
      </form>
    </div>

    <div class="thread">
      <?php if ($thread['filename']): ?>
          <?php $ext = strtolower(pathinfo($thread['filename'], PATHINFO_EXTENSION)); ?>
          <?php if ($ext === 'mp4'): ?>
              <video src="uploads/<?php echo htmlspecialchars($thread['filename']); ?>" width="200" controls></video>
          <?php else: ?>
              <img src="uploads/<?php echo htmlspecialchars($thread['filename']); ?>" width="200" alt="file">
          <?php endif; ?>
      <?php endif; ?>
      <div>
        <h3><?php echo htmlspecialchars($thread['title']); ?></h3>
        <p><?php echo nl2br(htmlspecialchars($thread['message'])); ?></p>
      </div>
    </div>

    <div class="replies">
      <?php
        $stmt = $db->prepare('SELECT * FROM replies WHERE thread_id = :tid ORDER BY created_at ASC');
        $stmt->bindValue(':tid', $thread_id, SQLITE3_INTEGER);
        $rRes = $stmt->execute();
        while ($reply = $rRes->fetchArray(SQLITE3_ASSOC)):
      ?>
        <div class="reply">
          <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
        </div>
      <?php endwhile; ?>
    </div>

<?php
    else:
        echo '<p>Thread not found!</p>';
    endif;
else:
?>
    <div class="new-thread-form">
      <h2>Start a New Thread</h2>
      <form method="post" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="newthread">
        <input type="text" name="title" placeholder="Title" required><br>
        <textarea name="message" rows="4" cols="50" placeholder="Message" required></textarea><br>
        <input type="file" name="file" accept=".png,.jpg,.jpeg,.gif,.mp4"><br>
        <button type="submit">Post Thread</button>
      </form>
    </div>

    <div class="threads">
      <?php
        $res = $db->query('SELECT * FROM threads ORDER BY bumped_at DESC');
        while ($thread = $res->fetchArray(SQLITE3_ASSOC)):
      ?>
        <div class="thread">
          <?php if ($thread['filename']): ?>
              <?php $ext = strtolower(pathinfo($thread['filename'], PATHINFO_EXTENSION)); ?>
              <?php if ($ext === 'mp4'): ?>
                  <video src="uploads/<?php echo htmlspecialchars($thread['filename']); ?>" width="200" controls></video>
              <?php else: ?>
                  <img src="uploads/<?php echo htmlspecialchars($thread['filename']); ?>" width="200" alt="file">
              <?php endif; ?>
          <?php endif; ?>
          <div>
            <h3><a href="?thread=<?php echo $thread['id']; ?>">
              <?php echo htmlspecialchars($thread['title']); ?></a></h3>
            <p><?php echo nl2br(htmlspecialchars($thread['message'])); ?></p>
            <?php
              $latest = $db->prepare('SELECT * FROM replies WHERE thread_id = :tid ORDER BY created_at DESC LIMIT 1');
              $latest->bindValue(':tid', $thread['id'], SQLITE3_INTEGER);
              $lRes = $latest->execute();
              if ($lastRep = $lRes->fetchArray(SQLITE3_ASSOC)):
            ?>
              <div class="latest-reply">
                <p><strong>Latest reply:</strong> <?php echo nl2br(htmlspecialchars($lastRep['message'])); ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
<?php
endif;
?>
</body>
</html>
