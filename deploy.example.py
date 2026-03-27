#!/usr/bin/env python3
"""
Build, package, and deploy stirlingmerge via a remote Docker host.
Usage:
  pip install paramiko
  cp deploy.example.py deploy.py   # then edit credentials below
  python3 deploy.py
"""
import os
import sys
import paramiko

HOST     = 'YOUR_DOCKER_HOST_IP'
PORT     = 22
USER     = 'YOUR_SSH_USER'
PASSWORD = 'YOUR_SSH_PASSWORD'
SUDO_PW  = 'YOUR_SSH_PASSWORD'   # same as PASSWORD if user is sudoer

LOCAL_ROOT  = os.path.dirname(os.path.abspath(__file__))
REMOTE_DIR  = '/tmp/stirlingmerge-build'
APP_DIR     = 'stirlingmerge'

NC_CONTAINER = 'YOUR_NC_CONTAINER_NAME'   # e.g. 'nextcloud' or 'nc-dev'
NC_APPS_PATH = '/var/www/html/apps'


def connect():
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
    return client


def run(client, cmd, sudo=False, check=True):
    full_cmd = f"echo '{SUDO_PW}' | sudo -S sh -c {repr(cmd)}" if sudo else cmd
    stdin, stdout, stderr = client.exec_command(full_cmd)
    out = stdout.read().decode()
    err = stderr.read().decode()
    rc  = stdout.channel.recv_exit_status()
    if check and rc != 0:
        print(f'  CMD:    {cmd[:120]}')
        print(f'  STDOUT: {out[:400]}')
        print(f'  STDERR: {err[:400]}')
        raise RuntimeError(f'Command failed (rc={rc})')
    return out, err, rc


def upload_dir(sftp, local_dir, remote_dir):
    """Recursively upload local_dir to remote_dir via SFTP."""
    try:
        sftp.mkdir(remote_dir)
    except OSError:
        pass  # already exists

    for entry in os.listdir(local_dir):
        if entry in ('node_modules', '__pycache__', '.git'):
            continue
        local_path  = os.path.join(local_dir, entry)
        remote_path = remote_dir + '/' + entry
        if os.path.isdir(local_path):
            upload_dir(sftp, local_path, remote_path)
        else:
            sftp.put(local_path, remote_path)


def main():
    print(f'Connecting to {HOST}...')
    client = connect()
    sftp   = client.open_sftp()

    # 1. Upload source
    print(f'Uploading source to {REMOTE_DIR}...')
    run(client, f'rm -rf {REMOTE_DIR}', sudo=True)
    run(client, f'mkdir -p {REMOTE_DIR}')
    upload_dir(sftp, os.path.join(LOCAL_ROOT, APP_DIR), f'{REMOTE_DIR}/{APP_DIR}')
    sftp.put(os.path.join(LOCAL_ROOT, 'package-app.sh'), f'{REMOTE_DIR}/package-app.sh')
    run(client, f'chmod +x {REMOTE_DIR}/package-app.sh')

    # 2. Build (requires Docker on the remote host)
    print('Building JS assets via Docker (this may take a minute)...')
    out, err, _ = run(client, f'cd {REMOTE_DIR} && bash package-app.sh', sudo=True)
    print(out)

    # 3. Download package
    out, _, _ = run(client, f'ls {REMOTE_DIR}/*.tar.gz')
    remote_pkg = out.strip().split('\n')[0]
    local_pkg  = os.path.join(LOCAL_ROOT, os.path.basename(remote_pkg))
    print(f'Downloading {os.path.basename(remote_pkg)}...')
    sftp.get(remote_pkg, local_pkg)

    # 4. Deploy to NC container
    pkg_name = os.path.basename(remote_pkg)
    print(f'Deploying to NC container ({NC_CONTAINER})...')
    run(client, f'docker cp {remote_pkg} {NC_CONTAINER}:{NC_APPS_PATH}/{pkg_name}', sudo=True)
    run(client,
        f'docker exec {NC_CONTAINER} sh -c '
        f'"rm -rf {NC_APPS_PATH}/stirlingmerge && '
        f'tar -xzf {NC_APPS_PATH}/{pkg_name} -C {NC_APPS_PATH} && '
        f'rm {NC_APPS_PATH}/{pkg_name}"',
        sudo=True)
    run(client, f'docker exec --user www-data {NC_CONTAINER} php occ app:enable stirlingmerge', sudo=True)
    print('Restarting NC container to clear OPcache...')
    run(client, f'docker restart {NC_CONTAINER}', sudo=True)

    # 5. Cleanup
    run(client, f'rm -rf {REMOTE_DIR}', sudo=True)
    sftp.close()
    client.close()

    print(f'\nDone! Package saved locally: {local_pkg}')


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f'\nERROR: {e}', file=sys.stderr)
        sys.exit(1)
