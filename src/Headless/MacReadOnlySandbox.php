<?php

declare(strict_types=1);

namespace Develate\GrokCli\Headless;

/**
 * Grok 1.0.13 rejects Docker Desktop's socket symlink while resolving the
 * read-only profile. On macOS child-network filtering is a no-op; replace
 * its automatic socket discovery with stricter directory denies instead.
 * Keep this version-scoped so future native network enforcement is retained.
 * https://github.com/xai-org/grok-build/blob/main/crates/codegen/xai-grok-sandbox/src/runtime_sockets.rs
 */
final class MacReadOnlySandbox
{
    public function install(string $grokHome): string
    {
        $profile = <<<'TOML'
extends = "read-only"
restrict_network = false
deny = ["/run/**", "/var/run/**", "/private/var/run/**", "/**/.docker/**"]
TOML;
        $name = 'grok-cli-php-read-only-macos-'.substr(hash('sha256', $profile), 0, 16);
        $section = "\n[profiles.{$name}]\n{$profile}\n";
        $path = $grokHome.'/sandbox.toml';
        if (is_link($path)) {
            throw new \RuntimeException('Refusing to modify a symlinked Grok sandbox configuration: '.$path);
        }
        $file = @fopen($path, 'c+');
        if ($file === false) {
            throw new \RuntimeException('Cannot open the Grok sandbox configuration: '.$path);
        }

        try {
            if (! flock($file, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock the Grok sandbox configuration: '.$path);
            }
            $existing = stream_get_contents($file);
            if ($existing === false) {
                throw new \RuntimeException('Cannot read the Grok sandbox configuration: '.$path);
            }
            if (str_contains($existing, $section)) {
                return $name;
            }
            if (str_contains($existing, $name)) {
                throw new \RuntimeException('The SDK-owned Grok sandbox profile has been modified: '.$name);
            }
            if (fwrite($file, $section) !== strlen($section) || ! fflush($file)) {
                throw new \RuntimeException('Cannot write the Grok sandbox configuration: '.$path);
            }

            return $name;
        } finally {
            fclose($file);
        }
    }

    /**
     * Only the standard Docker Desktop link is handled. Other socket layouts
     * retain Grok's native fail-closed behavior.
     *
     * @param  array<string, string|false>  $env
     */
    public function applicable(string $version, array $env): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin' || ! preg_match('/^(?:grok )?1\.0\.13(?:\s|$)/', $version)) {
            return false;
        }
        $userHome = $env['HOME'] ?? getenv('HOME');
        if (! is_string($userHome) || $userHome === '') {
            return false;
        }
        if (realpath($userHome) !== $userHome || is_link($userHome.'/.docker') || is_link($userHome.'/.docker/run') || is_link($userHome.'/.docker/desktop')) {
            return false;
        }
        if (! is_link('/var/run/docker.sock') || readlink('/var/run/docker.sock') !== $userHome.'/.docker/run/docker.sock') {
            return false;
        }
        // Do not substitute a profile for nonstandard symlink endpoints.
        $paths = ['/run/docker.sock', '/run/podman/podman.sock', '/var/run/podman/podman.sock', '/run/containerd/containerd.sock', '/var/run/containerd/containerd.sock', $userHome.'/.docker/run/docker.sock', $userHome.'/.docker/desktop/docker.sock'];
        foreach (['docker.sock', 'podman/podman.sock', 'containerd/containerd.sock'] as $suffix) {
            array_push($paths, ...(glob('/run/user/*/'.$suffix) ?: []));
        }
        foreach ($paths as $path) {
            if (is_link($path)) {
                return false;
            }
        }

        return true;
    }
}
