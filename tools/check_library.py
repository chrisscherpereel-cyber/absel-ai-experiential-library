"""Check the exercise library.

Validates every activities/<slug>/manifest.json against schema/exercise.schema.json,
checks each package against its manifest, and checks that the indexing rules and the
author prompt list every allowed value in the schema, that the prompt's manifest
template has the schema's fields, and that the WordPress plugin's copies of the schema
and prompt (in the submission page and in assets/) are current.

Usage: py tools/check_library.py          (needs: pip install jsonschema)
Exit code 1 if anything fails. Warnings don't fail the check.
"""
import json
import re
import sys
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

import build_form

ROOT = Path(__file__).resolve().parent.parent
SCHEMA = ROOT / "schema" / "exercise.schema.json"
DOCS = [ROOT / "schema" / "INDEXING-RULES.md", ROOT / "author-kit" / "author-prompt.md"]
ACTIVITIES = ROOT / "activities"

errors = []
warnings = []


def enum_values(node):
    """Every allowed value anywhere in the schema."""
    found = set()
    if isinstance(node, dict):
        for value in node.get("enum", []):
            if isinstance(value, str):
                found.add(value)
        for child in node.values():
            found |= enum_values(child)
    elif isinstance(node, list):
        for child in node:
            found |= enum_values(child)
    return found


def check_docs(schema):
    values = enum_values(schema)
    for doc in DOCS:
        text = doc.read_text(encoding="utf-8")
        for value in sorted(values):
            if not re.search(r"(?<![\w-])" + re.escape(value) + r"(?![\w-])", text):
                errors.append(f"{doc.relative_to(ROOT)}: allowed value '{value}' is not listed")


def check_prompt_template(schema):
    """The manifest template in the author prompt must have the schema's top-level fields."""
    prompt = DOCS[1]
    text = prompt.read_text(encoding="utf-8").split("MANIFEST FORMAT", 1)[-1]
    try:
        block = re.search(r"^\{\n.*?^\}$", text, re.S | re.M).group(0)
        template = json.loads(block)
    except (AttributeError, ValueError) as exc:
        errors.append(f"{prompt.relative_to(ROOT)}: can't read the manifest template ({exc})")
        return
    for field in sorted(set(schema["properties"]) - set(template)):
        errors.append(f"{prompt.relative_to(ROOT)}: template is missing field '{field}'")
    for field in sorted(set(template) - set(schema["properties"])):
        errors.append(f"{prompt.relative_to(ROOT)}: template has field '{field}', which isn't in the schema")


def check_form():
    """The submission form's copies of the schema and prompt must match the originals."""
    current = build_form.embedded(build_form.FORM.read_text(encoding="utf-8"))
    expected = build_form.sources()
    form = build_form.FORM.relative_to(ROOT)
    try:
        schema_ok = current["schema"] is not None and json.loads(current["schema"]) == json.loads(expected["schema"])
    except json.JSONDecodeError:
        schema_ok = False
    if not schema_ok:
        errors.append(f"{form}: its copy of the schema is out of date; run py tools/build_form.py")
    if current["prompt"] != expected["prompt"]:
        errors.append(f"{form}: its copy of the author prompt is out of date; run py tools/build_form.py")
    plugin_schema = build_form.PLUGIN_SCHEMA
    if not plugin_schema.is_file() or plugin_schema.read_text(encoding="utf-8") != build_form.SCHEMA.read_text(encoding="utf-8"):
        errors.append(f"{plugin_schema.relative_to(ROOT)} is out of date; run py tools/build_form.py")


def check_package(folder, manifest):
    where = f"{folder.name}/manifest.json"
    if manifest.get("slug", folder.name) != folder.name:
        errors.append(f"{where}: slug '{manifest.get('slug')}' doesn't match folder name")

    for field in ("class_size", "group_size"):
        rng = manifest.get(field)
        if rng and rng["max"] is not None and rng["min"] > rng["max"]:
            errors.append(f"{where}: {field} min is greater than max")
    rng = (manifest.get("duration") or {}).get("in_class_minutes")
    if rng and rng["max"] is not None and rng["min"] > rng["max"]:
        errors.append(f"{where}: duration.in_class_minutes min is greater than max")

    # Completeness is advice, not a rule: the library accepts anything from an idea up.
    words = len((manifest.get("summary") or "").split())
    if not words:
        warnings.append(f"{where}: no summary")
    elif not 60 <= words <= 180:
        warnings.append(f"{where}: summary is {words} words (aim for 60-180)")

    materials = manifest.get("materials", [])
    if manifest.get("stage") in ("ready-to-use", "classroom-tested") and not any(m.get("role") == "instructor-guide" for m in materials):
        warnings.append(f"{where}: marked {manifest['stage']} but no material has role instructor-guide")
    unconfirmed = [a["field"] for a in manifest.get("assumptions", []) if not a.get("confirmed")]
    if unconfirmed:
        warnings.append(f"{where}: assumptions not yet checked by the author: {', '.join(unconfirmed)}")

    listed = set()
    for m in materials:
        name = m.get("file")
        if not name:
            continue
        listed.add(name)
        if (folder / name).is_file():
            continue
        source = (folder / name).with_suffix(".md")
        if name.lower().endswith(".pdf") and source.is_file():
            listed.add(source.name)
            warnings.append(f"{folder.name}/{name}: not built yet; export {source.name} to PDF before uploading")
        else:
            errors.append(f"{where}: lists '{name}', which isn't in the folder")

    for path in sorted(folder.rglob("*")):
        rel = path.relative_to(folder).as_posix()
        if path.is_dir() or rel == "manifest.json" or any(p[0] in "._" for p in rel.split("/")):
            continue
        if rel not in listed:
            errors.append(f"{folder.name}/{rel}: file isn't listed in materials")


def main():
    schema = json.loads(SCHEMA.read_text(encoding="utf-8"))
    Draft202012Validator.check_schema(schema)
    validator = Draft202012Validator(schema, format_checker=FormatChecker())
    check_docs(schema)
    check_prompt_template(schema)
    check_form()

    folders = [f for f in sorted(ACTIVITIES.iterdir()) if f.is_dir() and f.name[0] not in "._"]
    for folder in folders:
        path = folder / "manifest.json"
        if not path.is_file():
            errors.append(f"{folder.name}: no manifest.json")
            continue
        try:
            manifest = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            errors.append(f"{folder.name}/manifest.json: not valid JSON ({exc})")
            continue
        problems = sorted(validator.iter_errors(manifest), key=lambda e: list(e.absolute_path))
        for err in problems:
            loc = "/".join(str(p) for p in err.absolute_path) or "(top level)"
            errors.append(f"{folder.name}/manifest.json: {loc}: {err.message}")
        if not problems:
            check_package(folder, manifest)

    for w in warnings:
        print("warning:", w)
    for e in errors:
        print("ERROR:", e)
    print(f"{len(folders)} exercise(s) checked: {len(errors)} error(s), {len(warnings)} warning(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
