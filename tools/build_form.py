"""Copy the schema and the author prompt into author-kit/submit.html.

The submission form is a single self-contained page, so it carries its own copies of
schema/exercise.schema.json and the prompt from author-kit/author-prompt.md. Run this
after changing either one; tools/check_library.py fails while the copies are out of date.

Usage: py tools/build_form.py
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCHEMA = ROOT / "schema" / "exercise.schema.json"
PROMPT_DOC = ROOT / "author-kit" / "author-prompt.md"
FORM = ROOT / "author-kit" / "submit.html"

BLOCKS = {
    "schema": re.compile(r'(<script type="application/json" id="absel-schema">)(.*?)(</script>)', re.S),
    "prompt": re.compile(r'(<script type="text/plain" id="absel-prompt">)(.*?)(</script>)', re.S),
}


def prompt_text():
    """The prompt authors copy: the contents of the ````text block in author-prompt.md."""
    doc = PROMPT_DOC.read_text(encoding="utf-8")
    match = re.search(r"^````text\n(.*?)\n````$", doc, re.S | re.M)
    if not match:
        raise ValueError(f"{PROMPT_DOC.name}: no ````text block found")
    return match.group(1)


def sources():
    return {"schema": SCHEMA.read_text(encoding="utf-8").strip(), "prompt": prompt_text().strip()}


def embedded(html):
    """The copies currently in the form, keyed like sources()."""
    found = {}
    for name, pattern in BLOCKS.items():
        match = pattern.search(html)
        found[name] = match.group(2).strip() if match else None
    return found


def build(html, content):
    for name, pattern in BLOCKS.items():
        body = content[name]
        if "</script" in body.lower():
            raise ValueError(f"{name} contains </script, which would break the page")
        html, count = pattern.subn(lambda m: m.group(1) + "\n" + body + "\n" + m.group(3), html, count=1)
        if count != 1:
            raise ValueError(f"{FORM.name}: no {name} block found")
    return html


def main():
    html = FORM.read_text(encoding="utf-8")
    updated = build(html, sources())
    if updated == html:
        print(f"{FORM.relative_to(ROOT)} is already up to date")
    else:
        with open(FORM, "w", encoding="utf-8", newline="\n") as f:
            f.write(updated)
        print(f"updated {FORM.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
