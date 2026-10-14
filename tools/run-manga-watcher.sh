#!/bin/sh
# Run under the tui-pi user from cron. flock is held by this shell.
set -eu

app_dir="$HOME/.local/share/mangareader-watcher"
state_dir="$HOME/.local/state/mangareader-watcher"
mkdir -p "$state_dir"
chmod 700 "$state_dir"

while :; do
    if [ ! -d /mnt/hdd4/var/www/html/public/manga ] ||
       [ ! -r /mnt/hdd4/var/www/html/public/wp-config.php ]; then
        printf '%s Waiting for WordPress storage\n' "$(date -Is)" >> "$state_dir/watcher.log"
        sleep 15
        continue
    fi
    /usr/bin/python3 "$app_dir/watch_manga_chapters.py" >> "$state_dir/watcher.log" 2>&1 || true
    printf '%s Watcher exited; restarting in 5 seconds\n' "$(date -Is)" >> "$state_dir/watcher.log"
    sleep 5
done
