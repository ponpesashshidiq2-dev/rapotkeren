<?php
require_once __DIR__ . '/layout.php';

// ---- Tambah / Hapus / Ubah urutan pertanyaan (global) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'tambah') {
        $teks = trim($_POST['teks'] ?? '');
        if ($teks !== '') {
            $max = (int)$db->query('SELECT COALESCE(MAX(urutan),0) m FROM refleksi_pertanyaan')->fetch()['m'];
            $db->prepare('INSERT INTO refleksi_pertanyaan (urutan, teks) VALUES (?, ?)')
               ->execute([$max + 1, $teks]);
            flash('Pertanyaan ditambahkan.');
        }
    } elseif ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM refleksi_pertanyaan WHERE id = ?')->execute([$id]);
        flash('Pertanyaan dihapus.');
    } elseif ($act === 'ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $teks = trim($_POST['teks'] ?? '');
        if ($id && $teks !== '') {
            $db->prepare('UPDATE refleksi_pertanyaan SET teks = ? WHERE id = ?')
               ->execute([$teks, $id]);
            flash('Pertanyaan diperbarui.');
        }
    }
    redirect('index.php?route=refleksi');
}

$soal = $db->query('SELECT * FROM refleksi_pertanyaan ORDER BY urutan, id')->fetchAll();

page_header('Refleksi Orang Tua', 'refleksi');
?>
<div class="panel">
    <div class="cp-section-head">
        <h3>Pertanyaan Refleksi Orang Tua</h3>
        <span class="badge">Global — sama untuk semua anak, diisi sekali</span>
    </div>

    <form method="post" style="padding:16px 18px; border-bottom:1px solid var(--border-light);">
        <input type="hidden" name="act" value="tambah">
        <div class="form-group" style="margin:0; display:flex; gap:10px; align-items:flex-end;">
            <div style="flex:1;">
                <label>Pertanyaan baru</label>
                <input type="text" name="teks" required placeholder="mis. Perubahan positif apa yang sudah ada pada Ananda?">
            </div>
            <button class="btn btn-primary">+ Tambah Pertanyaan</button>
        </div>
    </form>

    <?php if (!$soal): ?>
        <div class="panel muted" style="margin:16px;">Belum ada pertanyaan. Tambahkan di atas.</div>
    <?php else: ?>
    <div style="padding:8px 0;">
        <?php foreach ($soal as $i => $s): ?>
        <div style="display:flex; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--border-light);">
            <span style="font-weight:700; color:var(--hijau-botol); min-width:28px;"><?= $i + 1 ?>.</span>
            <form method="post" style="flex:1; display:flex; gap:8px; align-items:center; margin:0;">
                <input type="hidden" name="act" value="ubah">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="text" name="teks" value="<?= e($s['teks']) ?>" style="flex:1;" required>
                <button class="btn" type="submit" style="padding:6px 12px;">Simpan</button>
            </form>
            <form method="post" onsubmit="return confirm('Hapus pertanyaan ini?');" style="margin:0;">
                <input type="hidden" name="act" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn" type="submit" style="padding:6px 12px; color:#b91c1c; border-color:#fecaca;">Hapus</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="padding:14px 18px; border-top:1px solid var(--border-light);">
        <div class="muted" style="font-size:12.5px; margin-bottom:10px;">
            Di cetak, tiap pertanyaan diberi jeda garis untuk ditulis orang tua.
            Pertumbuhan &amp; kehadiran anak ada di menu <a href="index.php?route=pertumbuhan" style="font-weight:600;">Pertumbuhan &amp; Kehadiran</a>.
        </div>
        <a class="btn" href="index.php?route=pertumbuhan">→ Pertumbuhan &amp; Kehadiran</a>
        <a class="btn" href="index.php?route=cetak">Lanjut: Cetak →</a>
    </div>
</div>
<?php page_footer(); ?>
