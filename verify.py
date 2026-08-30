# -*- coding: utf-8 -*-
"""Baseline B2 verification — the checks of baseline.md section 3."""
import io, os, re, hashlib, glob

DOCS = 'docs'
BASE = ['problem-requirements-matrix', 'functional-requirements-specification',
        'use-case-model', 'behavioral-diagrams', 'data-model', 'system-architecture']

def read(n):
    return io.open(os.path.join(DOCS, n + '.md'), encoding='utf-8').read()

texts = dict((n, read(n)) for n in BASE)
allt = '\n'.join(texts.values())
frs = texts['functional-requirements-specification']
ucm = texts['use-case-model']

fails = []
def check(name, ok, detail=''):
    print(('  OK   ' if ok else '  FAIL ') + name + (('  -> ' + detail) if detail and not ok else ''))
    if not ok:
        fails.append(name)

print('=' * 70)
print('RETIRED IDENTIFIERS MUST NOT APPEAR AS LIVE REFERENCES')
print('=' * 70)

# Retired FRs / NFR must not appear in requirement tables or Rules lines
retired_reqs = ['FR-2.1', 'FR-2.2', 'NFR-2.7']
for r in retired_reqs:
    # find occurrences NOT on a line that mentions retire/CR-01/absent/no longer
    bad = []
    for n, t in texts.items():
        for i, line in enumerate(t.split('\n'), 1):
            if re.search(re.escape(r) + r'(?![0-9])', line):
                low = line.lower()
                if not any(k in low for k in ['retir', 'cr-01', 'absent', 'no longer', 'not reused',
                                              'baseline b1', 'replaced', 'defect', 'closed by',
                                              'was justified', 'original', 'b1 ', 'used', 'parallel run of', 'replaces', 'substitution']):
                    bad.append('%s:%d' % (n, i))
    check('%s has no live reference' % r, not bad, ', '.join(bad[:4]))

retired_brs = ['BR-02', 'BR-05', 'BR-11', 'BR-13', 'BR-15', 'BR-16', 'BR-17', 'BR-19', 'BR-21', 'BR-22']
bad = []
for n, t in texts.items():
    for i, line in enumerate(t.split('\n'), 1):
        if line.startswith('**Rules**'):
            for r in retired_brs:
                if re.search(re.escape(r) + r'(?![0-9])', line):
                    bad.append('%s:%d %s' % (n, i, r))
check('no Rules line cites a retired business rule', not bad, ', '.join(bad[:5]))

# EX-09
bad = []
for n, t in texts.items():
    for i, line in enumerate(t.split('\n'), 1):
        if 'EX-09' in line and 'retir' not in line.lower() and 'supersed' not in line.lower():
            bad.append('%s:%d' % (n, i))
check('EX-09 has no live reference', not bad, ', '.join(bad[:4]))

# ComputationEngine
bad = []
for n, t in texts.items():
    for i, line in enumerate(t.split('\n'), 1):
        if 'ComputationEngine' in line:
            low = line.lower()
            if not any(k in low for k in ['retir', 'cr-01', 'no longer', 'was justified',
                                          'original', 'b1', 'absent', 'defect', 'replace']):
                bad.append('%s:%d' % (n, i))
check('ComputationEngine has no live reference', not bad, ', '.join(bad[:4]))

print()
print('=' * 70)
print('IDENTIFIER RESOLUTION  (check 1)')
print('=' * 70)

# every FR-x.y referenced resolves to a definition in FRS section 4 or is a known retired one
defined_fr = set(re.findall(r'###\s*(?:[⊕✧]\s*)*((?:FR|NFR)-[0-9]+\.[0-9]+)', frs))
defined_fr |= set(re.findall(r'\|\s*\*\*(NFR-[0-9]+\.[0-9]+)\*\*\s*\|', frs))  # NFR table
defined_fr |= set(['DR-1.6','DR-2.1','DR-2.2','DR-2.3','DR-2.4','NFR-7.1','NFR-7.2','NFR-7.3','NFR-7.4'])
referenced = set(re.findall(r'\b((?:FR|NFR)-[0-9]+\.[0-9]+)', allt))
unresolved = sorted(r for r in referenced if r not in defined_fr and r not in retired_reqs)
check('every FR/NFR reference resolves', not unresolved, ', '.join(unresolved))

# business rules
_live = frs[:frs.index('## 7.8')] + frs[frs.index('## 7.9'):]
defined_br = set(re.findall(r'\|\s*\*\*(BR-[0-9]{2})\*\*\s*\|', _live))
defined_br_all = set(re.findall(r'\|\s*\*\*(BR-[0-9]{2})\*\*\s*\|', frs))
ref_br = set(re.findall(r'\b(BR-[0-9]{2})\b', allt))
unresolved_br = sorted(b for b in ref_br if b not in defined_br_all)
check('every BR reference resolves', not unresolved_br, ', '.join(unresolved_br))

# exceptions
defined_ex = set(re.findall(r'\|\s*(EX-[0-9]{2})\s*\|', frs))
ref_ex = set(re.findall(r'\b(EX-[0-9]{2})\b', allt))
unresolved_ex = sorted(e for e in ref_ex if e not in defined_ex and e != 'EX-09')
check('every EX reference resolves', not unresolved_ex, ', '.join(unresolved_ex))

# use cases
defined_uc = set(re.findall(r'###\s*(?:✧\s*)*(UC-(?:I?[0-9]+))\s*·', ucm))
ref_uc = set(re.findall(r'\b(UC-I?[0-9]+)\b', allt))
unresolved_uc = sorted(u for u in ref_uc if u not in defined_uc)
check('every UC reference resolves', not unresolved_uc, ', '.join(unresolved_uc))

# architectural decisions
arch = texts['system-architecture']
defined_ad = set(re.findall(r'\|\s*\*\*(AD-[0-9]{2})\*\*', arch))
ref_ad = set(re.findall(r'\b(AD-[0-9]{2})\b', allt))
unresolved_ad = sorted(a for a in ref_ad if a not in defined_ad)
check('every AD reference resolves', not unresolved_ad, ', '.join(unresolved_ad))

print()
print('=' * 70)
print('COUNTS  (checks 2, 3, 4, 11, 12)')
print('=' * 70)

n_br = len(defined_br)
check('business rules = 31 live', n_br == 31, 'found %d' % n_br)
gaps = [i for i in range(1, 42) if ('BR-%02d' % i) not in defined_br]
retired_nums = sorted(int(b.split('-')[1]) for b in retired_brs)
check('BR number line 1..41 complete (31 live + 10 retired)',
      sorted(gaps) == retired_nums, 'gaps %s vs retired %s' % (gaps, retired_nums))

n_ex = len(defined_ex)
check('exception rules = 13', n_ex == 13, 'found %d' % n_ex)

# FRS Table 8 vs UC Table 3 identifier sets
t8 = frs[frs.index('# 9. Requirements traceability'):frs.index('# 10. Acceptance')]
t8_ids = set(re.findall(r'\|\s*(?:[⊕✧]\s*)*((?:FR|NFR|DR)-[0-9]+\.[0-9]+)\s*\|', t8))
t3 = ucm[ucm.index('## 7.1 Requirement to use case'):ucm.index('## 7.2 Use case to problem')]
t3_ids = set(re.findall(r'\|\s*((?:FR|NFR|DR)-[0-9]+\.[0-9]+)\s', t3))
check('FRS Table 8 holds 45 items', len(t8_ids) == 45, 'found %d' % len(t8_ids))
check('UC Table 3 holds 45 items', len(t3_ids) == 45, 'found %d' % len(t3_ids))
check('Table 8 and Table 3 identifier sets identical', t8_ids == t3_ids,
      'only in T8: %s | only in T3: %s' % (sorted(t8_ids - t3_ids), sorted(t3_ids - t8_ids)))

n_fr = len([i for i in t8_ids if i.startswith('FR-')])
n_nfr = len([i for i in t8_ids if i.startswith('NFR-')])
n_dr = len([i for i in t8_ids if i.startswith('DR-')])
check('32 FR + 8 NFR + 5 DR = 45', (n_fr, n_nfr, n_dr) == (32, 8, 5),
      'FR=%d NFR=%d DR=%d' % (n_fr, n_nfr, n_dr))

n_uc = len([u for u in defined_uc if not u.startswith('UC-I')])
n_uci = len([u for u in defined_uc if u.startswith('UC-I')])
check('33 primary use cases', n_uc == 33, 'found %d' % n_uc)
check('7 included use cases', n_uci == 7, 'found %d' % n_uci)

# entities
dm = texts['data-model']
keys = dm[dm.index('## 5.1 Keys and constraints'):dm.index('## 5.2 Check constraints')]
ents = set(re.findall(r'\|\s*`([A-Z_]+)`\s*\|', keys))
check('39 entities in the keys table', len(ents) == 39, 'found %d' % len(ents))

print()
print('=' * 70)
print('STRUCTURE  (checks 15, 17, 18)')
print('=' * 70)

# markers inside identifier cells
bad = []
for n, t in texts.items():
    for i, line in enumerate(t.split('\n'), 1):
        for m in re.finditer(r'\|\s*(?:~~)?((?:FR|NFR|DR|BR|EX|AD|UC)-[0-9I]+(?:\.[0-9]+)?)(?:~~)?\s*\|', line):
            cell = m.group(0)
            if '~~' in cell or '✧' in cell or '✦' in cell or '⊕' in cell:
                bad.append('%s:%d %s' % (n, i, m.group(1)))
check('no marker or strikethrough inside an identifier cell', not bad, ', '.join(bad[:5]))

# mermaid fences balanced
total_diag = 0
for n, t in texts.items():
    opens = len(re.findall(r'```mermaid', t))
    closes = len(re.findall(r'^```$', t, re.M))
    total_diag += opens
    check('%s: mermaid fences balanced' % n, opens <= closes, '%d open, %d close' % (opens, closes))
check('30 diagrams across the baseline', total_diag == 30, 'found %d' % total_diag)

# markdown tables well formed
bad = []
for n, t in texts.items():
    lines = t.split('\n')
    infence = False
    for i, line in enumerate(lines):
        if line.startswith('```'):
            infence = not infence
        if infence:
            continue
        if re.match(r'^\s*\|[-: |]*-[-: |]*\|\s*$', line) and i > 0:
            hdr = lines[i - 1]
            if hdr.count('|') != line.count('|'):
                bad.append('%s:%d' % (n, i + 1))
check('markdown tables well-formed', not bad, ', '.join(bad[:5]))

print()
print('=' * 70)
print('VERSION STAMPS')
print('=' * 70)
for n, t in texts.items():
    head = t[:900]
    check('%s stamped B2 v1.2' % n, 'Baseline:** B2' in head and 'Version:** 1.2' in head)

print()
print('=' * 70)
print('LINE ENDINGS AND RECORDED HASHES  (check 25)')
print('=' * 70)

baseline_md = io.open(os.path.join(DOCS, 'baseline.md'), encoding='utf-8').read()
recorded = dict(re.findall(r'\]\(\./([a-z-]+)\.md\)\s*\|[^|]*\|[^|]*\|[^|]*\|\s*`([0-9a-f]{16})`', baseline_md))

for n in BASE:
    raw = io.open(os.path.join(DOCS, n + '.md'), 'rb').read()
    crlf = raw.count(b'\r\n')
    check('%s is LF-terminated' % n, crlf == 0, '%d CRLF line endings' % crlf)

for n in BASE:
    raw = io.open(os.path.join(DOCS, n + '.md'), 'rb').read()
    actual = hashlib.sha256(raw).hexdigest()[:16]
    want = recorded.get(n)
    check('%s hash matches baseline §1' % n, want == actual,
          'recorded %s, actual %s' % (want, actual))

print()
print('=' * 70)
print('SHA-256 (first 16) FOR THE BASELINE TABLE')
print('=' * 70)
for n in BASE:
    raw = io.open(os.path.join(DOCS, n + '.md'), 'rb').read()
    t = texts[n]
    print('  %-45s %s  %5d words %5d lines' % (
        n + '.md', hashlib.sha256(raw).hexdigest()[:16], len(t.split()), len(t.split('\n'))))

print()
print('=' * 70)
if fails:
    print('FAILED: %d check(s)' % len(fails))
    for f in fails:
        print('   - ' + f)
else:
    print('ALL CHECKS PASSED')
print('=' * 70)
