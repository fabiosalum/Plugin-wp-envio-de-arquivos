#!/usr/bin/env python3
"""Generate the installable WordPress package and its update manifest."""
import hashlib
import json
import re
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
SLUG = "f2-documentos-institucionais"
REPOSITORY = "fabiosalum/Plugin-wp-envio-de-arquivos"
source = (ROOT / (SLUG + ".php")).read_text()
readme = (ROOT / "readme.txt").read_text()

def header(name):
    match = re.search(r"^\s*\*\s*" + re.escape(name) + r":\s*(\S+)", source, re.MULTILINE)
    if not match:
        raise SystemExit("Missing plugin header " + name)
    return match.group(1)

version = header("Version")
if not re.fullmatch(r"[0-9]+(?:\.[0-9]+){1,3}", version):
    raise SystemExit("Release versions must contain only numbers and dots.")
if not re.search(r"const VERSION = '" + re.escape(version) + r"';", source):
    raise SystemExit("The plugin header and VERSION constant must match.")
if not re.search(r"^Stable tag:\s*" + re.escape(version) + r"\s*$", readme, re.MULTILINE):
    raise SystemExit("The readme Stable tag must match the plugin version.")

files = [ROOT / (SLUG + ".php"), ROOT / "readme.txt"]
for directory in ("assets", "includes"):
    files.extend(sorted(path for path in (ROOT / directory).rglob("*") if path.is_file()))
dist = ROOT / "dist"
dist.mkdir(exist_ok=True)
package = dist / (SLUG + ".zip")
with ZipFile(package, "w", compression=ZIP_DEFLATED) as archive:
    for path in files:
        if path.is_symlink():
            raise SystemExit("Symbolic links are not allowed in the release.")
        info = ZipInfo(SLUG + "/" + path.relative_to(ROOT).as_posix(), (2020, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())

manifest = {
    "slug": SLUG,
    "version": version,
    "requires": header("Requires at least"),
    "requires_php": header("Requires PHP"),
    "package": f"https://github.com/{REPOSITORY}/releases/download/v{version}/{SLUG}.zip",
    "sha256": hashlib.sha256(package.read_bytes()).hexdigest(),
}
(dist / "update.json").write_text(json.dumps(manifest, indent=2) + "\n")
(dist / "release-notes.md").write_text(
    f"eduCAT Documentos {version}\n\n"
    "Baixe f2-documentos-institucionais.zip para instalar ou atualizar no WordPress.\n\n"
    "O pacote inclui o catálogo por categorias e a integração de atualizações pelo GitHub. "
    "No painel, abra Documentos → Atualizações para consultar novas versões.\n"
)
print(version)
