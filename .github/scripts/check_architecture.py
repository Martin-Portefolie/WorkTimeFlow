"""Reject new violations of the repository's layering rules.

Existing findings are treated as a baseline so the check can be introduced to
an active codebase without hiding new violations. Each new finding still fails
the pull request and prints the file, line and rule that need attention.
"""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).parents[2]
RULES = (
    (re.compile(r"createQueryBuilder\s*\(|createNativeQuery\s*\(|->getQuery\s*\("),
     "database query construction belongs in a repository"),
    (re.compile(r"(?:executeQuery|executeStatement|prepare)\s*\("),
     "database execution belongs in a repository"),
    (re.compile(r"<(?:div|span|table|tr|td|button|section|p|form)(?:\s|>)", re.I),
     "HTML belongs in templates"),
)


def git(*args: str) -> str:
    return subprocess.check_output(["git", *args], cwd=ROOT, text=True)


def tracked_files(revision: str) -> list[str]:
    return [p for p in git("ls-tree", "-r", "--name-only", revision).splitlines()
            if p.startswith(("src/Service/", "src/Controller/", "assets/controllers/"))]


def findings(revision: str) -> set[tuple[str, str, str]]:
    result: set[tuple[str, str, str]] = set()
    for path in tracked_files(revision):
        text = git("show", f"{revision}:{path}")
        if path.startswith(("src/Service/", "src/Controller/")):
            rules = RULES
        else:
            rules = RULES[2:]
        for number, line in enumerate(text.splitlines(), 1):
            for pattern, message in rules:
                if pattern.search(line):
                    normalized = re.sub(r"\s+", " ", line.strip())
                    result.add((path, normalized, message))
    return result


def main() -> int:
    base, head = sys.argv[1:3]
    old = findings(base)
    new = sorted(findings(head) - old)
    if not new:
        print("Architecture check passed: no new layering violations.")
        return 0
    print("::error::New architecture violations found:")
    for path, line, message in new:
        print(f"::error file={path}::{message}: {line}")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
