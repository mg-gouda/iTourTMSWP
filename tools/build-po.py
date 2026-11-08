#!/usr/bin/env python3
"""Turn tools/translations/<locale>.json into .po files next to each package's .pot.

    python3 tools/build-po.py ar        → theme/wp-itourtms/languages/ar.po, plugins/<slug>/languages/<slug>-ar.po

Every msgid of the .pot is kept, so the .po doubles as a to-do list: untranslated strings stay
empty and fall back to English. Then run `bin/i18n.sh mo` to compile.
"""
import json
import os
import re
import sys

PLURALS = {'ar': 'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);'}
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


def field(block, name):
    m = re.search(r'^' + name + r' ((?:".*"\n?)+)', block, re.M)
    if not m:
        return None
    return ''.join(json.loads(s) for s in re.findall(r'"(?:[^"\\]|\\.)*"', m.group(1)))


def main(locale):
    tr = json.load(open(os.path.join(ROOT, 'tools', 'translations', locale + '.json'), encoding='utf-8'))
    for domain, strings in tr.items():
        if domain.startswith('_'):
            continue
        base = os.path.join(ROOT, 'theme', domain) if domain == 'wp-itourtms' else os.path.join(ROOT, 'plugins', domain)
        pot = open(os.path.join(base, 'languages', domain + '.pot'), encoding='utf-8').read()
        out = ['msgid ""', 'msgstr ""', q('Project-Id-Version: ' + domain + '\n'), q('Language: ' + locale + '\n'),
               q('MIME-Version: 1.0\n'), q('Content-Type: text/plain; charset=UTF-8\n'), q('Content-Transfer-Encoding: 8bit\n'),
               q('Plural-Forms: ' + PLURALS[locale] + '\n'), q('X-Domain: ' + domain + '\n'), '']
        done = total = 0
        for block in pot.split('\n\n')[1:]:
            mid = field(block, 'msgid')
            if mid is None:
                continue
            total += 1
            ctx = field(block, 'msgctxt')
            plural = field(block, 'msgid_plural')
            val = strings.get((ctx + '\u0004' if ctx else '') + mid)
            out += [l for l in block.splitlines() if l.startswith('#')]
            if ctx:
                out.append('msgctxt ' + q(ctx))
            out.append('msgid ' + q(mid))
            if plural:
                out.append('msgid_plural ' + q(plural))
                forms = val if isinstance(val, list) else [''] * 6
                out += ['msgstr[%d] %s' % (i, q(f)) for i, f in enumerate(forms)]
            else:
                out.append('msgstr ' + q(val if isinstance(val, str) else ''))
            done += 1 if val else 0
            out.append('')
        name = locale + '.po' if domain == 'wp-itourtms' else domain + '-' + locale + '.po'
        open(os.path.join(base, 'languages', name), 'w', encoding='utf-8').write('\n'.join(out))
        print('  %s/languages/%s: %d of %d translated' % (os.path.relpath(base, ROOT), name, done, total))


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else 'ar')
