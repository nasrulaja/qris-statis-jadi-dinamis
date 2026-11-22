<?php
/** @var array $u @var string $pesan @var string $galat */
/** @var array|null $edit @var array $kategori @var array $produk */
kepala('Produk');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h1>Kelola Produk</h1>
    <p class="muted"><?= count($produk) ?> produk terdaftar</p>
  </div>
  <a class="btn ghost mini" href="/app/owner/">&larr; Panel owner</a>
</div>

<?php if ($pesan !== ''): ?><div class="alert"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <header><h2><?= $edit ? 'Ubah Produk' : 'Tambah Produk' ?></h2></header>
  <div>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <div class="row3">
        <div class="field">
          <label for="nama">Nama produk</label>
          <input id="nama" name="nama" required value="<?= e($edit['nama'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="harga">Harga (Rp)</label>
          <input id="harga" name="harga" type="number" min="1" required value="<?= e((string) ($edit['harga'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="kategori_id">Kategori</label>
          <select id="kategori_id" name="kategori_id">
            <option value="0">— tanpa kategori —</option>
            <?php foreach ($kategori as $k): ?>
              <option value="<?= (int) $k['id'] ?>"
                <?= (int) ($edit['kategori_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>>
                <?= e($k['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="deskripsi">Deskripsi singkat</label>
        <input id="deskripsi" name="deskripsi" value="<?= e($edit['deskripsi'] ?? '') ?>">
      </div>
      <button class="btn" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah produk' ?></button>
      <?php if ($edit): ?>
        <a class="btn ghost" href="/app/owner/produk.php">Batal</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <header><h2>Daftar Produk</h2></header>
  <div>
    <table>
      <thead><tr><th>Nama</th><th>Kategori</th><th class="num">Harga</th><th>Status</th><th class="right">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($produk as $p): ?>
        <tr>
          <td>
            <b><?= e($p['nama']) ?></b>
            <?php if ($p['deskripsi'] !== ''): ?><br><small class="muted"><?= e($p['deskripsi']) ?></small><?php endif; ?>
          </td>
          <td class="muted"><?= e($p['kategori'] ?? '—') ?></td>
          <td class="num"><?= rupiah((int) $p['harga']) ?></td>
          <td><?= $p['aktif'] ? '<span class="badge ok">aktif</span>' : '<span class="badge off">nonaktif</span>' ?></td>
          <td class="right" style="white-space:nowrap">
            <a class="btn ghost mini" href="/app/owner/produk.php?edit=<?= (int) $p['id'] ?>">Ubah</a>
            <form method="post" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="aksi" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn ghost mini" type="submit"><?= $p['aktif'] ? 'Matikan' : 'Nyalakan' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Hapus produk ini?')">
              <?= csrfField() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn danger mini" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php kaki(); ?>
