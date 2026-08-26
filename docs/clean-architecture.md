# クリーンアーキテクチャ規約（横断コア）

Laravel のマイクロサービス／モジュールに共通して適用する**層構成・依存方向・設計思想**。

この文書は**横断コア**（どのサービスでも変わらない部分）だけを定義する。
サービス固有の適用（具体モデル名・namespace・参照実装パス・既存資産の例外）は、
各サービス側の規約ファイルに置いて分離する。二重管理を避けるため、
本文をコピーせず**この文書を参照**すること。

## 層構成図

```
Request(FormRequest)
  → Controller（Validation通過後を受取・UseCaseへ渡す・例外catch・Formatter/Responsesの出し分けのみ）
      → UseCase（Service層のオーケストレーション、薄いUseCaseは許容。DB::transaction()で
                 トランザクション境界を制御可。Serviceの戻り値であるDtoを変数として扱う）
          → Service（ビジネスロジック。Service間の相互呼び出し禁止。Helperを呼んでよい。
                     RepositoryInterfaceが返すDtoを引数・戻り値の型に使う）
              → RepositoryInterface（Serviceからのみ参照可。ModelではなくDtoを返す契約にする）
                  → Repository（Modelをuseしてクエリ実行。返す直前に private toDto() で Model→Dto 変換）
                      → Model（型・基本情報）
          → Helper（Hash化・トークン生成等の技術ユーティリティ。Model/DB非依存の純粋関数）
  ← Formatter（結果を「形」にする。JsonResponseは返さない、配列/文字列を返す）
  ← Response（HTTPレスポンス生成。Symfony Response::HTTP_* 定数使用）

Dto（App\DataTransferObjects\{Domain}\...Dto。Modelに一切依存しない末端ノード。全層から参照可能）
```

## 依存ルール（deptrac で機械検知）

| Layer | 許可される依存先 |
|---|---|
| Controller | UseCase, Request, Formatter, Response, Exception, Enum |
| UseCase | Service, Dto, Exception, Enum |
| Service | RepositoryInterface, Helper, Dto, Exception, Enum（Service間の相互呼び出し禁止） |
| Helper | Enum（Model/Repository/Serviceに依存しない） |
| RepositoryInterface | Dto, Enum（Modelは返さない） |
| Repository | RepositoryInterface, Model, Dto, Enum |
| Model | Enum |
| Formatter | Model, Enum |
| Response | Enum |
| Request | なし |
| Exception | Enum |
| Dto | なし（Modelにも一切依存しない末端ノード） |
| Enum | なし |

- **Model 直接呼び出し禁止**: Service / UseCase / Controller から Model を直接 use しない。
  Model を直接扱ってよいのは Repository 層のみ。
- **RepositoryInterface は Service からのみ**参照可能（UseCase/Controller から直接触らない）。
- **Repository のみ Model を use** してクエリを組む。返す直前に private `toDto()` で Model → Dto 変換。
  UseCase/Service/RepositoryInterface が扱う型は Model ではなく Dto。
- **Dto は Model に一切依存しない**: `fromModel()` のような Model を引数に取るファクトリを Dto に持たせない。
  Model → Dto 変換は必ず Repository 内で完結。`final readonly class` とし、プロパティ名は既存慣習に合わせる。
- **Service 間の相互呼び出し禁止**。複数 Service のオーケストレーションは UseCase が担う。
- **UseCase は DB::transaction() を制御してよい**。境界は UseCase のクロージャ内。例外時は Laravel 標準の
  自動ロールバックに委ねる（明示的 rollBack 不要）。

### 現実的な例外の扱い

Controller や Formatter が Dto に依存せざるを得ない設計を選ぶことはある
（例: 認証トークン情報を Dto に包んで受け渡す）。その場合は

1. **依存を増やす判断の理由を depfile.yaml にコメントで残す**
2. **Model 依存は増やさない**（Dto 依存に留める）

の2点を守る。ルールを曲げること自体より、**曲げた理由が追跡できないこと**が問題になる。

## 配置規約

- **1 Repository = 1 Model**。Repository は担当 Model 名のディレクトリに実装と Interface を同居させる
  （`App\Repositories\{ModelClassName}\`）。`Contracts/` で実装と Interface を分けない。
  同一 Model へのクエリを1箇所に集約し、重複クエリを防ぐ。
- **UseCase は Controller ベースのサブディレクトリに切る**（`App\UseCases\{Domain}\{Controller名}\`）。
  どの UseCase がどの Controller のどのアクションに対応するかを構造で明示する。
  Services / Repositories はモデル単位の平置きのまま（複数 Controller から使われうるため）。
  テストも本体構造に合わせる。
- **Dto は `App\DataTransferObjects\{Domain}\`**。

## Enum 化ルール

- 配置先は `app/Enums/{Domain}/`。PHP 8.1 backed enum（`: string` / `: int`）、`Enum` サフィックス。
- 可変な結果コード・メッセージキー等「意味のある選択肢」を Enum 化する。
- HTTP ステータスコードは Enum 化せず、Responses 層で `Symfony\...\Response::HTTP_*` 定数を使う。

## 共有 Model を使う場合

複数サービスが同じテーブルを参照する場合、Model を共有パッケージに切り出す構成を取れる。
その場合は次を守る。

- 各サービスは固有の Model 実体を持たず、共有パッケージの Model を Repository から use する。
- 共有 Model は複数サービスが参照するため、**単一サービスのリファクタでは改修しない**（cast 追加等も禁止）。
- deptrac の Model layer に**共有パッケージの namespace も含める**（含め忘れると層外扱いになり検知が漏れる）。

## deptrac 運用

- 層依存の機械検知は deptrac が担う（PHPStan は型担当で、層依存は検知できない）。
  **CI とローカルの両方で実行する**。
- Repository / RepositoryInterface は**ディレクトリではなくクラス名サフィックスで層判別**する
  （同一 Model 名ディレクトリに同居するため）。`RepositoryInterface` を先に定義し、
  `Repository` は否定後読みで `...RepositoryInterface` を除外する。
- 既存コードから始める場合、既知違反は `skip_violations` にベースライン登録してよい。
  ただし**新たな違反はここに追加しない**。該当箇所を正しい層へ移す。
  ベースラインは「減らしていく対象」であって、恒久的な逃げ道ではない。

## マジック値チェック

deptrac はマジック値検知をスコープに含まない。
文字列リテラルの直書きは grep ベースのチェックと、コードレビューの二段構えで潰す。
