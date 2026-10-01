@extends('setup.layout')

@section('content')
    <h1>データベースを準備できませんでした</h1>
    <p>初期設定の途中で、データベースの準備に失敗しました。</p>
    <pre>{{ $message }}</pre>
    <ul>
        <li>SQLite を使う場合は、<code>database/</code> ディレクトリに Web サーバーから書き込めるか確認してください。</li>
        <li>MySQL を使う場合は、<code>.env</code> の <code>DB_</code> で始まる設定と、データベースが作成済みかを確認してください。</li>
        <li>サーバーのコマンドラインで <code>php artisan wiki:install</code> を実行して初期設定することもできます。</li>
    </ul>
@endsection
