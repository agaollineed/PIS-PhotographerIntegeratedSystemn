"""Bikin versi WebP dari assets/*.jpg.

Jalankan setiap kali menambah atau mengganti gambar:

    python3 compress.py && python3 build.py

Butuh cwebp (macOS: `brew install webp`, Ubuntu: `apt install webp`).
File JPEG tetap disimpan sebagai cadangan untuk browser lama — build.py
menyusunnya jadi <picture> dengan WebP lebih dulu.
"""
import pathlib, shutil, subprocess, sys

ROOT = pathlib.Path(__file__).parent
SRC = ROOT / 'assets'

# Gambar berbutir kasar tidak banyak berkurang di kualitas tinggi, dan
# butirannya justru menyamarkan artefak — jadi dikompres lebih dalam.
QUALITY = {'pkg-arak': 68, 'm-crowd': 68, 'm-its': 68, 'm-bw': 68, 'pkg-couple': 70}
DEFAULT_Q = 75

# Gambar full-bleed yang perlu versi kecil untuk layar ponsel: nama -> lebar
SMALL = {'crowd': 960, 'ijazah': 800}


def run(args):
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit('cwebp gagal: ' + (r.stderr or r.stdout))


def main():
    if not shutil.which('cwebp'):
        sys.exit('cwebp tidak ditemukan. Pasang dulu: brew install webp')

    total_j = total_w = 0
    for jpg in sorted(SRC.glob('*.jpg')):
        q = QUALITY.get(jpg.stem, DEFAULT_Q)
        out = SRC / f'{jpg.stem}.webp'
        run(['cwebp', '-quiet', '-q', str(q), '-m', '6', '-mt', str(jpg), '-o', str(out)])

        line = f'  {jpg.stem:<14} {jpg.stat().st_size / 1024:6.0f} KB -> {out.stat().st_size / 1024:6.0f} KB'
        total_j += jpg.stat().st_size
        total_w += out.stat().st_size

        if jpg.stem in SMALL:
            w = SMALL[jpg.stem]
            small = SRC / f'{jpg.stem}-sm.webp'
            run(['cwebp', '-quiet', '-q', str(q), '-m', '6', '-mt', '-resize', str(w), '0',
                 str(jpg), '-o', str(small)])
            line += f'   (+ versi {w}px: {small.stat().st_size / 1024:.0f} KB)'
        print(line)

    print(f'\n  total {total_j / 1024:.0f} KB JPEG -> {total_w / 1024:.0f} KB WebP '
          f'({100 * (1 - total_w / total_j):.0f}% lebih ringan)')


if __name__ == '__main__':
    main()
