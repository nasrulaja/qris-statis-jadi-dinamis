<?php

declare(strict_types=1);

require __DIR__ . '/layout.php';

session_start();

if (penggunaSekarang()) {
    header('Location: /app/');
    exit;
}

$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sisa = sisaPercobaanLogin();
    if ($sisa <= 0) {
        $galat = 'Terlalu banyak percobaan. Tunggu 10 menit lalu coba lagi.';
    } elseif (!csrfValid()) {
        $galat = 'Sesi kedaluwarsa. Coba lagi.';
    } else {
        $st = db()->prepare('SELECT * FROM pengguna WHERE username = ?');
        $st->execute([trim((string) ($_POST['username'] ?? ''))]);
        $u = $st->fetch();

        if ($u && password_verify((string) ($_POST['sandi'] ?? ''), $u['sandi'])) {
            bersihkanGagalLogin();
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            $_SESSION['peran'] = $u['peran'];
            redirectSetelahLogin($u['peran']);
        }

        catatGagalLogin((string) ($_POST['username'] ?? ''));
        $sisa--;
        $galat = 'Username atau password salah.'
            . ($sisa > 0 ? ' Sisa percobaan: ' . $sisa . '.' : ' Akun dikunci sementara.');
    }
}

function redirectSetelahLogin(string $peran): never
{
    $tujuan = match ($peran) {
        'owner' => '/app/owner/',
        'admin' => '/app/admin/',
        default => '/app/',
    };
    header('Location: ' . $tujuan);
    exit;
}

$judul = 'Masuk';
$toko = setting('nama_toko');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — <?= e($toko) ?></title>
<link rel="stylesheet" href="/app/assets/kafe.css">
<style>
  .masuk-wrap { max-width: 400px; margin: 6vh auto; }
  .akun-demo { font-size: 12px; color: var(--muted); line-height: 2; margin-top: 16px;
    border-top: 1px solid var(--border); padding-top: 14px; }
  .akun-demo code { color: var(--accent); }
</style>
</head>
<body>
<main class="wrap">
  <div class="masuk-wrap">
    <div class="card">
      <header><h2>Masuk ke <?= e($toko) ?></h2></header>
      <div>
        <?php if ($galat !== ''): ?>
          <div class="alert bad"><?= e($galat) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrfField() ?>
          <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" autocomplete="username" autofocus required>
          </div>
          <div class="field">
            <label for="sandi">Password</label>
            <input id="sandi" name="sandi" type="password" autocomplete="current-password" required>
          </div>
          <button class="btn block" type="submit">Masuk</button>
        </form>
        <div class="akun-demo">
          <b>Akun demo:</b><br>
          <code>user</code> / <code>user123</code> — pembeli<br>
          <code>admin</code> / <code>admin123</code> — kasir<br>
          <code>owner</code> / <code>owner123</code> — pemilik
        </div>
      </div>
    </div>
    <p style="text-align:center;margin-top:16px"><a href="/app/" style="color:var(--muted);font-size:13px">&larr; Lihat menu tanpa masuk</a></p>
  </div>
</main>
</body>
</html>
