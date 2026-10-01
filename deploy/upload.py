#!/usr/bin/env python3
"""Upload Sign-Forge to shared hosting over SFTP.

The hosting account on Xneelo accepts SFTP. Passive FTP from this network
cannot open the data port, so this script uses SSH file transfer.

Environment:
  SF_FTP_HOST   host or IP
  SF_FTP_USER   FTP/SFTP username
  SF_FTP_PASS   FTP/SFTP password
  SF_FTP_PATH   remote directory, default public_html
  SF_CONFIG_LOCAL  optional path to the production config.local.php to upload

Local app/config/config.local.php is never uploaded. Pass SF_CONFIG_LOCAL
for the server copy. .git and log files are skipped.
"""

from __future__ import annotations

import os
import stat
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parent.parent
SKIP_DIRS = {".git"}
SKIP_FILES = {".DS_Store"}


def require_env(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if value == "":
        print(f"Missing {name}.", file=sys.stderr)
        sys.exit(1)
    return value


def should_skip(path: Path) -> bool:
    if path.name in SKIP_FILES:
        return True
    if path.name == "config.local.php":
        return True
    if path.suffix == ".log":
        return True
    return False


def mkdir_p(sftp: paramiko.SFTPClient, remote_dir: str) -> None:
    parts = remote_dir.strip("/").split("/")
    current = ""
    for part in parts:
        current += "/" + part
        try:
            sftp.stat(current)
        except FileNotFoundError:
            sftp.mkdir(current)


def upload_tree(sftp: paramiko.SFTPClient, local: Path, remote: str) -> int:
    count = 0
    for dirpath, dirnames, filenames in os.walk(local):
        dirnames[:] = [name for name in dirnames if name not in SKIP_DIRS]
        current = Path(dirpath)
        relative = current.relative_to(local).as_posix()
        remote_dir = remote if relative == "." else f"{remote}/{relative}"
        mkdir_p(sftp, remote_dir)
        for filename in filenames:
            local_file = current / filename
            if should_skip(local_file):
                continue
            remote_file = f"{remote_dir}/{filename}"
            sftp.put(str(local_file), remote_file)
            count += 1
            print(remote_file)
    return count


def main() -> None:
    host = require_env("SF_FTP_HOST")
    user = require_env("SF_FTP_USER")
    password = require_env("SF_FTP_PASS")
    remote_path = os.environ.get("SF_FTP_PATH", "public_html").strip("/") or "public_html"
    config_local = os.environ.get("SF_CONFIG_LOCAL", "").strip()

    transport = paramiko.Transport((host, 22))
    transport.connect(username=user, password=password)
    sftp = paramiko.SFTPClient.from_transport(transport)
    if sftp is None:
        print("Could not open SFTP.", file=sys.stderr)
        sys.exit(1)

    try:
        uploaded = upload_tree(sftp, ROOT, "/" + remote_path)
        if config_local != "":
            target = f"/{remote_path}/app/config/config.local.php"
            sftp.put(config_local, target)
            sftp.chmod(target, stat.S_IRUSR | stat.S_IWUSR)
            print(target)
            uploaded += 1
        for folder in ("storage", "storage/logs", "storage/quotes", "storage/uploads", "storage/temp"):
            remote_folder = f"/{remote_path}/{folder}"
            mkdir_p(sftp, remote_folder)
            sftp.chmod(remote_folder, 0o775)
        print(f"Uploaded {uploaded} files.")
    finally:
        sftp.close()
        transport.close()


if __name__ == "__main__":
    main()
