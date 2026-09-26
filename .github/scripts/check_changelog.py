"""Require a new, nonempty Unreleased bullet compared with the PR's merge base."""
import re
import subprocess
import sys
from collections import Counter


def git(*args):
    return subprocess.check_output(['git', *args], text=True)


def entries(text):
    section = re.search(r'^## \[Unreleased\]\s*\n(.*?)(?=^## |\Z)', text, re.M | re.S)
    if section is None:
        return Counter()
    return Counter(
        line.strip() for line in section[1].splitlines()
        if re.match(r'^[-*] \S', line.strip())
    )


if __name__ == '__main__':
    base, head = sys.argv[1:]
    ancestor = git('merge-base', base, head).strip()
    old = git('show', f'{ancestor}:CHANGELOG.md')
    new = git('show', f'{head}:CHANGELOG.md')
    if not entries(new) - entries(old):
        sys.exit('::error file=CHANGELOG.md::Add a new bullet under ## [Unreleased] describing this PR.')
    print('CHANGELOG.md contains a new Unreleased entry.')
