# clean-arch-laravel

**Laravel のクリーンアーキテクチャ規約を、CI で強制できる形にしたもの。**

<p>
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-12_推奨-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white">
  <img alt="deptrac" src="https://img.shields.io/badge/deptrac-依存方向をCI検証-2ea44f">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-green">
</p>

層構成と依存方向を文章で決めても、レビューで指摘し続けない限り守られません。
このリポジトリは、**同じルールを deptrac の設定として持ち**、
CI で落ちる形にすることで規約を維持する構成をまとめたものです。

- [規約本体（docs/clean-architecture.md）](docs/clean-architecture.md) — 層構成・依存表・配置規約
- [deptrac 設定（examples/depfile.yaml）](examples/depfile.yaml) — 上の依存表をそのまま機械検知する
- [検証用のサンプルと違反の例（examples/）](examples/) — この規約が実際に効くことを CI で確かめている

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
composer require --dev deptrac/deptrac
cp examples/depfile.yaml ./depfile.yaml
./vendor/bin/deptrac analyse --config-file=depfile.yaml
```

> `qossmic/deptrac` は放棄されたパッケージ名です。後継の `deptrac/deptrac` を使ってください。

### deptrac で検知できないもの

**deptrac は同じレイヤー内の依存を常に許可します。** そのため要点の 3 つ目
「Service 間の相互呼び出し禁止」は、depfile.yaml に書いても落ちません
（Service → Service はレイヤーをまたがないため）。

これは [`examples/tools/no-service-to-service.php`](examples/tools/no-service-to-service.php)
（依存ゼロ、PHP 標準の tokenizer だけ）で検知します。deptrac と並べて CI に入れてください。

```bash
php examples/tools/no-service-to-service.php app/Services
```

### 規約が効いていることの確認

`examples/` に、規約どおりのサンプル（全層）と典型的な違反 3 つを置いています。
CI では「サンプルは違反 0 件」「違反を 1 つずつ入れると必ず落ちる」の両方を確かめています。

```bash
cd examples && composer install && ./verify.sh
```

```
== 違反を 1 つずつ入れて、落ちることを確かめる
ok   DtoFromModel が検知された（... must not depend on App\Models\User (Model)）
ok   ServiceCallsService が検知された（tools/no-service-to-service.php が検知する ...）
ok   ServiceUsesModel が検知された（... must not depend on App\Models\User (Model)）
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

- Laravel 12 を推奨します（Laravel 10 / 11 はセキュリティサポートが終了しています）。
  depfile.yaml 自体はバージョンに依存しないので、10 / 11 でも同じように使えます
- PHP 8.2 以降（Laravel 12 の要件。backed enum を前提とした記述があります）
- モジュラモノリスにも、マイクロサービス構成にも適用できます
- 共有パッケージに Model を切り出す構成にも対応しています
  （deptrac の Model 層にその namespace を含める必要があります。詳細は設定ファイル内のコメント）

## ライセンス

MIT
