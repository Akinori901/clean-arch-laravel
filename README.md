# clean-arch-laravel

**Laravel のクリーンアーキテクチャ規約を、CI で強制できる形にしたもの。**

<p>
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-10+-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white">
  <img alt="deptrac" src="https://img.shields.io/badge/deptrac-依存方向をCI検証-2ea44f">
  <img alt="実績" src="https://img.shields.io/badge/実績-4サービスで既知違反ゼロ-6E56CF">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-green">
</p>

層構成と依存方向を文章で決めても、レビューで指摘し続けない限り守られません。
このリポジトリは、**同じルールを deptrac の設定として持ち**、
CI で落ちる形にすることで規約を維持する構成をまとめたものです。

- [規約本体（docs/clean-architecture.md）](docs/clean-architecture.md) — 層構成・依存表・配置規約
- [deptrac 設定（examples/depfile.yaml）](examples/depfile.yaml) — 上の依存表をそのまま機械検知する

実際のマイクロサービス群（Laravel 12・4サービス）で運用し、
既存モノリスからの移行を経て `skip_violations: []`（既知違反ゼロ）まで到達した構成を、
案件固有の情報を除いて一般化したものです。

## 層構成

```
Request(FormRequest)
  → Controller（UseCase へ渡す・例外catch・出し分けのみ）
      → UseCase（Service のオーケストレーション・トランザクション境界）
          → Service（ビジネスロジック。Service 間の相互呼び出し禁止）
              → RepositoryInterface（Model ではなく Dto を返す契約）
                  → Repository（Model を use する唯一の層。返す直前に Dto へ変換）
                      → Model
          → Helper（Model/DB 非依存の純粋関数）
  ← Formatter（配列/文字列を返す。JsonResponse は返さない）
  ← Response（HTTP レスポンス生成）

Dto（Model に一切依存しない末端ノード。全層から参照可能）
```

要点は3つです。

- **Model を直接触れるのは Repository だけ。** 他の層が扱うのは Dto。
  ORM の都合がビジネスロジックへ漏れるのを、層の定義そのもので止めます。
- **Dto は Model に依存しない。** `fromModel()` のようなファクトリを Dto に置かない。
  変換は Repository 内で完結させ、Dto を依存グラフの末端に保ちます。
- **Service 間の相互呼び出しを禁止。** 複数 Service の調整は UseCase の仕事。
  Service 同士が呼び合い始めると、依存が循環して層が意味を失います。

## なぜ deptrac なのか

PHPStan は型を見ますが、**層の依存方向は見ません**。
「Service から Model を直接呼んでいないか」は静的型検査では落ちないため、
別の仕組みが要ります。

```bash
composer require --dev qossmic/deptrac
cp examples/depfile.yaml ./depfile.yaml
./vendor/bin/deptrac analyse --config-file=depfile.yaml
```

CI に入れると、規約違反が**レビュー前に**落ちます。

### 既存コードに後から入れる場合

いきなり全違反を直すのは現実的ではないので、`skip_violations` に既知違反を
ベースライン登録して始められます。重要なのは運用ルールの方です。

- 既存の違反 → ベースラインに登録してよい
- **新規の違反 → 追加しない。** 該当箇所を正しい層へ移す

こうするとベースラインが「減らしていく対象」になり、逃げ道になりません。

## 実装上の判断について

規約どおりに書けない場面は必ず出ます。このリポジトリの立場は
「例外を作るな」ではなく、**例外を作った理由を追跡可能にしろ**です。

たとえば Controller が Dto に依存する設計を選ぶ場合、
`depfile.yaml` の該当箇所にその理由をコメントとして残します。
ルールを曲げたこと自体より、**なぜ曲げたか分からなくなること**が後で効いてきます。

## 想定していること

- Laravel 10 以降（PHP 8.1 以降の backed enum を前提とした記述があります）
- モジュラモノリスにも、マイクロサービス構成にも適用できます
- 共有パッケージに Model を切り出す構成にも対応しています
  （deptrac の Model 層にその namespace を含める必要があります。詳細は設定ファイル内のコメント）

## ライセンス

MIT
