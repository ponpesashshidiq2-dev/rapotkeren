<?php
require_once __DIR__ . '/layout.php';

// ---- Aksi ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add_sub') {
        $cpId = (int)$_POST['cp_id'];
        $nama = trim($_POST['nama'] ?? '');
        if ($nama !== '') {
            $st = $db->prepare('SELECT COALESCE(MAX(urutan),0)+1 u FROM sub_cp WHERE cp_id=?');
            $st->execute([$cpId]);
            $urut = (int)$st->fetch()['u'];
            $db->prepare('INSERT INTO sub_cp (cp_id, urutan, nama) VALUES (?,?,?)')
               ->execute([$cpId, $urut, $nama]);
            flash('Sub-bidang ditambahkan.');
        }
    }

    if ($act === 'del_sub') {
        $db->prepare('DELETE FROM sub_cp WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Sub-bidang dihapus.');
    }

    if ($act === 'add_capaian') {
        $subId = (int)$_POST['sub_cp_id'];
        $teks = trim($_POST['teks'] ?? '');
        if ($teks !== '') {
            $st = $db->prepare('SELECT COALESCE(MAX(urutan),0)+1 u FROM capaian WHERE sub_cp_id=?');
            $st->execute([$subId]);
            $urut = (int)$st->fetch()['u'];
            $db->prepare('INSERT INTO capaian (sub_cp_id, urutan, teks) VALUES (?,?,?)')
               ->execute([$subId, $urut, $teks]);
            flash('Capaian detail ditambahkan.');
        }
    }

    if ($act === 'del_capaian') {
        $db->prepare('DELETE FROM capaian WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Capaian detail dihapus.');
    }

    if ($act === 'add_cp') {
        $nama = trim($_POST['nama'] ?? '');
        if ($nama !== '') {
            $st = $db->prepare('SELECT COALESCE(MAX(urutan),0)+1 u FROM cp');
            $st->execute();
            $urut = (int)$st->fetch()['u'];
            $db->prepare('INSERT INTO cp (urutan, nama) VALUES (?,?)')->execute([$urut, $nama]);
            flash('CP ditambahkan.');
        }
    }

    if ($act === 'del_cp') {
        $db->prepare('DELETE FROM cp WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('CP dihapus.');
    }

    redirect('index.php?route=capaian');
}

page_header('Data Capaian', 'capaian');

$cps = $db->query('SELECT * FROM cp ORDER BY urutan, id')->fetchAll();
?>
<form method="post" class="panel toolbar-form">
    <input type="hidden" name="act" value="add_cp">
    <label>Tambah CP baru:</label>
    <input name="nama" placeholder="Nama Capaian Pembelajaran" required style="flex:1">
    <button class="btn btn-primary">Tambah CP</button>
</form>

<?php foreach ($cps as $cp): ?>
<div class="panel">
    <div class="cp-head">
        <h2><?= e($cp['nama']) ?></h2>
        <form method="post" onsubmit="return confirm('Hapus CP beserta semua sub-bidang & capaiannya?')">
            <input type="hidden" name="act" value="del_cp">
            <input type="hidden" name="id" value="<?= (int)$cp['id'] ?>">
            <button class="btn btn-sm btn-danger">Hapus CP</button>
        </form>
    </div>

    <?php
    $subs = $db->prepare('SELECT * FROM sub_cp WHERE cp_id = ? ORDER BY urutan, id');
    $subs->execute([$cp['id']]);
    $subs = $subs->fetchAll();
    ?>

    <?php if (!$subs): ?>
        <p class="muted">Belum ada sub-bidang.</p>
    <?php endif; ?>

    <?php foreach ($subs as $sub): ?>
    <div class="sub-block">
        <div class="sub-head">
            <strong><?= e($sub['nama']) ?></strong>
            <form method="post" class="inline" onsubmit="return confirm('Hapus sub-bidang ini?')">
                <input type="hidden" name="act" value="del_sub">
                <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                <button class="btn btn-sm btn-danger">Hapus</button>
            </form>
        </div>
        <?php
        $caps = $db->prepare('SELECT * FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
        $caps->execute([$sub['id']]);
        $caps = $caps->fetchAll();
        ?>
        <ul class="cap-list">
            <?php foreach ($caps as $c): ?>
            <li>
                <span><?= e($c['teks']) ?></span>
                <form method="post" class="inline" onsubmit="return confirm('Hapus capaian ini?')">
                    <input type="hidden" name="act" value="del_capaian">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-link">hapus</button>
                </form>
            </li>
            <?php endforeach; ?>
            <?php if (!$caps): ?><li class="muted">Belum ada capaian detail.</li><?php endif; ?>
        </ul>
        <form method="post" class="add-form">
            <input type="hidden" name="act" value="add_capaian">
            <input type="hidden" name="sub_cp_id" value="<?= (int)$sub['id'] ?>">
            <input name="teks" placeholder="Tambah capaian detail (mis. berbagi, menunggu giliran)" required>
            <button class="btn btn-sm">Tambah</button>
        </form>
    </div>
    <?php endforeach; ?>

    <form method="post" class="add-form add-sub">
        <input type="hidden" name="act" value="add_sub">
        <input type="hidden" name="cp_id" value="<?= (int)$cp['id'] ?>">
        <input name="nama" placeholder="Tambah sub-bidang / nilai (mis. Emosional, Hubungan sosial)" required>
        <button class="btn btn-sm">Tambah Sub-bidang</button>
    </form>
</div>
<?php endforeach; ?>
<?php page_footer(); ?>
