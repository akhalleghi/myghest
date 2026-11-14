"""Serve isolated HTML/JSON fixtures plus public assets on loopback, read-only."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit, unquote
import tempfile

fixture = Path(tempfile.gettempdir()) / "myghest-cheque-ui-preview"
public = Path(__file__).resolve().parents[1] / "public"


class PreviewHandler(SimpleHTTPRequestHandler):
    def translate_path(self, path):
        if urlsplit(path).path == "/api" and "workspace=1" in urlsplit(path).query:
            return str(fixture / "api/workspace.html")
        requested = Path(unquote(urlsplit(path).path).lstrip("/"))
        root = public if requested.parts and requested.parts[0] in {"build", "css", "js", "vendor", "fonts"} else fixture
        resolved = (root / requested).resolve()
        if not resolved.is_relative_to(root.resolve()):
            return str(fixture / "not-found")
        return str(resolved)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        super().end_headers()


if __name__ == "__main__":
    print("Read-only cheque preview: http://127.0.0.1:18857/", flush=True)
    ThreadingHTTPServer(("127.0.0.1", 18857), PreviewHandler).serve_forever()
