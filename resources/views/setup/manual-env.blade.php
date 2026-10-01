@extends('setup.layout')

@section('content')
    <h1>設定ファイル（.env）を配置してください</h1>
    <p>
        暗号化キー（APP_KEY）を生成しましたが、設定ファイル <code>{{ app()->environmentFilePath() }}</code>
        に書き込めませんでした。次のどちらかの方法で設定ファイルを用意してから、このページを再読み込みしてください。
    </p>
    <ol>
        <li>
            下の内容で <code>.env</code> を作成し、上記の場所に置く。
            <br>
            （再読み込みするたびに別のキーが生成されます。どれを使っても構いませんが、一度置いたら変えないでください）
        </li>
        <li>
            サーバーのコマンドラインで <code>php artisan wiki:install</code> を実行する。
        </li>
    </ol>
    <pre>{{ $env }}</pre>
@endsection
