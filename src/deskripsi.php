<?php
// Generator teks deskripsi raport dari skor capaian (1-4).

function skorPhrase(int $s): string {
    $map = [
        1 => 'belum berkembang dalam',
        2 => 'mulai berkembang dalam',
        3 => 'berkembang sesuai harapan dalam',
        4 => 'berkembang sangat baik dalam',
    ];
    return $map[$s] ?? '';
}

function joinKalimat(array $arr): string {
    if (!$arr) return '';
    if (count($arr) === 1) return $arr[0];
    $last = array_pop($arr);
    return implode(', ', $arr) . ', dan ' . $last;
}

/**
 * Susun deskripsi dari skor per capaian detail.
 * $skors = [capaian_id => 1..4]
 * Hasil: "berkembang sangat baik dalam berbagi, menunggu giliran, ..."
 * (nama anak ditambahkan pemanggil: "Ananda X " . hasil)
 */
function capaianSkorText(int $subId, array $skors, PDO $db): string {
    $st = $db->prepare('SELECT id, teks FROM capaian WHERE sub_cp_id = ? ORDER BY urutan, id');
    $st->execute([$subId]);
    $rated = [];
    foreach ($st->fetchAll() as $r) {
        $s = (int)($skors[(int)$r['id']] ?? 0);
        if ($s >= 1 && $s <= 4) {
            $rated[] = ['teks' => $r['teks'], 'skor' => $s];
        }
    }
    if (!$rated) return '';

    // Semua skor sama → satu frasa predikat di depan daftar detail
    $uniq = array_unique(array_column($rated, 'skor'));
    if (count($uniq) === 1) {
        $p = skorPhrase($rated[0]['skor']);
        return $p . ' ' . joinKalimat(array_column($rated, 'teks'));
    }
    // Skor campur → frasa melekat pada tiap detail
    $parts = [];
    foreach ($rated as $r) {
        $parts[] = skorPhrase($r['skor']) . ' ' . $r['teks'];
    }
    return joinKalimat($parts);
}
