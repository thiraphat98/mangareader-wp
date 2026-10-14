#!/usr/bin/env python3
"""Import changed manga folders through the signed WordPress endpoint.

inotify drives immediate imports. A low-frequency filesystem audit recovers
missed events without polling WordPress or importing unchanged chapters.
"""
from __future__ import annotations

import argparse
import ctypes
import errno
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import select
import signal
import struct
import subprocess
import sys
import time
from datetime import datetime

ROOT_DEFAULT = Path("/mnt/hdd4/var/www/html/public/manga")
CONFIG_DEFAULT = Path("/mnt/hdd4/var/www/html/public/wp-config.php")
STATE_DEFAULT = Path.home() / ".local/state/mangareader-watcher/state.json"
ENDPOINT = "https://mangareader.vvpns.com/wp-json/manga/v1/watcher-sync"
AUDIT_INTERVAL = 15 * 60
ROOT_CHECK_INTERVAL = 5
EVENT_SETTLE_SECONDS = 2
RECOVERY_SETTLE_SECONDS = 10
EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".webp", ".avif"}
EVENT = struct.Struct("iIII")
IN_ATTRIB = 0x00000004
IN_CLOSE_WRITE = 0x00000008
IN_MOVED_FROM = 0x00000040
IN_MOVED_TO = 0x00000080
IN_CREATE = 0x00000100
IN_DELETE = 0x00000200
IN_DELETE_SELF = 0x00000400
IN_MOVE_SELF = 0x00000800
IN_Q_OVERFLOW = 0x00004000
IN_IGNORED = 0x00008000
IN_ISDIR = 0x40000000
WATCH_MASK = (IN_ATTRIB | IN_CLOSE_WRITE | IN_MOVED_FROM | IN_MOVED_TO |
              IN_CREATE | IN_DELETE | IN_DELETE_SELF | IN_MOVE_SELF)
LIBC = ctypes.CDLL("libc.so.6", use_errno=True)
LIBC.inotify_init1.argtypes = [ctypes.c_int]
LIBC.inotify_init1.restype = ctypes.c_int
LIBC.inotify_add_watch.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_uint32]
LIBC.inotify_add_watch.restype = ctypes.c_int


def log(message: str) -> None:
    print(f"[{datetime.now().astimezone().isoformat()}] {message}", flush=True)


def secret_from_wp_config(path: Path) -> bytes:
    source = path.read_text(encoding="utf-8")
    match = re.search(
        r"""define\s*\(\s*['"]MANGA_WATCHER_SECRET['"]\s*,\s*['"]([^'"]+)['"]\s*\)""",
        source,
    )
    if not match or len(match.group(1)) < 32:
        raise RuntimeError("MANGA_WATCHER_SECRET is missing or too short")
    return match.group(1).encode("utf-8")


def image_manifest(folder: Path) -> str | None:
    try:
        entries = []
        with os.scandir(folder) as listing:
            for entry in listing:
                if Path(entry.name).suffix.lower() not in EXTENSIONS:
                    continue
                if not entry.is_file(follow_symlinks=False):
                    continue
                info = entry.stat(follow_symlinks=False)
                if info.st_size <= 0:
                    return None
                entries.append((entry.name, info.st_size, info.st_mtime_ns))
    except (FileNotFoundError, PermissionError, NotADirectoryError):
        return None
    if not entries:
        return None
    entries.sort(key=lambda item: item[0].casefold())
    payload = json.dumps(entries, ensure_ascii=False, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def save_json(path: Path, value: object) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    with temporary.open("w", encoding="utf-8") as stream:
        json.dump(value, stream, ensure_ascii=False, separators=(",", ":"))
        stream.flush()
        os.fsync(stream.fileno())
    os.chmod(temporary, 0o600)
    os.replace(temporary, path)


def load_state(path: Path) -> tuple[dict[str, str], list[str], bool]:
    if not path.exists():
        return {}, [], False
    data = json.loads(path.read_text(encoding="utf-8"))
    signatures = data["signatures"]
    pending = data["pending"]
    if not isinstance(signatures, dict) or not isinstance(pending, list):
        raise ValueError("Invalid watcher state")
    return signatures, pending, True


def send(endpoint: str, secret: bytes, relative: str, limit: int = 1) -> dict:
    stamp = int(time.time())
    message = f"{stamp}\n{relative}\n{limit}".encode("utf-8")
    signature = hmac.new(secret, message, hashlib.sha256).hexdigest()
    body = json.dumps(
        {"path": relative, "timestamp": stamp, "limit": limit},
        ensure_ascii=False, separators=(",", ":"),
    ).encode("utf-8")
    command = [
        "/usr/bin/curl", "--silent", "--show-error", "--max-time", "45",
        "--resolve", "mangareader.vvpns.com:443:127.0.0.1",
        "--request", "POST", "--header", "Content-Type: application/json",
        "--header", f"X-Manga-Signature: {signature}",
        "--data-binary", "@-", "--write-out", "\n%{http_code}", endpoint,
    ]
    result = subprocess.run(command, input=body, capture_output=True, timeout=50)
    if result.returncode:
        raise RuntimeError(f"curl failed: {result.stderr.decode('utf-8', 'replace')[:300]}")
    response, status = result.stdout.rsplit(b"\n", 1)
    if status != b"200":
        raise RuntimeError(f"HTTP {status.decode()}: {response[:300].decode('utf-8', 'replace')}")
    data = json.loads(response)
    if not isinstance(data, dict) or "created" not in data or "more" not in data:
        raise RuntimeError("Unexpected importer response")
    if data.get("errors"):
        raise RuntimeError(f"Importer errors: {data['errors'][:3]}")
    return data


class Watcher:
    def __init__(self, root: Path, state_path: Path, endpoint: str, secret: bytes,
                 audit_interval: int = AUDIT_INTERVAL):
        self.root = root.resolve(strict=True)
        self.state_path = state_path
        self.endpoint = endpoint
        self.secret = secret
        self.saved, restored, self.has_state = load_state(state_path)
        self.pending: dict[str, dict] = {
            path: {"due": time.monotonic() + 2, "priority": 0, "attempts": 0}
            for path in restored if self.safe_relative(path)
        }
        self.fd = -1
        self.watches: dict[int, Path] = {}
        self.next_send = 0.0
        self.next_audit = time.monotonic() + audit_interval
        self.next_root_check = 0.0
        self.audit_interval = audit_interval
        self.root_identity = self.identity()
        self.running = True

    def identity(self) -> tuple[int, int]:
        info = self.root.stat()
        return info.st_dev, info.st_ino

    @staticmethod
    def safe_relative(relative: str) -> bool:
        parts = relative.split("/")
        return (2 <= len(parts) <= 5 and
                all(part and not part.startswith(".") and "\\" not in part for part in parts))

    def relative(self, path: Path) -> str | None:
        try:
            relative = path.relative_to(self.root).as_posix()
        except ValueError:
            return None
        return relative if self.safe_relative(relative) else None

    def persist(self) -> None:
        save_json(self.state_path, {
            "signatures": self.saved,
            "pending": list(self.pending),
        })

    def queue(self, relative: str, priority: int, delay: int = EVENT_SETTLE_SECONDS) -> None:
        if not self.safe_relative(relative):
            return
        due = time.monotonic() + delay
        if relative in self.pending:
            item = self.pending[relative]
            if priority > item["priority"]:
                item["priority"] = priority
            if priority >= 2 or due < item["due"]:
                item["due"] = due
            return
        self.pending[relative] = {"due": due, "priority": priority, "attempts": 0}
        self.persist()
        log(f"Queued {relative}")

    def init_inotify(self) -> None:
        if self.fd >= 0:
            os.close(self.fd)
        self.fd = LIBC.inotify_init1(os.O_NONBLOCK | os.O_CLOEXEC)
        if self.fd < 0:
            raise OSError(ctypes.get_errno(), "inotify_init1 failed")
        self.watches.clear()

    def add_watch(self, folder: Path) -> None:
        wd = LIBC.inotify_add_watch(self.fd, os.fsencode(folder), WATCH_MASK)
        if wd < 0:
            error = ctypes.get_errno()
            if error not in (errno.ENOENT, errno.ENOTDIR):
                raise OSError(error, f"Cannot watch {folder}")
            return
        self.watches[wd] = folder

    def walk_library(self) -> dict[str, str]:
        found: dict[str, str] = {}
        def fail(error: OSError) -> None:
            raise error

        for directory, child_dirs, _files in os.walk(
            self.root, followlinks=False, onerror=fail
        ):
            folder = Path(directory)
            depth = len(folder.relative_to(self.root).parts)
            child_dirs[:] = [
                name for name in child_dirs
                if not name.startswith(".") and not (folder / name).is_symlink()
            ]
            if depth > 5:
                child_dirs.clear()
                continue
            self.add_watch(folder)
            if depth >= 2:
                relative = self.relative(folder)
                if relative:
                    signature = image_manifest(folder)
                    if signature:
                        found[relative] = signature
        return found

    def reconcile(self) -> None:
        before = self.identity()
        if before != self.root_identity:
            raise OSError("Manga library changed during watch setup")
        current = self.walk_library()
        if self.identity() != before:
            raise OSError("Manga library changed during reconciliation")
        if not self.has_state:
            # Recover recent uploads during the Windows-to-Linux handover.
            cutoff = time.time() - 24 * 60 * 60
            for relative in current:
                try:
                    if (self.root / relative).stat().st_mtime >= cutoff:
                        self.queue(relative, 1, RECOVERY_SETTLE_SECONDS)
                except FileNotFoundError:
                    pass
        else:
            for relative, signature in current.items():
                if self.saved.get(relative) != signature:
                    self.queue(relative, 1, RECOVERY_SETTLE_SECONDS)
        self.saved = current
        self.has_state = True
        self.persist()
        self.next_audit = time.monotonic() + self.audit_interval
        log(f"Watching {len(self.watches)} folders; pending={len(self.pending)}")

    def watch_new_tree(self, folder: Path, moved: bool) -> None:
        if folder.name.startswith(".") or not folder.is_dir() or folder.is_symlink():
            return
        for directory, child_dirs, _files in os.walk(folder, followlinks=False):
            current = Path(directory)
            depth = len(current.relative_to(self.root).parts)
            child_dirs[:] = [
                name for name in child_dirs
                if not name.startswith(".") and not (current / name).is_symlink()
            ]
            if depth > 5:
                child_dirs.clear()
                continue
            self.add_watch(current)
            if depth >= 2:
                relative = self.relative(current)
                if relative and image_manifest(current):
                    self.queue(relative, 2 if moved else 1,
                               EVENT_SETTLE_SECONDS if moved else RECOVERY_SETTLE_SECONDS)

    def handle_event(self, wd: int, mask: int, name: str) -> bool:
        if mask & (IN_Q_OVERFLOW | IN_MOVE_SELF | IN_DELETE_SELF):
            log("Filesystem watch moved, deleted, or overflowed; rebuilding watches")
            self.init_inotify()
            self.reconcile()
            return True
        folder = self.watches.get(wd)
        if folder is None:
            return False
        path = folder / name if name else folder
        if mask & IN_IGNORED:
            self.watches.pop(wd, None)
            return False
        if mask & IN_ISDIR:
            if mask & (IN_CREATE | IN_MOVED_TO):
                self.watch_new_tree(path, bool(mask & IN_MOVED_TO))
            return False
        if path.suffix.lower() in EXTENSIONS and mask & (
            IN_CLOSE_WRITE | IN_MOVED_TO | IN_DELETE | IN_MOVED_FROM
        ):
            relative = self.relative(folder)
            if relative:
                self.queue(relative, 2)
        return False

    def read_events(self) -> None:
        while True:
            try:
                data = os.read(self.fd, 1024 * 1024)
            except BlockingIOError:
                return
            if not data:
                return
            offset = 0
            while offset + EVENT.size <= len(data):
                wd, mask, _cookie, name_length = EVENT.unpack_from(data, offset)
                offset += EVENT.size
                name = os.fsdecode(data[offset:offset + name_length].split(b"\0", 1)[0])
                offset += name_length
                if self.handle_event(wd, mask, name):
                    return

    def sync_ready(self) -> None:
        now = time.monotonic()
        if now < self.next_send:
            return
        ready = [
            (relative, item) for relative, item in self.pending.items()
            if item["due"] <= now
        ]
        if not ready:
            return
        relative, item = min(
            ready, key=lambda pair: (-pair[1]["priority"], pair[1]["due"])
        )
        folder = self.root / relative
        before = image_manifest(folder)
        if before is None:
            self.pending.pop(relative, None)
            self.persist()
            return
        time.sleep(1)
        self.read_events()
        if item["due"] > time.monotonic():
            return
        after = image_manifest(folder)
        if before != after or after is None:
            item["due"] = time.monotonic() + 2
            return
        self.next_send = time.monotonic() + 1
        try:
            started = time.monotonic()
            result = send(self.endpoint, self.secret, relative, limit=1)
            elapsed = int((time.monotonic() - started) * 1000)
            log(f"Synced {relative}: created={result['created']} repaired={result['repaired']} "
                f"more={result['more']} duration_ms={elapsed}")
            self.saved[relative] = after
            if result["more"]:
                item["due"] = self.next_send
                item["attempts"] = 0
            else:
                self.pending.pop(relative, None)
            self.persist()
        except Exception as error:
            item["attempts"] += 1
            delay = min(60, 2 ** min(6, item["attempts"]))
            item["due"] = time.monotonic() + delay
            log(f"Retry {relative} in {delay}s: {error}")

    def run(self) -> None:
        self.init_inotify()
        self.reconcile()
        while self.running:
            try:
                now = time.monotonic()
                if now >= self.next_root_check:
                    self.next_root_check = now + ROOT_CHECK_INTERVAL
                    if self.identity() != self.root_identity:
                        log("Manga library mount changed; rebuilding watches")
                        self.root_identity = self.identity()
                        self.init_inotify()
                        self.reconcile()
                if now >= self.next_audit:
                    self.reconcile()
                self.sync_ready()
                now = time.monotonic()
                due = min(
                    (item["due"] for item in self.pending.values()),
                    default=now + 30,
                )
                timeout = max(0.1, min(1.0, due - now,
                                       self.next_audit - now,
                                       self.next_root_check - now))
                readable, _, _ = select.select([self.fd], [], [], timeout)
                if readable:
                    self.read_events()
            except (OSError, ValueError) as error:
                log(f"Recovering filesystem watcher: {error}")
                time.sleep(5)
                try:
                    self.root_identity = self.identity()
                except (OSError, ValueError):
                    continue
                self.init_inotify()
                self.reconcile()

    def close(self) -> None:
        if self.fd >= 0:
            os.close(self.fd)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=ROOT_DEFAULT)
    parser.add_argument("--wp-config", type=Path, default=CONFIG_DEFAULT)
    parser.add_argument("--state", type=Path, default=STATE_DEFAULT)
    parser.add_argument("--endpoint", default=ENDPOINT)
    parser.add_argument("--audit-interval", type=int, default=AUDIT_INTERVAL)
    arguments = parser.parse_args()
    if arguments.audit_interval < 30:
        parser.error("--audit-interval must be at least 30 seconds")
    secret = secret_from_wp_config(arguments.wp_config)
    watcher = Watcher(arguments.root, arguments.state, arguments.endpoint, secret,
                      arguments.audit_interval)
    signal.signal(signal.SIGTERM, lambda _signum, _frame: setattr(watcher, "running", False))
    signal.signal(signal.SIGINT, lambda _signum, _frame: setattr(watcher, "running", False))
    try:
        watcher.run()
    finally:
        watcher.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
