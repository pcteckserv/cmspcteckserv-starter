<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pacote de deploy gerado</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 3rem; line-height: 1.5; color: #18212f; }
        code { background: #f3f5f7; border-radius: 4px; padding: .15rem .35rem; }
        .warning { max-width: 60rem; border-left: 4px solid #b7791f; background: #fff8e6; padding: .75rem 1rem; }
    </style>
</head>
<body>
    <h1>Pacote de deploy gerado</h1>
    <p>O pacote foi criado em <code>{{ $package['relative_path'] }}</code>.</p>
    <p>Tamanho: <strong>{{ number_format($package['size'] / 1024 / 1024, 2, ',', ' ') }} MB</strong></p>
    <p>Data: <strong>{{ $package['created_at'] }}</strong></p>
    @foreach ($package['warnings'] ?? [] as $warning)
        <p class="warning"><strong>Aviso:</strong> {{ $warning }}</p>
    @endforeach
    <p>Dentro do ZIP encontras <code>application.zip</code> e <code>installer.php</code>. Coloca ambos no <code>public_html</code> e abre <code>/installer.php</code>.</p>
</body>
</html>
