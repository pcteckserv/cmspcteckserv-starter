<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? app(\Pcteckserv\CmsCore\Support\SiteOptions::class)->get('site_title', config('app.name', 'CMS')) }}</title>
    @if ($description = app(\Pcteckserv\CmsCore\Support\SiteOptions::class)->get('site_description'))
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="icon" type="image/png" href="{{ app(\Pcteckserv\CmsCore\Support\SiteOptions::class)->siteIconUrl() }}">
    @vite(['resources/css/site.scss', 'resources/js/site.js'])
</head>
<body>
    @yield('content')
</body>
</html>
