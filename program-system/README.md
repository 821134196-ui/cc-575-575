# 剧院节目单印刷与散场余量统计系统

编辑确定每场演出的节目单版本，前台安排各入口发放，运营人员根据散场实际余量决定是否补印。

技术栈：**PHP 8.3 + Laravel + Eloquent + SQLite**，**React 19 + Vite + Tailwind 4** 前端，印厂回执由**本地模拟接口**提供。

## 一条本地命令启动

```bash
./dev.sh          # 迁移+填充+构建 → http://127.0.0.1:8000
./dev.sh demo     # 命令行全流程演示（自动重置演示数据）
./dev.sh fresh    # 重置数据后启动 Web
```

> 环境没有系统 PHP 时，脚本会自动使用 `~/.local/php/php` 静态二进制。
> 首次部署：`composer install && npm install && npm run build`。

## 核心业务规则

| 规则 | 实现方式 |
| --- | --- |
| 演出信息确认后**锁定待印文件** | `program_versions.status: draft → locked`，锁定时固化 `file_hash` |
| **锁定后修改必须拒绝** | `ProgramVersion::guardEditable()` 抛 `VersionLockedException`，API 返回 **423**；前端只能走"新建版本" |
| 演员临时变化 / 增加场次 → **新建版本** | 旧版置 `superseded`，文件名保持不变——**已发出的旧节目单不能改名顶替** |
| 印厂交货、前台分配、入口发放、散场盘点**都按批次记录数量** | `print_batches` / `allocations` / `issues` / `stock_counts` |
| 错版报废**需要审批** | `scrap_requests: pending → approved`；待审批不扣库存，驳回不动库存，已处理不能重复审批 |
| 入口调拨**同时记录调出和调入，不增加总库存** | `transfers` 一条单据对应 `transfer_out + transfer_in` 两条流水 |
| 报废、发放与剩余**必须能核对** | 统一 `stock_movements` 流水：`交货 = 总仓 + 各入口 + 已发放 + 已报废` |

库存守恒不靠约定，靠**统一流水表 + 数据库事务**：每次数量变化在一个事务中成对写流水，
`StockService::reconcile()` 按库位汇总核对，差异必须为 0。

## 页面

- **场次管理** `/`：场次列表与状态统计，新建演出即生成 v1 草稿
- **场次详情** `/performances/{id}`：
  - 每个版本一张卡：文件/hash、演员名单、锁定操作、**尝试修改锁定版本（预期 423）**、新建版本
  - 印刷批次：下单 → 印厂交货（模拟回执）→ 各入口领用明细
  - 操作：入口发放、入口调拨、报废申请、散场盘点
  - 底部三页签：核对明细（守恒公式）、库存流水追溯、散场盘点记录
- **入口看板** `/board`：本场**允许发放的版本**（当前版/旧版标识）、各版本剩余数量、一键发放
- **报废审批** `/scraps`：待审批清单，批准/驳回，审批人与时间留痕

## 印厂回执（本地模拟接口）

`POST /api/mock/printer/receipt` —— 入参 `{batch_no, quantity_ordered}`，
返回确定性的回执号与交货数量（约 10% 的批次模拟约 2% 缺损，为补印决策提供依据）。
`PrinterGateway` 在 Web 运行时调用该接口；命令行环境不可达时退回内置模拟，演示可离线重放。

## 验证

```bash
./dev.sh demo     # 9 项领域规则断言：锁定拒绝/旧版不改名/报废审批/调拨守恒/盘点/核对
./e2e-test.sh     # 15 项真实 HTTP API 断言（需先启动服务），覆盖全部前端调用路径
```

## 目录要点

```
app/
├── Models/            Performance, ProgramVersion, PrintBatch, Entrance,
│                      Allocation, Issue, Transfer, ScrapRequest, StockCount, StockMovement
├── Services/
│   ├── StockService.php    # 事务化库存操作 + 盘点 + 核对（领域核心）
│   ├── PrinterGateway.php  # 印厂回执网关（本地模拟接口）
│   └── Location.php        # warehouse / entrance:{id} / audience / scrapped
├── Http/Controllers/Api/   # 场次、版本、批次、库存、看板、核对、模拟印厂
└── Console/Commands/TheaterDemo.php
database/migrations/        # 10 张业务表
resources/js/               # React SPA（pages + components）
routes/api.php              # 全部 JSON API
```
