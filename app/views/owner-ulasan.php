<?php
/** @var array $u @var string $pesan @var string $galat @var array $daftar @var array $rata */
kepala('Ulasan');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h1>Ulasan Pembeli</h1>
    <p class="muted">
      <?php if ((int) $rata['n'] > 0): ?>
        Rata-rata <?= number_format((float) $rata['r'], 1, ',', '.') ?> dari <?= (int) $rata['n'] ?> ulasan
      <?php else: ?>
        Belum ada ulasan masuk
      <?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn ghost mini" href="/app/owner/produk.php">Produk</a>
    <a class="btn ghost mini" href="/app/owner/">&larr; Panel owner</a>
  </div>
</div>

<?php if ($pesan !== ''): ?><div class="alert"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<?php if (!$daftar): ?>
  <div class="card"><div><div class="empty">Belum ada ulasan. Ulasan muncul setelah pembeli memesan dan membayar.</div></div></div>
<?php else: ?>
  <?php foreach ($daftar as $r): ?>
    <div class="card" style="margin-bottom:12px">
      <header style="display:flex;justify-content:space-between;gap:12px;align-items:center">
        <div>
          <b style="font-size:14px"><?= e($r['produk']) ?></b>
          <span style="color:var(--warn);letter-spacing:1px"><?= bintang((int) $r['bintang']) ?></span>
        </div>
        <small class="muted"><?= e((string) $r['dibuat']) ?></small>
      </header>
      <div>
        <div style="font-size:13.5px;margin-bottom:6px"><b><?= e($r['nama']) ?></b></div>
        <?php if ($r['komentar'] !== ''): ?>
          <p style="margin:0 0 12px;font-size:13.5px;color:var(--muted)"><?= nl2br(e($r['komentar'])) ?></p>
        <?php endif; ?>

        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="aksi" value="balas">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <div class="field">
            <label for="balasan<?= (int) $r['id'] ?>">Balasan owner</label>
            <textarea id="balasan<?= (int) $r['id'] ?>" name="balasan" maxlength="600"
                      placeholder="Terima kasih atas masukannya..."><?= e($r['balasan']) ?></textarea>
          </div>
          <button class="btn mini" type="submit">Simpan balasan</button>
        </form>

        <form method="post" style="display:inline" onsubmit="return confirm('Hapus ulasan ini?')">
          <?= csrfField() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn danger mini" type="submit">Hapus</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php kaki(); ?>
