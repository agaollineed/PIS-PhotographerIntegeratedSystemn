"""Build situs Lulus.

Satu sumber data paket + add-on (PKGS / ADDONS) dipakai untuk tiga tempat
sekaligus: kartu harga di halaman, form booking di browser, dan perhitungan
ulang di server (app/catalog.php). Jadi harga tidak bisa beda antar tempat.

Output:
  dist/preview/lulus-landing.html  -> satu file, gambar + font ikut di dalam.
                                      Untuk pratinjau tampilan. Booking-nya mode
                                      contoh: tidak menyimpan apa pun.
  dist/deploy/                     -> siap unggah ke public_html cPanel:
                                      index.php, api/, app/, admin/, booking/track/
"""
import base64, json, html, pathlib, re, shutil

ROOT = pathlib.Path(__file__).parent
TPL  = (ROOT / 'template.html').read_text(encoding='utf-8')
PHP  = ROOT / 'php'

NOTE_FAMILY = 'Keluarga dan teman boleh ikut foto.'
NOTE_TRANSPORT = 'Transport area ITS–UNAIR sudah termasuk.'

# 'hours' = durasi sesi; dipakai untuk mencocokkan paket dengan slot yang dipilih.
PKGS = [
    dict(id='single', name='The One & Only', kind='Single session',
         name_html='<span class="sw">T</span>he One &amp; Only',
         kind_html='Single <span class="sw">S</span>ession',
         tiers=[dict(label='1 jam', price=350_000, edited=25, hours=1),
                dict(label='2 jam', price=550_000, edited=35, hours=2)],
         note=f'{NOTE_FAMILY} {NOTE_TRANSPORT}',
         areas=['its', 'unair', 'lain'], transport=['its', 'unair'], group=False),
    dict(id='couple', name='Together We Made It', kind='Couple session',
         name_html='<span class="sw">T</span>ogether We <span class="sw">M</span>ade It',
         kind_html='Couple <span class="sw">S</span>ession',
         tiers=[dict(label='1 jam', price=500_000, edited=25, hours=1),
                dict(label='2,5 jam', price=799_000, edited=40, hours=2.5)],
         note=f'{NOTE_FAMILY} {NOTE_TRANSPORT}',
         areas=['its', 'unair', 'lain'], transport=['its', 'unair'], group=False),
    dict(id='group', name='Class of 2025', kind='Group session 3–5 gradumates',
         name_html='<span class="sw">C</span>lass of 2025',
         kind_html='Group <span class="sw">S</span>ession untuk 3–5 <span class="sw">G</span>radumates',
         tiers=[dict(label='1 jam', price=600_000, edited=25, hours=1),
                dict(label='2 jam', price=900_000, edited=40, hours=2)],
         note=NOTE_TRANSPORT,
         areas=['its', 'unair', 'lain'], transport=['its', 'unair'], group=True),
    dict(id='arak', name='Arak-Arakan', kind='Arak-arakan session',
         name_html='<span class="sw">A</span>rak-Arakan',
         kind_html='Session',
         tiers=[dict(label='Single, 1 sesi penuh', price=799_000, edited=30, hours=3, short='single'),
                dict(label='Couple, 1 sesi penuh', price=1_000_000, edited=30, hours=3, short='couple')],
         note=f'{NOTE_FAMILY} Khusus area ITS.',
         areas=['its'], transport=[], group=False),
]

# Add-on. 'hours' = tambahan durasi sesi; ikut dihitung saat mencocokkan slot.
ADDONS = [
    dict(id='extra30', name='Extra 30 menit', price=125_000, unit='30 menit', max=4, hours=0.5,
         desc='Perpanjang sesi setengah jam. Slot yang kamu pilih harus cukup menampungnya.'),
    dict(id='cover',   name='Sewa cover ijazah', price=50_000, unit='sesi', max=1, hours=0,
         desc='Cover ijazah sebagai properti foto, dipakai sepanjang sesi.'),
    dict(id='sameday', name='Same-day edit 5 foto', price=100_000, unit='paket', max=1, hours=0,
         desc='Lima foto pilihanmu diedit dan dikirim 4–5 jam setelah sesi selesai, '
              'siap langsung diunggah hari itu juga.'),
]

AREAS = {'its': 'ITS', 'unair': 'UNAIR', 'lain': 'Kampus / lokasi lain'}

UNIVERSITIES = [
    'Institut Teknologi Sepuluh Nopember', 'Universitas Airlangga',
    'Universitas Negeri Surabaya', 'Universitas Surabaya',
    'Universitas Kristen Petra', 'UIN Sunan Ampel Surabaya',
    'Universitas 17 Agustus 1945 Surabaya', 'Universitas Muhammadiyah Surabaya',
    'Universitas Ciputra', 'Universitas Katolik Widya Mandala Surabaya',
    'Universitas Wijaya Kusuma Surabaya', 'Universitas Hang Tuah',
    'Universitas Pembangunan Nasional Veteran Jawa Timur',
    'Universitas Nahdlatul Ulama Surabaya', 'Universitas Dr. Soetomo',
    'Universitas Hayam Wuruk Perbanas', 'Politeknik Elektronika Negeri Surabaya',
    'Politeknik Perkapalan Negeri Surabaya', 'Politeknik Negeri Malang',
    'Universitas Brawijaya', 'Universitas Negeri Malang', 'Universitas Jember',
]

TRACK_HREF = 'booking/track/'

PHP_OPEN = ("<?php require_once __DIR__ . '/app/lib.php'; boot(); ?>\n")
CSRF_META = '<meta name="csrf" content="<?= e(csrf_token()) ?>">'
CONFIG_PHP = ("<?= json_encode(['whatsapp' => cfg('site.whatsapp', ''), "
              "'instagram' => cfg('site.instagram', ''), 'email' => cfg('site.email', ''), "
              "'dpRate' => (float)cfg('booking.dp_rate', 0.4)], JSON_UNESCAPED_SLASHES) ?>")
CONFIG_PREVIEW = json.dumps({'whatsapp': '', 'instagram': '', 'email': '', 'dpRate': 0.4})


def short_price(p):
    return f'{p // 1000}k'


def tiers_html(pkg):
    rows = []
    for i, t in enumerate(pkg['tiers']):
        btn = f"Booking {t.get('short', t['label'])}"
        rows.append(
            '<li class="tier">'
            f'<p class="tier-price">{short_price(t["price"])}</p>'
            f'<p class="tier-spec"><strong>{html.escape(t["label"])}</strong><br>'
            f'unlimited photos, {t["edited"]} foto edit pilihanmu, free extra bonus</p>'
            f'<button type="button" class="tier-pick" data-pkg="{pkg["id"]}" data-tier="{i}" '
            f'aria-label="{html.escape(btn)}, {html.escape(pkg["name"])}">{html.escape(btn)}</button>'
            '</li>')
    return '<ul class="tiers">' + ''.join(rows) + '</ul>'


def univ_options():
    return ''.join(f'<option value="{html.escape(u)}">' for u in UNIVERSITIES)


def catalog():
    keep = ('id', 'name', 'kind', 'tiers', 'areas', 'transport', 'group')
    return {
        'packages': [{k: p[k] for k in keep} for p in PKGS],
        'addons': ADDONS,
        'areas': AREAS,
    }


def catalog_json():
    return json.dumps(catalog(), ensure_ascii=False)


def catalog_php():
    body = json.dumps(catalog(), ensure_ascii=False, indent=2)
    return ("<?php\n/* Dibuat otomatis oleh build.py — jangan diedit di sini.\n"
            "   Ubah harga di build.py lalu jalankan ulang build-nya. */\n"
            "return json_decode(<<<'JSON'\n" + body + "\nJSON, true);\n")


# Gambar full-bleed yang punya versi kecil (lihat compress.py):
#   nama -> (lebar kecil, lebar penuh, atribut sizes)
RESPONSIVE = {
    'crowd':  (960, 1920, '100vw'),
    'ijazah': (800, 1600, '(max-width: 860px) 100vw, 60vw'),
}

IMG_TAG = re.compile(r'<img\s+src="\{\{img:([\w-]+)\}\}"([^>]*?)>')


def wrap_pictures(s: str, inline: bool, prefix: str = '') -> str:
    """Bungkus tiap <img> jadi <picture> dengan WebP lebih dulu, JPEG sebagai cadangan.

    Di berkas pratinjau tidak ada cadangan: satu file saja sudah besar, jadi
    langsung WebP."""
    def rep(m):
        name, rest = m.group(1), m.group(2)
        if inline:
            return f'<img src="{{{{webp:{name}}}}}"{rest}>'
        if name in RESPONSIVE:
            small, full, sizes = RESPONSIVE[name]
            srcset = (f'{prefix}assets/{name}-sm.webp {small}w, {prefix}assets/{name}.webp {full}w')
            src = f' srcset="{srcset}" sizes="{sizes}"'
        else:
            src = f' srcset="{prefix}assets/{name}.webp"'
        return (f'<picture><source type="image/webp"{src}>'
                f'<img src="{prefix}assets/{name}.jpg"{rest}></picture>')
    return IMG_TAG.sub(rep, s)


def render(inline: bool, *, php: bool, prefix: str = ''):
    """inline=True menanam gambar/font sebagai data URI (untuk file pratinjau).
       prefix dipakai halaman di subfolder, mis. '../../' untuk booking/track/."""
    s = wrap_pictures(TPL, inline, prefix)
    for p in PKGS:
        s = s.replace(f'{{{{tiers:{p["id"]}}}}}', tiers_html(p))
        s = s.replace(f'{{{{name:{p["id"]}}}}}', p['name_html'])
        s = s.replace(f'{{{{kind:{p["id"]}}}}}', p['kind_html'])
        s = s.replace(f'{{{{note:{p["id"]}}}}}', html.escape(p['note']))
    s = s.replace('{{catalog_json}}', catalog_json())
    s = s.replace('{{univ_options}}', univ_options())
    s = s.replace('{{track_href}}', prefix + TRACK_HREF)
    s = s.replace('{{og_image}}', 'https://lulus.id/assets/crowd.jpg')
    s = s.replace('{{php_open}}', PHP_OPEN if php else '')
    s = s.replace('{{csrf_meta}}', CSRF_META if php else '')
    s = s.replace('{{config_json}}', CONFIG_PHP if php else CONFIG_PREVIEW)
    s = s.replace('{{api_base}}', 'api/' if php else '')
    return fill_assets(s, inline, prefix)


def fill_assets(s: str, inline: bool, prefix: str = ''):
    def img(m):
        f = ROOT / 'assets' / f'{m.group(1)}.jpg'
        if inline:
            return 'data:image/jpeg;base64,' + base64.b64encode(f.read_bytes()).decode()
        return f'{prefix}assets/{m.group(1)}.jpg'

    def webp(m):
        f = ROOT / 'assets' / f'{m.group(1)}.webp'
        if inline:
            return 'data:image/webp;base64,' + base64.b64encode(f.read_bytes()).decode()
        return f'{prefix}assets/{m.group(1)}.webp'

    def font(m):
        f = ROOT / 'fonts' / m.group(1)
        if inline:
            return 'data:font/woff2;base64,' + base64.b64encode(f.read_bytes()).decode()
        return f'{prefix}fonts/{m.group(1)}'

    s = re.sub(r'\{\{img:([\w-]+)\}\}', img, s)
    s = re.sub(r'\{\{webp:([\w-]+)\}\}', webp, s)
    s = re.sub(r'\{\{font:([\w.-]+)\}\}', font, s)
    left = re.findall(r'\{\{[^}]+\}\}', s)
    assert not left, f'placeholder tersisa: {left}'
    return s


def styles_block(prefix: str):
    """Ambil blok <style> halaman utama supaya halaman track memakai CSS yang sama."""
    m = re.search(r'<style>\n(.*?)\n</style>', TPL, re.S)
    assert m, 'blok <style> tidak ketemu di template.html'
    return fill_assets(m.group(1), inline=False, prefix=prefix)


def build_deploy(dist: pathlib.Path):
    shutil.copytree(PHP, dist, dirs_exist_ok=True)
    shutil.copytree(ROOT / 'assets', dist / 'assets', dirs_exist_ok=True)
    shutil.copytree(ROOT / 'fonts', dist / 'fonts', dirs_exist_ok=True,
                    ignore=shutil.ignore_patterns('*.orig'))   # font asli tidak perlu diunggah

    (dist / 'index.php').write_text(render(False, php=True), encoding='utf-8')
    (dist / 'app' / 'catalog.php').write_text(catalog_php(), encoding='utf-8')

    track = dist / 'booking' / 'track' / 'index.php'
    track.write_text(track.read_text(encoding='utf-8')
                     .replace('{{styles}}', styles_block('../../')), encoding='utf-8')

    shutil.copy(ROOT / 'CARA-DEPLOY.md', dist / 'CARA-DEPLOY.md')


if __name__ == '__main__':
    dist = ROOT / 'dist'
    shutil.rmtree(dist, ignore_errors=True)
    (dist / 'preview').mkdir(parents=True)
    (dist / 'deploy').mkdir(parents=True)

    (dist / 'preview' / 'lulus-landing.html').write_text(render(True, php=False), encoding='utf-8')
    build_deploy(dist / 'deploy')

    for f in sorted(dist.rglob('*')):
        if f.suffix in ('.html', '.php') and f.is_file():
            print(f'{f.relative_to(ROOT)}  {f.stat().st_size / 1024:.0f} KB')
