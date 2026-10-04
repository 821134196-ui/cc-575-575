#!/usr/bin/env bash
# 无 root 环境下把 PHP 8.2 + 必需扩展安装到 $HOME/local（Debian/Ubuntu 系）
set -euo pipefail

A=~/local/aptidx
D=~/local/php-debs
P=~/local/phproot
mkdir -p "$A" "$D" "$P" ~/local/bin

if [ ! -f "$A/Packages" ]; then
    echo "下载 Debian 包索引…"
    curl -sfL http://deb.debian.org/debian/dists/bookworm/main/binary-amd64/Packages.xz -o "$A/Packages.xz"
    xz -dkf "$A/Packages.xz"
fi

# 从索引解析依赖闭包并下载（仅取 PHP 及系统上缺失的少数库）
python3 - "$A/Packages" <<'PYEOF'
import re, sys, subprocess, pathlib
idx = pathlib.Path(sys.argv[1]).read_text()
stanzas = {}
for block in idx.split('\n\n'):
    m = re.search(r'^Package: (\S+)', block, re.M)
    if m: stanzas[m.group(1)] = block
def deps(n):
    b = stanzas.get(n, ''); m = re.search(r'^(?:Pre-)?Depends: (.+)', b, re.M)
    out = []
    if m:
        for d in m.group(1).split(','):
            for a in d.split('|'):
                a = a.strip().split()[0]
                if a in stanzas: out.append(a); break
    return out
roots = ['php8.2-cli','php8.2-sqlite3','php8.2-mbstring','php8.2-xml']
seen, q, urls = set(), list(roots), []
while q:
    p = q.pop(0)
    if p in seen or p not in stanzas: continue
    seen.add(p)
    fn = re.search(r'^Filename: (\S+)', stanzas[p], re.M).group(1)
    urls.append('http://deb.debian.org/debian/' + fn)
    q += deps(p)
have = subprocess.run(['ldconfig','-p'], capture_output=True, text=True).stdout
need = []
for u in sorted(set(urls)):
    fn = u.rsplit('/',1)[1]
    pkg = re.match(r'([a-z0-9.+-]+?)_', fn).group(1)
    # 系统已提供的通用库跳过；PHP 自身与 3 个稀有库下载
    if pkg.startswith('php') or pkg in ('libargon2-1','libsodium23','libonig5'):
        if not pathlib.Path.home().joinpath('local/php-debs', fn).exists():
            need.append(u)
for u in need:
    print('下载', u.rsplit('/',1)[1])
    subprocess.run(['curl','-sfLO',u], cwd=pathlib.Path.home()/'local/php-debs', check=True)
PYEOF

echo "解包到 $P …"
for f in "$D"/*.deb; do dpkg-deb -x "$f" "$P"; done

SCANDIR="$P/usr/lib/php/$(ls "$P/usr/lib/php" | grep -E '^[0-9]{6}$' | head -1)"
mkdir -p ~/local/phpconf
cat > ~/local/phpconf/php.ini <<EOF
extension_dir = $SCANDIR
extension = pdo
extension = sqlite3
extension = pdo_sqlite
extension = mbstring
extension = dom
extension = xml
extension = simplexml
extension = xmlwriter
extension = tokenizer
extension = ctype
extension = fileinfo
extension = phar
zend_extension = opcache
phar.readonly = Off
memory_limit = 512M
date.timezone = Asia/Shanghai
openssl.cafile = /etc/ssl/certs/ca-certificates.crt
curl.cainfo = /etc/ssl/certs/ca-certificates.crt
EOF

cat > ~/local/bin/php <<EOF
#!/bin/bash
export LD_LIBRARY_PATH=\$HOME/local/phproot/usr/lib/x86_64-linux-gnu\${LD_LIBRARY_PATH:+:\$LD_LIBRARY_PATH}
exec $P/usr/bin/php8.2 -c \$HOME/local/phpconf/php.ini "\$@"
EOF
chmod +x ~/local/bin/php

if [ ! -f ~/local/bin/composer ]; then
    curl -sfL https://getcomposer.org/download/latest-stable/composer.phar -o ~/local/bin/composer
    chmod +x ~/local/bin/composer
fi

~/local/bin/php -v | head -1
echo "PHP 安装完成：$HOME/local/bin/php"
