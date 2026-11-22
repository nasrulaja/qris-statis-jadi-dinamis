<?php

declare(strict_types=1);

// Akun: ganti password sendiri (semua peran).
require __DIR__ . '/layout.php';

session_start();
$u = penggunaSekarang();
if (!$u) {
    header('Location: /app/masuk.php');
    exit;
}

$pesan = '';
$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
    $lama = (string) ($_POST['lama'] ?? '');
    $baru = (string) ($_POST['baru'] ?? '');
    $ulang = (string) ($_POST['ulang'] ?? '');

    if (!password_verify($lama, $u['sandi'])) {
        $galat = 'Password lama salah.';
    } elseif (mb_strlen($baru) < 8) {
        $galat = 'Password baru minimal 8 karakter.';
    } elseif ($baru === $lama) {
        $galat = 'Password baru harus berbeda dari yang lama.';
    } elseif ($baru !== $ulang) {
        $galat = 'Konfirmasi password tidak cocok.';
    } else {
        $st = db()->prepare('UPDATE pengguna SET sandi = ? WHERE id = ?');
        $st->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $u['id']]);
        $pesan = 'Password berhasil diubah.';
        $u = penggunaSekarang(); // segarkan hash di memori
    }
}

kepala('Akun');
?>
<h1>Akun Saya</h1>
<p class="muted" style="margin-bottom:20px">Ganti password untuk akun <code><?= e($u['username']) ?></code>.</p>

<?php if ($pesan !== ''): ?><div class="alert"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<div class="row">
  <div>
    <div class="card">
      <header><h2>Ganti Password</h2></header>
      <div>
        <form method="post">
          <?= csrfField() ?>
          <div class="field">
            <label for="lama">Password lama</label>
            <input id="lama" name="lama" type="password" autocomplete="current-password" required>
          </div>
          <div class="field">
            <label for="baru">Password baru (min. 8 karakter)</label>
            <input id="baru" name="baru" type="password" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="field">
            <label for="ulang">Ulangi password baru</label>
            <input id="ulang" name="ulang" type="password" autocomplete="new-password" minlength="8" required>
          </div>
          <button class="btn block" type="submit">Simpan password baru</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <header><h2>Profil</h2></header>
      <div>
        <table>
          <tr><td class="muted">Nama</td><td class="right"><?= e($u['nama']) ?></td></tr>
          <tr><td class="muted">Username</td><td class="right"><code><?= e($u['username']) ?></code></td></tr>
          <tr><td class="muted">Peran</td><td class="right"><?= e($u['peran']) ?></td></tr>
          <tr><td class="muted">Terdaftar</td><td class="right"><?= e((string) $u['dibuat']) ?></td></tr>
        </table>
        <p class="muted" style="font-size:12.5px;margin-top:14px">
          Password disimpan sebagai hash bcrypt. Tidak ada yang bisa membaca password asli Anda.
        </p>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
