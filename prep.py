from PIL import Image, ImageChops, ImageOps
S = 1920/1456
col = Image.open('raw/img-013.jpg').convert('RGB')
crops = {
 'm-cumlaude': (0,0,400,222), 'm-its': (0,232,563,563), 'm-truck': (600,200,1118,484),
 'm-wave': (918,2,1182,172), 'm-crowd': (1190,2,1456,398), 'm-trio': (0,572,403,819),
 'm-bw': (732,660,1045,819), 'm-couple': (1048,488,1440,819),
}
def save(im, name, maxw, q=72):
    im = im.copy()
    if im.width > maxw:
        im = im.resize((maxw, round(im.height*maxw/im.width)), Image.LANCZOS)
    im.save(f'assets/{name}.jpg', 'JPEG', quality=q, optimize=True, progressive=True)
    print(name, im.size)
for n,(a,b,c,d) in crops.items():
    save(col.crop(tuple(round(v*S) for v in (a,b,c,d))), n, 900)
def trim_white(im):
    g = im.convert('L').point(lambda v: 255 if v < 245 else 0)
    return im.crop(g.getbbox())
save(Image.open('raw/img-002.jpg').convert('RGB'), 'crowd', 1920, 70)
save(Image.open('raw/img-004.jpg').convert('RGB'), 'ijazah', 1600, 72)
save(Image.open('raw/img-008.jpg').convert('RGB'), 'moments', 960, 76)
save(trim_white(Image.open('raw/img-016.jpg').convert('RGB')), 'pkg-single', 900, 76)
save(Image.open('raw/img-024.jpg').convert('RGB'), 'pkg-couple', 900, 78)
save(trim_white(Image.open('raw/img-030.jpg').convert('RGB')), 'pkg-group', 960, 76)
save(Image.open('raw/img-037.jpg').convert('RGB'), 'pkg-arak', 960, 76)
