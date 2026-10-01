{{-- 初期設定の前（セッション・ビルド済みアセットに頼れない段階）で使う素の HTML --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>初期設定 - Metiwiki</title>
    <style>
        body { font-family: system-ui, sans-serif; line-height: 1.7; color: #1f2937; background: #f9fafb; margin: 0; }
        main { max-width: 44rem; margin: 3rem auto; padding: 2rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; }
        h1 { font-size: 1.25rem; margin-top: 0; }
        pre { background: #f3f4f6; padding: 1rem; border-radius: .375rem; overflow-x: auto; font-size: .8125rem; }
        code { background: #f3f4f6; padding: .1rem .3rem; border-radius: .25rem; }
        @media (prefers-color-scheme: dark) {
            body { color: #e5e7eb; background: #111827; }
            main { background: #1f2937; border-color: #374151; }
            pre, code { background: #111827; }
        }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
