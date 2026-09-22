<?php
/* Email ringkasan booking & pemberitahuan perubahan status.
   Pakai mail() bawaan PHP — di cPanel ini jalan selama 'from' memakai
   alamat di domain sendiri. Kalau mail() dimatikan hosting, set
   mail.enabled = false; situs tetap jalan, hanya emailnya yang tidak terkirim. */

require_once __DIR__ . '/booking.php';

function send_mail(string $to, string $subject, string $html): bool {
  if (!cfg('mail.enabled', true)) return false;
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

  $from     = cfg('mail.from', '');
  $fromName = cfg('mail.from_name', 'Lulus');
  $replyTo  = cfg('mail.reply_to', '') ?: $from;
  if (!$from) return false;

  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>',
    'Reply-To: ' . $replyTo,
    'X-Mailer: Lulus',
  ];
  $subject = mb_encode_mimeheader($subject, 'UTF-8');

  $ok = @mail($to, $subject, $html, implode("\r\n", $headers), '-f' . $from);
  if (!$ok) error_log('[lulus] gagal kirim email ke ' . $to);
  return $ok;
}

/* ---------- Template email ---------- */

function mail_shell(string $title, string $intro, string $body, string $cta = '', string $ctaUrl = ''): string {
  $site = e(cfg('site.name', 'Lulus'));
  $btn = $cta && $ctaUrl
    ? '<p style="margin:26px 0 0"><a href="' . e($ctaUrl) . '" style="display:inline-block;background:#051f50;color:#fff;
        text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:600">' . e($cta) . '</a></p>'
    : '';
  return '<!doctype html><html lang="id"><body style="margin:0;background:#f2f6fb;
      font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0b1b33">
    <div style="max-width:600px;margin:0 auto;padding:28px 20px">
      <p style="margin:0 0 20px;font-size:22px;letter-spacing:-.02em;color:#051f50"><strong>' . $site . '</strong></p>
      <div style="background:#fff;border-radius:16px;padding:28px 26px">
        <h1 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:#051f50">' . e($title) . '</h1>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#39435a">' . $intro . '</p>
        ' . $body . $btn . '
      </div>
      <p style="margin:22px 0 0;font-size:12px;color:#6b7689;line-height:1.6">
        Email ini dikirim otomatis oleh ' . $site . '. Ada yang mau ditanyakan atau diubah?
        Balas email ini saja, atau sapa admin lewat WhatsApp — kami bantu 🙌</p>
    </div></body></html>';
}

function mail_table(array $b): string {
  $rows = '';
  foreach (booking_lines($b) as $line) {
    [$k, $v] = array_pad(explode(': ', $line, 2), 2, '');
    $rows .= '<tr>
      <td style="padding:7px 0;color:#6b7689;font-size:14px;vertical-align:top;width:40%">' . e($k) . '</td>
      <td style="padding:7px 0;font-size:14px;vertical-align:top"><strong>' . e($v) . '</strong></td></tr>';
  }
  return '<table style="width:100%;border-collapse:collapse;border-top:1px solid #e3e9f2">' . $rows . '</table>';
}

function mail_booking_created(array $b): void {
  $code = e($b['code']);
  $intro = 'Terima kasih banyak, <strong>' . e($b['client_name']) . '</strong> 🤍 Booking kamu sudah kami catat
            dengan kode <strong style="color:#051f50">' . $code . '</strong>. Admin Lulus akan mengonfirmasi
            slotnya lewat WhatsApp sebentar lagi. Simpan kodenya ya, dipakai untuk cek status kapan saja.';
  $html = mail_shell('Booking diterima — ' . $b['code'], $intro, mail_table($b),
                     'Cek status booking', track_url($b['code']));
  send_mail($b['client_email'], 'Booking ' . $b['code'] . ' diterima — ' . cfg('site.name', 'Lulus'), $html);

  $adminTo = cfg('mail.admin_to', '');
  if ($adminTo) {
    $intro2 = 'Booking baru dari <strong>' . e($b['client_name']) . '</strong> (' . e($b['whatsapp']) . ').';
    send_mail($adminTo, '[Booking baru] ' . $b['code'] . ' — ' . $b['client_name'],
      mail_shell('Booking baru masuk', $intro2, mail_table($b), 'Buka admin', rtrim(cfg('site.url', ''), '/') . '/admin/'));
  }
}

function mail_status_changed(array $b, string $note = ''): void {
  $label = status_label($b['status']);
  $intro = 'Status booking <strong>' . e($b['code']) . '</strong> sekarang: <strong>' . e($label) . '</strong>.<br>'
         . e($note !== '' ? $note : status_note($b['status']));
  $body = mail_table($b);
  if ($b['status'] === 'terkirim' && $b['drive_link']) {
    $body .= '<p style="margin:18px 0 0;font-size:14px">Link hasil foto: <a href="' . e($b['drive_link']) . '">'
           . e($b['drive_link']) . '</a> — aktif 14 hari.</p>';
  }
  $html = mail_shell('Update booking ' . $b['code'], $intro, $body, 'Lihat status', track_url($b['code']));
  send_mail($b['client_email'], 'Booking ' . $b['code'] . ' — ' . $label, $html);
}
