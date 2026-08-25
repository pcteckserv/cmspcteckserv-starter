<?php

namespace Pcteckserv\CmsCore\Updates;

use Symfony\Component\Process\Process;

class GitTagUpdateChecker
{
    public function latestVersion(string $package): ?string
    {
        $repository = config("cms-core.updates.repositories.{$package}");

        if (! is_string($repository) || $repository === '') {
            return null;
        }

        $githubVersion = $this->latestGithubVersion($repository);

        if ($githubVersion !== null) {
            return $githubVersion;
        }

        $process = new Process($this->command($repository));
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return collect(explode("\n", trim($process->getOutput())))
            ->map(fn (string $line): ?string => $this->versionFromLine($line))
            ->filter()
            ->sort(fn (string $a, string $b): int => version_compare($a, $b))
            ->last();
    }

    private function latestGithubVersion(string $repository): ?string
    {
        $token = config('cms-core.updates.github_token');

        if (! is_string($token) || $token === '' || ! preg_match('#^https://github\.com/([^/]+)/([^/.]+)(?:\.git)?$#', $repository, $matches)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer '.$token,
                    'User-Agent: PCTECK-CMS-Updater',
                    'X-GitHub-Api-Version: 2022-11-28',
                ]),
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents("https://api.github.com/repos/{$matches[1]}/{$matches[2]}/tags?per_page=100", false, $context);

        if (! is_string($response)) {
            return null;
        }

        $tags = json_decode($response, true);

        if (! is_array($tags)) {
            return null;
        }

        return collect($tags)
            ->map(fn (mixed $tag): ?string => is_array($tag) && isset($tag['name']) && is_string($tag['name'])
                ? $this->versionFromTag($tag['name'])
                : null)
            ->filter()
            ->sort(fn (string $a, string $b): int => version_compare($a, $b))
            ->last();
    }

    private function versionFromLine(string $line): ?string
    {
        if (! str_contains($line, 'refs/tags/')) {
            return null;
        }

        $tag = preg_replace('/\^{}$/', '', substr($line, strrpos($line, '/') + 1));

        if (! is_string($tag)) {
            return null;
        }

        $version = ltrim($tag, 'v');

        return $this->versionFromTag($version);
    }

    private function versionFromTag(string $tag): ?string
    {
        $version = ltrim($tag, 'v');

        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1
            ? $version
            : null;
    }

    /**
     * @return array<int, string>
     */
    private function command(string $repository): array
    {
        $token = config('cms-core.updates.github_token');

        if (! is_string($token) || $token === '' || ! str_starts_with($repository, 'https://github.com/')) {
            return ['git', 'ls-remote', '--tags', '--refs', $repository];
        }

        return [
            'git',
            '-c',
            "http.https://github.com/.extraheader=AUTHORIZATION: bearer {$token}",
            'ls-remote',
            '--tags',
            '--refs',
            $repository,
        ];
    }
}
