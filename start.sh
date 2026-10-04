#!/usr/bin/env bash
#
# 剧院节目单印刷与散场余量统计系统 —— 一条本地命令启动
# 用法：
#   ./start.sh           初始化（依赖/迁移/种子/前端构建）并启动 Web 服务
#   ./start.sh --fresh   重置数据库为演示种子数据后启动
#   ./start.sh demo      运行命令行端到端演示（锁定拦截/错版报废/入口调拨/库存核对）
#
set -euo pipefail
cd "$(dirname "$0")/server"
ROOT="$(cd .. && pwd)"

# ---------- 1. 定位 PHP（优先本地私有安装，其次系统 PHP） ----------
if [ -x "$HOME/local/bin/php" ]; then
    PHP="$HOME/local/bin/php"
elif command -v php >/dev/null 2>&1; then
    PHP="php"
else
    echo "未找到 PHP。正在把 PHP 8.2 安装到 \$HOME/local（无需 root）…"
    "$ROOT/scripts/install-php.sh"
    PHP="$HOME/local/bin/php"
fi

COMPOSER="$HOME/local/bin/composer"
[ -x "$COMPOSER" ] || COMPOSER="$(command -v composer || true)"

export PATH="$HOME/local/bin:$PATH"

# ---------- 2. 后端依赖 ----------
if [ ! -d vendor ]; then
    echo "==> 安装 Composer 依赖"
    "$PHP" "$COMPOSER" install --no-interaction --prefer-dist
fi

# ---------- 3. 前端依赖与构建 ----------
if [ ! -d node_modules ]; then
    echo "==> 安装 npm 依赖"
    npm install
fi
if [ ! -f public/build/manifest.json ]; then
    echo "==> 构建 React 前端"
    npm run build
fi

# ---------- 4. 环境与数据库 ----------
if [ ! -f .env ]; then
    cp .env.example .env
fi
grep -q '^APP_KEY=base64' .env || "$PHP" artisan key:generate
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i 's/^SESSION_DRIVER=database/SESSION_DRIVER=file/' .env
touch database/database.sqlite

if [ "${1:-}" = "--fresh" ]; then
    echo "==> 重置数据库并写入演示种子"
    "$PHP" artisan migrate:fresh --seed --force
elif [ "${1:-}" = "demo" ]; then
    echo "==> 运行端到端演示"
    "$PHP" artisan demo:run
    exit $?
else
    "$PHP" artisan migrate --force >/dev/null
    # 空库才播种
    if [ "$("$PHP" artisan tinker --execute='echo \App\Models\Production::count();' 2>/dev/null | tail -1)" = "0" ]; then
        "$PHP" artisan db:seed --force
    fi
fi

# ---------- 5. 启动 ----------
PORT="${PORT:-8000}"
echo ""
echo "============================================================"
echo "  系统已启动：http://127.0.0.1:${PORT}"
echo "  - 场次安排/追溯：   打开首页 → 场次"
echo "  - 版本与锁定演示： 节目单版本"
echo "  - 入口看板：       /board"
echo "  - 错版报废审批：   /scraps"
echo "  - 库存总数核对：   /reconciliation"
echo "  - 命令行演示：     ./start.sh demo"
echo "  按 Ctrl+C 停止"
echo "============================================================"
exec "$PHP" -S "127.0.0.1:${PORT}" -t public server.php
