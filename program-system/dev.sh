#!/usr/bin/env bash
# 剧院节目单印刷与散场余量统计系统 —— 一条本地命令启动
# 用法: ./dev.sh            迁移+填充+构建+启动 Web（默认 http://127.0.0.1:8000）
#       ./dev.sh demo       只跑命令行全流程演示（锁定修改/错版报废/调拨/库存核对）
#       ./dev.sh fresh      重置演示数据后启动
set -euo pipefail
cd "$(dirname "$0")"

# 兼容无系统 PHP 的环境：使用用户态静态 PHP
if ! command -v php >/dev/null 2>&1; then
  if [ -x "$HOME/.local/php/php" ]; then
    export PATH="$HOME/.local/php:$PATH"
  else
    echo "未找到 php，请先安装（或放置静态二进制到 ~/.local/php/php）" >&2
    exit 1
  fi
fi

PORT="${PORT:-8000}"

# 首次运行：安装依赖
if [ ! -f vendor/autoload.php ]; then
  echo "首次运行：composer install …"
  php "$HOME/.local/php/composer" install --no-interaction --prefer-dist
fi
if [ ! -d node_modules ]; then
  echo "首次运行：npm install …"
  npm install
fi

migrate_seed() {
  touch database/database.sqlite
  php artisan migrate:fresh --seed --force
}

if [ "${1:-serve}" = "demo" ]; then
  migrate_seed >/dev/null
  php artisan theater:demo
  exit $?
fi

if [ "${1:-serve}" = "fresh" ] || [ ! -f database/database.sqlite ]; then
  migrate_seed
fi

# 前端已预构建；如希望热更新可另开: npm run dev
if [ ! -f public/build/manifest.json ]; then
  npm install
  npm run build
fi

echo ""
echo "🎭 系统启动中 → http://127.0.0.1:${PORT}"
echo "   场次管理 / 入口看板 / 报废审批 均在该地址"
echo "   命令行演示: ./dev.sh demo"
echo ""
# 多 worker：印厂交货时后端会回调本地模拟接口 /api/mock/printer/receipt
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
exec php artisan serve --host=127.0.0.1 --port="${PORT}" --no-reload
