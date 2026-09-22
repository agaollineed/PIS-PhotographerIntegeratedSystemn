"""Perkecil font Pinyon Script dengan membuang huruf yang tidak dipakai.

Pinyon cuma dipakai untuk huruf hias: wordmark "Lulus" dan beberapa kapital di
nama paket. Versi utuhnya 38 KB untuk ratusan glif yang tidak pernah muncul.

    python3 subset_fonts.py

Menghasilkan dua berkas:
  fonts/pinyon-script-latin-400-normal.woff2   A-Z a-z  (situs publik, ~18 KB)
  fonts/pinyon-lulus.woff2                     L dan l  (wordmark admin, ~2 KB)

Aslinya disimpan sebagai .orig dan tidak ikut diunggah ke server.
Butuh: pip install fonttools brotli
"""
import pathlib, shutil, subprocess, sys

ROOT = pathlib.Path(__file__).parent
FONTS = ROOT / 'fonts'
NAME = 'pinyon-script-latin-400-normal.woff2'
ORIG = FONTS / (NAME + '.orig')

# A-Z, a-z, spasi, ampersand, tanda hubung. Sengaja tidak dipersempit ke huruf
# yang dipakai hari ini supaya nama paket boleh diganti tanpa merusak tampilan.
PUBLIC = 'U+0020,U+0026,U+002D,U+0041-005A,U+0061-007A'


def sub(unicodes: str, out: pathlib.Path):
    subprocess.run([sys.executable, '-m', 'fontTools.subset', str(ORIG),
                    f'--unicodes={unicodes}', '--flavor=woff2',
                    f'--output-file={out}'], check=True)
    print(f'  {out.name:<42} {out.stat().st_size / 1024:5.1f} KB')


def main():
    try:
        import fontTools  # noqa: F401
    except ImportError:
        sys.exit('Butuh fontTools. Jalankan: pip install fonttools brotli')

    if not ORIG.exists():
        shutil.copy(FONTS / NAME, ORIG)
    print(f'  asli{"":<38} {ORIG.stat().st_size / 1024:5.1f} KB')
    sub(PUBLIC, FONTS / NAME)
    sub('U+004C,U+006C', FONTS / 'pinyon-lulus.woff2')


if __name__ == '__main__':
    main()
