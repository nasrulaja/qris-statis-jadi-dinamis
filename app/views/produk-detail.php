<?php
/** @var array $p @var array $ulasan @var float $rata @var int $jml */
/** @var bool $sudahUlas @var bool $bolehUlas @var array|null $u @var string $pesan @var string $galat */
kepala($p['nama']);
?>
<p style="margin-bottom:16px"><a href="/app/" style="color:var(--muted);font-size:13px">&larr; Kembali ke menu</a></p>

<div class="row">
  <div>
    <div class="card" style="margin-bottom:16px">
      <header><h2><?= e($p['kategori'] ?? 'Produk') ?></h2></header>
      <div>
        <h1 style="margin-bottom:6px"><?= e($p['nama']) ?></h1>
        <div style="margin:8px 0 12px;font-size:18px;letter-spacing:2px">
          <?php if ($jml > 0): ?>
            <?= bintang((int) round($rata)) ?>
            <span style="font-family:var(--mono);font-size:14px;color:var(--muted);letter-spacing:0">
              <?= number_format($rata, 1, ',', '.') ?> · <?= $jml ?> ulasan
            </span>
          <?php else: ?>
            <span class="muted" style="font-size:13px;letter-spacing:0">Belum ada ulasan</span>
          <?php endif; ?>
        </div>
        <p class="muted"><?= e($p['deskripsi']) ?></p>
        <div class="harga" style="font-family:var(--mono);color:var(--accent);font-size:24px;font-weight:650;margin:14px 0">
          <?= rupiah((int) $p['harga']) ?>
        </div>
        <?php if ($p['aktif']): ?>
          <form method="post" action="/app/" style="display:flex;gap:8px">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="produk_id" value="<?= (int) $p['id'] ?>">
            <input name="jumlah" type="number" min="1" max="99" value="1" style="width:80px">
            <button class="btn" type="submit">Tambah ke keranjang</button>
          </form>
        <?php else: ?>
          <div class="alert bad">Produk sedang tidak tersedia.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <header><h2>Komentar Pembeli (<?= $jml ?>)</h2></header>
      <div style="padding:0">
        <?php if (!$ulasan): ?>
          <div class="empty" style="padding:16px 18px">Belum ada komentar. Jadi yang pertama!</div>
        <?php else: ?>
          <?php foreach ($ulasan as $r): ?>
            <div class="notif-item">
              <div style="flex:1">
                <div class="jd">
                  <?= e($r['nama']) ?>
                  <span style="color:var(--warn);letter-spacing:1px;font-weight:400"><?= bintang((int) $r['bintang']) ?></span>
                </div>
                <?php if ($r['komentar'] !== ''): ?>
                  <div class="is" style="margin-top:4px"><?= nl2br(e($r['komentar'])) ?></div>
                <?php endif; ?>
                <div class="wk"><?= e((string) $r['dibuat']) ?></div>
                <?php if ($r['balasan'] !== ''): ?>
                  <div style="margin-top:9px;padding:9px 12px;background:var(--panel2);border-left:2px solid var(--accent);border-radius:0 7px 7px 0">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--accent)">Balasan owner</div>
                    <div style="font-size:13px;margin-top:3px"><?= nl2br(e($r['balasan'])) ?></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <header><h2>Tulis Ulasan</h2></header>
      <div>
        <?php if ($pesan !== ''): ?><div class="alert"><?= e($pesan) ?></div><?php endif; ?>
        <?php if ($galat !== ''): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

        <?php if ($sudahUlas): ?>
          <div class="alert info">Anda sudah mengulas produk ini. Terima kasih!</div>
        <?php elseif (!$u): ?>
          <p class="muted" style="font-size:13.5px">Masuk sebagai pembeli untuk memberi ulasan.</p>
          <a class="btn block" href="/app/masuk.php">Masuk</a>
        <?php elseif (!$bolehUlas): ?>
          <div class="alert info">Ulasan terbuka untuk pembeli yang sudah memesan dan membayar produk ini.</div>
        <?php else: ?>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="ulas">
            <div class="field">
              <label>Rating</label>
              <div class="bintang-pilih">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" id="b<?= $i ?>" name="bintang" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                  <label for="b<?= $i ?>" title="<?= $i ?> bintang">★</label>
                <?php endfor; ?>
              </div>
            </div>
            <div class="field">
              <label for="komentar">Komentar (opsional)</label>
              <textarea id="komentar" name="komentar" maxlength="600"
                        placeholder="Bagaimana rasa dan pelayanannya?"></textarea>
            </div>
            <button class="btn block" type="submit">Kirim ulasan</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php kaki(); ?>
