# Git 工具通用规范（草案）

面向未来的 Git 工具抽象，目标：高内聚、低耦合、可测试、便于注册。

## 1. 公共接口/约定
- **命名**：每个工具独立类，名称与职责一致，如 `GitDiffTool`、`GitCommitLogTool`。
- **注册方法**：`public static function register(ToolRegistry $registry, ?string $repoPath = null, ?callable $runner = null): void`
  - `repoPath`：默认 `getcwd()`；必须是字符串。
  - `runner`：可注入命令执行器，签名 `fn(array $cmd): array{stdout:string,error:?string}`，便于 stub。
- **调用参数**：每个工具定义自身需要的参数列表，均做验证（类型、必填、范围）。
- **返回结构**（建议统一）：
  - 成功：`['success'=>true, ...payload...]`
  - 失败：`['success'=>false, 'error'=>string]`
  - 不抛异常；仅参数验证失败时抛 `ValidationException`。

## 2. Git 命令执行层
- 提取轻量 runner/助手（可为类或闭包），负责：
  - 命令数组构造（不使用 shell 拼接，避免注入）。
  - 执行并返回 `stdout`/`error`。
  - 不做业务解析。
- 工具类仅组合 runner，专注解析与业务输出。

## 3. GitCommitLogTool（本次要实现的功能）
- **用途**：提取最近 N 条提交信息，写入 JSON 文件。
- **参数**：
  - `repo`（可选，string）
  - `limit`（必填，int > 0）
  - `output`（必填，string，目标 JSON 文件路径）
- **命令**：
  - `['git','-C',$repo,'log','-n',(string)$limit,'--pretty=format:%H%x1f%an%x1f%ad%x1f%s','--date=iso-strict']`
- **解析**：
  - 逐行按 `\x1f` 分隔，映射字段：`hash/author/date/subject`，过滤空行。
- **输出**：
  - 写入 JSON（推荐 `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`）。
  - 成功返回：`['success'=>true,'path'=>$output,'count'=>N]`
  - Git 失败或写文件失败：`success=false` + `error` 文本。
  - 参数非法：抛 `ValidationException`。

## 4. 测试准则（TDD）
- 所有工具需可注入 runner 以 stub 命令。
- 参数验证独立覆盖（非法 repo、limit、缺 output）。
- 错误路径覆盖（git error、写文件失败）。
- 成功路径覆盖（命令构造、解析、输出文件内容）。
- JSON 写入使用临时目录，测试后清理。

## 5. 注册便利层（可选）
- 提供 `GitToolsFacade::registerAll($registry, $repoPath = null, $runner = null)` 依次注册各工具，便于一次性启用。
- 仍允许按需单独注册，避免强耦合。
