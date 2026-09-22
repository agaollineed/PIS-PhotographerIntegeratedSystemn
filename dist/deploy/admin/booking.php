<?php
/* Satu booking. Pusatnya adalah jalur tahap yang bisa diklik — persis jalur
   yang dilihat klien di /booking/track/, bedanya di sini bisa digeser. */
require_once __DIR__ . '/_boot.php';
admin_require();

$id = (int)($_GET['id'] ?? 0);
$b  = booking_by_id($id);
if (!$b) { admin_head('Tidak ditemukan'); echo '<div class="card"><p>Booking tidak ditemukan.</p></div>'; admin_foot(); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  admin_guard_post();

  if (isset($_POST['tahap'])) {
    $baru = (string)$_POST['tahap'];
    $note = clean((string)($_POST['note'] ?? ''), 400);
    if (isset(status_list()[$baru])) {
      $b = booking_set_status($id, $baru, $note);
      $pesan = 'Tahapnya sekarang "' . status_label($baru) . '".';
      if (!empty($_POST['kirim_email'])) {
        try { mail_status_changed($b, $note); $pesan .= ' Email pemberitahuan dikirim.'; }
        catch (Throwable $e) { $pesan .= ' Email gagal dikirim, cek log server.'; }
      }
      flash($pesan . ' Pesan WhatsApp-nya ikut menyesuaikan.');
    }
  }

  if (isset($_POST['simpan_catatan'])) {
    q('UPDATE bookings SET admin_note = ?, drive_link = ?, updated_at = NOW() WHERE id = ?',
      [clean((string)($_POST['admin_note'] ?? ''), 1000), clean((string)($_POST['drive_link'] ?? ''), 255), $id]);
    flash('Catatan disimpan.');
  }

  if (isset($_POST['hapus'])) {
    q('DELETE FROM bookings WHERE id = ?', [$id]);
    flash('Booking ' . $b['code'] . ' dihapus, slotnya terbuka lagi.');
    header('Location: index.php'); exit;
  }

  header('Location: booking.php?id=' . $id); exit;
}

$flow  = status_flow();
$kini  = array_search($b['status'], $flow, true);
$batal = $b['status'] === 'batal';
$next  = status_next($b['status']);

$waktu = [];
foreach ($b['events'] as $ev) $waktu[$ev['status']] = $ev['created_at'];

$templates = [];
foreach (array_keys(status_list()) as $st) $templates[$st] = wa_message($b, $st);
$mailAktif = cfg('mail.enabled', true) && cfg('mail.from', '');
$slotTxt = booking_slot_text($b);

admin_head('Booking ' . $b['code']); ?>

<p class="sub" style="margin:0 4px 14px"><a href="index.php">← Semua booking</a></p>

<div class="card">
  <div style="display:flex;flex-wrap:wrap;gap:10px 18px;align-items:baseline">
    <h2 style="font-size:1.5rem;font-weight:500;letter-spacing:.01em"><?= e($b['code']) ?></h2>
    <span class="tag <?= e(status_tone($b['status'])) ?>"><?= e(status_label($b['status'])) ?></span>
    <span class="sub" style="margin-left:auto"><?= e(status_todo($b['status'])) ?></span>
  </div>
  <p class="sub" style="margin-top:8px"><?= e($b['client_name']) ?> · <?= e($b['whatsapp']) ?> · <?= e($b['client_email']) ?></p>
  <p style="margin-top:10px"><b><?= e(fmt_date($b['photo_date'])) ?></b> · <?= e($slotTxt) ?> · <?= e($b['area_label']) ?></p>
</div>

<form class="card" method="post">
  <?= csrf_field() ?>
  <h2>Jalur booking</h2>
  <p class="sub">Klik tahapnya untuk memindahkan booking ini. Klien melihat jalur yang sama
    di halaman cek status, jadi perubahannya langsung terlihat olehnya.</p>

  <?php if ($batal): ?>
    <p class="tag off" style="margin-bottom:14px">Booking ini dibatalkan pada <?= e(date('d/m/Y H:i', strtotime($b['updated_at']))) ?></p>
  <?php endif; ?>

  <ol class="track" <?= $batal ? 'style="opacity:.5"' : '' ?>>
    <?php foreach ($flow as $i => $st):
      $cls = $batal ? '' : ($i < $kini ? 'done' : ($i === $kini ? 'now' : '')); ?>
      <li class="<?= $cls ?>">
        <button class="step" type="submit" name="tahap" value="<?= e($st) ?>"
                aria-current="<?= $cls === 'now' ? 'step' : 'false' ?>">
          <span><?= e(status_label($st)) ?>
            <?php if (isset($waktu[$st])): ?><time><?= e(date('d/m H:i', strtotime($waktu[$st]))) ?></time><?php endif; ?>
          </span>
        </button>
      </li>
    <?php endforeach; ?>
  </ol>

  <label for="note">Catatan yang ikut terlihat klien <span class="sub">(opsional — kosongkan untuk teks bawaan)</span></label>
  <input id="note" name="note" placeholder="Contoh: slot jam 9 sudah kami kunci, ya">

  <?php if ($mailAktif): ?>
    <label style="display:flex;gap:10px;align-items:center;margin-top:14px;color:inherit;font-size:.95rem">
      <input type="checkbox" name="kirim_email" value="1" checked>
      <span>Kirim juga email pemberitahuan ke <?= e($b['client_email']) ?></span>
    </label>
  <?php endif; ?>

  <div class="acts">
    <?php if ($next): ?>
      <button class="btn" type="submit" name="tahap" value="<?= e($next) ?>">→ <?= e(status_label($next)) ?></button>
    <?php endif; ?>
    <?php if (!$batal): ?>
      <button class="btn danger" type="submit" name="tahap" value="batal"
              onclick="return confirm('Tandai booking ini dibatalkan? Slotnya akan terbuka lagi.')">Batalkan booking</button>
    <?php endif; ?>
  </div>
</form>

<div class="card wa">
  <h2>Pesan untuk klien</h2>
  <p class="sub">Sudah menyesuaikan tahap sekarang. Boleh diedit dulu sebelum dikirim.</p>
  <div class="wa-top">
    <select id="wa-status" aria-label="Tahap pesan">
      <?php foreach (status_list() as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $b['status'] === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option>
      <?php endforeach; ?>
    </select>
    <a class="btn" id="wa-open" href="#">Buka WhatsApp</a>
    <button class="btn quiet" type="button" id="wa-copy">Salin</button>
    <span class="said" id="wa-said"></span>
  </div>
  <textarea id="wa-text" aria-label="Isi pesan"><?= e($templates[$b['status']] ?? '') ?></textarea>
</div>

<div class="card">
  <h2>Rincian</h2>
  <ul class="list" style="margin-top:12px">
    <?php foreach (booking_lines($b) as $line): [$k, $v] = array_pad(explode(': ', $line, 2), 2, ''); ?>
      <li><span class="dim" style="min-width:170px"><?= e($k) ?></span><span class="grow"><?= e($v) ?></span></li>
    <?php endforeach; ?>
    <li><span class="dim" style="min-width:170px">Email</span><span class="grow"><?= e($b['client_email']) ?></span></li>
    <?php if ($b['instagram']): ?>
      <li><span class="dim" style="min-width:170px">Instagram</span><span class="grow">@<?= e($b['instagram']) ?></span></li>
    <?php endif; ?>
  </ul>
</div>

<form class="card" method="post">
  <?= csrf_field() ?>
  <h2>Hasil foto &amp; catatan</h2>
  <p class="sub">Link Drive yang disimpan di sini ikut masuk ke pesan tahap “Hasil dikirim” dan muncul di halaman klien.</p>
  <label for="dl">Link Google Drive</label>
  <input id="dl" name="drive_link" value="<?= e($b['drive_link']) ?>" placeholder="https://drive.google.com/...">
  <label for="an">Catatan internal <span class="sub">(tidak terlihat klien)</span></label>
  <textarea id="an" name="admin_note"><?= e($b['admin_note']) ?></textarea>
  <div class="acts"><button class="btn quiet" type="submit" name="simpan_catatan" value="1">Simpan</button></div>
</form>

<div class="card">
  <h2>Riwayat</h2>
  <ul class="list" style="margin-top:12px">
    <?php foreach (array_reverse($b['events']) as $ev): ?>
      <li>
        <span class="dim" style="min-width:140px"><?= e(date('d/m/Y H:i', strtotime($ev['created_at']))) ?></span>
        <span class="tag <?= e(status_tone($ev['status'])) ?>"><?= e(status_label($ev['status'])) ?></span>
        <span class="grow dim"><?= e($ev['note']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<form class="card" method="post" onsubmit="return confirm('Hapus <?= e($b['code']) ?> permanen?')">
  <?= csrf_field() ?>
  <h2>Hapus permanen</h2>
  <p class="sub">Untuk pembatalan biasa pakai tombol “Batalkan booking” supaya riwayatnya tetap tersimpan.
    Hapus permanen hanya untuk booking iseng atau salah input.</p>
  <div class="acts"><button class="btn danger" type="submit" name="hapus" value="1">Hapus permanen</button></div>
</form>

<script>
const WA_TPL = <?= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const WA_NO  = <?= json_encode(preg_replace('/\D/', '', $b['whatsapp'])) ?>;
const sel = document.getElementById('wa-status');
const txt = document.getElementById('wa-text');
const said = document.getElementById('wa-said');

sel.addEventListener('change', () => { txt.value = WA_TPL[sel.value] || ''; });

document.getElementById('wa-open').addEventListener('click', e => {
  e.preventDefault();
  window.open('https://wa.me/' + WA_NO + '?text=' + encodeURIComponent(txt.value), '_blank', 'noopener');
});

document.getElementById('wa-copy').addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(txt.value); }
  catch (err) { txt.select(); document.execCommand('copy'); }
  said.textContent = 'Tersalin';
  setTimeout(() => { said.textContent = ''; }, 2000);
});
</script>
<?php admin_foot();
