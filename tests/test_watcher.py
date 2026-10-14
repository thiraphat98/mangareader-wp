"""Runtime checks for the Linux chapter watcher, without touching WordPress."""
import json
from pathlib import Path
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import watch_manga_chapters as watcher_module


class WatcherTests(unittest.TestCase):
    def test_image_manifest_changes_on_upload(self):
        with tempfile.TemporaryDirectory() as directory:
            chapter = Path(directory)
            self.assertIsNone(watcher_module.image_manifest(chapter))
            page = chapter / "001.jpg"
            page.write_bytes(b"one")
            first = watcher_module.image_manifest(chapter)
            page.write_bytes(b"two pages")
            self.assertNotEqual(first, watcher_module.image_manifest(chapter))

    def test_inotify_queues_and_syncs_one_folder(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "manga"
            chapter = root / "Series" / "1"
            chapter.mkdir(parents=True)
            state = Path(directory) / "state.json"
            watcher = watcher_module.Watcher(root, state, "https://example.test", b"unused")
            watcher.init_inotify()
            try:
                watcher.reconcile()
                (chapter / "001.jpg").write_bytes(b"page")
                time.sleep(0.05)
                watcher.read_events()
                self.assertIn("Series/1", watcher.pending)
                watcher.pending["Series/1"]["due"] = 0
                with patch.object(watcher_module, "send", return_value={
                    "created": 1, "repaired": 0, "more": False, "errors": []
                }) as send:
                    watcher.sync_ready()
                send.assert_called_once_with("https://example.test", b"unused", "Series/1", limit=1)
                self.assertEqual(watcher.saved["Series/1"], watcher_module.image_manifest(chapter))
                self.assertEqual(json.loads(state.read_text())["pending"], [])
            finally:
                watcher.close()

    def test_open_file_waits_for_close(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "manga"
            chapter = root / "Series" / "2"
            chapter.mkdir(parents=True)
            watcher = watcher_module.Watcher(root, Path(directory) / "state.json",
                                             "https://example.test", b"unused")
            watcher.init_inotify()
            try:
                watcher.reconcile()
                with (chapter / "001.jpg").open("wb") as page:
                    page.write(b"partial")
                    page.flush()
                    watcher.read_events()
                    self.assertNotIn("Series/2", watcher.pending)
                watcher.read_events()
                self.assertIn("Series/2", watcher.pending)
            finally:
                watcher.close()

    def test_audit_recovers_missed_event(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "manga"
            chapter = root / "Series" / "3"
            chapter.mkdir(parents=True)
            state = Path(directory) / "state.json"
            watcher = watcher_module.Watcher(root, state, "https://example.test", b"unused")
            watcher.init_inotify()
            try:
                watcher.reconcile()
                (chapter / "001.jpg").write_bytes(b"page")
                # Simulate an event that never reached the process.
                watcher.init_inotify()
                watcher.reconcile()
                self.assertIn("Series/3", watcher.pending)
                self.assertIn("Series/3", json.loads(state.read_text())["pending"])
            finally:
                watcher.close()

    def test_mount_identity_changes_when_root_is_replaced(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "manga"
            root.mkdir()
            watcher = watcher_module.Watcher(root, Path(directory) / "state.json",
                                             "https://example.test", b"unused")
            original = watcher.root_identity
            root.rename(Path(directory) / "old-manga")
            root.mkdir()
            self.assertNotEqual(original, watcher.identity())

    def test_directory_rename_rebuilds_watch_paths(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "manga"
            chapter = root / "Series" / "4"
            chapter.mkdir(parents=True)
            watcher = watcher_module.Watcher(root, Path(directory) / "state.json",
                                             "https://example.test", b"unused")
            watcher.init_inotify()
            try:
                watcher.reconcile()
                renamed = chapter.with_name("5")
                chapter.rename(renamed)
                watcher.read_events()
                self.assertIn(renamed, watcher.watches.values())
                (renamed / "001.jpg").write_bytes(b"page")
                watcher.read_events()
                self.assertIn("Series/5", watcher.pending)
            finally:
                watcher.close()


if __name__ == "__main__":
    unittest.main()
