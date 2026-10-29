"""Build theme/wp-itourtms/assets/data/google-fonts.json from WordPress's own Google Fonts collection
(the list the Font Library uses). Run when updating fonts: python3 tools/build-google-fonts.py
Output per font: [family, category, [weights]], sorted by family."""
import json
import pathlib
import re
import urllib.request

URL = 'https://s.w.org/images/fonts/wp-7.1/collections/google-fonts-with-preview.json'
OUT = pathlib.Path(__file__).resolve().parent.parent / 'theme/wp-itourtms/assets/data/google-fonts.json'

with urllib.request.urlopen(URL, timeout=60) as r:  # noqa: S310 - fixed wordpress.org URL
    data = json.load(r)

fonts = []
for item in data['font_families']:
    s = item['font_family_settings']
    weights = sorted({int(f['fontWeight']) for f in s.get('fontFace', [])
                      if f.get('fontStyle', 'normal') == 'normal' and re.fullmatch(r'\d{3}', str(f.get('fontWeight', '')))})
    if not weights:
        continue
    cats = [c for c in item.get('categories', []) if c != 'All']
    fonts.append([s['name'], cats[0] if cats else 'sans-serif', weights])

fonts.sort(key=lambda f: f[0].lower())
OUT.parent.mkdir(parents=True, exist_ok=True)
OUT.write_text(json.dumps(fonts, separators=(',', ':'), ensure_ascii=False), encoding='utf-8')
print(f'{len(fonts)} fonts → {OUT} ({OUT.stat().st_size // 1024} KB)')
