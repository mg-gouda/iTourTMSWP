#!/usr/bin/env python3
"""Merge a translation patch into tools/translations/<locale>.json (patch wins).

    python3 tools/merge-translations.py ar patch.json
"""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(ROOT, 'tools', 'translations', sys.argv[1] + '.json')
data = json.load(open(path, encoding='utf-8'))
patch = json.load(open(sys.argv[2], encoding='utf-8'))
for domain, strings in patch.items():
    data.setdefault(domain, {}).update(strings)
with open(path, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=1)
    f.write('\n')
print('merged', sum(len(v) for v in patch.values()), 'strings into', os.path.relpath(path, ROOT))
