"""Copy the schema and the author prompt into the WordPress plugin.

The submission page (wp-plugin/absel-ai-library/assets/submit.html) is a single
self-contained page, so it carries its own copies of schema/exercise.schema.json,
the prompt from author-kit/author-prompt.md and the SELAR emblem
(assets/selar-emblem.svg). The plugin also checks submissions against its own copy
of the schema (assets/exercise.schema.json). Run this after changing the schema,
the prompt or the emblem; tools/check_library.py fails while any copy is out of
date.

Usage: py tools/build_form.py
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCHEMA = ROOT / "schema" / "exercise.schema.json"
PROMPT_DOC = ROOT / "author-kit" / "author-prompt.md"
ASSETS = ROOT / "wp-plugin" / "absel-ai-library" / "assets"
FORM = ASSETS / "submit.html"
PLUGIN_SCHEMA = ASSETS / "exercise.schema.json"
EMBLEM = ASSETS / "selar-emblem.svg"

BLOCKS = {
    "schema": re.compile(r'(<script type="application/json" id="absel-schema">)(.*?)(</script>)', re.S),
    "prompt": re.compile(r'(<script type="text/plain" id="absel-prompt">)(.*?)(</script>)', re.S),
    "emblem": re.compile(r'(<div class="emblem" aria-hidden="true">)(.*?)(</div>)', re.S),
}


def prompt_text():
    """The prompt authors copy: the contents of the ````text block in author-prompt.md."""
    doc = PROMPT_DOC.read_text(encoding="utf-8")
    match = re.search(r"^````text\n(.*?)\n````$", doc, re.S | re.M)
    if not match:
        raise ValueError(f"{PROMPT_DOC.name}: no ````text block found")
    return match.group(1)


def sources():
    return {"schema": SCHEMA.read_text(encoding="utf-8").strip(), "prompt": prompt_text().strip(),
            "emblem": EMBLEM.read_text(encoding="utf-8").strip()}


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


def write_if_changed(path, text):
    if path.is_file() and path.read_text(encoding="utf-8") == text:
        print(f"{path.relative_to(ROOT)} is already up to date")
        return
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write(text)
    print(f"updated {path.relative_to(ROOT)}")


def main():
    write_if_changed(FORM, build(FORM.read_text(encoding="utf-8"), sources()))
    write_if_changed(PLUGIN_SCHEMA, SCHEMA.read_text(encoding="utf-8"))
    return 0


if __name__ == "__main__":
    sys.exit(main())
