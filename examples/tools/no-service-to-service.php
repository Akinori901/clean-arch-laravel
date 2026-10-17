<?php

declare(strict_types=1);

/**
 * Service 間の相互呼び出しを検知する（deptrac では検知できないため）。
 *
 * deptrac はレイヤー「間」の依存しか見ず、同じレイヤー内の依存は常に許可する。
 * そのため「Service が別の Service を呼ぶ」は depfile.yaml に書いても落ちない。
 * ここでは App\Services 配下の各クラスが参照しているクラス名を解決し、
 * 自分以外の App\Services\*Service を参照していたら違反とする。
 *
 * 依存ゼロ（PHP 標準の tokenizer だけ）。
 *   php tools/no-service-to-service.php app/Services
 * 違反があれば一覧を出して終了コード 1。
 */

$dir = $argv[1] ?? 'app/Services';
if (!is_dir($dir)) {
    fwrite(STDERR, "ディレクトリがありません: {$dir}\n");
    exit(2);
}

/** @return list<string> 参照しているクラス名（完全修飾） */
function referencedClasses(string $code): array
{
    $tokens = token_get_all($code);
    $namespace = '';
    $uses = [];           // alias => FQCN
    $names = [];          // 生の名前
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t)) {
            continue;
        }
        [$id, $text] = $t;

        if ($id === T_NAMESPACE) {
            $namespace = '';
            for ($j = $i + 1; $j < $count && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $namespace .= $tokens[$j][1];
                }
            }
            $i = $j;
            continue;
        }

        // トップレベルの use 文（クロージャの use(...) とトレイトの use は除く）
        if ($id === T_USE && isTopLevelUse($tokens, $i)) {
            $fqcn = '';
            $alias = null;
            for ($j = $i + 1; $j < $count && $tokens[$j] !== ';'; $j++) {
                $tj = $tokens[$j];
                if (is_array($tj) && in_array($tj[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    if ($fqcn !== '' && is_array($tokens[$j - 2] ?? null) && $tokens[$j - 2][0] === T_AS) {
                        $alias = $tj[1];
                    } elseif ($fqcn === '') {
                        $fqcn = ltrim($tj[1], '\\');
                    }
                }
            }
            if ($fqcn !== '') {
                $uses[$alias ?? substr($fqcn, (int) strrpos('\\' . $fqcn, '\\'))] = $fqcn;
            }
            $i = $j;
            continue;
        }

        if (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $names[] = $text;
        }
    }

    $resolved = [];
    foreach ($names as $name) {
        if (str_starts_with($name, '\\')) {
            $resolved[] = ltrim($name, '\\');
            continue;
        }
        $first = explode('\\', $name)[0];
        if (isset($uses[$first])) {
            $resolved[] = $uses[$first] . substr($name, strlen($first));
        } else {
            $resolved[] = ($namespace !== '' ? $namespace . '\\' : '') . $name;
        }
    }

    return array_values(array_unique($resolved));
}

/** @param array<int, mixed> $tokens */
function isTopLevelUse(array $tokens, int $i): bool
{
    $depth = 0;
    for ($k = 0; $k < $i; $k++) {
        if ($tokens[$k] === '{') {
            $depth++;
        } elseif ($tokens[$k] === '}') {
            $depth--;
        } elseif (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $depth++;
        }
    }
    return $depth === 0;
}

function declaredClass(string $code): ?string
{
    $tokens = token_get_all($code);
    $namespace = '';
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_NAMESPACE) {
            for ($j = $i + 1; isset($tokens[$j]) && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $namespace .= $tokens[$j][1];
                }
            }
        }
        if (is_array($t) && in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            for ($j = $i + 1; isset($tokens[$j]); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    return ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$j][1];
                }
            }
        }
    }
    return null;
}

$violations = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $code = (string) file_get_contents($file->getPathname());
    $self = declaredClass($code);
    foreach (referencedClasses($code) as $class) {
        if ($class !== $self && preg_match('/^App\\\\Services\\\\.*Service$/', $class)) {
            $violations[] = sprintf('%s: %s が %s を参照している', $file->getPathname(), $self ?? '(不明)', $class);
        }
    }
}

if ($violations !== []) {
    echo "Service 間の相互呼び出し（複数 Service の調整は UseCase の仕事）:\n";
    foreach (array_unique($violations) as $v) {
        echo "  {$v}\n";
    }
    exit(1);
}
echo "Service 間の相互呼び出し: なし\n";
