<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'CMS') }}</title>
    @vite(['resources/css/site.scss', 'resources/js/site.js'])
</head>
<body>
    @yield('content')
</body>
</html>
